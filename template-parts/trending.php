<?php
/**
 * template-parts/trending.php
 * Minimal container for the Trending widget. JS populates #twispeer-trending-list
 */
?>
<div id="twispeer-trending" class="twispeer-trending">
  <div class="trending__header">
    <div class="trending__title">
      <span class="trending__icon" aria-hidden="true">🔥</span>
      <span class="trending__label">Trending</span>
    </div>

    <div class="trending__controls">
      <select id="twispeer-trend-sort" aria-label="Sort trends">
        <option value="hot">Hot</option>
        <option value="recent">Recent</option>
        <option value="top">Top</option>
      </select>
    </div>
  </div>

  <div id="twispeer-trending-list" class="trending__list" role="list">
    <div class="trending__empty">Loading trends…</div>
  </div>

  <div class="trending__footer">
    <button id="twispeer-see-all" class="btn btn--full">See all trends</button>
  </div>
</div>
