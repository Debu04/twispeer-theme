<?php
/**
 * Demo REST endpoints for Twispeer theme
 * Routes:
 *  GET  /wp-json/twispeer/v1/feed  -> returns a simple feed (latest posts)
 *  POST /wp-json/twispeer/v1/post  -> create a new post (requires edit_posts capability)
 */

add_action( 'rest_api_init', function () {
    register_rest_route( 'twispeer/v1', '/feed', array(
        'methods'  => 'GET',
        'callback' => 'twispeer_rest_get_feed',
        'permission_callback' => '__return_true', // public read
    ) );

    register_rest_route( 'twispeer/v1', '/post', array(
        'methods'  => 'POST',
        'callback' => 'twispeer_rest_create_post',
        'permission_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
        'args' => array(
            'content' => array(
                'required' => true,
                'sanitize_callback' => 'wp_kses_post',
            ),
            'title' => array(
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            )
        ),
    ) );
} );

function twispeer_rest_get_feed( $request ) {
    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    $q = new WP_Query( $args );

    $items = array();

    if ( $q->have_posts() ) {
        while ( $q->have_posts() ) {
            $q->the_post();
            $items[] = array(
                'id'      => get_the_ID(),
                'title'   => get_the_title(),
                'content' => apply_filters( 'the_excerpt', get_the_excerpt() ?: wp_trim_words( get_the_content(), 30 ) ),
                'author'  => get_the_author_meta( 'display_name', get_post_field( 'post_author', get_the_ID() ) ),
                'date'    => get_the_date( 'c' ),
                'link'    => get_permalink(),
            );
        }
        wp_reset_postdata();
    }

    return rest_ensure_response( $items );
}

function twispeer_rest_create_post( WP_REST_Request $request ) {
    $params = $request->get_json_params();

    $content = isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '';
    $title   = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : wp_trim_words( $content, 6, '...' );

    if ( empty( $content ) ) {
        return new WP_Error( 'empty_content', 'Content is required', array( 'status' => 400 ) );
    }

    $post_id = wp_insert_post( array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
        'post_type'    => 'post',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    $post_obj = get_post( $post_id );

    return rest_ensure_response( array(
        'id'      => $post_obj->ID,
        'title'   => get_the_title( $post_obj ),
        'content' => apply_filters( 'the_content', $post_obj->post_content ),
        'date'    => get_post_time( 'c', true, $post_obj ),
        'link'    => get_permalink( $post_obj ),
    ) );
}


/* ---------- Report endpoint: POST /wp-json/twispeer/v1/report ----------
   Body JSON: { "post_id": 123 }
   Response: { "success": true, "count": <new_count> }
*/
add_action( 'rest_api_init', function () {
    register_rest_route( 'twispeer/v1', '/report', array(
        'methods'  => 'POST',
        'callback' => 'twispeer_rest_report_post',
        'permission_callback' => '__return_true', // public - adjust if needed
        'args' => array(
            'post_id' => array(
                'required' => true,
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );
} );

function twispeer_rest_report_post( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
    if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) {
        return new WP_Error( 'invalid_post', 'Invalid post ID', array( 'status' => 400 ) );
    }

    // Increment numeric report count stored in post meta
    $count = (int) get_post_meta( $post_id, 'twispeer_report_count', true );
    $count++;
    update_post_meta( $post_id, 'twispeer_report_count', $count );

    // Optionally store a timestamp of last report (for moderation)
    update_post_meta( $post_id, 'twispeer_report_last', current_time( 'mysql' ) );

    return rest_ensure_response( array(
        'success' => true,
        'count'   => $count,
    ) );
}



/* ---------- Reactions API (per-post, per-user single reaction) ----------
   Adds persistent reaction counts and per-user reaction record.
   - GET  /wp-json/twispeer/v1/reactions?post_id=123
   - POST /wp-json/twispeer/v1/reactions  { post_id, emoji }  (must be logged in)
   Data stored in post meta:
     - twispeer_reactions => associative array emoji => count
     - twispeer_reactors  => associative array user_id => emoji
---------------------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
    // GET aggregated reactions for a post
    register_rest_route( 'twispeer/v1', '/reactions', array(
        'methods'  => 'GET',
        'callback' => 'twispeer_rest_get_reactions',
        'permission_callback' => '__return_true',
        'args' => array(
            'post_id' => array(
                'required' => true,
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );

    // POST set user reaction (requires login)
    register_rest_route( 'twispeer/v1', '/reactions', array(
        'methods'  => 'POST',
        'callback' => 'twispeer_rest_set_reaction',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'sanitize_callback' => 'absint',
            ),
            'emoji' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ) );
} );

/**
 * Helper: load reaction arrays from postmeta (returns arrays)
 */
function twispeer_load_reaction_meta( $post_id ) {
    $reactions = get_post_meta( $post_id, 'twispeer_reactions', true );
    $reactors  = get_post_meta( $post_id, 'twispeer_reactors', true );

    if ( ! is_array( $reactions ) ) $reactions = array();
    if ( ! is_array( $reactors ) ) $reactors = array();

    return array( 'reactions' => $reactions, 'reactors' => $reactors );
}

/**
 * Helper: write reaction arrays back to postmeta
 */
function twispeer_save_reaction_meta( $post_id, $reactions, $reactors ) {
    update_post_meta( $post_id, 'twispeer_reactions', $reactions );
    update_post_meta( $post_id, 'twispeer_reactors', $reactors );
}

/**
 * Build aggregated response shape
 */
function twispeer_build_reactions_response( $post_id ) {
    $meta = twispeer_load_reaction_meta( $post_id );
    $reactions = $meta['reactions']; // emoji => count
    $reactors  = $meta['reactors'];  // user_id => emoji

    // compute total
    $total = 0;
    foreach ( $reactions as $cnt ) {
        $total += intval( $cnt );
    }

    // compute top two emojis (by count)
    arsort( $reactions ); // descending numeric
    $top = array_slice( $reactions, 0, 2, true ); // emoji => count

    // find current user reaction (if logged in)
    $current_user_id = get_current_user_id();
    $user_reaction = isset( $reactors[ $current_user_id ] ) ? $reactors[ $current_user_id ] : null;

    return array(
        'post_id' => $post_id,
        'reactions' => $reactions,
        'top' => $top,
        'total' => $total,
        'user_reaction' => $user_reaction,
    );
}

/**
 * GET handler: return aggregated reactions
 */
function twispeer_rest_get_reactions( WP_REST_Request $request ) {
    $post_id = (int) $request->get_param( 'post_id' );
    if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) {
        return new WP_Error( 'invalid_post', 'Invalid post ID', array( 'status' => 400 ) );
    }
    return rest_ensure_response( twispeer_build_reactions_response( $post_id ) );
}

/**
 * POST handler: set a user's reaction (single reaction per user)
 * Body: { post_id: <int>, emoji: "<emoji char or string>" }
 */
function twispeer_rest_set_reaction( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
    $emoji   = isset( $params['emoji'] ) ? trim( wp_strip_all_tags( $params['emoji'] ) ) : '';

    if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) {
        return new WP_Error( 'invalid_post', 'Invalid post ID', array( 'status' => 400 ) );
    }
    if ( empty( $emoji ) ) {
        return new WP_Error( 'invalid_emoji', 'Emoji is required', array( 'status' => 400 ) );
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'not_logged_in', 'You must be logged in to react', array( 'status' => 401 ) );
    }

    // load stored meta
    $meta = twispeer_load_reaction_meta( $post_id );
    $reactions = $meta['reactions'];
    $reactors  = $meta['reactors'];

    // ensure arrays are correct types
    if ( ! is_array( $reactions ) ) $reactions = array();
    if ( ! is_array( $reactors ) ) $reactors = array();

    // if user had a previous reaction, decrement its count
    if ( isset( $reactors[ $user_id ] ) && $reactors[ $user_id ] !== $emoji ) {
        $prev = $reactors[ $user_id ];
        if ( isset( $reactions[ $prev ] ) ) {
            $reactions[ $prev ] = max( 0, intval( $reactions[ $prev ] ) - 1 );
            if ( $reactions[ $prev ] <= 0 ) unset( $reactions[ $prev ] );
        }
    }

    // if user clicked the same emoji again we interpret as "remove reaction" (toggle)
    if ( isset( $reactors[ $user_id ] ) && $reactors[ $user_id ] === $emoji ) {
        // remove mapping and decrement count
        unset( $reactors[ $user_id ] );
        if ( isset( $reactions[ $emoji ] ) ) {
            $reactions[ $emoji ] = max( 0, intval( $reactions[ $emoji ] ) - 1 );
            if ( $reactions[ $emoji ] <= 0 ) unset( $reactions[ $emoji ] );
        }
    } else {
        // set new reaction for user and increment count
        $reactors[ $user_id ] = $emoji;
        if ( isset( $reactions[ $emoji ] ) ) {
            $reactions[ $emoji ] = intval( $reactions[ $emoji ] ) + 1;
        } else {
            $reactions[ $emoji ] = 1;
        }
    }

    // save back
    twispeer_save_reaction_meta( $post_id, $reactions, $reactors );

    // return updated aggregated response
    return rest_ensure_response( twispeer_build_reactions_response( $post_id ) );
}






// --- TWISPEER: trends by reactions (append to end of inc/rest-api.php) ---
add_action( 'rest_api_init', function () {
  register_rest_route( 'twispeer/v1', '/trends', array(
    'methods'  => WP_REST_Server::READABLE,
    'callback' => 'twispeer_rest_get_trends_by_reactions',
    'permission_callback' => '__return_true',
  ) );
} );

/**
 * Return trending posts ranked by reaction counts.
 *
 * The function tries two common storage patterns:
 *  1) postmeta entry that stores an aggregated count per post (meta_key: twispeer_reactions_count)
 *  2) a custom table (wp_twispeer_reactions) where each row is a reaction with post_id
 *
 * The endpoint returns an array of objects:
 *  [ { id: 123, score: 456 }, ... ]
 *
 * Edit $limit, $meta_key, or the custom table name if your schema differs.
 */
if ( ! function_exists( 'twispeer_rest_get_trends_by_reactions' ) ) {
  function twispeer_rest_get_trends_by_reactions( WP_REST_Request $request ) {
    global $wpdb;

    // Change these if your meta key/table differ
    $limit = 12;
    $meta_key = 'twispeer_reactions_count'; // if you store aggregated count as postmeta
    $custom_table = $wpdb->prefix . 'twispeer_reactions'; // if you store each reaction as a row

    // 1) If postmeta aggregated counts exist, prefer that (fast)
    $has_meta = $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(pm.meta_id) FROM {$wpdb->postmeta} pm WHERE pm.meta_key = %s LIMIT 1",
      $meta_key
    ) );

    if ( $has_meta ) {
      // Select posts with highest meta value
      $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.ID as post_id, CAST(pm.meta_value AS UNSIGNED) as score
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
         WHERE p.post_status = 'publish' AND p.post_type = 'post'
         ORDER BY score DESC
         LIMIT %d",
         $meta_key, $limit
      ), ARRAY_A );

      $out = array();
      if ( $rows ) {
        foreach ( $rows as $r ) {
          $out[] = array(
            'id'    => intval( $r['post_id'] ),
            'score' => intval( $r['score'] ),
          );
        }
      }

      return rest_ensure_response( $out );
    }

    // 2) If a custom reactions table exists, aggregate counts per post
    $table_exists = $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
      $custom_table
    ) );

    if ( $table_exists ) {
      // Adjust column names below if your table uses different names (post_id, reaction_type, user_id)
      $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.post_id AS post_id, COUNT(*) AS score
         FROM {$custom_table} r
         INNER JOIN {$wpdb->posts} p ON p.ID = r.post_id AND p.post_status = 'publish' AND p.post_type = 'post'
         GROUP BY r.post_id
         ORDER BY score DESC
         LIMIT %d",
         $limit
      ), ARRAY_A );

      $out = array();
      if ( $rows ) {
        foreach ( $rows as $r ) {
          $out[] = array(
            'id'    => intval( $r['post_id'] ),
            'score' => intval( $r['score'] ),
          );
        }
      }

      return rest_ensure_response( $out );
    }

    // 3) Fallback: no meta or custom table detected — return most recent posts (score = 0)
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT p.ID as post_id
       FROM {$wpdb->posts} p
       WHERE p.post_status = 'publish' AND p.post_type = 'post'
       ORDER BY p.post_date DESC
       LIMIT %d",
       $limit
    ), ARRAY_A );

    $out = array();
    if ( $rows ) {
      foreach ( $rows as $r ) {
        $out[] = array(
          'id'    => intval( $r['post_id'] ),
          'score' => 0,
        );
      }
    }

    return rest_ensure_response( $out );
  }
}
// --- end TWISPEER trends by reactions ---









// --- TWISPEER: trends by reactions (top-N ordered by reactions desc, ties by most recent) ---
add_action( 'rest_api_init', function () {
  register_rest_route( 'twispeer/v1', '/trends', array(
    'methods'  => WP_REST_Server::READABLE,
    'callback' => 'twispeer_rest_get_trends_by_reactions_topn',
    'permission_callback' => '__return_true',
  ) );
} );

/**
 * Return top-N trending posts ordered by total reactions (desc) then post_date (desc).
 *
 * Response: [ { id: <post_id>, score: <int>, date: "YYYY-mm-dd hh:mm:ss" }, ... ]
 */
if ( ! function_exists( 'twispeer_rest_get_trends_by_reactions_topn' ) ) {
  function twispeer_rest_get_trends_by_reactions_topn( WP_REST_Request $request ) {
    global $wpdb;

    // parameters (change as needed)
    $limit = 12; // number of trending posts to return
    $meta_key = 'twispeer_reactions_count'; // aggregated count per post (optional)
    $custom_table = $wpdb->prefix . 'twispeer_reactions'; // custom reactions table (optional)

    // Helper to format date string from MySQL row
    $format_row = function( $post_id, $score, $post_date ) {
      return array(
        'id'    => intval( $post_id ),
        'score' => intval( $score ),
        'date'  => $post_date,
      );
    };

    // 1) Prefer aggregated postmeta if present (fast)
    $has_meta = $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(pm.meta_id) FROM {$wpdb->postmeta} pm WHERE pm.meta_key = %s LIMIT 1",
      $meta_key
    ) );

    if ( $has_meta ) {
      $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.ID as post_id, CAST(pm.meta_value AS UNSIGNED) as score, p.post_date
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
         WHERE p.post_status = 'publish' AND p.post_type = 'post'
         ORDER BY score DESC, p.post_date DESC
         LIMIT %d",
         $meta_key, $limit
      ), ARRAY_A );

      $out = array();
      if ( $rows ) {
        foreach ( $rows as $r ) {
          $out[] = $format_row( $r['post_id'], $r['score'], $r['post_date'] );
        }
      }
      return rest_ensure_response( $out );
    }

    // 2) If custom reactions table exists, aggregate counts per post
    $table_exists = $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
      $custom_table
    ) );

    if ( $table_exists ) {
      // adjust column names if your table differs (assumes r.post_id exists)
      $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.post_id AS post_id, COUNT(*) AS score, p.post_date AS post_date
         FROM {$custom_table} r
         INNER JOIN {$wpdb->posts} p ON p.ID = r.post_id AND p.post_status = 'publish' AND p.post_type = 'post'
         GROUP BY r.post_id
         ORDER BY score DESC, p.post_date DESC
         LIMIT %d",
         $limit
      ), ARRAY_A );

      $out = array();
      if ( $rows ) {
        foreach ( $rows as $r ) {
          $out[] = $format_row( $r['post_id'], $r['score'], $r['post_date'] );
        }
      }
      return rest_ensure_response( $out );
    }

    // 3) Fallback: no meta or custom table: return most recent posts but treat score=0
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT p.ID as post_id, 0 as score, p.post_date
       FROM {$wpdb->posts} p
       WHERE p.post_status = 'publish' AND p.post_type = 'post'
       ORDER BY p.post_date DESC
       LIMIT %d",
       $limit
    ), ARRAY_A );

    $out = array();
    if ( $rows ) {
      foreach ( $rows as $r ) {
        $out[] = $format_row( $r['post_id'], $r['score'], $r['post_date'] );
      }
    }

    return rest_ensure_response( $out );
  }
}
// --- end TWISPEER trends top-N ---
