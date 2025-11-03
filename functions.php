<?php
function twispeer_setup() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  load_theme_textdomain('twispeer', get_template_directory() . '/languages');
}
add_action('after_setup_theme', 'twispeer_setup');

function twispeer_enqueue() {
  wp_enqueue_style('twispeer-style', get_template_directory_uri() . '/assets/css/style.css', array(), '1.0.0');
  wp_enqueue_script('twispeer-main', get_template_directory_uri() . '/assets/js/main.js', array('jquery'), '1.0.0', true);
  wp_localize_script('twispeer-main', 'twispeer_vars', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'rest_url' => rest_url('twispeer/v1/'),
    'nonce' => wp_create_nonce('wp_rest')
  ));
}
add_action('wp_enqueue_scripts', 'twispeer_enqueue');

require_once get_template_directory() . '/inc/config.php';
require_once get_template_directory() . '/inc/rest-api.php';


