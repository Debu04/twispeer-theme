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
