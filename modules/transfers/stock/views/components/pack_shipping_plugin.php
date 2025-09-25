<?php
declare(strict_types=1);

/** @var array $packConfigData */
/** @var array $packCarrierSupport */

$config = $packConfigData ?? [];
$support = $packCarrierSupport ?? ['gss' => false, 'nzpost' => false];
$configJson = htmlspecialchars(json_encode($config, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
?>

<div class="card mb-3 pack-shipping-panel">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Shipping &amp; Labels</strong>
    <span class="badge bg-light text-dark small">
      <?= ($support['gss'] ? 'GSS' : 'GSS off') ?> · <?= ($support['nzpost'] ? 'NZ Post' : 'NZ Post off') ?>
    </span>
  </div>
  <div class="card-body">
    <div id="cis-ship-plugin" data-config="<?= $configJson ?>"></div>
    <div class="text-muted small mt-3">
      <p class="mb-1">Labels submit through the transfer queue. Use Manual Entry if carrier credentials are unavailable for this outlet.</p>
      <p class="mb-0">Printed tickets will appear in the parcel history once the queue completes.</p>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><strong>Manual Tracking Fallback</strong></div>
  <div class="card-body">
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="form-label small text-muted">Carrier</label>
        <select class="form-select form-select-sm" id="ship-manual-carrier">
          <option value="INTERNAL">Internal Van</option>
          <option value="GSS">GSS / NZ Couriers</option>
          <option value="NZPOST">NZ Post</option>
          <option value="ARAMEX">Aramex</option>
          <option value="OTHER">Other</option>
        </select>
      </div>
      <div class="col-6">
        <label class="form-label small text-muted">Tracking #</label>
        <input type="text" class="form-control form-control-sm" id="ship-manual-tracking" placeholder="e.g. ABC1234567">
      </div>
    </div>
    <div class="mb-2">
      <label class="form-label small text-muted">Weight (g)</label>
      <input type="number" min="0" class="form-control form-control-sm" id="ship-manual-weight" placeholder="e.g. 850">
    </div>
    <div class="mb-2">
      <label class="form-label small text-muted">Notes</label>
      <textarea class="form-control form-control-sm" rows="2" id="ship-manual-notes" placeholder="Optional instructions"></textarea>
    </div>
    <button class="btn btn-sm btn-outline-primary w-100" type="button" id="ship-manual-save">Save Manual Label</button>
  </div>
</div>
