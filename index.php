<?php
/**
 * Front page shell for Twispeer Theme (modular)
 */
get_header();
?>

<main class="tp-main" role="main" aria-label="Main content area">

  <!-- LEFT NAV -->
  <nav class="tp-left" aria-label="Main navigation">
    <?php get_template_part( 'template-parts/nav' ); ?>
  </nav>

  <!-- CENTER -->
  <section class="tp-center" aria-label="Feed and composer">
    <?php
      // composer partial (form)
      get_template_part( 'template-parts/composer' );

      // feed partial (container only — JS will fill)
      get_template_part( 'template-parts/feed' );
    ?>
  </section>

  <!-- RIGHT SIDEBAR -->
  <aside class="tp-right" aria-label="Right sidebar">
    <?php get_template_part( 'template-parts/rightbar' ); ?>
  </aside>

</main>

<?php
get_footer();
