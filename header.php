<?php
/**
 * Header for Twispeer Theme
 *
 * Top bar: site title (left), search + avatar (right)
 * This file includes wp_head() so scripts/styles are printed from functions.php.
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); /* required for enqueued styles/scripts */ ?>
</head>
<body <?php body_class(); ?>>
  <!-- Outer rounded shell (centers content and gives that big card feel) -->
  <div id="twispeer-app" class="twispeer-shell">

    <!-- Top bar -->
    <div class="tp-topbar">
      <div class="tp-topbar-left">
        <!-- Brand / Title -->
        <div class="tp-brand">
          <h1 class="tp-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo('name'); ?></a></h1>
        </div>
      </div>

      <div class="tp-topbar-right">
        <!-- Search input -->
        <div class="tp-search">
          <input type="search" aria-label="Search" placeholder="Search" id="tp-search-input" />
        </div>

        <!-- Simple avatar placeholder -->
        <div class="tp-avatar" title="Your account">
          <!-- Use WP custom logo or fallback avatar -->
          <?php if ( function_exists( 'get_custom_user_avatar' ) ) : ?>
            <?php echo get_custom_user_avatar( get_current_user_id() ); ?>
          <?php else: ?>
            <div class="tp-avatar-fallback" aria-hidden="true">U</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
