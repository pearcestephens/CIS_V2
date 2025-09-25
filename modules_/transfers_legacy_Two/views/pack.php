<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'].'/modules/transfers/_base.php';

// REQUIRED: transfer_id in query
$transferId = isset($_GET['transfer_id']) ? (int)$_GET['transfer_id'] : 0;

// Resolve tokens from the transfer record itself
$tok = transfers_outlet_tokens_by_transfer($transferId);

// Build page context for JS
$ctx = [
  'transferId' => $transferId,
  'outletFrom' => (string)$tok['outlet_id'],          // now derived, not passed
  'outletName' => (string)$tok['outlet_name'],
  'endpoints'  => [
    'ajax' => '/modules/transfers/stock/ajax/handler.php',
  ],
  'requestId'  => transfers_reqid(),
  'csrf'       => transfers_csrf(),
  'courierTokens' => [
    'gss_token'                => (string)$tok['gss_token'],
    'nz_post_api_key'          => (string)$tok['nz_post_api_key'],
    'nz_post_subscription_key' => (string)$tok['nz_post_subscription_key'],
  ],
];

// expose context & meta csrf
transfers_expose_ctx('PackPage', $ctx);
echo '<meta name="csrf-token" content="'.htmlspecialchars($ctx['csrf'], ENT_QUOTES, 'UTF-8').'">';
?>

<input type="hidden" id="transfer_id" value="<?= (int)$transferId ?>">
<input type="hidden" id="outlet_from"  value="<?= htmlspecialchars((string)$tok['outlet_id'], ENT_QUOTES) ?>">

<div class="pack-page container-fluid" data-request-id="<?= htmlspecialchars($ctx['requestId'], ENT_QUOTES) ?>">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h5 class="mb-0">
        Packing Transfer #<span id="pack-transfer"><?= (int)$transferId ?></span>
        <?php if (!empty($ctx['outletName'])): ?>
          <small class="text-muted"> — <?= htmlspecialchars($ctx['outletName'], ENT_QUOTES) ?></small>
        <?php endif; ?>
      </h5>
      <?php if (!empty($tok['outlet_id'])): ?>
        <div class="small text-muted">
          Outlet: <span class="mono"><?= htmlspecialchars((string)$tok['outlet_id'], ENT_QUOTES) ?></span>
        </div>
      <?php endif; ?>
    </div>
    <div class="d-flex align-items-center" style="gap:.5rem">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-label-gss">Generate Label (MVP)</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-label-nzpost" disabled>Generate Label (NZPost)</button>
      <button type="button" class="btn btn-outline-dark  btn-sm" data-bs-toggle="modal" data-bs-target="#manualTrackingModal">Manual Tracking</button>
      <button type="button" class="btn btn-primary btn-sm" id="btn-save-pack">Save Pack</button>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-8">
      <div class="card mb-3">
        <div class="card-header"><strong>Items</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0" id="tblItems">
              <thead>
                <tr>
                  <th style="width:40%">Product</th>
                  <th style="width:10%">Req</th>
                  <th style="width:15%">Pack Qty</th>
                  <th style="width:15%">Ship Units</th>
                  <th style="width:20%">Weight(g)</th>
                </tr>
              </thead>
              <tbody><!-- filled by JS --></tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><strong>Notes</strong></div>
        <div class="card-body">
          <textarea class="form-control" id="pack-notes" rows="3" placeholder="Notes..."></textarea>
          <div class="small text-muted mt-1">Notes are saved when you generate a label or click “Save Pack”.</div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Parcels</strong>
          <button class="btn btn-sm btn-outline-secondary" id="btn-add-parcel" type="button">Add Parcel</button>
        </div>
        <div class="card-body">
          <div id="parcelList">
            <div class="parcel-row">
              <div class="d-flex align-items-center" style="gap:.5rem">
                <span class="text-muted">#1</span>
                <input type="number" min="0" class="form-control form-control-sm parcel-weight-input" placeholder="Weight(g)" style="width:140px">
                <button class="btn btn-sm btn-outline-secondary add-row" type="button">Add Row</button>
              </div>
              <div class="small text-muted mt-1">
                If you don’t attach items here, the backend will auto-attach from table quantities.
              </div>
            </div>
          </div>
          <div class="mt-2 small text-muted">MVP labelling — tracking will surface as we integrate carrier APIs.</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><strong>Summary</strong></div>
        <div class="card-body">
          <div class="d-flex justify-content-between"><span>Parcels</span><span id="sum-parcels">0</span></div>
          <div class="d-flex justify-content-between"><span>Total Weight(g)</span><span id="sum-weight">0</span></div>
          <div class="small text-muted mt-1" id="request-id">req: <span class="mono"><?= htmlspecialchars($ctx['requestId'], ENT_QUOTES) ?></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Manual tracking fallback modal -->
<div class="modal fade" id="manualTrackingModal" tabindex="-1" aria-labelledby="manualTrackingLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="manualTrackingLabel">Manual Tracking (fallback)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Carrier Name</label>
          <input type="text" class="form-control form-control-sm" id="manual-carrier" placeholder="NZ Couriers / NZ Post / Aramex / Internal">
        </div>
        <div class="mb-2">
          <label class="form-label">Tracking Number</label>
          <input type="text" class="form-control form-control-sm" id="manual-tracking">
        </div>
        <div class="mb-2">
          <label class="form-label">Tracking URL (optional)</label>
          <input type="url" class="form-control form-control-sm" id="manual-label-url" placeholder="https://...">
        </div>
        <div class="small text-muted">
          Leave both carrier & number empty to record Internal Delivery (no tracking).
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary btn-sm" id="btn-apply-manual">Apply</button>
      </div>
    </div>
  </div>
</div>
