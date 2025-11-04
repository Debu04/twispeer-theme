// assets/js/components/feed.js
// Responsible for fetching the feed and rendering into #feed-items,
// and for handling per-card UI: menu toggle, report action, and share action.

/* IIFE to avoid globals */
(function(){
  if ( typeof TWISPEER === 'undefined' ) return;

  const base = TWISPEER.rest_url.replace(/\/$/, '') + '/';
  const feedItems = document.getElementById('feed-items');
  if (!feedItems) return;

  // Fetch feed from REST and render
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

  // Render array of items
  function render(items){
    if (!items || items.length === 0) {
      feedItems.innerHTML = '<div class="tp-empty">No posts yet.</div>';
      return;
    }

    feedItems.innerHTML = items.map(i => {
      // i.id, i.title, i.content, i.author, i.date, i.link
      const safeTitle = escapeHtml(i.title || '');
      const safeContent = i.content || '';
      const safeAuthor = escapeHtml(i.author || 'Unknown');
      const dateStr = new Date(i.date).toLocaleString();

      return `<div class="tp-feed-item" data-post-id="${i.id}" data-post-link="${escapeAttr(i.link)}">
                <div class="tp-feed-item-header">
                  <h3 class="tp-feed-item-title">${safeTitle}</h3>

                  <!-- three dots menu button -->
                  <div style="position:relative;">
                    <button class="tp-menu-button" aria-expanded="false" aria-label="Open menu">⋯</button>

                    <!-- menu panel (hidden by default) -->
                    <div class="tp-menu-panel" role="menu" aria-hidden="true">
                      <button class="tp-menu-item tp-report-btn" data-action="report" type="button">Report</button>
                      <!-- you can add more items here (e.g., hide, bookmark) -->
                    </div>
                  </div>
                </div>

                <div class="meta"><strong>${safeAuthor}</strong> — ${dateStr}</div>

                <div class="content">${safeContent}</div>

                <div class="tp-feed-item-actions">
                  <div class="tp-reactions">
                    <div class="tp-reaction">❤️ 0</div>
                    <div class="tp-reaction">😊 0</div>
                  </div>

                  <!-- share button on bottom-right -->
                  <div>
                    <button class="tp-share-btn" type="button" aria-label="Share post">Share</button>
                    <span class="tp-action-notice" aria-hidden="true" style="display:none;"></span>
                  </div>
                </div>
              </div>`;
    }).join('');
  }

  // escape helpers
  function escapeHtml(str){
    if (!str) return '';
    return str.replace(/[&<>"']/g, function (m) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }
  function escapeAttr(s){
    return (s||'').replace(/"/g, '&quot;');
  }

  /* ---------------------------
     Event delegation for menu / report / share
     --------------------------- */
  document.addEventListener('click', function(e){
    // 1) open/close menu
    const menuBtn = e.target.closest('.tp-menu-button');
    if (menuBtn) {
      const panel = menuBtn.parentElement.querySelector('.tp-menu-panel');
      if (!panel) return;
      const isOpen = panel.classList.contains('open');
      // close any other open panels first
      document.querySelectorAll('.tp-menu-panel.open').forEach(p => {
        if (p !== panel) {
          p.classList.remove('open');
          const btn = p.parentElement.querySelector('.tp-menu-button');
          if (btn) btn.setAttribute('aria-expanded','false');
          p.setAttribute('aria-hidden','true');
        }
      });
      if (isOpen) {
        panel.classList.remove('open');
        menuBtn.setAttribute('aria-expanded','false');
        panel.setAttribute('aria-hidden','true');
      } else {
        panel.classList.add('open');
        menuBtn.setAttribute('aria-expanded','true');
        panel.setAttribute('aria-hidden','false');
      }
      return;
    }

    // 2) click outside menu should close open menus
    if (!e.target.closest('.tp-menu-panel')) {
      document.querySelectorAll('.tp-menu-panel.open').forEach(p => {
        p.classList.remove('open');
        const btn = p.parentElement.querySelector('.tp-menu-button');
        if (btn) btn.setAttribute('aria-expanded','false');
        p.setAttribute('aria-hidden','true');
      });
    }

    // 3) report button
    const reportBtn = e.target.closest('.tp-report-btn');
    if (reportBtn) {
      const feedItem = reportBtn.closest('.tp-feed-item');
      if (!feedItem) return;
      const postId = feedItem.dataset.postId;
      // call report API
      reportPost(postId).then(result => {
        // show brief notice next to share button
        const notice = feedItem.querySelector('.tp-action-notice');
        if (notice) {
          notice.textContent = result && result.count ? `Reported (${result.count})` : 'Reported';
          notice.style.display = 'inline';
          setTimeout(()=> { notice.style.display = 'none'; }, 1800);
        }
      }).catch(err => {
        console.error('Report failed', err);
        const notice = feedItem.querySelector('.tp-action-notice');
        if (notice) {
          notice.textContent = 'Report failed';
          notice.style.display = 'inline';
          setTimeout(()=> notice.style.display = 'none', 1800);
        }
      });
      // close menu
      const panel = reportBtn.closest('.tp-menu-panel');
      if (panel) { panel.classList.remove('open'); panel.setAttribute('aria-hidden','true'); const btn = panel.parentElement.querySelector('.tp-menu-button'); if (btn) btn.setAttribute('aria-expanded','false'); }
      return;
    }

    // 4) share button
    const shareBtn = e.target.closest('.tp-share-btn');
    if (shareBtn) {
      const feedItem = shareBtn.closest('.tp-feed-item');
      if (!feedItem) return;
      const link = feedItem.dataset.postLink || window.location.href;
      doShare(link, feedItem);
      return;
    }
  });

  // Report a post via REST: increments a counter in post meta and returns the new value
  async function reportPost(postId) {
    if (!postId) throw new Error('No post id');
    try {
      const res = await fetch(base + 'report', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId }),
        credentials: 'same-origin'
      });
      if (!res.ok) {
        const errBody = await res.json().catch(()=>({}));
        throw new Error(errBody.message || 'Report failed');
      }
      return await res.json();
    } catch (err) {
      throw err;
    }
  }

  // Share helper: use navigator.share if available, otherwise fallback to copy link
  async function doShare(link, feedItem) {
    try {
      if (navigator.share) {
        await navigator.share({ title: document.title, url: link });
        showShareNotice(feedItem, 'Shared');
      } else {
        // fallback: copy to clipboard
        await copyToClipboard(link);
        showShareNotice(feedItem, 'Link copied');
      }
    } catch (err) {
      console.error('Share failed', err);
      showShareNotice(feedItem, 'Share failed');
    }
  }
  function showShareNotice(feedItem, text) {
    const notice = feedItem.querySelector('.tp-action-notice');
    if (!notice) return;
    notice.textContent = text;
    notice.style.display = 'inline';
    setTimeout(()=> { notice.style.display = 'none'; }, 1800);
  }
  async function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    // fallback for older browsers
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
    } finally {
      document.body.removeChild(ta);
    }
  }

  // initialize
  fetchFeed();

  // expose refresh
  window.TWISPEER_feedRefresh = fetchFeed;
})();
