'use strict';
(function(){
  const qs = sel => document.querySelector(sel);
  const qsa = sel => Array.from(document.querySelectorAll(sel));
  let page = 1, size = 25;

  function collectFilters(){
    const params = new URLSearchParams();
    const from = qs('#av-from').value;
    const to = qs('#av-to').value;
    const entity = qs('#av-entity').value.trim();
    const action = qs('#av-action').value.trim();
    const status = qs('#av-status').value.trim();
    const actor = qs('#av-actor').value.trim();
    const transfer = qs('#av-transfer').value.trim();
    const q = qs('#av-q').value.trim();
    if(from) params.set('from', from);
    if(to) params.set('to', to);
    if(entity) params.set('entity', entity);
    if(action) params.set('action', action);
    if(status) params.set('status', status);
    if(actor) params.set('actor', actor);
    if(transfer) params.set('transfer_id', transfer);
    if(q) params.set('q', q);
    params.set('page', page);
    params.set('size', size);
    return params;
  }

  function apiList(){
    const params = collectFilters();
    params.set('ajax_action','list');
    return fetch('https://staff.vapeshed.co.nz/modules/module.php?module=_shared/admin/audit&view=viewer', {
      method: 'POST',
      headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'fetch', 'X-CSRF-Token': (window.CSRF_TOKEN||'') },
      body: params.toString()
    }).then(r=>r.json());
  }

  function apiGet(id){
    const params = new URLSearchParams();
    params.set('ajax_action','get');
    params.set('id', String(id));
    return fetch('https://staff.vapeshed.co.nz/modules/module.php?module=_shared/admin/audit&view=viewer', {
      method: 'POST',
      headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'fetch', 'X-CSRF-Token': (window.CSRF_TOKEN||'') },
      body: params.toString()
    }).then(r=>r.json());
  }

  function renderRows(rows){
    const tbody = qs('#av-tbody');
    tbody.innerHTML = rows.map(r => {
      const t = r.created_at ? new Date(r.created_at.replace(' ','T')).toLocaleString() : '';
      const actor = (r.actor_type||'') + ':' + (r.actor_id||'');
      const tr = r.transfer_id || '';
      return `<tr>
        <td>${t}</td>
        <td>${(r.entity_type||'')}</td>
        <td>${(r.action||'')}</td>
        <td><span class="badge badge-${r.status==='success'?'success':(r.status==='error'?'danger':'secondary')}">${r.status}</span></td>
        <td>${actor}</td>
        <td>${tr}</td>
        <td><button class="btn btn-sm btn-outline-primary av-view" data-id="${r.id}">View</button></td>
      </tr>`;
    }).join('');
    qsa('.av-view').forEach(btn => btn.addEventListener('click', async (e) => {
      const id = e.currentTarget.getAttribute('data-id');
      const res = await apiGet(id);
      if(res && res.success && res.data){
        qs('#av-json').textContent = JSON.stringify(res.data.row, null, 2);
        qs('#av-before').textContent = JSON.stringify(res.data.before, null, 2);
        qs('#av-after').textContent = JSON.stringify(res.data.after, null, 2);
        $('#av-modal').modal('show');
      }
    }));
  }

  function search(){
    apiList().then(res => {
      if(!res || !res.success){ return; }
      renderRows(res.data.rows||[]);
      qs('#av-pg-status').textContent = `${res.data.range||''}`;
    });
  }

  qs('#av-search').addEventListener('click', ()=>{ page=1; search(); });
  qs('#av-prev').addEventListener('click', ()=>{ if(page>1){ page--; search(); }});
  qs('#av-next').addEventListener('click', ()=>{ page++; search(); });

})();
