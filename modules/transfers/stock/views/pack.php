<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_base.php';

// Inputs
$transferId = isset($_GET['transfer_id']) ? (int)$_GET['transfer_id'] : 0;
$rid = transfers_reqid();

// Expose minimal runtime ctx for JS
transfers_expose_ctx('PACK_CTX', [
  'transfer_id' => $transferId,
  'csrf'        => transfers_csrf(),
  'req_id'      => $rid,
  'api_base'    => '/assets/services/queue/public',
]);

?>
<div class="container-fluid">
  <div class="fade-in">

    <div class="d-flex align-items-center mb-3">
      <h4 class="mb-0">Pack Transfer<?= $transferId ? " #".htmlspecialchars((string)$transferId) : '' ?></h4>
      <div class="ms-auto d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" id="btn-refresh">Refresh</button>
        <button class="btn btn-outline-primary btn-sm" id="btn-add-parcel">Add Parcel</button>
        <button class="btn btn-primary btn-sm" id="btn-generate-label">Generate Label</button>
        <button class="btn btn-success btn-sm" id="btn-close">Close Transfer</button>
      </div>
    </div>

    <div id="pack-alerts" class="mb-3" aria-live="polite"></div>

    <div class="row">
      <div class="col-12 col-xl-8">
        <div class="card mb-3">
          <div class="card-header">Items</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0" id="tbl-items">
                <thead>
                  <tr>
                    <th style="min-width:220px">Product</th>
                    <th class="text-end">Requested</th>
                    <th class="text-end">Packed</th>
                    <th class="text-end">Outstanding</th>
                    <th class="text-end">Pack Now</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody><!-- rows injected --></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">Packing Notes</div>
          <div class="card-body">
            <textarea id="pack-notes" rows="3" class="form-control" placeholder="Optional notes to store on this transfer…"></textarea>
            <small class="text-muted">Notes store alongside transfer log entries when labels are generated or the transfer is closed.</small>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-4">
        <div class="card mb-3">
          <div class="card-header">Parcels</div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm mb-2" id="tbl-parcels">
                <thead>
                  <tr>
                    <th>#</th>
                    <th class="text-end">Weight (g)</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody><!-- parcels injected --></tbody>
              </table>
            </div>
            <button class="btn btn-outline-primary btn-sm" id="btn-add-parcel-2">Add Parcel</button>
          </div>
        </div>

        <div class="card">
          <div class="card-header">Summary</div>
          <div class="card-body">
            <div class="d-flex justify-content-between"><span>Parcels</span><span id="sum-parcels">0</span></div>
            <div class="d-flex justify-content-between"><span>Total Weight</span><span id="sum-weight">0 g</span></div>
            <hr class="my-2">
            <small class="text-muted d-block">Req: <span class="mono" id="req-id"><?= htmlspecialchars($rid) ?></span></small>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function() {
  const api = (window.PACK_CTX && PACK_CTX.api_base) || '/assets/services/queue/public';
  const transferId = (window.PACK_CTX && PACK_CTX.transfer_id) || 0;

  const $alerts = document.getElementById('pack-alerts');
  const $items  = document.querySelector('#tbl-items tbody');
  const $parcels= document.querySelector('#tbl-parcels tbody');
  const $sumPar = document.getElementById('sum-parcels');
  const $sumWt  = document.getElementById('sum-weight');

  let state = {
    items: [],
    parcels: [],
  };

  function alert(type, msg) {
    $alerts.innerHTML = `<div class="alert alert-${type} py-2 mb-2">${msg}</div>`;
    if (type !== 'danger') setTimeout(() => { $alerts.innerHTML=''; }, 5000);
  }

  function fmt(n) { return (n==null? '0' : String(n)); }

  function render() {
    // Items
    $items.innerHTML = state.items.map(row => {
      const out  = Math.max(0, (row.qty_requested||0) - (row.qty_sent_total||0));
      const now  = out; // default to pack outstanding
      return `<tr data-item-id="${row.id}">
        <td class="mono">${row.product_id||''}</td>
        <td class="text-end">${fmt(row.qty_requested)}</td>
        <td class="text-end">${fmt(row.qty_sent_total)}</td>
        <td class="text-end">${fmt(out)}</td>
        <td class="text-end">
          <input type="number" class="form-control form-control-sm js-pack-qty" min="0" value="${now}">
        </td>
        <td class="text-end">
          <button class="btn btn-outline-secondary btn-sm js-attach">Attach</button>
        </td>
      </tr>`;
    }).join('') || `<tr><td colspan="6" class="text-muted text-center">No items.</td></tr>`;

    // Parcels
    $parcels.innerHTML = state.parcels.map((p,i) =>
      `<tr data-parcel-idx="${i}">
        <td>${i+1}</td>
        <td class="text-end"><input type="number" min="0" step="1" class="form-control form-control-sm js-wt" value="${p.weight||0}" aria-label="Weight grams"></td>
        <td class="text-end"><button class="btn btn-link btn-sm text-danger js-del">remove</button></td>
      </tr>`
    ).join('') || `<tr><td colspan="3" class="text-muted text-center">No parcels yet.</td></tr>`;

    const totalW = state.parcels.reduce((a,b)=>a + (parseInt(b.weight||0,10)||0), 0);
    $sumPar.textContent = String(state.parcels.length);
    $sumWt.textContent  = String(totalW) + ' g';
  }

  function addParcel() {
    state.parcels.push({ weight: 0 });
    render();
  }

  // Load transfer details
  async function load() {
    if (!transferId) { alert('warning','No transfer id specified.'); return; }
    const url = `${api}/transfer.inspect.php?id=${encodeURIComponent(transferId)}`;
    const r = await fetch(url, { credentials: 'include', headers: { 'Accept':'application/json' }});
    if (!r.ok) { alert('danger', `Inspect failed: HTTP ${r.status}`); return; }
    const j = await r.json().catch(()=>({}));
    if (!j || !j.ok || !j.data) { alert('danger','Invalid inspect response'); return; }
    const items = Array.isArray(j.data.items) ? j.data.items : [];
    // Normalize
    state.items = items.map(it => ({
      id: it.id,
      product_id: it.product_id,
      qty_requested: it.qty_requested ?? 0,
      qty_sent_total: it.qty_sent_total ?? 0
    }));
    render();
  }

  async function generateLabel() {
    if (!transferId) return;
    const lines = [];
    // pack “now” values distribute into a single parcel if only one exists
    const oneParcel = (state.parcels.length === 1) ? 0 : null;

    document.querySelectorAll('#tbl-items tbody tr').forEach(tr => {
      const id  = parseInt(tr.getAttribute('data-item-id')||'0',10);
      const inp = tr.querySelector('.js-pack-qty');
      const qty = parseInt(inp && inp.value || '0', 10) || 0;
      if (id && qty>0) lines.push({ item_id: id, qty });
    });

    if (!lines.length) { alert('warning','Nothing to pack. Enter some quantities.'); return; }
    if (!state.parcels.length) { addParcel(); }

    const payload = {
      transfer_pk: transferId,
      carrier: 'MVP',
      parcel_plan: {
        parcels: state.parcels.map(p => ({ weight_g: parseInt(p.weight||0,10)||0 })),
        attach: oneParcel !== null ? lines : [] // attach if single parcel, else backend will auto-attach
      }
    };

    const r = await fetch(`${api}/transfer.label.php`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type':'application/json', 'Accept':'application/json' },
      body: JSON.stringify(payload)
    });
    const j = await r.json().catch(()=>({}));
    if (!r.ok || !j.ok) { alert('danger', `Label failed: ${j.error && j.error.message ? j.error.message : 'unknown'}`); return; }
    alert('success', `Label job #${(j.data && j.data.job_id)||'—'} queued.`);
  }

  async function closeTransfer() {
    if (!transferId) return;
    const r = await fetch(`${api}/transfer.close.php`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type':'application/json', 'Accept':'application/json' },
      body: JSON.stringify({ transfer_pk: transferId })
    });
    const j = await r.json().catch(()=>({}));
    if (!r.ok || !j.ok) { alert('danger', `Close failed: ${j.error && j.error.message ? j.error.message : 'unknown'}`); return; }
    alert('success', `Close queued (job #${(j.data && j.data.job_id)||'—'}).`);
  }

  // Events
  document.getElementById('btn-refresh').addEventListener('click', load);
  document.getElementById('btn-add-parcel').addEventListener('click', addParcel);
  document.getElementById('btn-add-parcel-2').addEventListener('click', addParcel);
  document.getElementById('btn-generate-label').addEventListener('click', generateLabel);
  document.getElementById('btn-close').addEventListener('click', closeTransfer);

  $parcels.addEventListener('input', (e) => {
    const tr = e.target.closest('tr[data-parcel-idx]');
    if (!tr) return;
    const idx = parseInt(tr.getAttribute('data-parcel-idx')||'0',10);
    if (e.target.classList.contains('js-wt')) {
      const v = parseInt(e.target.value||'0',10)||0;
      state.parcels[idx].weight = v;
      render();
    }
  });

  $parcels.addEventListener('click', (e) => {
    if (e.target.classList.contains('js-del')) {
      const tr = e.target.closest('tr[data-parcel-idx]');
      const idx = parseInt(tr.getAttribute('data-parcel-idx')||'0',10);
      state.parcels.splice(idx,1);
      render();
    }
  });

  // Attach button (future enhancement: item->parcel mapping UI)
  $items.addEventListener('click', (e) => {
    if (e.target.classList.contains('js-attach')) {
      if (!state.parcels.length) addParcel();
      alert('info','Items will be auto-attached by backend unless explicit mapping is supplied.');
    }
  });

  // Initial load
  load();
})();
</script>
