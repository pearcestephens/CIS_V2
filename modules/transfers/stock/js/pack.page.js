(function(){
  const S    = window.PackPage || {};
  const ajax = (S.endpoints && S.endpoints.ajax) || '/modules/transfers/stock/ajax/handler.php';
  const rid  = S.requestId || '';
  const csrf = S.csrf || '';
  const tid  = parseInt(S.transferId || 0, 10);

  const $  = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  async function api(action, body){
    const res = await fetch(ajax, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Request-ID': rid,
        'X-CSRF-Token': csrf
      },
      body: JSON.stringify(Object.assign({ action }, body || {}))
    }).catch(()=>null);
    return res ? res.json() : null;
  }

  async function loadItems(){
    const j = await api('list_items', { transfer_id: tid });
    if (!j || !j.ok) return;
    const tb = $('#tblItems tbody');
    tb.innerHTML = '';
    (j.items||[]).forEach(r => {
      const tr = document.createElement('tr');
      tr.dataset.itemId    = r.id;
      tr.dataset.productId = r.product_id;
      tr.innerHTML = `
        <td><div class="mono">${r.sku || r.product_id}</div><div class="small">${r.name || ''}</div></td>
        <td>${r.requested_qty ?? 0}</td>
        <td><input class="form-control form-control-sm qty-input" type="number" min="0" value="${r.requested_qty ?? 0}"></td>
        <td class="ship-units">${r.suggested_ship_units ?? '-'}</td>
        <td class="weight-g">${r.unit_g ?? '-'}</td>`;
      tb.appendChild(tr);
    });
    computeSummary();
  }

  function addParcelRow() {
    const list = $('#parcelList');
    const idx  = $$('.parcel-row', list).length + 1;
    const div  = document.createElement('div');
    div.className = 'parcel-row';
    div.innerHTML = `
      <div class="d-flex align-items-center" style="gap:.5rem">
        <span class="text-muted">#${idx}</span>
        <input type="number" min="0" class="form-control form-control-sm parcel-weight-input" placeholder="Weight(g)" style="width:140px">
        <button class="btn btn-sm btn-outline-secondary add-row" type="button">Add Row</button>
      </div>`;
    list.appendChild(div);
  }

  function computeSummary(){
    $('#sum-parcels').textContent = String($$('.parcel-row').length);
    let totalG = 0;
    $$('.parcel-weight-input').forEach(inp => {
      const v = parseInt(inp.value || '0', 10);
      if (!isNaN(v)) totalG += v;
    });
    $('#sum-weight').textContent = String(totalG);
  }

  function planFromUI(){
    const parcels = $$('.parcel-row').map(row => {
      const weight = parseInt($('.parcel-weight-input', row)?.value || '0', 10) || 0;
      return { weight_g: weight, items: [] };
    });
    return { parcels: parcels.length ? parcels : [{ weight_g: 0, items: [] }] };
  }

  async function saveNotes(){
    const notes = ($('#pack-notes')?.value || '').trim();
    if (!notes) return;
    await api('save_pack', { transfer_id: tid, notes });
  }

  async function generateLabelMVP(){
    await saveNotes();
    const plan = planFromUI();
    const j = await api('generate_label', { transfer_id: tid, carrier: 'MVP', parcel_plan: plan });
    $('#request-id').textContent = (j && j.request_id) ? `req ${j.request_id}` : '';
    if (!j || !j.ok) { alert('Label generation failed'); return; }
    await refreshParcels();
    alert(`Label recorded (MVP). Shipment #${j.shipment_id ?? '?'}`);
  }

  async function refreshParcels(){
    const p = await api('get_parcels', { transfer_id: tid });
    const host = $('#parcelList');
    if (p && p.parcels) {
      host.innerHTML = p.parcels.map(x =>
        `<div class="mb-2 mono">#${x.box_number} · ${x.weight_kg} kg · ${x.items_count} items</div>`
      ).join('');
    }
    computeSummary();
  }

  async function applyManualTracking(){
    const carrier = ($('#manual-carrier')?.value || '').trim() || 'internal_drive';
    const number  = ($('#manual-tracking')?.value || '').trim();
    const url     = ($('#manual-label-url')?.value || '').trim();

    if (carrier !== 'internal_drive' && !number) {
      alert('Tracking number is required for couriers.');
      return;
    }

    // attach to box #1 by default (or latest)
    const box = Math.max(1, $$('.parcel-row').length || 1);

    const j = await api('set_parcel_tracking', {
      transfer_id: tid,
      box_number:  box,
      carrier:     carrier,
      tracking_number: number || null,
      tracking_url:    url    || null
    });

    if (!j || !j.ok) { alert('Failed to save tracking'); return; }
    await refreshParcels();
    try {
      const m = document.getElementById('manualTrackingModal');
      if (m && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getInstance(m)?.hide();
    } catch (e) {}
    alert('Tracking saved');
  }

  // events
  document.addEventListener('click', ev => {
    if (ev.target?.matches('#btn-add-parcel,.add-row')) { ev.preventDefault(); addParcelRow(); computeSummary(); }
    if (ev.target?.matches('#btn-label-gss'))          { ev.preventDefault(); generateLabelMVP(); }
    if (ev.target?.matches('#btn-save-pack'))          { ev.preventDefault(); saveNotes().then(()=>alert('Saved')); }
    if (ev.target?.matches('#btn-apply-manual'))       { ev.preventDefault(); applyManualTracking(); }
  });

  document.addEventListener('input', ev => {
    if (ev.target?.matches('.parcel-weight-input')) computeSummary();
  });

  // bootstrap
  if (tid > 0) {
    loadItems().then(() => refreshParcels());
  }
})();
