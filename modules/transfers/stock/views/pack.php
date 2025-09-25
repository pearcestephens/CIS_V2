<?php
declare(strict_types=1);

/**
 * Expected variables provided by the controller:
 * - $meta      : array (title, breadcrumb, etc.)
 * - $transfer  : array with keys:
 *     id, outlet_from (string|int), outlet_to (string|int),
 *     items => [ ['id','product_id','product_name','qty_requested','qty_sent_total','inventory_level'], ... ]
 * - $user      : array with user display info (optional)
 *
 * This view renders *only* the inner content. Header/Sidebar/Footer are handled by assets/template.
 */

$tid       = (int)($transfer['id'] ?? (int)($_GET['transfer'] ?? 0));
$outletFromName = htmlspecialchars($transfer['outlet_from_name'] ?? ($transfer['outlet_from'] ?? 'Source'), ENT_QUOTES, 'UTF-8');
$outletToName   = htmlspecialchars($transfer['outlet_to_name']   ?? ($transfer['outlet_to']   ?? 'Destination'), ENT_QUOTES, 'UTF-8');
$userName  = htmlspecialchars(trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
$items     = is_array($transfer['items'] ?? null) ? $transfer['items'] : [];
?>

<div id="pack-app"
     class="vs-pack"
     data-transfer-id="<?= $tid ?>"
     data-post-url="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div>
        <h4 class="mb-1"><?= htmlspecialchars($meta['title'] ?? "Pack Transfer #{$tid}", ENT_QUOTES, 'UTF-8') ?></h4>
        <div class="small text-muted">
          <?= $outletFromName ?> → <?= $outletToName ?>
        </div>
      </div>

      <div class="btn-group">
        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fa fa-cog mr-2"></i> Options
        </button>
        <div class="dropdown-menu dropdown-menu-right shadow border-0">
          <button class="dropdown-item" type="button" data-toggle="modal" data-target="#mergeTransferModal">
            <i class="fa fa-code-fork mr-2"></i> Merge Transfer
          </button>
          <button class="dropdown-item" type="button" data-toggle="modal" data-target="#addProductsModal">
            <i class="fa fa-plus mr-2"></i> Add Products
          </button>
          <div class="dropdown-divider"></div>
          <button class="dropdown-item text-danger" type="button" data-action="delete-transfer">
            <i class="fa fa-trash mr-2"></i> Delete Transfer
          </button>
        </div>
      </div>
    </div>

    <div class="card-body">
      <!-- Status / Draft toolbar -->
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 12px;">
        <div class="d-flex align-items-center" style="gap: 10px;">
          <span class="badge badge-pill badge-secondary" id="draft-status">Draft: Off</span>
          <span class="text-muted small" id="draft-last-saved">Not saved</span>

          <div class="btn-group ml-2" role="group" aria-label="Draft actions">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-save-draft" title="Save a local draft (Ctrl+S)">
              Save now
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" id="btn-restore-draft" disabled>
              Restore
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="btn-discard-draft" disabled>
              Discard
            </button>
          </div>

          <div class="custom-control custom-switch ml-2" title="Auto-save to this browser only (does not update Vend)">
            <input type="checkbox" class="custom-control-input" id="toggle-autosave">
            <label class="custom-control-label" for="toggle-autosave">Autosave</label>
          </div>
        </div>

        <div class="d-flex align-items-center" style="gap: 8px;">
          <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#addProductsModal">
            <i class="fa fa-plus mr-2"></i> Add Products
          </button>
          <button class="btn btn-outline-secondary" type="button" id="tbl-print" title="Print picking sheet">
            <i class="fa fa-print mr-2"></i> Print
          </button>
        </div>
      </div>

      <!-- Totals / Summary -->
      <div class="card mb-3" id="table-card">
        <div class="card-body py-2">
          <div class="d-flex flex-wrap align-items-center" style="gap: 16px;">
            <span>Items: <strong id="itemsToTransfer"><?= count($items) ?></strong></span>
            <span>Planned total: <strong id="plannedTotal">0</strong></span>
            <span>Counted total: <strong id="countedTotal">0</strong></span>
            <span>Diff: <strong id="diffTotal">0</strong></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-fill-counted">
              Fill counted = planned
            </button>
          </div>

          <div class="table-responsive mt-2">
            <table class="table table-sm table-bordered table-striped" id="transfer-table">
              <thead>
                <tr>
                  <th style="width:36px;"></th>
                  <th>Name</th>
                  <th>Qty In Stock</th>
                  <th>Planned Qty</th>
                  <th>Counted Qty</th>
                  <th>Source</th>
                  <th>Destination</th>
                  <th style="width:110px;">Line ID</th>
                </tr>
              </thead>
              <tbody id="productSearchBody">
              <?php
              $row = 0;
              foreach ($items as $i) {
                  $row++;
                  $pid     = htmlspecialchars((string)$i['product_id'], ENT_QUOTES, 'UTF-8');
                  $pname   = htmlspecialchars((string)($i['product_name'] ?? $i['product_id']), ENT_QUOTES, 'UTF-8');
                  $inv     = (int)($i['inventory_level'] ?? 0);
                  $planned = (int)($i['qty_requested']    ?? 0);
                  $sent    = (int)($i['qty_sent_total']   ?? 0);
                  if ($planned <= 0) continue;
                  ?>
                  <tr data-inventory="<?= $inv ?>" data-planned="<?= $planned ?>">
                    <td class="text-center align-middle">
                      <img src="/assets/img/remove-icon.png" alt="Remove" title="Remove Product"
                           class="vs-remove-row" style="height: 13px; cursor: pointer;">
                      <input type="hidden" class="productID" value="<?= $pid ?>">
                    </td>
                    <td><?= $pname ?></td>
                    <td class="inv"><?= $inv ?></td>
                    <td class="planned"><?= $planned ?></td>
                    <td class="counted-td">
                      <input type="number"
                             min="0" max="<?= $inv ?>"
                             class="form-control form-control-sm vs-counted"
                             value="<?= $sent > 0 ? $sent : '' ?>"
                             placeholder="0">
                      <span class="counted-print-value d-none"><?= max(0, $sent) ?></span>
                    </td>
                    <td><?= $outletFromName ?></td>
                    <td><?= $outletToName ?></td>
                    <td><span class="id-counter"><?= $tid ?>-<?= $row ?></span></td>
                  </tr>
                  <?php
              }
              ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Delivery & Notes (kept, wiring added in JS Section) -->
      <div class="card mb-3" id="delivery-tracking-card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
          <strong>Delivery &amp; Notes</strong>
          <small class="text-muted">Consignment-first | Send mode with server-side fallback</small>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="mb-2"><strong>Notes &amp; Discrepancies</strong></label>
              <textarea class="form-control" id="notesForTransfer" rows="4"
                        placeholder="Enter any notes, discrepancies, or special instructions..."></textarea>
            </div>
            <div class="col-md-6 mb-3">
              <label class="mb-2"><strong>Delivery Method</strong></label>
              <div class="mb-2">
                <div class="custom-control custom-radio">
                  <input type="radio" id="mode-courier" name="delivery-mode" class="custom-control-input" value="courier" checked>
                  <label class="custom-control-label" for="mode-courier">Courier delivery</label>
                </div>
                <div class="custom-control custom-radio">
                  <input type="radio" id="mode-internal" name="delivery-mode" class="custom-control-input" value="internal">
                  <label class="custom-control-label" for="mode-internal">Internal (drive/drop)</label>
                </div>
              </div>

              <!-- Courier service selector (detailed panels wired in JS Section) -->
              <div class="mb-2">
                <label class="mb-1"><strong>Courier Services &amp; Labels</strong></label>
                <select id="courier-service" class="form-control">
                  <option value="">Select courier service...</option>
                  <option value="nzpost">NZ Post</option>
                  <option value="gss">NZ Couriers</option>
                  <option value="manual">Manual Entry</option>
                </select>
                <div id="printer-status" class="small text-muted mt-1" style="display:none;">
                  <i class="fa fa-info-circle"></i>
                  <span id="printer-status-text"></span>
                </div>
              </div>

              <!-- Panels container (content injected by JS bundle later) -->
              <div id="shipping-panels"></div>
            </div>
          </div>

          <!-- Manual Tracking quick-add (kept simple; advanced UI in JS Section) -->
          <div id="manual-panel" class="mt-2" style="display:none;">
            <label class="small mb-1"><strong>Manual Tracking Numbers</strong></label>
            <div id="tracking-items" class="mb-2"></div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-tracking">Add tracking number</button>
            <div class="mt-2 small text-muted"><span id="tracking-count">0 numbers</span></div>
          </div>

          <div id="generated-labels" class="mt-2"></div>
        </div>
      </div>

      <!-- Box Labels Printer -->
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
              Labels include Transfer #<?= $tid ?>, FROM/TO stores, and box numbers
            </small>
          </div>
        </div>
      </div>

      <!-- Footer actions -->
