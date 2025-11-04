<?php
/**
 * template-parts/composer.php
 * Composer form — JS hooks onto #tp-post-form and #tp-content
 */
?>
<div class="tp-card tp-composer">
  <form id="tp-post-form" class="tp-composer-form" action="#" method="post" onsubmit="return false;">
    <textarea id="tp-content" class="tp-composer-input" placeholder="What's whispering in your mind?" aria-label="Compose"></textarea>
    <div class="tp-composer-actions">
      <div class="tp-composer-meta">
        <button class="tp-ghost" type="button" aria-hidden="true">😊</button>
        <button class="tp-ghost" type="button" aria-hidden="true">＋</button>
      </div>
      <div>
        <button id="tp-submit" class="tp-btn" type="button">Post</button>
      </div>
    </div>
    <div id="tp-message" role="status" aria-live="polite" class="tp-message"></div>
  </form>
</div>
