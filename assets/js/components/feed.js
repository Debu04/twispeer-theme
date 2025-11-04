// assets/js/components/feed.js
// Responsible only for fetching the feed and rendering into #feed-items

(function(){
  if ( typeof TWISPEER === 'undefined' ) return;

  const base = TWISPEER.rest_url.replace(/\/$/, '') + '/';
  const feedItems = document.getElementById('feed-items');
  if (!feedItems) return;

  async function fetchFeed(){
    feedItems.innerHTML = '<div class="tp-empty">Loading…</div>';
    try {
      const res = await fetch(base + 'feed');
      if (!res.ok) throw new Error('Failed to load feed');
      const items = await res.json();
      render(items);
    } catch (err) {
      console.error(err);
      feedItems.innerHTML = '<div class="tp-empty">Unable to load feed</div>';
    }
  }

  function render(items){
    if (!items || items.length === 0) {
      feedItems.innerHTML = '<div class="tp-empty">No posts yet.</div>';
      return;
    }
    feedItems.innerHTML = items.map(i => {
      return `<div class="tp-feed-item">
                <div class="meta"><strong>${escapeHtml(i.author||'Unknown')}</strong> — ${new Date(i.date).toLocaleString()}</div>
                <div class="content">${i.content}</div>
              </div>`;
    }).join('');
  }

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  // initialize
  fetchFeed();

  // expose for manual refresh if needed
  window.TWISPEER_feedRefresh = fetchFeed;
})();
