document.addEventListener('DOMContentLoaded', function(){
  const postsContainer = document.getElementById('postsContainer');
  const trendingList = document.getElementById('trendingList');

  function renderPost(item){
    const div = document.createElement('div');
    div.className = 'post-card';
    div.innerHTML = `
      <div class="post-meta">Anonymous • ${item.time}</div>
      <div class="post-text">${item.text}</div>
      <div class="reaction-bar">
        ${Object.keys(item.reactions||{}).map(e => `<button class="reaction-btn">${e} ${item.reactions[e]}</button>`).join('')}
        <button class="reaction-btn">+ Add</button>
      </div>
    `;
    postsContainer.appendChild(div);
  }

  fetch(twispeer_vars.rest_url + 'feed', {
    headers: { 'X-WP-Nonce': twispeer_vars.nonce }
  })
  .then(r=>r.json())
  .then(data=>{
    postsContainer.innerHTML = '';
    data.forEach(renderPost);
    trendingList.innerHTML = data.slice(0,3).map(i => `<div style="padding:6px 0;">${i.text} <div style="color:#94A3B8;font-size:12px;">${i.time}</div></div>`).join('');
  })
  .catch(e => { postsContainer.innerHTML = '<div style="color:#94A3B8">Failed to load feed</div>'; });

  const postBtn = document.getElementById('postBtn');
  const composer = document.getElementById('composer');
  postBtn.addEventListener('click', function(){
    const text = composer.value.trim();
    if (!text) return alert('Write something first');
    fetch(twispeer_vars.rest_url + 'post', {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'X-WP-Nonce': twispeer_vars.nonce
      },
      body: JSON.stringify({text})
    })
    .then(r => {
      if (!r.ok) throw r;
      return r.json();
    })
    .then(item => {
      const div = document.createElement('div');
      div.className = 'post-card';
      div.innerHTML = `<div class="post-meta">You • ${item.time}</div><div class="post-text">${item.text}</div><div class="reaction-bar"><button class="reaction-btn">+ Add</button></div>`;
      postsContainer.insertBefore(div, postsContainer.firstChild);
      composer.value = '';
    })
    .catch(async err => {
      const msg = (await err.json().catch(()=>({message:'Error'}))).message || 'Post failed';
      alert(msg);
    });
  });
});
