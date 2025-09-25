<?php
declare(strict_types=1);

/** @var array $packConfigData */
/** @var array $packCarrierSupport */
/** @var array $packDestDefaultsData */

$config = $packConfigData ?? [];
$support = $packCarrierSupport ?? ['gss' => false, 'nzpost' => false];
$destDefaults = $packDestDefaultsData ?? [];
$configJson = htmlspecialchars(json_encode($config, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

$field = static function (string $key, ?string $fallback = null) use ($destDefaults): string {
    return htmlspecialchars((string) ($destDefaults[$key] ?? $fallback ?? ''), ENT_QUOTES, 'UTF-8');
};
?>

<div class="card mb-3 pack-shipping-panel">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Shipping &amp; Labels</strong>
    <span class="badge bg-light text-dark small">
      <span class="me-2"><i class="bi bi-truck"></i> <?= $support['nzpost'] ? 'NZ Post Ready' : 'NZ Post Unavailable' ?></span>
      <span><i class="bi bi-box-seam"></i> <?= $support['gss'] ? 'GSS Ready' : 'GSS Unavailable' ?></span>
    </span>
  </div>
  <div class="card-body" data-config="<?= $configJson ?>">
    <div class="mb-3">
      <label class="form-label small text-muted">Carrier</label>
      <div class="btn-group" role="group">
        <input type="radio" class="btn-check" name="carrier_choice" id="carrier-nzpost" autocomplete="off" value="NZ_POST" <?= $support['nzpost'] ? 'checked' : '' ?> <?= $support['nzpost'] ? '' : 'disabled' ?>>
        <label class="btn btn-outline-primary btn-sm" for="carrier-nzpost">NZ Post</label>
        <input type="radio" class="btn-check" name="carrier_choice" id="carrier-gss" autocomplete="off" value="GSS" <?= !$support['nzpost'] && $support['gss'] ? 'checked' : '' ?> <?= $support['gss'] ? '' : 'disabled' ?>>
        <label class="btn btn-outline-warning btn-sm" for="carrier-gss">GSS / NZ Couriers</label>
      </div>
    </div>

    <div class="row g-2 align-items-end">
      <div class="col-12 col-sm-8">
        <label class="form-label small text-muted">Service Code</label>
        <input type="text" class="form-control form-control-sm" id="carrier-service-code" value="COURIER" placeholder="e.g. COURIER" autocomplete="off">
      </div>
      <div class="col-12 col-sm-4 text-sm-end">
        <button type="button" class="btn btn-primary btn-sm w-100" id="btn-create-labels" <?= ($support['gss'] || $support['nzpost']) ? '' : 'disabled' ?>>Create Shipping Labels</button>
      </div>
    </div>
    <div class="small text-muted mt-2" id="job-status"></div>

    <div class="accordion mt-3" id="addressOverride">
      <div class="accordion-item">
        <h2 class="accordion-header" id="addressOverrideHeading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#addressOverrideCollapse" aria-expanded="false" aria-controls="addressOverrideCollapse">
            Address Override (optional)
          </button>
        </h2>
        <div id="addressOverrideCollapse" class="accordion-collapse collapse" aria-labelledby="addressOverrideHeading" data-bs-parent="#addressOverride">
          <div class="accordion-body">
            <form id="dest-override-form" class="row g-2">
              <div class="col-md-6">
                <label class="form-label small text-muted">Recipient Name</label>
                <input type="text" class="form-control form-control-sm" data-dest-field="name" value="<?= $field('name') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Company</label>
                <input type="text" class="form-control form-control-sm" data-dest-field="company" value="<?= $field('company') ?>">
              </div>
              <div class="col-12">
                <label class="form-label small text-muted">Address Line 1</label>
                <input type="text" class="form-control form-control-sm" data-dest-field="addr1" value="<?= $field('addr1') ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label small text-muted">Address Line 2</label>
                <input type="text" class="form-control form-control-sm" data-dest-field="addr2" value="<?= $field('addr2') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Suburb</label>
                <input type="text" class="form-control form-control-sm" data-dest-field="suburb" value="<?= $field('suburb') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">City</label>
                <input type="text" class="form-control form-control-sm" data-dest-field="city" value="<?= $field('city') ?>" required>
              </div>
              <div class="col-md-2">
                <label class="form-label small text-muted">Postcode</label>
                <input type="text" class="form-control form-control-sm text-uppercase" data-dest-field="postcode" value="<?= $field('postcode') ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Email</label>
                <input type="email" class="form-control form-control-sm" data-dest-field="email" value="<?= $field('email') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Phone</label>
                <input type="text" class="form-control form-control-sm" data-dest-field="phone" value="<?= $field('phone') ?>">
              </div>
              <div class="col-12">
                <label class="form-label small text-muted">Delivery Instructions</label>
                <textarea class="form-control form-control-sm" rows="2" data-dest-field="instructions"><?= $field('instructions') ?></textarea>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="alert alert-info mt-3 small mb-0">
      Labels print remotely via the live carrier integration. Overrides are snapshotted against the shipment for audit.
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><strong>Manual Tracking Fallback</strong></div>
  <div class="card-body">
    <p class="small text-muted">Use when carriers are offline. Tracking numbers entered here will be saved without contacting a courier.</p>
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
    <div class="row g-2 mb-2">
      <div class="col-md-6">
        <label class="form-label small text-muted">Weight (g)</label>
        <input type="number" min="0" class="form-control form-control-sm" id="ship-manual-weight" placeholder="e.g. 850">
      </div>
      <div class="col-md-6">
        <label class="form-label small text-muted">Notes</label>
        <input type="text" class="form-control form-control-sm" id="ship-manual-notes" placeholder="Optional">
      </div>
    </div>
    <button class="btn btn-sm btn-outline-secondary w-100" type="button" id="ship-manual-save">Save Manual Tracking</button>
  </div>
</div>
