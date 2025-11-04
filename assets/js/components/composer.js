// assets/js/components/composer.js
// Only wires composer behavior: read textarea and POST to REST endpoint

(function(){
  if ( typeof TWISPEER === 'undefined' ) return;

  const base = TWISPEER.rest_url.replace(/\/$/, '') + '/';
  const submit = document.getElementById('tp-submit');
  const input = document.getElementById('tp-content');
  const msg = document.getElementById('tp-message');

  if (!submit || !input) return;

  submit.addEventListener('click', async function(){
    const content = (input.value || '').trim();
    if (!content) {
      msg.textContent = 'Write something first.';
      return;
    }
    msg.textContent = 'Posting…';
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
        msg.textContent = body.message || 'Failed to post';
        return;
      }
      msg.textContent = 'Posted!';
      input.value = '';
      // ask feed component to refresh (we exposed a helper)
      if ( window.TWISPEER_feedRefresh ) window.TWISPEER_feedRefresh();
    } catch (err) {
      console.error(err);
      msg.textContent = 'Network error';
    } finally {
      setTimeout(()=> msg.textContent = '', 2200);
    }
  });
})();
