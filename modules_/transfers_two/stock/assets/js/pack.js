/* pack.js (stock path, migrated) */
(function(){
	function collectItems(){
		const root = document.querySelector('.stx-outgoing'); if(!root) return [];
		const rows = root.querySelectorAll('#transfer-table tbody tr');
		const out = [];
		rows.forEach(tr=>{
			const pid = tr.querySelector('.productID')?.value || '';
			const inp = tr.querySelector('[data-behavior="counted-input"]');
			const qty = parseFloat(inp?.value||'0')||0; if (!pid) return; if (qty<=0) return;
			out.push({ product_id: pid, qty_picked: qty });
		});
		return out;
	}

	async function packGoods(){
		const root = document.querySelector('.stx-outgoing'); if(!root) return;
		const tid = document.getElementById('transferID')?.value || '';
		const items = collectItems();
		if (!items.length){ STX.emit('toast',{type:'error', text:'No counted quantities entered'}); return; }
		try{
			await STX.fetchJSON('pack_goods', { transfer_id: tid, items: JSON.stringify(items) });
			STX.emit('toast', {type:'success', text:'Packed goods saved'});
		}catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
	}

	async function sendTransfer(force){
		const tid = document.getElementById('transferID')?.value || '';
		try{
			await STX.fetchJSON('send_transfer', { transfer_id: tid, force: force? '1':'0' });
			STX.emit('toast', {type:'success', text:'Transfer sent'});
			window.location.reload();
		}catch(err){
			const msg = err.response?.error || err.message || 'Failed to send';
			STX.emit('toast', {type:'error', text: msg});
			const btn = document.querySelector('[data-action="force-send"]'); if(btn) btn.classList.remove('d-none');
		}
	}

	window.STXPack = { collectItems, packGoods, sendTransfer };
})();

/* pack.js (new functionality) */
(function(){
  'use strict';
  const root = document.getElementById('stx-pack');
  if(!root) return;
  const transferId = root.getAttribute('data-transfer-id');
  const csrf = root.getAttribute('data-csrf') || '';
  const ajaxBase = (window.__STX_PACK__ && window.__STX_PACK__.ajaxBase) || '';

  const $ = (sel) => root.querySelector(sel);
  const setText = (sel, v) => { const el = $(sel); if(el) el.textContent = v; };

  const req = async (action, payload={}, opts={}) => {
    const form = new URLSearchParams();
    form.set('ajax_action', action);
    form.set('transfer_id', transferId);
    if (csrf) form.set('csrf', csrf);
    Object.entries(payload).forEach(([k,v])=>{ if(v!==undefined && v!==null) form.set(k, typeof v==='object'? JSON.stringify(v): String(v)); });
    const res = await fetch(ajaxBase, { method:'POST', headers:{ 'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded' }, body: form.toString() });
    return res.json();
  };

  // Lock lifecycle
  const lockState = $('#stx-lock-state');
  const btnRequest = $('#btn-request-edit');
  let lockTimer = null, isOwner = false;

  async function acquireLock() {
    lockState.textContent = 'Acquiring lock…';
    const r = await req('acquire_lock');
    if (r && r.success && r.data) {
      isOwner = !!r.data.owner;
      updateLockUI(r.data);
      scheduleHeartbeat();
    } else {
      isOwner = false;
      updateLockUI(r && r.data ? r.data : { owner:false, held_by:r?.data?.held_by || 'someone', ttl:60 });
    }
  }
  function updateLockUI(data){
    if (data.owner) {
      lockState.textContent = 'You are editing (lock held).';
      btnRequest.classList.add('d-none');
      root.classList.remove('is-readonly');
    } else {
      lockState.textContent = `Read-only — held by ${data.held_by||'another user'}.`;
      btnRequest.classList.remove('d-none');
      root.classList.add('is-readonly');
    }
  }
  async function heartbeat(){
    if (!isOwner) return;
    const r = await req('heartbeat_lock');
    if (!(r && r.success)) {
      isOwner = false; updateLockUI({owner:false});
    }
  }
  function scheduleHeartbeat(){
    clearInterval(lockTimer);
    lockTimer = setInterval(heartbeat, 45000); // 45s within 60s TTL
  }
  async function requestEdit(){
    btnRequest.disabled = true;
    const r = await req('request_lock');
    btnRequest.disabled = false;
    if (r && r.success) {
      lockState.textContent = 'Edit requested — waiting up to 60s for owner response…';
      setTimeout(acquireLock, 60000);
    }
  }
  window.addEventListener('beforeunload', () => { navigator.sendBeacon(ajaxBase, new URLSearchParams({ajax_action:'release_lock', transfer_id:transferId, csrf})); });

  // Data hydration
  async function loadHeader(){
    const r = await req('get_transfer_header');
    if (r && r.success && r.data) {
      setText('#stx-route', `${r.data.type||'Stock'} — ${r.data.from_name||r.data.from} → ${r.data.to_name||r.data.to}`);
      setText('#stx-transfer-id', r.data.display_id ? `#${r.data.display_id}` : `#${transferId}`);
    }
  }
  async function loadItems(){
    const [itemsR, weightsR, costsR] = await Promise.all([
      req('list_items'),
      req('get_product_weights'),
      req('get_unit_costs')
    ]);
  const items = (itemsR && itemsR.success && itemsR.data && itemsR.data.items) ? itemsR.data.items : [];
  const weights = (weightsR && weightsR.success && weightsR.data && weightsR.data.weights) ? weightsR.data.weights : {};
    const costs = (costsR && costsR.success && costsR.data && costsR.data.unit_costs) ? costsR.data.unit_costs : {};

    let skus = 0, units = 0, grams = 0, boxes = 0, totalCost = 0;
    const rows = items.map(it => {
      skus += 1; units += (it.qty||it.qty_requested||0);
  const w = weights[String(it.product_id)] || 100; // default 100g (Bible fallback)
      grams += w * (it.qty||it.qty_requested||0);
      const cost = Number(costs[String(it.product_id)] || 0);
      const lineCost = cost * (it.qty||it.qty_requested||0);
      totalCost += lineCost;
      return `<tr>
        <td>${escapeHtml(it.name||'')}</td>
        <td>${escapeHtml(it.sku||'')}</td>
        <td class="text-end">${(it.qty||it.qty_requested||0)}</td>
        <td class="text-end">$${cost.toFixed(2)}</td>
        <td class="text-end">$${lineCost.toFixed(2)}</td>
      </tr>`;
    });

    boxes = Math.ceil(units / 20) || 0; // naive default
      // Weight-based estimation using per-store default max box weight (kg), fallback 15kg
      let maxKg = 15;
      try {
        const cfg = await req('get_printers_config');
        if (cfg && cfg.success && cfg.data && cfg.data.defaults && cfg.data.defaults.max_box_weight_kg) {
          maxKg = Number(cfg.data.defaults.max_box_weight_kg) || 15;
        }
      } catch (e) { /* ignore */ }
      const totalKg = grams / 1000;
      boxes = Math.max(1, Math.ceil(totalKg / Math.max(1, maxKg)));
    $('#items-body').innerHTML = rows.length ? rows.join('') : '<tr><td colspan="5">No items</td></tr>';
    setText('#sum-skus', String(skus));
    setText('#sum-units', String(units));
    setText('#sum-weight', `${(grams/1000).toFixed(2)} kg`);
    setText('#sum-boxes', String(boxes));
    setText('#sum-cost', `$${totalCost.toFixed(2)}`);

    // Enable print slips
    const btn = $('#btn-print-box-slips');
    if (btn) btn.onclick = () => {
        const manual = parseInt(localStorage.getItem(`stx.boxes.${transferId}`)||'0',10);
        const count = Number.isFinite(manual) && manual>0 ? manual : boxes;
        for (let i=1; i<=count; i++) {
        const url = `https://staff.vapeshed.co.nz/modules/transfers/stock/print/box_slip.php?transfer=${encodeURIComponent(transferId)}&box=${i}`;
        window.open(url, '_blank');
      }
    };

    // After items load, refresh shipping summary based on accurate weight
    try { await loadShippingSummary(); } catch(e) { console.error(e); }
  }

  // Notes (append-only)
  const saveInd = $('#save-indicator');
  async function addNote(){
    const ta = $('#note-text');
    const txt = (ta && ta.value || '').trim();
    if (!txt) return;
    setSaving();
    const r = await req('notes_add', { note_text: txt });
    clearSaving();
    if (r && r.success) {
      ta.value='';
      await loadNotes();
    }
  }
  // Blink 'SAVING' while typing/editing
  let __blinkTimer=null; let __blinkOn=false; let __debounce=null;
  function __startBlink(){
    if (__blinkTimer) return;
    __blinkTimer = setInterval(()=>{
      __blinkOn = !__blinkOn;
      if (saveInd) saveInd.textContent = __blinkOn ? 'SAVING' : '';
    }, 450);
  }
  function __stopBlink(){ if (__blinkTimer){ clearInterval(__blinkTimer); __blinkTimer=null; } if (saveInd) saveInd.textContent = 'Idle'; }
  function setSaving(){ __startBlink(); if (__debounce){ clearTimeout(__debounce); } }
  function clearSaving(){ if (__debounce){ clearTimeout(__debounce); } __debounce = setTimeout(__stopBlink, 700); }
  async function loadNotes(){
    const r = await req('notes_list');
    const list = (r && r.success && r.data && Array.isArray(r.data.items)) ? r.data.items : [];
    const html = list.map(n => `<li class=\"list-group-item\"><div class=\"small text-muted\">${escapeHtml(n.created_at||'')}</div><div>${autoLink(escapeHtml(n.note_text||''))}</div></li>`).join('');
    $('#notes-list').innerHTML = html || '<li class=\"list-group-item\">No notes yet</li>';
  }

  // Carriers availability
  async function loadCarriers(){
    const r = await req('get_printers_config');
    const hasNz = !!(r && r.success && r.data && r.data.has_nzpost);
    const hasGss = !!(r && r.success && r.data && r.data.has_gss);
    if (hasNz) $('#chip-nzpost').classList.remove('d-none');
    if (hasGss) $('#chip-gss').classList.remove('d-none');
  }

  async function markReady(){
    setSaving();
    const r = await req('mark_ready');
    clearSaving();
    if (r && r.success) {
      alert('Marked ready.');
    }
  }

  function escapeHtml(s){ return String(s).replace(/[&<>\"]/g, (c)=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c])); }
  function autoLink(text){
    const urlRe = /(https?:\/\/[^\s]+)/g;
    return text.replace(urlRe, (m)=>`<a href=\"${m}\" target=\"_blank\" rel=\"noopener\">${m}</a>`);
  }

  // Wire events
  $('#btn-request-edit')?.addEventListener('click', requestEdit);
  $('#btn-add-note')?.addEventListener('click', addNote);
  $('#btn-mark-ready')?.addEventListener('click', markReady);

  // Persist manual box count per browser
  const boxesInputEl = document.getElementById('stx-boxes-input');
  if (boxesInputEl){
    const k = `stx.boxes.${transferId}`;
    const prev = localStorage.getItem(k); if (prev) boxesInputEl.value = prev;
    boxesInputEl.addEventListener('input', ()=>{
      setSaving();
      localStorage.setItem(k, boxesInputEl.value || '');
      clearSaving();
      // manual box count tweak shouldn't change cost but refresh summary for visibility
      loadShippingSummary().catch(console.error);
    });
  }
  // Saving indicator while typing notes
  const noteEl = $('#note-text');
  if (noteEl){ noteEl.addEventListener('input', ()=>{ setSaving(); }); noteEl.addEventListener('change', ()=>{ clearSaving(); }); }
  // Carrier selection persistence (browser)
  ['chip-nzpost','chip-gss','chip-manual'].forEach(id=>{
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', ()=>{
      ['chip-nzpost','chip-gss','chip-manual'].forEach(o=>document.getElementById(o)?.classList.remove('selected'));
      el.classList.add('selected');
      localStorage.setItem(`stx.carrier.${transferId}`, id);
    });
  });
  const preSel = localStorage.getItem(`stx.carrier.${transferId}`);
  if (preSel && document.getElementById(preSel)) document.getElementById(preSel).classList.add('selected');

  // Init
  acquireLock();
  Promise.all([loadHeader(), loadItems(), loadNotes(), loadCarriers()]).catch(console.error);

  // Shipping summary integration
  async function loadShippingSummary(){
    const r = await req('get_shipping_summary');
    const container = document.getElementById('ship-summary');
    if (!container) return r;
    if (!(r && r.success && r.data)) { container.innerHTML = '<div class="text-danger">Failed to calculate shipping.</div>'; return r; }
    const d = r.data;
    const totalKg = (Number(d.total_kg||0)).toFixed(2);
    const best = d.recommended ? `${d.recommended.carrier_code} — ${d.recommended.container_code} (${d.recommended.kind||''}) $${Number(d.recommended.cost||0).toFixed(2)}` : '—';
    const altLines = Array.isArray(d.carriers) ? d.carriers.map(c=>{
      const b = c.best; if (!b) return '';
      const cost = Number(b.cost||0).toFixed(2);
      const code = (b.code||b.container_code||'');
      return `<li>${escapeHtml(c.carrier_code||'')} — ${escapeHtml(code)} (${escapeHtml(b.kind||'')}) $${cost}</li>`;
    }).filter(Boolean).join('') : '';
    container.innerHTML = `
      <div class="row g-2 align-items-center">
        <div class="col-auto"><strong>Total Weight:</strong> <span id="ship-total-kg">${totalKg} kg</span></div>
        <div class="col-auto"><strong>Best Option:</strong> <span id="ship-best">${escapeHtml(best)}</span></div>
      </div>
      ${altLines ? `<div class="mt-2"><div class="small text-muted">Candidates</div><ul class="mb-0">${altLines}</ul></div>`:''}
      <div class="mt-2"><button class="btn btn-sm btn-outline-primary" id="btn-refresh-shipping">Refresh</button></div>
    `;
    document.getElementById('btn-refresh-shipping')?.addEventListener('click', ()=>{ loadShippingSummary().catch(console.error); });
    return r;
  }
  // Bind refresh button if present initially
  document.getElementById('btn-refresh-shipping')?.addEventListener('click', ()=>{ loadShippingSummary().catch(console.error); });
})();
