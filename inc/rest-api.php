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

/*--------------------------------------------------------------
    TRENDING ENDPOINT
    GET /wp-json/twispeer/v1/trending
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
    register_rest_route( 'twispeer/v1', '/trending', array(
        'methods'  => 'GET',
        'callback' => 'twispeer_rest_get_trending_posts',
        'permission_callback' => '__return_true',
    ) );
} );

/**
 * Trending Logic:
 * 1. Load ALL posts (limit ~100 for safety)
 * 2. Compute total reactions per post
 * 3. Remove posts with zero reactions
 * 4. Sort:
 *      - First by total reactions (DESC)
 *      - If equal, by post date (DESC)
 */
function twispeer_rest_get_trending_posts( WP_REST_Request $request ) {

    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $q = new WP_Query( $args );
    $trending = array();

    if ( $q->have_posts() ) {
        while ( $q->have_posts() ) {
            $q->the_post();
            $post_id = get_the_ID();

            // Load reaction meta
            $meta = twispeer_load_reaction_meta( $post_id );
            $reactions = $meta['reactions'];

            // total reaction count
            $total = 0;
            foreach ( $reactions as $cnt ) {
                $total += intval( $cnt );
            }

            // skip posts with no reactions
            if ( $total <= 0 ) continue;

            $trending[] = array(
                'id'      => $post_id,
                'title'   => get_the_title(),
                'content' => apply_filters(
                    'the_excerpt',
                    get_the_excerpt() ?: wp_trim_words( get_the_content(), 30 )
                ),
                'author'  => get_the_author_meta(
                    'display_name',
                    get_post_field( 'post_author', $post_id )
                ),
                'date'     => get_the_date( 'c' ),
                'link'     => get_permalink(),
                'reactions_total' => $total,          // ← important for sorting
                'reactions'       => $reactions,      // for front-end display
            );
        }
        wp_reset_postdata();
    }

    // Sort trending:
    // 1) total reactions DESC
    // 2) post date DESC if total reactions match
    usort( $trending, function( $a, $b ) {
        if ( $b['reactions_total'] == $a['reactions_total'] ) {
            return strtotime( $b['date'] ) - strtotime( $a['date'] );
        }
        return $b['reactions_total'] - $a['reactions_total'];
    });

    return rest_ensure_response( $trending );
}


/*--------------------------------------------------------------
    END OF FILE
--------------------------------------------------------------*/
