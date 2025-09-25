<?php
/** @var array|null $transfer */
$tx      = $transfer ?: ['id' => 0, 'outlet_from' => '', 'outlet_to' => '', 'items' => []];
$txId    = (int)($tx['id'] ?? 0);
$items   = is_array($tx['items'] ?? null) ? $tx['items'] : [];
$fromLbl = htmlspecialchars((string)($tx['outlet_from'] ?? ''), ENT_QUOTES, 'UTF-8');
$toLbl   = htmlspecialchars((string)($tx['outlet_to']   ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="animated fadeIn">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h4 class="card-title mb-0">
          Pack Transfer #<?= $txId ?><br>
          <small class="text-muted"><?= $fromLbl ?> → <?= $toLbl ?></small>
        </h4>
        <div class="small text-muted">Count, label and finalize this consignment</div>
      </div>

      <div class="btn-group">
        <button id="savePack" class="btn btn-primary">
          <i class="fa fa-save mr-1"></i> Save Pack
        </button>
        <button class="btn btn-outline-secondary" id="autofillFromPlanned" type="button" title="counted = planned">
          <i class="fa fa-magic mr-1"></i> Autofill
        </button>
      </div>
    </div>

    <div class="card-body transfer-data">

      <!-- Draft toolbar -->
      <div class="d-flex justify-content-between align-items-start w-100 mb-3" id="table-action-toolbar" style="gap:8px;">
        <div class="d-flex flex-column" style="gap:4px;">
          <div class="d-flex align-items-center" style="gap:8px;">
            <span class="badge badge-pill badge-secondary" id="draft-status">Draft: Off</span>
            <span class="text-muted small" id="draft-last-saved">Not saved</span>
          </div>
          <div class="d-flex align-items-center" style="gap:12px;">
            <div class="d-flex" style="gap:8px;" role="group" aria-label="Draft actions">
              <button type="button" class="btn btn-sm btn-outline-primary" id="btn-save-draft">Save now (Ctrl+S)</button>
              <button type="button" class="btn btn-sm btn-outline-success" id="btn-restore-draft" disabled>Restore</button>
              <button type="button" class="btn btn-sm btn-outline-danger" id="btn-discard-draft" disabled>Discard</button>
            </div>
            <div class="custom-control custom-switch" title="Auto-save to this browser only">
              <input type="checkbox" class="custom-control-input" id="toggle-autosave">
              <label class="custom-control-label" for="toggle-autosave">Autosave</label>
            </div>
          </div>
        </div>

        <!-- Summary strip -->
        <div class="d-flex align-items-center flex-wrap" style="gap:10px;">
          <span>Items: <strong id="itemsToTransfer"><?= count($items) ?></strong></span>
          <span>Planned total: <strong id="plannedTotal">0</strong></span>
          <span>Counted total: <strong id="countedTotal">0</strong></span>
          <span>Diff: <strong id="diffTotal">0</strong></span>
        </div>
      </div>

      <!-- Items table -->
      <div class="card tfx-card-tight mb-3" id="table-card">
        <div class="card-body py-2">
          <table class="table table-responsive-sm table-bordered table-striped table-sm" id="transfer-table">
            <thead>
              <tr>
                <th style="width:38px;"></th>
                <th>Product</th>
                <th>Planned Qty</th>
                <th>Counted Qty</th>
                <th>From</th>
                <th>To</th>
                <th>ID</th>
              </tr>
            </thead>
            <tbody id="productSearchBody">
            <?php
            $row = 0;
            foreach ($items as $i) {
              $row++;
              $iid      = (int)($i['id'] ?? 0);
              $pid      = htmlspecialchars((string)($i['product_id'] ?? ''), ENT_QUOTES, 'UTF-8');
              $planned  = (int)($i['qty_requested'] ?? 0);
              $sentSoFar= (int)($i['qty_sent_total'] ?? 0);
              $inventory= max($planned, $sentSoFar);
              if ($planned <= 0) continue;
              echo '<tr data-inventory="'.$inventory.'" data-planned="'.$planned.'">';
              echo   "<td class='text-center align-middle'>
                        <button class='btn btn-sm btn-outline-danger' type='button' data-action='remove-product' title='Remove'>
                          <i class='fa fa-times'></i>
                        </button>
                        <input type='hidden' class='productID' value='{$iid}'>
                      </td>";
              echo   '<td>'.($pid ?: 'Product').'</td>';
              echo   '<td class="planned">'.$planned.'</td>';
              echo   "<td class='counted-td'>
                        <input type='number' min='0' max='{$inventory}' value='".($sentSoFar ?: '')."' class='tfx-num'>
                        <span class='counted-print-value d-none'>".($sentSoFar ?: 0)."</span>
                      </td>";
              echo   '<td>'.$fromLbl.'</td>';
              echo   '<td>'.$toLbl.'</td>';
              echo   '<td><span class="id-counter">'.$txId.'-'.$row.'</span></td>';
              echo '</tr>';
            }
            if (!$items) {
              echo '<tr><td colspan="7" class="text-center text-muted py-4">No items on this transfer.</td></tr>';
            }
            ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Shipping & Labels (unified card) -->
      <div class="card mt-3" id="ship-labels-card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <strong>Shipping & Labels</strong>
            <span class="text-muted ml-2">Create labels or record internal delivery boxes</span>
          </div>
          <div class="d-flex align-items-center" style="gap:.5rem;">
            <select id="sl-delivery-mode" class="form-control form-control-sm" style="width:auto">
              <option value="courier" selected>Courier</option>
              <option value="driver_pickup">Driver Pickup</option>
              <option value="driver_drop">Driver Drop-off</option>
              <option value="internal">Internal Delivery</option>
            </select>
            <select id="sl-carrier" class="form-control form-control-sm" style="width:auto">
              <option value="nz_post">NZ Post (Starshipit)</option>
              <option value="gss">NZ Couriers (GoSweetSpot)</option>
              <option value="manual">Manual (no external)</option>
            </select>
            <input id="sl-service" class="form-control form-control-sm" style="width:160px" placeholder="Service (e.g., CPOLTPA5)">
          </div>
        </div>
        <div class="card-body">
          <div class="row g-2 mb-2">
            <div class="col-auto form-check">
              <input class="form-check-input" type="checkbox" id="sl-signature" checked>
              <label for="sl-signature" class="form-check-label">Signature</label>
            </div>
            <div class="col-auto form-check">
              <input class="form-check-input" type="checkbox" id="sl-saturday">
              <label for="sl-saturday" class="form-check-label">Saturday</label>
            </div>
            <div class="col-auto form-check">
              <input class="form-check-input" type="checkbox" id="sl-atl">
              <label for="sl-atl" class="form-check-label">Authority to leave</label>
            </div>
            <div class="col-auto">
              <input id="sl-printer" class="form-control form-control-sm" style="width:220px" placeholder="GSS printer (optional)">
            </div>
          </div>

          <textarea id="sl-instructions" class="form-control mb-2" rows="2" placeholder="Delivery instructions (optional)"></textarea>

          <div class="table-responsive">
            <table id="sl-packages" class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width:22%">Name</th>
                  <th style="width:15%">L (cm)</th>
                  <th style="width:15%">W (cm)</th>
                  <th style="width:15%">H (cm)</th>
                  <th style="width:15%">Weight (kg)</th>
                  <th style="width:18%"></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="mb-2">
            <button id="sl-add" class="btn btn-outline-secondary btn-sm">Add box</button>
            <button id="sl-copy" class="btn btn-outline-secondary btn-sm">Copy last</button>
            <button id="sl-clear" class="btn btn-outline-danger btn-sm">Clear</button>
            <button id="sl-override" class="btn btn-outline-primary btn-sm float-right">Override address</button>
          </div>

          <div id="sl-address" class="row g-2 mt-2 d-none">
            <div class="col-12"><strong>Recipient override</strong></div>
            <div class="col-md-4"><input id="sl-name" class="form-control form-control-sm" placeholder="Name"></div>
            <div class="col-md-4"><input id="sl-company" class="form-control form-control-sm" placeholder="Company"></div>
            <div class="col-md-4"><input id="sl-email" class="form-control form-control-sm" placeholder="Email"></div>
            <div class="col-md-4"><input id="sl-phone" class="form-control form-control-sm" placeholder="Phone"></div>
            <div class="col-md-6"><input id="sl-street1" class="form-control form-control-sm" placeholder="Street 1"></div>
            <div class="col-md-6"><input id="sl-street2" class="form-control form-control-sm" placeholder="Street 2"></div>
            <div class="col-md-3"><input id="sl-suburb" class="form-control form-control-sm" placeholder="Suburb"></div>
            <div class="col-md-3"><input id="sl-city" class="form-control form-control-sm" placeholder="City"></div>
            <div class="col-md-2"><input id="sl-state" class="form-control form-control-sm" placeholder="State"></div>
            <div class="col-md-2"><input id="sl-postcode" class="form-control form-control-sm" placeholder="Postcode"></div>
            <div class="col-md-2"><input id="sl-country" class="form-control form-control-sm" value="NZ" placeholder="Country"></div>
          </div>

          <div class="mt-3 d-flex justify-content-between">
            <div id="sl-feedback" class="small text-muted"></div>
            <div class="btn-group">
              <button id="sl-create" class="btn btn-success btn-sm">Create Labels</button>
              <a id="sl-print-slips" class="btn btn-outline-dark btn-sm" target="_blank" href="#">Print Box Slips</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Notes & Manual Tracking -->
      <div class="row mt-3">
        <div class="col-md-6 mb-3">
          <label class="mb-2"><strong>Notes & Discrepancies</strong></label>
          <textarea class="form-control" id="notesForTransfer" rows="4" placeholder="Enter any notes..."></textarea>
        </div>
        <div class="col-md-6 mb-3">
          <label class="mb-2"><strong>Manual Tracking Numbers</strong></label>
          <div id="tracking-items" class="mb-2"></div>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-tracking">
            <i class="fa fa-plus"></i> Add tracking number
          </button>
          <div class="mt-2 small text-muted"><span id="tracking-count">0 numbers</span></div>
        </div>
      </div>

      <!-- Hidden context for JS -->
      <input type="hidden" id="transferID" value="<?= $txId ?>">
      <input type="hidden" id="sourceID" value="<?= $fromLbl ?>">
      <input type="hidden" id="destinationID" value="<?= $toLbl ?>">
      <input type="hidden" id="staffID" value="<?= (int)($_SESSION['userID'] ?? 0) ?>">

      <!-- Box Slips quick printer -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-warning text-dark py-2 d-flex justify-content-between">
          <div><i class="fa fa-print mr-2"></i><strong>Box Label Printer</strong></div>
          <small class="badge badge-dark">Quick Print</small>
        </div>
        <div class="card-body py-3">
          <div class="row align-items-center">
            <div class="col-md-4">
              <label class="form-label small font-weight-bold mb-1">Number of Boxes:</label>
              <input type="number" min="1" max="50" class="form-control form-control-sm"
                     id="box-count-input" value="1" placeholder="Boxes">
            </div>
            <div class="col-md-8 d-flex align-items-center justify-content-end" style="gap: 8px;">
              <button class="btn btn-warning btn-sm" type="button" id="btn-preview-labels">
                <i class="fa fa-eye"></i> Preview
              </button>
              <button class="btn btn-success btn-sm" type="button" id="btn-print-labels">
                <i class="fa fa-print"></i> Print Now
              </button>
              <button class="btn btn-outline-secondary btn-sm" type="button" id="btn-open-label-window">
                <i class="fa fa-external-link"></i> Open Window
              </button>
            </div>
          </div>
          <div class="mt-2">
            <small class="text-muted">
              <i class="fa fa-info-circle"></i>
              Labels include Transfer #<?= $txId ?>, FROM/TO stores, and box numbers
            </small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
