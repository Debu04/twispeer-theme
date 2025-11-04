<?php
/**
 * Twispeer theme functions
 */

if ( ! defined( 'TWISPEER_THEME_DIR' ) ) {
    define( 'TWISPEER_THEME_DIR', get_template_directory() );
    define( 'TWISPEER_THEME_URI', get_template_directory_uri() );
}

/* Include config and rest API helpers */
require_once TWISPEER_THEME_DIR . '/inc/config.php';
require_once TWISPEER_THEME_DIR . '/inc/rest-api.php';

/* Theme setup */
function twispeer_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'gallery', 'caption' ) );
    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'twispeer' ),
    ) );

    register_sidebar( array(
        'name'          => 'Left Sidebar',
        'id'            => 'twispeer-left',
        'before_widget' => '<div class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    register_sidebar( array(
        'name'          => 'Right Sidebar',
        'id'            => 'twispeer-right',
        'before_widget' => '<div class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'after_setup_theme', 'twispeer_setup' );

/* Enqueue assets (automatically loads all component CSS/JS) */
function twispeer_enqueue_assets() {
    $ver = defined('TWISPEER_VERSION') ? TWISPEER_VERSION : '1.0.2';

    // root and main
    wp_enqueue_style( 'twispeer-root-style', get_stylesheet_uri(), array(), $ver );
    $theme_uri = defined( 'TWISPEER_THEME_URI' ) ? TWISPEER_THEME_URI : get_template_directory_uri();
    $theme_dir = defined( 'TWISPEER_THEME_DIR' ) ? TWISPEER_THEME_DIR : get_template_directory();

    wp_enqueue_style( 'twispeer-main', $theme_uri . '/assets/css/style.css', array('twispeer-root-style'), $ver );

    // load ALL component CSS files in assets/css/components/*.css
    $component_css_dir = $theme_dir . '/assets/css/components';
    if ( is_dir( $component_css_dir ) ) {
        foreach ( glob( $component_css_dir . '/*.css' ) as $file ) {
            $handle = 'twispeer-' . basename( $file, '.css' );
            $uri = str_replace( $theme_dir, $theme_uri, $file );
            wp_enqueue_style( $handle, $uri, array('twispeer-main'), $ver );
        }
    }

    // main script (bootstrap)
    wp_enqueue_script( 'twispeer-main', $theme_uri . '/assets/js/main.js', array('jquery'), $ver, true );

    // load component JS files in assets/js/components/*.js (in footer)
    $component_js_dir = $theme_dir . '/assets/js/components';
    if ( is_dir( $component_js_dir ) ) {
        foreach ( glob( $component_js_dir . '/*.js' ) as $file ) {
            $handle = 'twispeer-' . basename( $file, '.js' );
            $uri = str_replace( $theme_dir, $theme_uri, $file );
            wp_enqueue_script( $handle, $uri, array('twispeer-main'), $ver, true );
        }
    }

    // localize for JS
        wp_localize_script( 'twispeer-main', 'TWISPEER', array(
        'rest_url' => esc_url_raw( rest_url( 'twispeer/v1' ) ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
        'current_user' => get_current_user_id(),
    ) );

}
add_action( 'wp_enqueue_scripts', 'twispeer_enqueue_assets' );


/* -------------------------
 * Admin helper: create a sample post via admin-post
 * ------------------------- */

/**
 * Add an admin bar item for quick sample-post creation (users with edit_posts)
 */
function twispeer_admin_bar_sample_post( $wp_admin_bar ) {
    if ( ! is_user_logged_in() ) {
        return;
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    $url = admin_url( 'admin-post.php?action=twispeer_create_sample_post&_wpnonce=' . wp_create_nonce( 'twispeer_create_sample' ) );
    $args = array(
        'id'    => 'twispeer-create-sample',
        'title' => 'Create sample post',
        'href'  => $url,
        'meta'  => array( 'class' => 'twispeer-admin-sample' ),
    );
    $wp_admin_bar->add_node( $args );
}
add_action( 'admin_bar_menu', 'twispeer_admin_bar_sample_post', 100 );

/**
 * Handle admin_post action and create a sample post
 */
function twispeer_handle_create_sample_post() {
    // verify nonce and capability
    if ( ! is_user_logged_in() ) {
        wp_die( 'You must be logged in.' );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'Insufficient permissions.' );
    }

    if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'twispeer_create_sample' ) ) {
        wp_die( 'Invalid nonce.' );
    }

    $current_user = wp_get_current_user();
    $title = 'Sample post — ' . current_time( 'mysql' );
    $content = '<p>This is a sample post created by Twispeer Theme helper by user: ' . esc_html( $current_user->display_name ) . '</p>';

    $post_id = wp_insert_post( array(
        'post_title'   => wp_strip_all_tags( $title ),
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => $current_user->ID,
        'post_type'    => 'post',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        wp_die( 'Failed to create post: ' . $post_id->get_error_message() );
    }

    // redirect back to referer or home
    $redirect = wp_get_referer() ? wp_get_referer() : home_url();
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_twispeer_create_sample_post', 'twispeer_handle_create_sample_post' );
