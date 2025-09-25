(function(){
  'use strict';
  // __MODULE_NAME__ client JS
  const el = document.querySelector('[data-module="__MODULE_SLUG__"]');
  if (!el) return;

  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  function post(action, data){
    const form = new FormData();
    form.append('action', action);
    Object.entries(data||{}).forEach(([k,v])=> form.append(k, v));
    return fetch('https://staff.vapeshed.co.nz/modules/__MODULE_SLUG__/ajax/handler.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrf },
      body: form
    }).then(r=>r.json());
  }

  document.querySelectorAll('[data-action="ping"]').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      btn.disabled = true;
      try {
        const res = await post('__MODULE_SLUG__.ping', {});
        alert(res.success ? 'PONG OK' : 'Error: '+(res.error?.message||'unknown'));
      } finally { btn.disabled = false; }
    });
  });
})();
