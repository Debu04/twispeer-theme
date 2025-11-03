<?php
get_header();
?>

<div id="twispeer-app" style="min-height:100vh; background:#0F172A; color:#F8FAFC; font-family:Inter, Poppins, sans-serif;">
  <header style="position:sticky; top:0; z-index:40; background:rgba(15,23,42,0.9); backdrop-filter: blur(6px); border-bottom:1px solid rgba(255,255,255,0.03);">
    <div style="max-width:1100px; margin:0 auto; padding:12px 16px; display:flex; align-items:center; gap:12px;">
      <div style="font-weight:600; font-size:18px; color:#F8FAFC;">Twispeer</div>
      <div style="flex:1; text-align:center; color:#94A3B8;">Where your thoughts whisper — and reactions speak.</div>
      <div><a href="<?php echo wp_login_url(); ?>" style="color:#A855F7; text-decoration:none;">Log in</a></div>
    </div>
  </header>

  <main style="max-width:1100px; margin:20px auto; display:flex; gap:20px; padding:0 16px;">
    <aside style="width:240px; display:none;" id="leftSidebar">
      <nav style="background:#111827; border-radius:12px; padding:12px;">
        <a style="display:block;color:#F8FAFC;padding:8px;border-radius:8px;text-decoration:none;" href="#">Feed</a>
        <a style="display:block;color:#94A3B8;padding:8px;border-radius:8px;text-decoration:none;" href="#">Trending</a>
        <a style="display:block;color:#94A3B8;padding:8px;border-radius:8px;text-decoration:none;" href="#">My Thoughts</a>
      </nav>
    </aside>

    <section style="flex:1;">
      <div style="background:#1E293B; border-radius:12px; padding:12px; margin-bottom:16px;">
        <textarea id="composer" maxlength="280" placeholder="What’s whispering in your mind?" style="width:100%; background:transparent; color:#F8FAFC; border:none; resize:none; min-height:60px; outline:none;"></textarea>
        <div style="text-align:right; margin-top:8px;">
          <button id="postBtn" style="background:#6366F1; color:white; border:none; padding:8px 12px; border-radius:8px; cursor:pointer;">Post</button>
        </div>
      </div>
      <div id="postsContainer"></div>
    </section>

    <aside style="width:300px; display:none;" id="rightSidebar">
      <div style="background:#111827; border-radius:12px; padding:12px;">
        <h4 style="margin:0 0 8px 0; color:#F8FAFC;">Trending</h4>
        <div id="trendingList" style="color:#94A3B8;">Loading...</div>
      </div>
    </aside>
  </main>
</div>

<style>
@media(min-width: 900px) {
  #leftSidebar { display:block; }
  #rightSidebar { display:block; }
}
@media(max-width: 899px) {
  header div { font-size:14px; }
  main { padding-bottom:72px; }
  #leftSidebar, #rightSidebar { display:none; }
}
.post-card { background:#111827; border-radius:12px; padding:12px; margin-bottom:12px; border:1px solid rgba(255,255,255,0.02); }
.post-meta { color:#94A3B8; font-size:13px; margin-bottom:8px; }
.reaction-bar { display:flex; gap:8px; margin-top:10px; align-items:center; }
.reaction-btn { background:transparent; border:1px solid rgba(255,255,255,0.04); padding:6px 8px; border-radius:8px; cursor:pointer; color:#F8FAFC; }
</style>

<?php
get_footer();
?>
