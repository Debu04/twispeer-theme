/**
 * trending.js — SPA-style toggle between Feed and Trending (robust + header update)
 */
(function () {
  const feedContainer = document.getElementById('feed-items');
  const navItems = document.querySelectorAll('.tp-nav-item');
  const headerEl = document.querySelector('.tp-feed-heading');
  const trendingButtonSelectorText = 'Trending';
  const feedHeadingText = headerEl ? headerEl.textContent.trim() : 'Feed';
  const trendsAPI = (window.TWISPEER_REST && TWISPEER_REST.trends_route)
    ? TWISPEER_REST.trends_route
    : window.location.origin + '/wp-json/twispeer/v1/trends';

  if (!feedContainer || !navItems.length) return;

  let currentView = 'feed'; // 'feed' or 'trending'
  let cachedFeedHTML = null; // store current feed DOM so we can restore quickly
  let isAnimating = false;

  // Utility: fade out -> run async callback -> fade in (with error-safe restore)
  async function crossfadeReplace(asyncReplaceFn, onErrorRestore = true) {
    if (isAnimating) return;
    isAnimating = true;

    // fade out
    feedContainer.style.transition = 'opacity 220ms cubic-bezier(.2,.9,.2,1), transform 220ms cubic-bezier(.2,.9,.2,1)';
    feedContainer.style.opacity = '0';
    feedContainer.style.transform = 'translateY(6px)';

    // wait end of fade-out
    await new Promise(r => setTimeout(r, 220));

    // run replacement (async)
    try {
      await asyncReplaceFn();
    } catch (e) {
      console.error('crossfadeReplace error:', e);
      if (onErrorRestore && cachedFeedHTML !== null) {
        feedContainer.innerHTML = cachedFeedHTML;
      } else if (onErrorRestore === false) {
        // leave whatever the replace function did
      } else {
        // final fallback message
        feedContainer.innerHTML = '<div class="tp-error">Could not load content</div>';
      }
    }

    // fade in
    feedContainer.style.opacity = '1';
    feedContainer.style.transform = 'translateY(0)';

    // allow next animation after finished
    await new Promise(r => setTimeout(r, 240));
    isAnimating = false;
  }

  function setHeading(text) {
    if (!headerEl) return;
    headerEl.textContent = text;
  }

  // Best: ask TWISPEER_FEED to load trending (it fetches posts by id and renders using same renderer)
  async function loadTrendingAndRender() {
    try {
      const res = await fetch(trendsAPI, { credentials: 'same-origin' });
      if (!res.ok) throw new Error('Trends fetch failed: ' + res.status);
      const trendResp = await res.json();

      if (window.TWISPEER_FEED && typeof window.TWISPEER_FEED.loadTrendingFromTrendResponse === 'function') {
        await window.TWISPEER_FEED.loadTrendingFromTrendResponse(trendResp);
        return;
      }

      // Fallback: if the trends API returns objects with title/content, render them directly
      if (Array.isArray(trendResp) && trendResp.length && (trendResp[0].title || trendResp[0].content)) {
        feedContainer.innerHTML = trendResp.map(d => `
          <article class="tp-post" data-id="${d.id || ''}">
            <h3 class="tp-post-title">${d.title || ''}</h3>
            <div class="tp-post-body">${d.content || d.excerpt || ''}</div>
          </article>
        `).join('');
        return;
      }

      // Last fallback: if trendResp is array of ids {id,score}, try to fetch minimal posts (batch)
      if (Array.isArray(trendResp) && trendResp.length) {
        const ids = trendResp.map(t => t.id).filter(Boolean);
        if (ids.length) {
          const postPromises = ids.map(id => {
            const url = (window.TWISPEER_REST && TWISPEER_REST.root)
              ? TWISPEER_REST.root + 'wp/v2/posts/' + id + '?_embed'
              : window.location.origin + '/wp-json/wp/v2/posts/' + id + '?_embed';
            return fetch(url, { credentials: 'same-origin' }).then(r => r.ok ? r.json() : null).catch(() => null);
          });

          const posts = (await Promise.all(postPromises)).filter(Boolean);
          feedContainer.innerHTML = posts.map(p => `
            <article class="tp-post" data-id="${p.id}">
              <h3 class="tp-post-title">${(p.title && (p.title.rendered || p.title)) || ''}</h3>
              <div class="tp-post-body">${(p.content && (p.content.rendered || p.content)) || ''}</div>
            </article>
          `).join('');
          return;
        }
      }

      // if nothing, show message
      feedContainer.innerHTML = `<div class="tp-empty">No trending posts found.</div>`;
    } catch (err) {
      console.error('Error loading trending', err);
      // throw so crossfadeReplace can restore cached feed
      throw err;
    }
  }

  // Restore feed view using TWISPEER_FEED.loadFeed() if available, otherwise restore cached DOM
  async function loadFeedAndRender() {
    if (window.TWISPEER_FEED && typeof window.TWISPEER_FEED.loadFeed === 'function') {
      await window.TWISPEER_FEED.loadFeed();
      return;
    }

    // fallback: restore cached HTML (if available). If not, call feed refresh if available.
    if (cachedFeedHTML !== null) {
      feedContainer.innerHTML = cachedFeedHTML;
      if (typeof window.initFeedInteractions === 'function') {
        window.initFeedInteractions();
      }
      return;
    }

    if (typeof window.TWISPEER_feedRefresh === 'function') {
      await window.TWISPEER_feedRefresh();
      return;
    }

    // last resort reload
    window.location.reload();
  }

  // Click handler for nav items
  navItems.forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const isTrendingBtn = btn.textContent && btn.textContent.trim().includes(trendingButtonSelectorText);

      if (!isTrendingBtn) {
        // Clicking any other nav item: restore feed if currently showing trending
        if (currentView === 'trending') {
          currentView = 'feed';
          document.querySelectorAll('.tp-nav-item').forEach(i => i.classList.remove('active'));
          btn.classList.add('active');

          // update heading
          setHeading(feedHeadingText);

          // crossfade back to feed
          await crossfadeReplace(loadFeedAndRender);
        }
        return;
      }

      // It's the Trending button
      if (currentView === 'trending') {
        // toggle back to feed if clicked again
        currentView = 'feed';
        document.querySelectorAll('.tp-nav-item').forEach(i => i.classList.remove('active'));
        const feedBtn = Array.from(document.querySelectorAll('.tp-nav-item')).find(n => n.textContent && n.textContent.includes('Feed'));
        if (feedBtn) feedBtn.classList.add('active');

        // update heading
        setHeading(feedHeadingText);

        await crossfadeReplace(loadFeedAndRender);
        return;
      }

      // Enter trending view:
      currentView = 'trending';
      document.querySelectorAll('.tp-nav-item').forEach(i => i.classList.remove('active'));
      btn.classList.add('active');

      // update heading
      setHeading('Trending');

      // Cache current feed HTML before we replace it (so we can restore quickly)
      cachedFeedHTML = feedContainer.innerHTML;

      // Crossfade: fade out -> load trending -> fade in. If trending fails, restore cached HTML.
      await crossfadeReplace(loadTrendingAndRender, true);
    });
  });

  // Optional: if you want deep linking/history, add history.pushState() calls here

})();
