<?php
declare(strict_types=1);

/**
 * Carrier chooser + status + manual fallback
 * Usage: include once on /stock/views/pack.php (right column)
 *
 * Requires: $_GET['transfer_id'] or a hidden #transfer_id input already present on the page.
 */
$pdo = db();
$transferId = isset($transferId) ? (int)$transferId : (int)($_GET['transfer_id'] ?? 0);
$outletFrom = isset($outletFrom) ? (string)$outletFrom : (string)($_GET['outlet_from'] ?? '');

$gss = $nzp = false;
$name = '';
if ($outletFrom !== '') {
    $q = $pdo->prepare('SELECT name,gss_token,nz_post_api_key,nz_post_subscription_key FROM vend_outlets WHERE id=:o LIMIT 1');
    $q->execute([':o' => $outletFrom]);
    if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $name = (string)$row['name'];
        $gss = !empty($row['gss_token']);
        $nzp = !empty($row['nz_post_api_key']) && !empty($row['nz_post_subscription_key']);
    }
}
?>
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Carrier</strong>
    <small class="text-muted"><?= htmlspecialchars($name ?: 'Outlet', ENT_QUOTES) ?></small>
  </div>
  <div class="card-body">
    <div class="btn-group mb-2" role="group" aria-label="Label providers">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-label-gss" <?= $gss ? '' : 'disabled' ?>>
        NZ Couriers / GSS Label
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-label-nzpost" <?= $nzp ? '' : 'disabled' ?>>
        NZ Post Label
      </button>
      <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#manualLabelForm">
        Manual Tracking
      </button>
    </div>

    <div class="collapse" id="manualLabelForm">
      <div class="border rounded p-2">
        <div class="mb-2">
          <label class="form-label small">Carrier</label>
          <select class="form-select form-select-sm" id="ml-carrier">
            <option value="GSS">NZ Couriers / GSS</option>
            <option value="NZPOST">NZ Post</option>
            <option value="OTHER">Other</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small">Service (optional)</label>
          <input type="text" class="form-control form-control-sm" id="ml-service" placeholder="e.g. Standard / Overnight">
        </div>
        <div class="mb-2">
          <label class="form-label small">Tracking #</label>
          <input type="text" class="form-control form-control-sm" id="ml-tracking" placeholder="Enter tracking number">
        </div>
        <div class="mb-2">
          <label class="form-label small">Weight (g)</label>
          <input type="number" min="0" class="form-control form-control-sm" id="ml-weight" placeholder="e.g. 850">
        </div>
        <button type="button" class="btn btn-sm btn-success" id="btn-label-manual-save">Save Manual Label</button>
      </div>
    </div>

    <small class="text-muted d-block mt-2">
      Tip: when a provider button is disabled, use Manual Tracking — it still records parcels and unblocks the transfer.
    </small>
  </div>
</div>

<script>
(function(){
  const transferId = parseInt(document.getElementById('transfer_id')?.value || '0', 10);
  const outletFrom = document.getElementById('outlet_from')?.value || '';
  const base = '/modules/transfers/stock/ajax';

  async function post(url, data) {
    const r = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Request-ID': (crypto?.randomUUID?.() || Math.random().toString(16).slice(2)),
        'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]')?.content || '')
      },
      body: JSON.stringify(data || {})
    });
    return r.json();
  }

  // GSS/NZPost auto-label (MVP via PackHelper->generateLabel)
  async function createLabel(carrier){
    const plan = window.PackUI?.buildParcelPlan ? window.PackUI.buildParcelPlan() : { parcels: [] };
    const res = await post(base + '/label.create.php', { transfer_pk: transferId, carrier, parcel_plan: plan });
    alert(res.ok ? ('Label created: shipment #' + (res.shipment_id ?? '')) : ('Label failed: ' + (res.error || 'unknown')));
    if (window.PackUI?.refreshParcels) window.PackUI.refreshParcels(transferId);
  }

  document.getElementById('btn-label-gss')?.addEventListener('click', ()=> createLabel('GSS'));
  document.getElementById('btn-label-nzpost')?.addEventListener('click', ()=> createLabel('NZPOST'));

  // Manual label save
  document.getElementById('btn-label-manual-save')?.addEventListener('click', async ()=>{
    const payload = {
      transfer_pk: transferId,
      carrier: document.getElementById('ml-carrier').value || 'OTHER',
      service: document.getElementById('ml-service').value || null,
      tracking: document.getElementById('ml-tracking').value || '',
      weight_g: parseInt(document.getElementById('ml-weight').value || '0', 10) || 0
    };
    const res = await post(base + '/label.manual.php', payload);
    alert(res.ok ? 'Manual label saved' : ('Save failed: ' + (res.error || 'unknown')));
    if (window.PackUI?.refreshParcels) window.PackUI.refreshParcels(transferId);
  });
})();
</script>
