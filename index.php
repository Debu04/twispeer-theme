<?php
/**
 * Front page shell for Twispeer Theme
 *
 * Layout:
 *  - left nav (vertical)
 *  - center (composer + feed)
 *  - right (trending / prompt / active)
 */
get_header();
?>

<main class="tp-main" role="main" aria-label="Main content area">

  <!-- LEFT NAV -->
  <nav class="tp-left" aria-label="Main navigation">
    <ul class="tp-nav-list">
      <li class="tp-nav-item active"><span class="tp-icon">🏠</span><span class="tp-nav-label">Feed</span></li>
      <li class="tp-nav-item"><span class="tp-icon">🔥</span><span class="tp-nav-label">Trending</span></li>
      <li class="tp-nav-item"><span class="tp-icon">💭</span><span class="tp-nav-label">My Thoughts</span></li>
      <li class="tp-nav-item"><span class="tp-icon">＋</span><span class="tp-nav-label">New Post</span></li>
      <li class="tp-nav-item"><span class="tp-icon">⚙️</span><span class="tp-nav-label">Settings</span></li>
      <li class="tp-nav-item"><span class="tp-icon">⎋</span><span class="tp-nav-label">Logout</span></li>
    </ul>
  </nav>

  <!-- CENTER: Composer + Feed -->
  <section class="tp-center" aria-label="Feed and composer">

    <!-- Composer card -->
    <div class="tp-card tp-composer">
      <textarea id="tp-content" class="tp-composer-input" placeholder="What's whispering in your mind?"></textarea>
      <div class="tp-composer-actions">
        <div class="tp-composer-meta">
          <!-- small placeholder for attachments / emoji controls -->
          <button class="tp-ghost" type="button" aria-hidden="true">😊</button>
          <button class="tp-ghost" type="button" aria-hidden="true">＋</button>
        </div>
        <div>
          <button id="tp-submit" class="tp-btn">Post</button>
        </div>
      </div>
    </div>

    <!-- Feed list (items rendered by JS via REST) -->
    <div id="feed" class="tp-feed">
      <!-- feed header -->
      <h2 class="tp-feed-heading">Feed</h2>
      <div id="feed-items" class="tp-feed-items">
        <!-- JS will inject feed items here -->
        <div class="tp-empty">Loading…</div>
      </div>
    </div>

  </section>

  <!-- RIGHT SIDEBAR -->
  <aside class="tp-right" aria-label="Right sidebar">

    <div class="tp-card tp-right-block">
      <h3 class="tp-right-title">Trending</h3>
      <div class="tp-trending">
        <!-- sample items (will be replaced by live data) -->
        <div class="tp-trend-item"><div class="tp-trend-text">How to stay focused</div><div class="tp-trend-count">😄 120</div></div>
        <div class="tp-trend-item"><div class="tp-trend-text">Morning routines</div><div class="tp-trend-count">🙂 15</div></div>
      </div>
    </div>

    <div class="tp-card tp-right-block">
      <h3 class="tp-right-title">Today's Prompt</h3>
      <div class="tp-prompt">Describe your current mood in one word.</div>
    </div>

    <div class="tp-card tp-right-block">
      <h3 class="tp-right-title">Recently Active</h3>
      <div class="tp-active">
        <div class="tp-avatar-mini"></div>
        <div class="tp-avatar-mini"></div>
        <div class="tp-avatar-mini"></div>
        <div class="tp-avatar-mini"></div>
      </div>
    </div>

  </aside>

</main>

<?php
get_footer();
