<?php
// modules/transfers/stock-transfers/blocks/shipping_tabs.php
$PACKONLY = !empty($PACKONLY);
$fromName = tpl_e($transferData->outlet_from->name ?? '');
$toName   = tpl_e($transferData->outlet_to->name ?? '');
// Prefer per-outlet token in vend_outlets; fallback to client-config checks
$outletFromVendId = (string)($transferData->outlet_from->id ?? '');
$__carrier_has_token = function(string $carrier, string $vendOutletId): bool {
  try {
    if ($vendOutletId === '') return false;
    $host = defined('DB_HOST') ? (string)DB_HOST : (string)getenv('DB_HOST');
    $name = defined('DB_NAME') ? (string)DB_NAME : (string)getenv('DB_NAME');
    $user = defined('DB_USER') ? (string)DB_USER : (string)getenv('DB_USER');
    $pass = defined('DB_PASS') ? (string)DB_PASS : (string)getenv('DB_PASS');
    $port = defined('DB_PORT') ? (string)DB_PORT : ((string)getenv('DB_PORT') ?: '3306');
    if ($host === '' || $name === '' || $user === '') return false;
    $pdo = new PDO('mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4', $user, $pass, [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $stmt = $pdo->prepare('SELECT * FROM vend_outlets WHERE id = ? LIMIT 1');
    $stmt->execute([$vendOutletId]);
    $row = $stmt->fetch(); if (!$row) return false;
    $carrier = strtoupper($carrier);
    if ($carrier === 'NZ_POST' || $carrier === 'NZPOST') { $candidates = ['nzpost_token','nzpost_api_key','starshipit_api_key']; }
    else { $candidates = ['gss_token','gss_api_key','nzcouriers_api_key']; }
    foreach ($candidates as $col) { if (array_key_exists($col, $row) && trim((string)$row[$col]) !== '') return true; }
    return false;
  } catch (Throwable $e) { return false; }
};

$nzpostAvailable = ($__carrier_has_token('NZ_POST', $outletFromVendId))
  || (class_exists('NZPostEShipClient') && NZPostEShipClient::configured((int)($_SESSION['website_outlet_id'] ?? 0)));
$gssAvailable = ($__carrier_has_token('GSS', $outletFromVendId))
  || (class_exists('GSSClient') && GSSClient::configured());
?>
<section id="trackingInfo" class="w-100" aria-labelledby="delivery-notes-title">
  <div class="card mb-2 mt-3" id="delivery-tracking-card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
      <strong id="delivery-notes-title">Delivery &amp; Notes</strong>
      <small class="text-muted">Consignment-first | Send mode with server-side fallback</small>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="mb-2" for="notesForTransfer"><strong>Notes &amp; Discrepancies</strong></label>
          <textarea class="form-control" id="notesForTransfer" rows="4" placeholder="Enter any notes, discrepancies, or special instructions..."></textarea>
        </div>
        <div class="col-md-6 mb-3">
          <div class="row">
            <div class="col-md-5">
              <fieldset aria-labelledby="delivery-mode-legend">
                <legend id="delivery-mode-legend" class="mb-2 h6"><strong>Delivery Method</strong></legend>
                <div class="mb-3">
                  <div class="custom-control custom-radio mb-2">
                    <input type="radio" id="mode-courier" name="delivery-mode" class="custom-control-input" value="courier" checked data-action="toggle-tracking">
                    <label class="custom-control-label" for="mode-courier">Courier delivery</label>
                  </div>
                  <div class="custom-control custom-radio">
                    <input type="radio" id="mode-internal" name="delivery-mode" class="custom-control-input" value="internal" data-action="toggle-tracking">
                    <label class="custom-control-label" for="mode-internal">Internal (drive/drop)</label>
                  </div>
                </div>
              </fieldset>
            </div>
            <div class="col-md-7" id="tracking-section" style="display:none;">
              <label class="mb-2 d-flex align-items-center">
                <strong>Courier Services &amp; Labels</strong>
                <?php if ($PACKONLY): ?><span class="badge badge-warning ml-2"><i class="fa fa-lock" aria-hidden="true"></i> Read-Only Mode</span><?php endif; ?>
              </label>

              <div class="mb-2">
                <label class="sr-only" for="courier-service">Courier service</label>
                <select id="courier-service" class="form-control" style="font-size: 15px; padding: 12px 16px; height: auto;" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                  <option value="">Select courier service...</option>
                  <?php if ($nzpostAvailable): ?>
                    <option value="nzpost" selected>NZ Post</option>
                  <?php endif; ?>
                  <?php if ($gssAvailable): ?>
                    <option value="gss" <?php echo (!$nzpostAvailable && $gssAvailable) ? 'selected' : ''; ?>>NZ Couriers</option>
                  <?php endif; ?>
                  <option value="manual" <?php echo (!$nzpostAvailable && !$gssAvailable) ? 'selected' : ''; ?>>Manual Entry</option>
                </select>
                <div id="printer-status" class="small text-muted mt-1 d-flex align-items-center" style="display: none; gap:6px;">
                  <i class="fa fa-info-circle" aria-hidden="true"></i>
                  <span id="printer-status-text"></span>
                  <button type="button" class="btn btn-sm btn-outline-secondary ml-2" data-action="refresh-printer" style="font-size: 0.7rem; padding: 1px 6px;">
                    <i class="fa fa-refresh" aria-hidden="true"></i> Refresh
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-primary" data-action="sync-shipment" style="font-size: 0.7rem; padding: 1px 6px;">
                    <i class="fa fa-sync" aria-hidden="true"></i> Sync Shipment
                  </button>
                </div>
              </div>

              <?php // Printer controls moved to footer (very bottom) per staff UX request ?>

              <aside id="gss-panel" class="courier-panel card border-primary mb-2" style="display:none;" aria-labelledby="gss-panel-title">
                <div class="card-header py-2 bg-primary text-white d-flex justify-content-between align-items-center">
                  <small id="gss-panel-title" class="mb-0"><strong>NZ Couriers Label Creation</strong></small>
                  <?php if ($PACKONLY): ?><small class="mb-0"><i class="fa fa-lock"></i> Disabled in pack-only mode</small><?php endif; ?>
                </div>
                <div class="card-body py-2">
                  <div class="mb-2 p-2 bg-light rounded">
                    <small class="text-muted d-block"><strong>Shipping Details:</strong></small>
                    <small class="d-block"><strong>From:</strong> <?php echo $fromName; ?></small>
                    <small class="d-block"><strong>To:</strong> <?php echo $toName; ?></small>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label class="small" for="gss-package-type">Package Type</label>
                      <select id="gss-package-type" class="form-control form-control-sm" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                        <option value="satchel3kg">Satchel 3kg</option>
                        <option value="satchel5kg">Satchel 5kg</option>
                        <option value="box3kg">Box 3kg</option>
                        <option value="box5kg">Box 5kg</option>
                        <option value="box10kg">Box 10kg</option>
                      </select>
                    </div>
                    <div class="form-group col-md-6">
                      <label class="small d-block">Options</label>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="gss-signature" checked <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                        <label class="form-check-label small" for="gss-signature">Signature</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="gss-saturday" <?php echo $PACKONLY ? 'disabled' : ''; ?>>
                        <label class="form-check-label small" for="gss-saturday">Saturday</label>
                      </div>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="small" for="gss-instructions">Instructions</label>
                    <textarea id="gss-instructions" class="form-control form-control-sm" rows="2" placeholder="Special delivery instructions..." <?php echo $PACKONLY ? 'disabled' : ''; ?>></textarea>
                  </div>
                  <div class="mt-1 d-flex align-items-center">
                    <?php if (!$PACKONLY): ?>
                      <button type="button" class="btn btn-sm btn-primary" data-action="gss-create-label"><i class="fa fa-print" aria-hidden="true"></i> Create &amp; Print GSS Label</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-secondary" disabled title="Disabled in pack-only mode"><i class="fa fa-lock" aria-hidden="true"></i> Shipping Locked</button>
                    <?php endif; ?>
                    <span id="gss-status" class="ml-2 small" role="status" aria-live="polite"></span>
                  </div>
                </div>
              </aside>

              <?php // Full service tabs kept as separate section for NZ Post / GSS / Manual / History ?>
              <section id="shipping-tabs-container" class="courier-panel mb-2" style="display:none;" aria-label="Shipping services">
                <?php tpl_block('shipping_tabs_full'); ?>
              </section>

              <section id="manual-panel" class="courier-panel mb-2" style="display:none;" aria-labelledby="manual-inline-title">
                <label id="manual-inline-title" class="mb-2 small d-block"><strong>Manual Tracking Numbers</strong></label>
                <div id="tracking-items" class="mb-2"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-tracking">Add tracking number</button>
                <div class="mt-2 small text-muted"><span id="tracking-count">0 numbers</span></div>
              </section>

              <div id="generated-labels" class="mt-2" aria-live="polite" aria-atomic="true"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
