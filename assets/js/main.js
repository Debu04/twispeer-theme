/**
 * Front-end JS: fetch feed and post
 * - uses TWISPEER.rest_url and TWISPEER.nonce localized in functions.php
 */

/* Immediately invoked block */
(function(){
  if ( typeof TWISPEER === 'undefined' ) {
    console.warn('TWISPEER config missing');
    return;
  }

  const base = TWISPEER.rest_url.replace(/\/$/, '') + '/';
  const feedItems = document.getElementById('feed-items');
  const formBtn = document.getElementById('tp-submit');
  const contentInput = document.getElementById('tp-content');
  const messageEl = document.getElementById('tp-message');

  async function fetchFeed(){
    feedItems.innerHTML = '<div class="tp-empty">Loading…</div>';
    try {
      const res = await fetch(base + 'feed');
      if (!res.ok) throw new Error('Failed to load feed');
      const items = await res.json();
      renderFeed(items);
    } catch (err) {
      console.error(err);
      feedItems.innerHTML = '<div class="tp-empty">Unable to load feed</div>';
    }
  }

  function renderFeed(items){
    if (!items || items.length === 0) {
      feedItems.innerHTML = '<div class="tp-empty">No posts yet.</div>';
      return;
    }
    feedItems.innerHTML = items.map(i => {
      return `<div class="tp-feed-item">
                <div class="meta"><strong>${escapeHtml(i.author||'Unknown')}</strong> — ${new Date(i.date).toLocaleString()}</div>
                <div class="content">${i.content}</div>
                <div class="tp-reactions">
                  <div class="tp-reaction">❤️ 0</div>
                  <div class="tp-reaction">😊 0</div>
                </div>
              </div>`;
    }).join('');
  }

  function escapeHtml(str){
    if (!str) return '';
    return str.replace(/[&<>"']/g, function (m) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  async function createPost(){
    const content = (contentInput.value || '').trim();
    if (!content) {
      messageEl.textContent = 'Write something first.';
      return;
    }
    messageEl.textContent = 'Posting…';
    try {
      const res = await fetch(base + 'post', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': TWISPEER.nonce
        },
        body: JSON.stringify({ content }),
        credentials: 'same-origin'
      });
      const body = await res.json();
      if (!res.ok) {
        messageEl.textContent = body.message || 'Failed to post';
        return;
      }
      messageEl.textContent = 'Posted!';
      contentInput.value = '';
      fetchFeed(); // refresh feed
    } catch (err) {
      console.error(err);
      messageEl.textContent = 'Network error';
    } finally {
      setTimeout(()=>{ messageEl.textContent = ''; }, 2500);
    }
  }

  if (formBtn) formBtn.addEventListener('click', createPost);

  // load feed on start
  fetchFeed();
})();
