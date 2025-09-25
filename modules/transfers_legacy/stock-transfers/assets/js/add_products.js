(function(){
  const modal = document.getElementById('addProductsModal');
  if (!modal) return;
  const $search = modal.querySelector('#apm-search');
  const $qty = modal.querySelector('#apm-qty');
  const $results = modal.querySelector('#apm-results');
  const $selected = modal.querySelector('#apm-selected');
  const $status = modal.querySelector('#apm-status');
  const $btnAdd = modal.querySelector('#apm-add');
  const $btnClear = modal.querySelector('#apm-clear');
  const $selCount = modal.querySelector('#apm-selected-count');
  const $ownSelCount = modal.querySelector('#apm-own-selected-count');
  const $loadOwn = modal.querySelector('#apm-load-own');
  const $ownList = modal.querySelector('#apm-own-list');
  const $ownSearch = modal.querySelector('#apm-own-search');
  const $selectAll = modal.querySelector('#apm-select-all');
  const $clearAll = modal.querySelector('#apm-clear-all');
  const selection = new Set();
  let ownItems = [];
  const transferId = document.getElementById('transferID')?.value || '';

  const state = { items: new Map(), busy:false, t:null };
  function setBusy(b){ state.busy = !!b; $results.setAttribute('aria-busy', state.busy?'true':'false'); }
  function toast(text,type){ STX.emit('toast',{text, type:type||'info'}); }

  function renderSelected(){
    $selected.innerHTML='';
    for (const [pid, item] of state.items){
      const li = document.createElement('li'); li.className='list-group-item d-flex align-items-center';
      const name = document.createElement('div'); name.className='flex-grow-1'; name.innerHTML = `<strong>${escapeHtml(item.name||pid)}</strong><br><small class="text-muted">SKU: ${escapeHtml(item.sku||'')}</small>`;
      const input = document.createElement('input'); input.type='number'; input.min='1'; input.value=item.qty||1; input.className='form-control form-control-sm ml-2'; input.style.width='80px';
      input.addEventListener('input',()=>{ const v = Math.max(1, parseInt(input.value||'1',10)||1); item.qty = v; state.items.set(pid,item); });
      const rm = document.createElement('button'); rm.className='btn btn-sm btn-link text-danger ml-2'; rm.innerHTML='<i class="fa fa-times" aria-hidden="true"></i>'; rm.addEventListener('click',()=>{ state.items.delete(pid); renderSelected(); });
      li.appendChild(name); li.appendChild(input); li.appendChild(rm); $selected.appendChild(li);
    }
    const count = state.items.size;
    if ($selCount) $selCount.textContent = String(count);
    if ($btnAdd) $btnAdd.disabled = count === 0;
  }

  function addSelected(item){
    const defQty = Math.max(1, parseInt($qty.value||'1',10)||1);
    const cur = state.items.get(item.product_id) || {qty:defQty};
    cur.name = item.name; cur.sku = item.sku; cur.qty = cur.qty || defQty; state.items.set(item.product_id, cur);
    renderSelected();
  }

  function renderResults(list){
    $results.innerHTML='';
    list.forEach(item=>{
      const li = document.createElement('li'); li.className='list-group-item list-group-item-action d-flex align-items-center'; li.tabIndex=0;
      const img = document.createElement('img'); img.className='mr-2'; img.src=item.image_url||''; img.alt=''; img.style.cssText='width:32px;height:32px;object-fit:cover;border-radius:4px;';
      const body = document.createElement('div'); body.className='flex-grow-1'; body.innerHTML = `<strong>${escapeHtml(item.name||item.product_id)}</strong><br><small class="text-muted">${escapeHtml(item.sku||'')}</small>`;
      const btn = document.createElement('button'); btn.className='btn btn-sm btn-outline-primary'; btn.textContent='Add'; btn.addEventListener('click',()=> addSelected(item));
      li.appendChild(img); li.appendChild(body); li.appendChild(btn);
      li.addEventListener('dblclick',()=> addSelected(item));
      $results.appendChild(li);
    });
  }

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }

  async function doSearch(){
    const q = ($search.value||'').trim();
    if (q.length < 2){ renderResults([]); return; }
    try { setBusy(true); const res = await STX.fetchJSON('search_products', { q, limit: 20 }); renderResults((res.data && res.data.results) || []); }
    catch (e){ $status.textContent = (e && e.message) || 'Search failed'; toast('Search failed: '+$status.textContent,'error'); }
    finally { setBusy(false); }
  }

  $search.addEventListener('input', ()=>{ clearTimeout(state.t); state.t = setTimeout(doSearch, 250); });
  $btnClear.addEventListener('click', ()=>{ state.items.clear(); renderSelected(); });

  $btnAdd.addEventListener('click', async ()=>{
    if (!transferId){ toast('Missing transfer id','error'); return; }
    const lines = [];
    for (const [pid, v] of state.items){ lines.push({ product_id: pid, qty: Math.max(1, parseInt(v.qty||1,10)||1) }); }
    if (!lines.length){ toast('No products selected','error'); return; }
    try {
      $btnAdd.disabled = true; $status.textContent='Adding products…';
      const ids = Array.from(selection);
      if (ids.length > 0){
        const resp = await STX.fetchJSON('bulk_add_products', { transfer_ids: ids.join(','), lines: JSON.stringify(lines) });
        const sum = (resp.data && resp.data.summary) || {succeeded:0, failed:0, requested:0};
        toast(`Bulk add: ${sum.succeeded} succeeded, ${sum.failed} failed`, sum.failed? 'error':'success');
        $status.textContent = `Bulk add completed. ${sum.succeeded} ok, ${sum.failed} failed.`;
      } else {
        const resp = await STX.fetchJSON('add_products', { transfer_id: transferId, lines: JSON.stringify(lines) });
        toast('Products added','success');
        $status.textContent='Added '+(resp.data && (resp.data.added_count||resp.data.count)|| lines.length)+' products.';
        STX.emit('products:added', { transferId, lines });
      }
      // Close modal after a short delay
      setTimeout(()=>{ $(modal).modal('hide'); }, 600);
    } catch (e){ toast('Add failed: '+(e && e.message || 'Error'),'error'); $status.textContent='Add failed'; }
    finally { $btnAdd.disabled = false; }
  });

  // No explicit validation flow; list is server-sourced and scoped

  // Load transfers for own outlet and render selectable list
  if ($loadOwn && $ownList){
    $loadOwn.addEventListener('click', async ()=>{
      $loadOwn.disabled = true; $ownList.innerHTML = '<li class="list-group-item small">Loading…</li>';
      try {
        const res = await STX.fetchJSON('list_transfers', { own: 1, limit: 200 });
        ownItems = (res.data && res.data.items) || [];
        // Exclude current transfer id from suggestions
        const currentId = parseInt(document.getElementById('transferID')?.value||'0',10)||0;
        if (currentId>0) ownItems = ownItems.filter(i=> i.id !== currentId);
        if (!ownItems.length){ $ownList.innerHTML = '<li class="list-group-item small text-muted">No transfers</li>'; return; }
        renderOwnList(ownItems);
      } catch (e){ $ownList.innerHTML = '<li class="list-group-item small text-danger">Failed to load</li>'; }
      finally { $loadOwn.disabled = false; }
    });
  }

  function renderOwnList(list){
    $ownList.innerHTML = '';
    list.forEach(row=>{
      const li = document.createElement('li'); li.className='list-group-item d-flex align-items-center';
  const cb = document.createElement('input'); cb.type='checkbox'; cb.className='mr-2'; cb.checked = selection.has(row.id);
  cb.addEventListener('change',()=>{ if(cb.checked) selection.add(row.id); else selection.delete(row.id); updateOwnSelectedCount(); });
      const label = document.createElement('div'); label.className='flex-grow-1'; label.innerHTML = `<strong>#${row.id}</strong> <small class=\"text-muted\">${escapeHtml(row.vend_number||'')}</small><br><small>${escapeHtml(row.outlet_to_name||row.outlet_to||'')}</small>`;
      li.appendChild(cb); li.appendChild(label); $ownList.appendChild(li);
    });
  }
  // No auto-validate; using only dropdown selection

  // In-list search filter
  if ($ownSearch){
    let st; $ownSearch.addEventListener('input', ()=>{ clearTimeout(st); st = setTimeout(()=>{
      const q = ($ownSearch.value||'').toLowerCase().trim();
      if (!ownItems.length){ $ownList.innerHTML = '<li class="list-group-item small text-muted">No transfers</li>'; return; }
      if (!q){ renderOwnList(ownItems); return; }
      const f = ownItems.filter(r=> String(r.id).includes(q) || (r.vend_number||'').toLowerCase().includes(q));
      renderOwnList(f);
    }, 150); });
  }

  // Select all / Clear all
  if ($selectAll){ $selectAll.addEventListener('click', ()=>{ ownItems.forEach(i=> selection.add(i.id)); renderOwnList(ownItems); }); }
  if ($clearAll){ $clearAll.addEventListener('click', ()=>{ selection.clear(); renderOwnList(ownItems); }); }

  function updateOwnSelectedCount(){ if ($ownSelCount) $ownSelCount.textContent = String(selection.size); }
  // Removed CSV/generator flow – dropdown only
  // Initialize counts and button state
  renderSelected();
})();
