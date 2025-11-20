// assets/js/components/feed.js
// Renders default 5 emoji buttons (with counts) + inline + button to add custom emoji.
// Shows top-2 custom emojis (those NOT in the default five) with counts.
// Enforces one reaction per user by calling server POST /twispeer/v1/reactions (server enforces single reaction).
// Also provides a Share modal (native share if available, else copy link).

(function(){
  if ( typeof TWISPEER === 'undefined' ) return;

  const base = TWISPEER.rest_url.replace(/\/$/, '') + '/';
  const feedItems = document.getElementById('feed-items');
  if (!feedItems) return;

  // Default five emojis (preserved order)
  const DEFAULT_EMOJIS = ['❤️','😂','😮','😢','😡'];

  /* ---------- Fetch feed (posts) ---------- */
  async function fetchFeed(){
    feedItems.innerHTML = '<div class="tp-empty">Loading…</div>';
    try {
      const res = await fetch(base + 'feed');
      if (!res.ok) throw new Error('Failed to load feed');
      const items = await res.json();
      renderPosts(items);
    } catch (err) {
      console.error(err);
      feedItems.innerHTML = '<div class="tp-empty">Unable to load feed</div>';
    }
  }

  /* ---------- Render posts (basic markup) ---------- */
  function renderPosts(items){
    if (!items || items.length === 0) {
      feedItems.innerHTML = '<div class="tp-empty">No posts yet.</div>';
      return;
    }

    feedItems.innerHTML = items.map(i => {
      const id = i.id;
      const author = escapeHtml(i.author || 'Unknown');
      const dateShort = formatDateShort(i.date);
      const content = i.content || '';

      return `<div class="tp-feed-item" data-post-id="${id}" data-post-link="${escapeAttr(i.link)}">
                <div class="tp-feed-item-header">
                  <div class="tp-profile">
                    <div class="tp-avatar-sm" aria-hidden="true">${escapeHtml((author||'U').charAt(0).toUpperCase())}</div>
                    <div class="tp-profile-meta">
                      <div class="tp-profile-name">${author}</div>
                      <div class="tp-profile-time">${dateShort}</div>
                    </div>
                  </div>

                  <div style="position:relative;">
                    <button class="tp-menu-button" aria-expanded="false" aria-label="Open post menu">⋯</button>
                    <div class="tp-menu-panel" role="menu" aria-hidden="true">
                      <button class="tp-menu-item tp-report-btn" data-action="report" type="button">Report</button>
                      <button class="tp-menu-item tp-remove-btn" data-action="remove" type="button">Remove</button>
                    </div>
                  </div>
                </div>

                <div class="content">${content}</div>

                <div class="tp-reactions-row">
                  <div class="tp-reactions" data-rendered="false">
                    <!-- default emoji buttons + plus will be rendered here -->
                    <div class="tp-empty-inline">Loading reactions…</div>
                  </div>

                  <div class="tp-actions">
                    <button class="tp-action-btn tp-share-btn" type="button" aria-label="Share">
                      🔗 <span>Share</span>
                    </button>
                    <span class="tp-action-notice" style="display:none;"></span>
                  </div>
                </div>
              </div>`;
    }).join('');

    // fetch & render reactions per post
    document.querySelectorAll('.tp-feed-item').forEach(item => {
      fetchAndRenderReactions(item);
    });
  }

  /* ---------- Fetch reactions for a single post ---------- */
  async function fetchAndRenderReactions(feedItem){
    const postId = feedItem.dataset.postId;
    const reactionsEl = feedItem.querySelector('.tp-reactions');
    if (!postId || !reactionsEl) return;

    reactionsEl.innerHTML = '<div class="tp-empty-inline">Loading…</div>';

    try {
      const url = base + `reactions?post_id=${encodeURIComponent(postId)}`;
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) throw new Error('Failed to load reactions');
      const data = await res.json();
      renderReactionsUI(feedItem, data);
    } catch (err) {
      console.error(err);
      reactionsEl.innerHTML = '<div class="tp-empty-inline">—</div>';
    }
  }

  /* ---------- Render reactions UI ---------- */
  function renderReactionsUI(feedItem, data){
    // data.reactions => { emoji: count, ... }
    // data.top => top two (emoji=>count)
    // data.total => total number
    // data.user_reaction => string emoji or null

    const reactions = data.reactions || {};
    const userReaction = data.user_reaction || null;
    const totalCount = data.total || 0;

    const reactionsEl = feedItem.querySelector('.tp-reactions');
    reactionsEl.innerHTML = ''; // clear

    // 1) Render the five default emojis (in order). Each shows count (0 allowed).
    DEFAULT_EMOJIS.forEach(em => {
      const cnt = reactions[em] ? reactions[em] : 0;
      const btn = document.createElement('button');
      btn.className = 'tp-reaction-btn';
      btn.tabIndex = 0;
      btn.setAttribute('data-emoji', em);
      if (userReaction && userReaction === em) btn.classList.add('active');
      btn.innerHTML = `<span class="tp-reaction-emoji">${escapeHtml(em)}</span><span class="tp-reaction-count">${cnt}</span>`;
      btn.addEventListener('click', () => setReaction(feedItem, em));
      reactionsEl.appendChild(btn);
    });

    // 2) Inline + add button (same line)
    const addBtn = document.createElement('button');
    addBtn.className = 'tp-add-emoji';
    addBtn.title = 'Add a custom emoji reaction';
    addBtn.textContent = '+';
    addBtn.addEventListener('click', () => {
      const custom = prompt('Add an emoji (single character, e.g. 😎)');
      if (!custom) return;
      const emojiChar = custom.trim().charAt(0);
      if (!emojiChar) return;
      setReaction(feedItem, emojiChar); // server will add/increment this emoji
    });
    reactionsEl.appendChild(addBtn);

    // 3) Now compute custom emojis (those NOT among DEFAULT_EMOJIS) and pick top 2
    const customs = [];
    for (const [em, cnt] of Object.entries(reactions)) {
      if (!DEFAULT_EMOJIS.includes(em)) customs.push({ e: em, c: cnt });
    }
    // sort by count desc
    customs.sort((a,b)=> b.c - a.c);

    // 4) Render the two top custom emojis (if exist) in their own small area (after default row)
    if (customs.length > 0) {
      const customWrapper = document.createElement('div');
      customWrapper.className = 'tp-custom-top';
      // label optional: you asked to show top two most popular custom emojis
      // We'll show them inline as small buttons with counts
      customs.slice(0,2).forEach(item => {
        const btn = document.createElement('button');
        btn.className = 'tp-reaction-btn';
        btn.setAttribute('data-emoji', item.e);
        if (userReaction && userReaction === item.e) btn.classList.add('active');
        btn.innerHTML = `<span class="tp-reaction-emoji">${escapeHtml(item.e)}</span><span class="tp-reaction-count">${item.c}</span>`;
        btn.addEventListener('click', () => setReaction(feedItem, item.e));
        customWrapper.appendChild(btn);
      });

      // show total custom reactions number (all non-default emojis total)
      const totalCustom = customs.reduce((s,it)=> s + (it.c||0), 0);
      const totalBadge = document.createElement('div');
      totalBadge.className = 'tp-custom-total';
      totalBadge.textContent = `${totalCustom} custom`;
      customWrapper.appendChild(totalBadge);

      // attach wrapper after the default row (end of reactionsEl)
      reactionsEl.parentElement.insertBefore(customWrapper, reactionsEl.parentElement.querySelector('.tp-actions'));
    } else {
      // remove possible old custom wrapper
      const old = feedItem.querySelector('.tp-custom-top');
      if (old) old.remove();
    }

    // 5) update total reactions notice (we'll show as small text next to actions)
    const notice = feedItem.querySelector('.tp-action-notice');
    if (notice) {
      notice.textContent = `${totalCount} reacted`;
      notice.style.display = totalCount > 0 ? 'inline' : 'none';
    }

    // mark rendered so we don't re-fetch for the same DOM
    const _reactionsEl = feedItem.querySelector('.tp-reactions');
    if (_reactionsEl) _reactionsEl.setAttribute('data-rendered', 'true');
  }

  /* ---------- Set reaction (POST) ---------- */
  // Posts to /twispeer/v1/reactions with { post_id, emoji } and expects updated aggregated response
  async function setReaction(feedItem, emoji) {
    const postId = feedItem.dataset.postId;
    if (!postId) {
      showNotice(feedItem, 'Invalid post');
      return;
    }

    try {
      const res = await fetch( base + 'reactions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': TWISPEER.nonce },
        body: JSON.stringify({ post_id: postId, emoji: emoji }),
        credentials: 'same-origin'
      } );
      if (!res.ok) {
        // read JSON for message if available
        const errBody = await res.json().catch(()=>({}));
        const msg = (errBody && errBody.message) ? errBody.message : 'React failed';
        throw new Error(msg);
      }
      const data = await res.json();
      // Re-render reactions UI with returned aggregated data
      renderReactionsUI(feedItem, data);
      showNotice(feedItem, 'Saved');
    } catch (err) {
      console.error(err);
      showNotice(feedItem, err.message || 'React failed');
    }
  }

  /* ---------- Event delegation: menus & share ---------- */
  document.addEventListener('click', function(e){
    // menu button
    const menuBtn = e.target.closest('.tp-menu-button');
    if (menuBtn) {
      const panel = menuBtn.parentElement.querySelector('.tp-menu-panel');
      if (!panel) return;
      const isOpen = panel.classList.contains('open');
      // close others
      document.querySelectorAll('.tp-menu-panel.open').forEach(p => {
        if (p !== panel) {
          p.classList.remove('open');
          const b = p.parentElement.querySelector('.tp-menu-button');
          if (b) b.setAttribute('aria-expanded','false');
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

    // click outside closes panels
    if (!e.target.closest('.tp-menu-panel')) {
      document.querySelectorAll('.tp-menu-panel.open').forEach(p => {
        p.classList.remove('open');
        const b = p.parentElement.querySelector('.tp-menu-button');
        if (b) b.setAttribute('aria-expanded','false');
        p.setAttribute('aria-hidden','true');
      });
    }

    // report
    const rb = e.target.closest('.tp-report-btn');
    if (rb) {
      const feedItem = rb.closest('.tp-feed-item');
      const postId = feedItem && feedItem.dataset.postId;
      if (!postId) return;
      fetch(base + 'report', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ post_id: postId }), credentials: 'same-origin' })
        .then(r => r.json()).then(json => showNotice(feedItem, `Reported (${json.count||''})`))
        .catch(()=> showNotice(feedItem, 'Report failed'));
      closePanel(rb);
      return;
    }

    // remove (delete)
    const removeBtn = e.target.closest('.tp-remove-btn');
    if (removeBtn) {
      const feedItem = removeBtn.closest('.tp-feed-item');
      const postId = feedItem && feedItem.dataset.postId;
      if (!postId) return;
      if (!confirm('Remove this post?')) { closePanel(removeBtn); return; }
      const url = window.location.origin + '/wp-json/wp/v2/posts/' + postId;
      fetch(url, { method: 'DELETE', headers: { 'X-WP-Nonce': TWISPEER.nonce }, credentials: 'same-origin' })
        .then(res => { if (!res.ok) throw new Error('Delete failed'); feedItem.remove(); })
        .catch(err => { console.error(err); showNotice(feedItem, 'Delete failed'); });
      closePanel(removeBtn);
      return;
    }

    // share button -> open Share modal
    const sb = e.target.closest('.tp-share-btn');
    if (sb) {
      const feedItem = sb.closest('.tp-feed-item');
      const link = feedItem && feedItem.dataset.postLink ? feedItem.dataset.postLink : window.location.href;
      openShareModal(link, feedItem);
      return;
    }
  });

  function closePanel(btn){
    const panel = btn.closest('.tp-menu-panel');
    if (panel) {
      panel.classList.remove('open');
      panel.setAttribute('aria-hidden','true');
      const b = panel.parentElement.querySelector('.tp-menu-button');
      if (b) b.setAttribute('aria-expanded','false');
    }
  }

  /* ---------- Share modal implementation ---------- */
  let shareModalEl = null;
  function openShareModal(link, feedItem) {
    if (!shareModalEl) createShareModal();
    shareModalEl.querySelector('.share-link-input').value = link;
    shareModalEl.classList.add('open');
    shareModalEl.style.display = 'block';
    document.body.classList.add('tp-modal-open');
    // record which feedItem opened modal to show notice back
    shareModalEl._currentFeedItem = feedItem;
  }
  function closeShareModal() {
    if (!shareModalEl) return;
    shareModalEl.classList.remove('open');
    shareModalEl.style.display = 'none';
    document.body.classList.remove('tp-modal-open');
    shareModalEl._currentFeedItem = null;
  }

  function createShareModal(){
    shareModalEl = document.createElement('div');
    shareModalEl.className = 'tp-share-modal';
    shareModalEl.innerHTML = `
      <div class="tp-share-modal-inner" role="dialog" aria-modal="true">
        <button class="tp-share-modal-close" aria-label="Close">✕</button>
        <h3>Share post</h3>
        <div class="tp-share-options">
          <button class="tp-share-native tp-action-btn">🔗 Share (device)</button>
          <button class="tp-share-copy tp-action-btn">📋 Copy link</button>
          <input class="share-link-input" readonly />
          <div class="tp-share-smallprint">Choose how you'd like to share this post.</div>
        </div>
      </div>`;
    document.body.appendChild(shareModalEl);

    // events
    shareModalEl.querySelector('.tp-share-modal-close').addEventListener('click', closeShareModal);
    shareModalEl.querySelector('.tp-share-native').addEventListener('click', async function(){
      const link = shareModalEl.querySelector('.share-link-input').value;
      try {
        if (navigator.share) {
          await navigator.share({ title: document.title, url: link });
          showModalNotice('Shared');
          if (shareModalEl._currentFeedItem) showNotice(shareModalEl._currentFeedItem, 'Shared');
        } else {
          showModalNotice('Native share not available on this device');
        }
      } catch (err) {
        showModalNotice('Share cancelled');
      }
    });
    shareModalEl.querySelector('.tp-share-copy').addEventListener('click', async function(){
      const link = shareModalEl.querySelector('.share-link-input').value;
      try {
        await copyToClipboard(link);
        showModalNotice('Link copied');
        if (shareModalEl._currentFeedItem) showNotice(shareModalEl._currentFeedItem, 'Link copied');
      } catch (err) {
        showModalNotice('Copy failed');
      }
    });

    // basic click outside to close
    shareModalEl.addEventListener('click', function(e){
      if (e.target === shareModalEl) closeShareModal();
    });
  }

  function showModalNotice(txt) {
    if (!shareModalEl) return;
    let el = shareModalEl.querySelector('.tp-share-modal-notice');
    if (!el) {
      el = document.createElement('div');
      el.className = 'tp-share-modal-notice';
      shareModalEl.querySelector('.tp-share-options').appendChild(el);
    }
    el.textContent = txt;
    setTimeout(()=> { if (el) el.textContent = ''; }, 1600);
  }

  /* ---------- Helpers ---------- */
  async function copyToClipboard(text){
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
    const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); } finally { document.body.removeChild(ta); }
  }

  function formatDateShort(iso){
    try {
      const d = new Date(iso);
      const now = Date.now();
      const diff = Math.floor((now - d.getTime())/1000);
      if (diff < 60) return `${diff}s · ${d.toLocaleDateString()}`;
      const mins = Math.floor(diff/60);
      if (mins < 60) return `${mins}m · ${d.toLocaleDateString()}`;
      const hrs = Math.floor(mins/60);
      if (hrs < 24) return `${hrs}h · ${d.toLocaleDateString()}`;
      const days = Math.floor(hrs/24);
      return `${days}d · ${d.toLocaleDateString()}`;
    } catch (e) { return ''; }
  }

  function escapeHtml(str){ if (!str) return ''; return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
  function escapeAttr(s){ return (s||'').replace(/"/g, '&quot;'); }

  function showNotice(feedItem, text){
    const notice = feedItem ? feedItem.querySelector('.tp-action-notice') : null;
    if (!notice) return;
    notice.textContent = text;
    notice.style.display = 'inline';
    setTimeout(()=> { notice.style.display = 'none'; }, 1600);
  }

  /**
   * Re-initialize per-post interactions after an external render.
   * This will:
   *  - call fetchAndRenderReactions for each .tp-feed-item so reaction counts show up
   *  - (delegated click handlers for menus/share/report are already global and will work)
   */
  function initFeedInteractions() {
    document.querySelectorAll('.tp-feed-item').forEach(item => {
      // If reactions container hasn't been rendered, fetch & render
      const reactionsEl = item.querySelector('.tp-reactions');
      if (reactionsEl) {
        // prevent duplicate calls: only fetch when data-rendered is not true
        if (reactionsEl.getAttribute('data-rendered') !== 'true') {
          // call the same function used during initial render
          fetchAndRenderReactions(item);
        }
      } else {
        // If markup is different, attempt to find post id and create reactions container + fetch
        const postId = item.dataset && item.dataset.postId;
        if (postId) {
          // safe fallback: call fetchAndRenderReactions if present
          try { fetchAndRenderReactions(item); } catch(e) { /* ignore */ }
        }
      }
    });
  }

  // expose for other modules (TWISPEER_FEED.renderPostsIntoFeed will call this)
  window.initFeedInteractions = initFeedInteractions;

  // initialize
  fetchFeed();

  // expose refresh
  window.TWISPEER_feedRefresh = fetchFeed;

})();



// === TWISPEER_FEED public API (append only) ===
(function () {
  // Ensure we don't overwrite if already set
  window.TWISPEER_FEED = window.TWISPEER_FEED || {};

  // === Twispeer: escape helpers (added to fix Trending section error) ===
function escapeHtml(s) {
  if (typeof window.escapeHtml === 'function') return window.escapeHtml(s);
  if (!s) return '';
  return ('' + s).replace(/[&<>"']/g, m => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[m]));
}

function escapeAttr(s) {
  if (typeof window.escapeAttr === 'function') return window.escapeAttr(s);
  return (s || '').replace(/"/g, '&quot;');
}
// === End of patch ===


  // internal cache for posts we've already fetched/rendered
  const cache = {
    postsById: {},   // id -> post object
    lastList: []     // last array of posts shown in feed
  };

  // --- replace renderPostHTML with this (keeps same markup as renderPosts) ---
  function renderPostHTML(post) {
    const id = post.id || '';
    const author = escapeHtml(post.author || 'Unknown');
    const dateShort = ''; // TWISPEER_FEED render doesn't currently know the exact date; feed.loadFeed provides full objects if needed
    const content = post.content || '';

    // Use the same structure as renderPosts() produced earlier so event delegation & reaction hooks work
    return `<div class="tp-feed-item" data-post-id="${id}" data-post-link="${escapeAttr(post.link || '')}">
              <div class="tp-feed-item-header">
                <div class="tp-profile">
                  <div class="tp-avatar-sm" aria-hidden="true">${escapeHtml((author||'U').charAt(0).toUpperCase())}</div>
                  <div class="tp-profile-meta">
                    <div class="tp-profile-name">${author}</div>
                    <div class="tp-profile-time">${dateShort}</div>
                  </div>
                </div>

                <div style="position:relative;">
                  <button class="tp-menu-button" aria-expanded="false" aria-label="Open post menu">⋯</button>
                  <div class="tp-menu-panel" role="menu" aria-hidden="true">
                    <button class="tp-menu-item tp-report-btn" data-action="report" type="button">Report</button>
                    <button class="tp-menu-item tp-remove-btn" data-action="remove" type="button">Remove</button>
                  </div>
                </div>
              </div>

              <div class="content">${content}</div>

              <div class="tp-reactions-row">
                <div class="tp-reactions" data-rendered="false">
                  <div class="tp-empty-inline">Loading reactions…</div>
                </div>

                <div class="tp-actions">
                  <button class="tp-action-btn tp-share-btn" type="button" aria-label="Share">
                    🔗 <span>Share</span>
                  </button>
                  <span class="tp-action-notice" style="display:none;"></span>
                </div>
              </div>
            </div>`;
  }


  function escapeHtml(s) {
    if (!s) return '';
    return ('' + s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  // Render a list of post objects into #feed-items, preserving your existing event hooks
  function renderPostsIntoFeed(posts) {
    const feed = document.getElementById('feed-items');
    if (!feed) return;
    // Save last list
    cache.lastList = posts.slice();

    // Build HTML using the project's renderer if available (hook for you)
    if (window.TWISPEER_RENDER && typeof window.TWISPEER_RENDER.post === 'function') {
      feed.innerHTML = posts.map(p => window.TWISPEER_RENDER.post(p)).join('');
    } else {
      feed.innerHTML = posts.map(renderPostHTML).join('');
    }

    // Re-attach interaction handlers (reactions etc.) if your main feed script exposes them.
    // If you already have a function like initFeedInteractions(), call it here.
    if (typeof window.initFeedInteractions === 'function') {
      window.initFeedInteractions();
    }
  }

  // Fetch a single post by WP REST ID (wp/v2/posts) — fallback safe method
  async function fetchPostById(id) {
    if (cache.postsById[id]) return cache.postsById[id];

    try {
      const url = (window.TWISPEER_REST && TWISPEER_REST.root)
        ? TWISPEER_REST.root + 'wp/v2/posts/' + id + '?_embed'
        : window.location.origin + '/wp-json/wp/v2/posts/' + id + '?_embed';

      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) throw new Error('Post fetch failed');
      const post = await res.json();

      // Get author name from _embedded if available
      let authorName = '';
      if (post._embedded && post._embedded.author && post._embedded.author[0] && post._embedded.author[0].name) {
        authorName = post._embedded.author[0].name;
      } else if (post.author && typeof post.author === 'string') {
        authorName = post.author;
      } else {
        authorName = '';
      }

      // Normalize to minimal shape used by renderer (include link & author name)
      const normalized = {
        id: post.id,
        title: post.title && (post.title.rendered || post.title) || '',
        content: post.content && (post.content.rendered || post.content) || '',
        author: authorName || '',
        link: post.link || '',
        likes: post.meta && post.meta._likes ? post.meta._likes : 0,
        comments: post.comment_count || 0
      };

      cache.postsById[id] = normalized;
      return normalized;
    } catch (err) {
      console.warn('fetchPostById error', err);
      return null;
    }
  }

  // Bulk fetch posts by an array of IDs (tries cache first)
  async function fetchPostsByIds(ids = []) {
    const results = [];
    for (const id of ids) {
      const cached = cache.postsById[id];
      if (cached) {
        results.push(cached);
      } else {
        // fetch each (could be optimized to batch; keep simple and reliable)
        const p = await fetchPostById(id);
        if (p) results.push(p);
      }
    }
    return results;
  }

  // Public: load feed normally (calls your existing feed loader if present)
    // Public: load feed normally (calls your existing feed loader if present)
  async function loadFeed() {
    // Prefer your existing feed loader (we expose it as TWISPEER_feedRefresh)
    if (typeof window.loadMainFeed === 'function') {
      return window.loadMainFeed();
    }
    if (typeof window.TWISPEER_feedRefresh === 'function') {
      return window.TWISPEER_feedRefresh();
    }

    // Fallback: if cache has lastList, render it, else try to fetch /wp/v2/posts (simple)
    if (cache.lastList && cache.lastList.length) {
      renderPostsIntoFeed(cache.lastList);
      return;
    }

    try {
      const url = (window.TWISPEER_REST && TWISPEER_REST.root)
        ? TWISPEER_REST.root + 'wp/v2/posts?per_page=10'
        : window.location.origin + '/wp-json/wp/v2/posts?per_page=10';

      const res = await fetch(url, { credentials: 'same-origin' });
      const arr = await res.json();
      const normalized = arr.map(p => ({
        id: p.id,
        title: p.title && (p.title.rendered || p.title) || '',
        content: p.content && (p.content.rendered || p.content) || '',
        author: p.author || '',
        likes: p.meta && p.meta._likes ? p.meta._likes : 0,
        comments: p.comment_count || 0
      }));

      // cache them
      normalized.forEach(p => cache.postsById[p.id] = p);
      renderPostsIntoFeed(normalized);
    } catch (err) {
      console.error('TWISPEER_FEED.loadFeed error', err);
      // if everything fails, show a friendly message but don't break the whole UI
      const feed = document.getElementById('feed-items');
      if (feed) feed.innerHTML = '<div class="tp-empty">Unable to load feed</div>';
    }
  }


  // Public: load trending posts given an array of post IDs OR fallback to provided trend objects that already contain full data
  async function loadTrendingFromTrendResponse(trendResp = []) {
    // trendResp can be array of {id: number} OR full post-like objects
    if (!Array.isArray(trendResp)) return;

    // If trendResp looks like full posts (has title & content), just render
    const needIds = [];
    const fullPosts = [];
    for (const t of trendResp) {
      if (t.id && (t.title || t.content)) fullPosts.push({
        id: t.id, title: t.title || '', content: t.content || '', author: t.author || '', likes: t.likes || 0, comments: t.comments || 0
      });
      else if (t.id) needIds.push(t.id);
    }

    let posts = fullPosts.slice();
    if (needIds.length) {
      const fetched = await fetchPostsByIds(needIds);
      posts = posts.concat(fetched.filter(Boolean));
    }

    // cache
    posts.forEach(p => cache.postsById[p.id] = p);
    renderPostsIntoFeed(posts);
  }

  // Export API
  window.TWISPEER_FEED.fetchPostById = fetchPostById;
  window.TWISPEER_FEED.fetchPostsByIds = fetchPostsByIds;
  window.TWISPEER_FEED.renderPostsIntoFeed = renderPostsIntoFeed;
  window.TWISPEER_FEED.loadFeed = loadFeed;
  window.TWISPEER_FEED.loadTrendingFromTrendResponse = loadTrendingFromTrendResponse;
  window.TWISPEER_FEED._cache = cache;

  // Optionally auto-load feed on initial page load if your theme doesn't already
  if (!document.querySelector('#feed-items').children.length) {
    document.addEventListener('DOMContentLoaded', () => {
      // small delay so other code initializes
      setTimeout(() => {
        if (typeof window.TWISPEER_FEED.loadFeed === 'function') window.TWISPEER_FEED.loadFeed();
      }, 40);
    });
  }
})();
