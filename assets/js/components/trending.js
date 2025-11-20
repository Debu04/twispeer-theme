/**
 * trending.js
 * Minimal, safe version — works WITH existing feed.js (unchanged)
 */

(function () {

  const feedContainer = document.getElementById("feed-items");
  const feedHeading = document.querySelector(".tp-feed-heading");

  const navItems = document.querySelectorAll(".tp-nav-item");
  const trendingBtn = Array.from(navItems).find(btn =>
    btn.textContent.trim().includes("Trending")
  );
  const feedBtn = Array.from(navItems).find(btn =>
    btn.textContent.trim().includes("Feed")
  );

  if (!trendingBtn || !feedBtn) return;

  let currentView = "feed";

  const trendingAPI =
    (window.TWISPEER_REST && TWISPEER_REST.trending_route)
      ? TWISPEER_REST.trending_route
      : window.location.origin + "/wp-json/twispeer/v1/trending";


  /* ---------------------------------------------------
      Switch UI state
  --------------------------------------------------- */

  function setActiveButton(activeBtn) {
    navItems.forEach(b => b.classList.remove("active"));
    activeBtn.classList.add("active");
  }


  function showLoading(text = "Loading…") {
    feedContainer.innerHTML = `<div class="tp-loading">${text}</div>`;
  }


  /* ---------------------------------------------------
      Load Trending → reuse FEED renderer
  --------------------------------------------------- */

  async function loadTrending() {
    showLoading("Loading trending posts…");

    try {
      const res = await fetch(trendingAPI, { credentials: "same-origin" });
      if (!res.ok) throw new Error("Network failed");

      const trendingPosts = await res.json();

      // Call feed.js built-in renderer
      if (window.TWISPEER_FEED && typeof TWISPEER_FEED.renderPostsIntoFeed === "function") {

        TWISPEER_FEED.renderPostsIntoFeed(trendingPosts);

      } else {
        feedContainer.innerHTML = "<div class='tp-error'>Feed renderer not found.</div>";
      }

    } catch (err) {
      console.error("Trending error:", err);
      feedContainer.innerHTML = `<div class="tp-error">Could not load trending posts.</div>`;
    }
  }


  /* ---------------------------------------------------
      Load Feed (existing feed.js)
  --------------------------------------------------- */

  function loadFeed() {
    if (window.TWISPEER_FEED && typeof TWISPEER_FEED.loadFeed === "function") {
      TWISPEER_FEED.loadFeed();
    }
  }


  /* ---------------------------------------------------
      BUTTON CLICK HANDLERS
  --------------------------------------------------- */

  trendingBtn.addEventListener("click", async () => {

    if (currentView === "trending") return; // already here

    currentView = "trending";
    setActiveButton(trendingBtn);

    // Change title
    if (feedHeading) feedHeading.textContent = "Trending";

    await loadTrending();
  });


  feedBtn.addEventListener("click", () => {

    if (currentView === "feed") return;

    currentView = "feed";
    setActiveButton(feedBtn);

    if (feedHeading) feedHeading.textContent = "Feed";

    loadFeed();
  });


})();
