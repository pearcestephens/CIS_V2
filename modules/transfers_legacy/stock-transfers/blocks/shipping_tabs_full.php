<?php
// modules/transfers/stock-transfers/blocks/shipping_tabs_full.php
// Prefer per-outlet token in vend_outlets; fallback to client-config checks.
$outletFromVendId = (string)($transferData->outlet_from->id ?? '');

$__carrier_has_token = function(string $carrier, string $vendOutletId): bool {
  try {
    if ($vendOutletId === '') return false;
    // Build PDO from constants or env
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
    if ($carrier === 'NZ_POST' || $carrier === 'NZPOST') {
      $candidates = ['nzpost_token','nzpost_api_key','starshipit_api_key'];
    } else { // GSS / NZ Couriers
      $candidates = ['gss_token','gss_api_key','nzcouriers_api_key'];
    }
    foreach ($candidates as $col) {
      if (array_key_exists($col, $row) && trim((string)$row[$col]) !== '') return true;
    }
    return false;
  } catch (Throwable $e) { return false; }
};

$nzpostAvailable = ($__carrier_has_token('NZ_POST', $outletFromVendId))
  || (class_exists('NZPostEShipClient') && NZPostEShipClient::configured((int)($_SESSION['website_outlet_id'] ?? 0)));
$gssAvailable = ($__carrier_has_token('GSS', $outletFromVendId))
  || (class_exists('GSSClient') && GSSClient::configured());
?>
<div class="card shadow-sm border-0">
  <div class="card-header p-0 bg-white border-bottom">
    <ul class="nav nav-tabs nav-fill shipping-tabs" id="shippingTabs" role="tablist">
      <li class="nav-item" role="presentation" <?php echo !$nzpostAvailable ? 'style="display:none;"' : ''; ?>>
        <a class="nav-link d-flex align-items-center justify-content-center <?php echo $nzpostAvailable ? 'active' : ''; ?>" id="nzpost-tab" data-toggle="tab" href="#nzpost-pane" role="tab" aria-controls="nzpost-pane" aria-selected="<?php echo $nzpostAvailable ? 'true' : 'false'; ?>">
          <i class="fa fa-truck text-danger mr-2" aria-hidden="true"></i>
          <span class="font-weight-bold">NZ Post</span>
          <span class="badge badge-danger ml-2 d-none" id="nzpost-badge">1</span>
        </a>
      </li>
      <li class="nav-item" role="presentation" <?php echo !$gssAvailable ? 'style="display:none;"' : ''; ?>>
        <a class="nav-link d-flex align-items-center justify-content-center <?php echo (!$nzpostAvailable && $gssAvailable) ? 'active' : ''; ?>" id="gss-tab" data-toggle="tab" href="#gss-pane" role="tab" aria-controls="gss-pane" aria-selected="<?php echo (!$nzpostAvailable && $gssAvailable) ? 'true' : 'false'; ?>">
          <i class="fa fa-shipping-fast text-success mr-2" aria-hidden="true"></i>
          <span class="font-weight-bold">GSS Courier</span>
          <span class="badge badge-success ml-2 d-none" id="gss-badge">1</span>
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <?php $manualActive = (!$nzpostAvailable && !$gssAvailable); ?>
        <a class="nav-link d-flex align-items-center justify-content-center <?php echo $manualActive ? 'active' : ''; ?>" id="manual-tab" data-toggle="tab" href="#manual-pane" role="tab" aria-controls="manual-pane" aria-selected="<?php echo $manualActive ? 'true' : 'false'; ?>">
          <i class="fa fa-edit text-primary mr-2" aria-hidden="true"></i>
          <span class="font-weight-bold">Manual Entry</span>
          <span class="badge badge-primary ml-2 d-none" id="manual-badge">1</span>
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link d-flex align-items-center justify-content-center" id="history-tab" data-toggle="tab" href="#history-pane" role="tab" aria-controls="history-pane" aria-selected="false">
          <i class="fa fa-history text-info mr-2" aria-hidden="true"></i>
          <span class="font-weight-bold">Recent</span>
        </a>
      </li>
    </ul>
  </div>
  <div class="tab-content" id="shippingTabContent">
  <div class="tab-pane fade <?php echo $nzpostAvailable ? 'show active' : ''; ?>" id="nzpost-pane" role="tabpanel" aria-labelledby="nzpost-tab">
      <div class="card-body">
        <div class="alert alert-info d-none" id="nzpost-info"></div>
        <div class="form-row mb-3">
          <div class="form-group col-md-4">
            <label class="small font-weight-bold" for="nzpost-service-type">Service Type</label>
            <select id="nzpost-service-type" class="form-control form-control-sm">
              <option value="">Choose service...</option>
              <option value="CPOLTPDL">DLE Overnight</option>
              <option value="CPOLTPA5">A5 Overnight</option>
              <option value="CPOLTPA4">A4 Overnight</option>
              <option value="CPOLP">Parcel Overnight</option>
              <option value="CPOLE" selected>Economy (2-3 Days)</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label class="small font-weight-bold">Dimensions (cm)</label>
            <div class="input-group input-group-sm">
              <input type="number" id="nzpost-length" class="form-control" placeholder="L" min="1" max="120">
              <div class="input-group-append input-group-prepend"><span class="input-group-text">×</span></div>
              <input type="number" id="nzpost-width" class="form-control" placeholder="W" min="1" max="120">
              <div class="input-group-append input-group-prepend"><span class="input-group-text">×</span></div>
              <input type="number" id="nzpost-height" class="form-control" placeholder="H" min="1" max="120">
            </div>
            <small class="form-text text-muted"><span id="nzpost-volume-weight">Vol: 0kg</span> • <span id="nzpost-actual-weight">Act: 0kg</span></small>
          </div>
          <div class="form-group col-md-4">
            <label class="small font-weight-bold">Weight &amp; Cost</label>
            <div class="input-group input-group-sm">
              <input type="number" id="nzpost-weight" class="form-control" placeholder="2.5" step="0.1" min="0.1" max="30" value="2.5">
              <div class="input-group-append"><span class="input-group-text">kg</span><span class="input-group-text bg-success text-white font-weight-bold" id="nzpost-cost-display">$0.00</span></div>
            </div>
            <small class="form-text text-muted" id="nzpost-cost-breakdown">Enter dimensions</small>
          </div>
        </div>
        <div class="form-row mb-2">
          <div class="form-group col-12">
            <div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input" id="nzpost-signature"><label class="custom-control-label" for="nzpost-signature">Signature required</label></div>
            <div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input" id="nzpost-saturday"><label class="custom-control-label" for="nzpost-saturday">Saturday delivery</label></div>
            <div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input" id="nzpost-print-now"><label class="custom-control-label" for="nzpost-print-now">Print immediately</label></div>
          </div>
        </div>
        <div class="form-row mb-2">
          <div class="form-group col-md-6"><label class="small" for="nzpost-attention">Attention</label><input type="text" id="nzpost-attention" class="form-control form-control-sm" placeholder="Recipient / attention of (optional)"></div>
          <div class="form-group col-md-6"><label class="small" for="nzpost-instructions">Delivery Instructions</label><textarea class="form-control form-control-sm" id="nzpost-instructions" rows="2" placeholder="Special delivery instructions (optional)"></textarea></div>
        </div>
        <div class="mt-2">
          <div class="d-flex justify-content-between align-items-center mb-1"><small class="text-muted"><strong>Package List:</strong></small><button type="button" class="btn btn-outline-primary btn-sm" data-action="nzpost-add-package"><i class="fa fa-plus" aria-hidden="true"></i> Add Package</button></div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered" id="nzpost-packages-table">
              <thead class="table-light"><tr><th style="width:15%;">Dimensions (L×W×H)</th><th style="width:15%;">Weight</th><th style="width:15%;">Vol. Weight</th><th style="width:25%;">Description</th><th style="width:15%;">Cost Est.</th><th style="width:15%;">Actions</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="btn-group" role="group" aria-label="NZ Post actions">
            <button class="btn btn-outline-danger btn-sm" data-action="nzpost-order" id="nzpost-order-btn"><i class="fa fa-list" aria-hidden="true"></i> Create Order</button>
            <button class="btn btn-danger btn-sm" data-action="nzpost-create" id="nzpost-create-btn"><i class="fa fa-print" aria-hidden="true"></i> <span id="nzpost-btn-text">Create &amp; Print Label</span></button>
            <button class="btn btn-outline-danger btn-sm d-none" data-action="nzpost-reprint" id="nzpost-reprint-btn"><i class="fa fa-repeat" aria-hidden="true"></i> Re-Print Label</button>
          </div>
          <div class="d-flex flex-column text-right"><div class="text-success font-weight-bold" id="nzpost-total-cost">Total: $0.00</div><small class="text-muted"><span id="nzpost-package-count">0 packages</span> • <span id="nzpost-total-weight">0.0kg total</span> • <span id="nzpost-service-type-display">Economy</span></small></div>
        </div>
        <div class="mt-1" id="nzpost-status" role="status" aria-live="polite"></div>
      </div>
    </div>
    <!-- Address Selection Modal (NZ Post) -->
    <div class="modal fade" id="nzpostAddressModal" tabindex="-1" role="dialog" aria-labelledby="nzpostAddressModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="nzpostAddressModalLabel">Confirm Destination Address</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info small">Select the correct destination address suggested by NZ Post to improve delivery accuracy.</div>
            <div id="nzpost-address-candidates" class="list-group"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="nzpost-address-apply">Use Selected Address</button>
          </div>
        </div>
      </div>
    </div>
  <div class="tab-pane fade <?php echo (!$nzpostAvailable && $gssAvailable) ? 'show active' : ''; ?>" id="gss-pane" role="tabpanel" aria-labelledby="gss-tab">
      <div class="card-body">
        <div class="form-row mb-3">
          <div class="form-group col-md-4">
            <label class="small font-weight-bold" for="gss-service-type">Courier Service</label>
            <select id="gss-service-type" class="form-control form-control-sm">
              <option value="">Choose service...</option>
              <option value="ROAD">Road (Standard)</option>
              <option value="OVERNIGHT">Overnight</option>
              <option value="SAMEDAY">Same Day</option>
              <option value="ECONOMY" selected>Economy</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label class="small font-weight-bold">Dimensions (cm)</label>
            <div class="input-group input-group-sm">
              <input type="number" id="gss-length" class="form-control" placeholder="L" min="1" max="120">
              <div class="input-group-append input-group-prepend"><span class="input-group-text">×</span></div>
              <input type="number" id="gss-width" class="form-control" placeholder="W" min="1" max="120">
              <div class="input-group-append input-group-prepend"><span class="input-group-text">×</span></div>
              <input type="number" id="gss-height" class="form-control" placeholder="H" min="1" max="120">
            </div>
          </div>
          <div class="form-group col-md-4">
            <label class="small font-weight-bold">Weight &amp; Cost</label>
            <div class="input-group input-group-sm">
              <input type="number" id="gss-weight" class="form-control" placeholder="2.5" step="0.1" min="0.1" max="30" value="2.5">
              <div class="input-group-append"><span class="input-group-text">kg</span><span class="input-group-text bg-success text-white font-weight-bold" id="gss-cost-display">$0.00</span></div>
            </div>
          </div>
        </div>
        <div class="mb-2">
          <small class="text-muted"><strong>Quick Presets:</strong></small><br>
          <button type="button" class="btn btn-outline-success btn-sm mr-1 mb-1" data-action="gss-preset" data-dim="20,15,10,1.5">Small Box (20×15×10) 1.5kg</button>
          <button type="button" class="btn btn-outline-success btn-sm mr-1 mb-1" data-action="gss-preset" data-dim="30,20,15,2.5">Medium Box (30×20×15) 2.5kg</button>
          <button type="button" class="btn btn-outline-success btn-sm mr-1 mb-1" data-action="gss-preset" data-dim="40,30,20,4.0">Large Box (40×30×20) 4kg</button>
          <button type="button" class="btn btn-outline-warning btn-sm mr-1 mb-1" data-action="gss-preset" data-dim="35,25,3,0.8">Flat Pack (35×25×3) 0.8kg</button>
        </div>
        <div class="mt-2">
          <div class="d-flex justify-content-between align-items-center mb-1"><small class="text-muted"><strong>Package List:</strong></small><button type="button" class="btn btn-outline-success btn-sm" data-action="gss-add-package"><i class="fa fa-plus" aria-hidden="true"></i> Add Package</button></div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered" id="gss-packages-table">
              <thead class="table-light"><tr><th style="width:20%;">Dimensions</th><th style="width:15%;">Weight</th><th style="width:25%;">Description</th><th style="width:20%;">Cost Est.</th><th style="width:20%;">Actions</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <div class="form-group mb-2"><label class="small" for="gss-instructions">Special Instructions</label><textarea class="form-control form-control-sm" id="gss-instructions" rows="2" placeholder="Special handling instructions (optional)"></textarea></div>
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <button class="btn btn-success btn-sm" data-action="gss-create-booking" id="gss-create-btn"><i class="fa fa-truck" aria-hidden="true"></i> <span id="gss-btn-text">Create GSS Booking</span></button>
            <button class="btn btn-outline-success btn-sm d-none" data-action="gss-reprint" id="gss-reprint-btn"><i class="fa fa-repeat" aria-hidden="true"></i> Re-Print Docket</button>
          </div>
          <div class="d-flex flex-column text-right"><div class="text-success font-weight-bold" id="gss-total-cost">Total: $0.00</div><small class="text-muted"><span id="gss-package-count">0 packages</span> • <span id="gss-total-weight">0.0kg total</span> • <span id="gss-service-type-display">Economy</span></small></div>
        </div>
        <div class="mt-1" id="gss-status" role="status" aria-live="polite"></div>
      </div>
    </div>
    <div class="tab-pane fade" id="manual-pane" role="tabpanel" aria-labelledby="manual-tab">
      <div class="card-body">
        <div class="alert alert-info d-flex align-items-center mb-3" role="alert"><i class="fa fa-edit mr-2" aria-hidden="true"></i><div><strong>Manual Tracking Entry</strong><br><small>For shipments created outside the system or other courier services</small></div></div>
        <div class="form-group"><label class="small font-weight-bold" for="manual-courier">Courier Service</label><select id="manual-courier" class="form-control"><option value="">Select courier...</option><option value="NZ_POST">NZ Post</option><option value="GSS">NZ Couriers</option><option value="FASTWAY">Fastway</option><option value="DHL">DHL</option><option value="FEDEX">FedEx</option><option value="ARAMEX">Aramex</option><option value="OTHER">Other</option></select></div>
        <div class="form-group"><label class="small font-weight-bold">Tracking Numbers</label><div id="manual-tracking-items" class="mb-2"></div><button type="button" class="btn btn-sm btn-outline-primary" data-action="manual-add-tracking"><i class="fa fa-plus" aria-hidden="true"></i> Add Tracking Number</button></div>
        <div class="form-group"><label class="small font-weight-bold" for="manual-notes">Notes</label><textarea class="form-control" id="manual-notes" rows="3" placeholder="Additional notes about this shipment..."></textarea></div>
        <button class="btn btn-primary" data-action="manual-save"><i class="fa fa-save" aria-hidden="true"></i> Save Tracking Information</button>
        <div class="mt-2" id="manual-status" role="status" aria-live="polite"></div>
      </div>
    </div>
    <div class="tab-pane fade" id="history-pane" role="tabpanel" aria-labelledby="history-tab">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3"><h2 class="h6 mb-0"><i class="fa fa-history text-info mr-2" aria-hidden="true"></i>Recent Shipments</h2><button class="btn btn-sm btn-outline-info" data-action="history-refresh"><i class="fa fa-refresh" aria-hidden="true"></i> Refresh</button></div>
        <div class="table-responsive"><table class="table table-sm table-hover" aria-label="Recent shipments"><thead class="table-light"><tr><th>Date</th><th>Transfer</th><th>Courier</th><th>Tracking</th><th>Status</th><th>Actions</th></tr></thead><tbody id="shipment-history-tbody"><tr><td colspan="6" class="text-center py-4 text-muted"><i class="fa fa-clock-o fa-2x mb-2" aria-hidden="true"></i><br>Loading recent shipments...</td></tr></tbody></table></div>
      </div>
    </div>
  </div>
</div>
