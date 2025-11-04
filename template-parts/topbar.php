<?php
/**
 * template-parts/topbar.php
 * Top bar: brand left + search + avatar right
 */
?>
<div class="tp-topbar">
  <div class="tp-topbar-left">
    <div class="tp-brand">
      <h1 class="tp-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo('name'); ?></a></h1>
    </div>
  </div>

  <div class="tp-topbar-right">
    <div class="tp-search">
      <input type="search" aria-label="Search" placeholder="Search" id="tp-search-input" />
    </div>
    <div class="tp-avatar-fallback" title="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>">
      <?php echo esc_html( strtoupper( substr( wp_get_current_user()->display_name ?: 'U', 0, 1 ) ) ); ?>
    </div>
  </div>
</div>
