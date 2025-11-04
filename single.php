<?php
/**
 * Single post view for Twispeer Theme
 */
get_header();
if ( have_posts() ) : while ( have_posts() ) : the_post();
?>

<main class="tp-main" role="main">
  <aside class="tp-left" aria-label="Left sidebar">
    <?php if ( is_active_sidebar( 'twispeer-left' ) ) : ?>
      <?php dynamic_sidebar( 'twispeer-left' ); ?>
    <?php endif; ?>
  </aside>

  <article id="post-<?php the_ID(); ?>" <?php post_class( 'tp-article' ); ?>>
    <header class="tp-article-header">
      <h1 class="tp-article-title"><?php the_title(); ?></h1>
      <div class="meta"><?php echo get_the_author_meta( 'display_name', get_post_field( 'post_author', get_the_ID() ) ); ?> — <?php echo get_the_date(); ?></div>
    </header>

    <div class="tp-article-content">
      <?php the_content(); ?>
    </div>

    <footer class="tp-article-footer">
      <?php
        the_tags( '<div class="tags">Tags: ', ', ', '</div>' );
        if ( comments_open() || get_comments_number() ) {
          comments_template();
        }
      ?>
    </footer>
  </article>

  <aside class="tp-right" aria-label="Right sidebar">
    <?php if ( is_active_sidebar( 'twispeer-right' ) ) : ?>
      <?php dynamic_sidebar( 'twispeer-right' ); ?>
    <?php endif; ?>
  </aside>
</main>

<?php
endwhile; endif;
get_footer();
