<?php
declare(strict_types=1);

/** @var array $receiveConfigVar */
/** @var array $receiveTransfer */
/** @var array $receiveItems */
/** @var array $receiveShipments */
/** @var array $receiveDiscrepancies */
/** @var array $receiveMedia */
/** @var array $receiveNotes */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
tpl_shared_assets();

if (function_exists('cis_csrf_token')) {
  echo '<meta name="csrf-token" content="' . htmlspecialchars(cis_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

$configJson = htmlspecialchars(json_encode($receiveConfigVar, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$transferId = (int)($receiveTransfer['id'] ?? 0);
$transferCode = htmlspecialchars((string)($receiveConfigVar['transferCode'] ?? ('Transfer #' . $transferId)), ENT_QUOTES, 'UTF-8');
$status      = strtolower((string)($receiveConfigVar['transferStatus'] ?? '')); 
$statusClass = match ($status) {
    'received' => 'success',
    'partial'  => 'warning',
    'sent'     => 'primary',
    'draft'    => 'secondary',
    default    => 'secondary',
};

$metrics       = $receiveConfigVar['metrics'] ?? [];
$itemMetrics   = $metrics['items'] ?? [];
$parcelMetrics = $metrics['parcels'] ?? [];
$flags         = $metrics['flags'] ?? [];

tpl_breadcrumb($receiveConfigVar['breadcrumb'] ?? [
    ['label' => 'Transfers', 'href' => '/cisv2/router.php?module=transfers/stock'],
    ['label' => 'Receive'],
]);
?>

<div class="container-fluid py-3 transfers-receive">
  <div class="receive-screen" data-receive-config="<?= $configJson ?>">
    <div class="card shadow-sm mb-3">
      <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
        <div>
          <h5 class="mb-1">
            <?= $transferCode ?>
            <span class="badge bg-<?= $statusClass ?> text-uppercase ms-2"><?= htmlspecialchars($status ?: 'unknown', ENT_QUOTES, 'UTF-8') ?></span>
          </h5>
          <div class="text-muted small">
            <span class="me-3">From: <strong><?= htmlspecialchars((string)($receiveTransfer['origin_outlet_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></span>
            <span class="me-3">To: <strong><?= htmlspecialchars((string)($receiveTransfer['dest_outlet_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></span>
            <span>Created: <strong><?= htmlspecialchars((string)($receiveTransfer['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></span>
          </div>
        </div>
        <div class="d-flex flex-column gap-2 align-items-lg-end w-100 w-lg-auto">
          <div class="progress w-100" style="height: 10px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: <?= (float)($itemMetrics['progress_pct'] ?? 0) ?>%" aria-valuenow="<?= (float)($itemMetrics['progress_pct'] ?? 0) ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <div class="text-muted small">
            <span class="me-3">Expected: <strong><?= (int)($itemMetrics['total_expected'] ?? 0) ?></strong></span>
            <span class="me-3">Received: <strong id="metric-items-received"><?= (int)($itemMetrics['total_received'] ?? 0) ?></strong></span>
            <span>Outstanding: <strong id="metric-items-outstanding"><?= (int)($itemMetrics['total_outstanding'] ?? 0) ?></strong></span>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" id="btn-finalize" <?= !empty($flags['has_open_discrepancies']) ? 'disabled' : '' ?>>Finalize Receive</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-refresh">Refresh</button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-generate-qr">Upload QR</button>
          </div>
          <div class="small" id="receive-feedback"></div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-xl-8 d-flex flex-column gap-3">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>Items</span>
            <span class="badge bg-light text-dark">Scan or edit quantities; values auto-save</span>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-sm mb-0" id="receive-items">
                <thead class="table-light">
                  <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th class="text-end">Expected</th>
                    <th class="text-end">Received</th>
                    <th class="text-end">Outstanding</th>
                    <th class="text-end">%</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($receiveItems as $item):
                      $expected = (int)($item['expected_qty'] ?? 0);
                      $received = (int)($item['qty_received_total'] ?? 0);
                      $outstanding = max(0, $expected - $received);
                      $pct = $expected > 0 ? round(($received / $expected) * 100, 1) : 0;
                  ?>
                  <tr data-item-id="<?= (int)$item['id'] ?>" data-expected="<?= $expected ?>">
                    <td class="mono text-nowrap"><?= htmlspecialchars((string)($item['sku'] ?: $item['product_id']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end fw-semibold"><?= $expected ?></td>
                    <td class="text-end" style="width:130px;">
                      <input type="number" class="form-control form-control-sm qty-input" min="0" max="<?= $expected ?>" value="<?= $received ?>">
                    </td>
                    <td class="text-end outstanding-cell"><?= $outstanding ?></td>
                    <td class="text-end"><span class="badge bg-<?= $pct >= 100 ? 'success' : ($pct > 0 ? 'info' : 'secondary') ?>"><?= $pct ?>%</span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">Add Discrepancy</div>
          <div class="card-body">
            <form id="form-discrepancy" class="row g-2">
              <div class="col-md-3">
                <label class="form-label small text-muted">Product ID</label>
                <input type="text" name="product_id" class="form-control form-control-sm" required>
              </div>
              <div class="col-md-2">
                <label class="form-label small text-muted">Type</label>
                <select name="type" class="form-select form-select-sm" required>
                  <option value="missing">Missing</option>
                  <option value="damaged">Damaged</option>
                  <option value="lost">Lost</option>
                  <option value="mistake">Mistake</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small text-muted">Qty</label>
                <input type="number" name="qty" class="form-control form-control-sm" min="1" value="1" required>
              </div>
              <div class="col-md-5">
                <label class="form-label small text-muted">Notes</label>
                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
              </div>
              <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-outline-danger btn-sm">Log discrepancy</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>Evidence uploads</span>
            <span class="badge bg-light text-dark">Latest 20</span>
          </div>
          <div class="card-body">
            <?php if (!$receiveMedia): ?>
              <div class="text-muted small">No media captured yet.</div>
            <?php else: ?>
              <div class="row g-3" id="media-list">
                <?php foreach ($receiveMedia as $media): ?>
                  <div class="col-md-4 col-lg-3">
                    <div class="media-tile border rounded p-2 h-100">
                      <div class="media-thumb mb-2">
                        <?php if (str_starts_with($media['mime_type'], 'image/')): ?>
                          <img src="<?= htmlspecialchars($media['path'], ENT_QUOTES, 'UTF-8') ?>" alt="Media" class="img-fluid rounded">
                        <?php else: ?>
                          <div class="media-icon text-center text-muted">
                            <i class="bi bi-file-earmark-play"></i>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div class="small mb-1">
                        <?= htmlspecialchars(strtoupper($media['kind']), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($media['mime_type'], ENT_QUOTES, 'UTF-8') ?>
                      </div>
                      <?php if (!empty($media['parcel_id'])): ?>
                        <div class="badge bg-info bg-opacity-25 text-info-emphasis text-wrap mb-1">Parcel #<?= (int)$media['parcel_id'] ?></div>
                      <?php endif; ?>
                      <?php if (!empty($media['discrepancy_id'])): ?>
                        <div class="badge bg-warning bg-opacity-25 text-warning-emphasis text-wrap mb-1">Discrepancy #<?= (int)$media['discrepancy_id'] ?></div>
                      <?php endif; ?>
                      <?php if (!empty($media['note'])): ?>
                        <div class="text-muted small">"<?= htmlspecialchars($media['note'], ENT_QUOTES, 'UTF-8') ?>"</div>
                      <?php endif; ?>
                      <a href="<?= htmlspecialchars($media['path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-sm btn-outline-secondary w-100 mt-2">Open</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <div class="col-xl-4 d-flex flex-column gap-3">
        <div class="card">
          <div class="card-header">Parcel overview</div>
          <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3 text-muted small">
              <span>Total: <strong id="metric-parcels-total"><?= (int)($parcelMetrics['total'] ?? 0) ?></strong></span>
              <span>Received: <strong id="metric-parcels-received"><?= (int)($parcelMetrics['received'] ?? 0) ?></strong></span>
              <span class="text-danger">Missing: <strong id="metric-parcels-missing"><?= (int)($parcelMetrics['missing'] ?? 0) ?></strong></span>
              <span class="text-warning">Damaged: <strong id="metric-parcels-damaged"><?= (int)($parcelMetrics['damaged'] ?? 0) ?></strong></span>
            </div>
            <?php if (!$receiveShipments): ?>
              <div class="alert alert-info small mb-0">No parcels declared yet. Use the form below to record boxes as they arrive.</div>
            <?php else: ?>
              <?php foreach ($receiveShipments as $shipment): ?>
                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Shipment #<?= (int)$shipment['id'] ?></strong>
                    <span class="badge bg-light text-dark text-uppercase"><?= htmlspecialchars($shipment['status'], ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                  <?php if (empty($shipment['parcels'])): ?>
                    <div class="text-muted small">No parcels recorded yet.</div>
                  <?php else: ?>
                    <ul class="list-group list-group-flush">
                      <?php foreach ($shipment['parcels'] as $parcel):
                          $badgeClass = match ($parcel['status']) {
                              'received' => 'success',
                              'missing'  => 'danger',
                              'damaged'  => 'warning',
                              default    => 'secondary',
                          };
                      ?>
                      <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                        <div>
                          <div class="fw-semibold">Box #<?= (int)$parcel['box_number'] ?></div>
                          <div class="small text-muted">Courier: <?= htmlspecialchars((string)($parcel['courier'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?> · Tracking: <?= htmlspecialchars((string)($parcel['tracking_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small text-muted">Items: <?= (int)$parcel['items_received'] ?> / <?= (int)$parcel['items_declared'] ?></div>
                        </div>
                        <div class="text-end">
                          <div class="mb-1"><span class="badge bg-<?= $badgeClass ?> text-uppercase"><?= htmlspecialchars($parcel['status'], ENT_QUOTES, 'UTF-8') ?></span></div>
                          <div class="btn-group btn-group-sm" role="group" aria-label="Parcel actions">
                            <button class="btn btn-outline-success parcel-action" data-parcel="<?= (int)$parcel['id'] ?>" data-action="mark_received">✔</button>
                            <button class="btn btn-outline-warning parcel-action" data-parcel="<?= (int)$parcel['id'] ?>" data-action="mark_damaged">⚠</button>
                            <button class="btn btn-outline-danger parcel-action" data-parcel="<?= (int)$parcel['id'] ?>" data-action="mark_missing">✖</button>
                          </div>
                          <button class="btn btn-link btn-sm text-decoration-none ps-0 parcel-qr" data-parcel="<?= (int)$parcel['id'] ?>">QR upload</button>
                        </div>
                      </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <hr>
            <form id="form-parcel-declare" class="row g-2">
              <div class="col-4">
                <label class="form-label small text-muted">Box #</label>
                <input type="number" name="box_number" class="form-control form-control-sm" min="1" required>
              </div>
              <div class="col-4">
                <label class="form-label small text-muted">Weight (kg)</label>
                <input type="number" name="weight_kg" class="form-control form-control-sm" min="0" step="0.1">
              </div>
              <div class="col-4">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                  <option value="received">Received</option>
                  <option value="in_transit">In Transit</option>
                  <option value="missing">Missing</option>
                  <option value="damaged">Damaged</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small text-muted">Notes</label>
                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional comments">
              </div>
              <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-outline-primary btn-sm">Declare parcel</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header">Discrepancy log</div>
          <div class="card-body">
            <?php if (!$receiveDiscrepancies): ?>
              <div class="text-muted small">No discrepancies logged.</div>
            <?php else: ?>
              <ul class="list-group list-group-flush" id="discrepancy-list">
                <?php foreach ($receiveDiscrepancies as $disc):
                    $badgeClass = match ($disc['status']) {
                        'open'       => 'danger',
                        'reconciled' => 'success',
                        'void'       => 'secondary',
                        default      => 'secondary',
                    };
                ?>
                <li class="list-group-item px-0">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="fw-semibold">#<?= (int)$disc['id'] ?> · <?= htmlspecialchars(strtoupper($disc['type']), ENT_QUOTES, 'UTF-8') ?></div>
                      <?php
                      $qtyLabel = $disc['qty'] ?? ($disc['qty_expected'] ?: $disc['qty_actual']);
                      ?>
                      <div class="small text-muted">Product: <?= htmlspecialchars((string)$disc['product_id'], ENT_QUOTES, 'UTF-8') ?> · Qty: <?= htmlspecialchars((string)$qtyLabel, ENT_QUOTES, 'UTF-8') ?></div>
                      <?php if (!empty($disc['notes'])): ?>
                        <div class="small text-muted">"<?= htmlspecialchars($disc['notes'], ENT_QUOTES, 'UTF-8') ?>"</div>
                      <?php endif; ?>
                      <div class="small text-muted">Logged <?= htmlspecialchars((string)$disc['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <span class="badge bg-<?= $badgeClass ?> text-uppercase"><?= htmlspecialchars($disc['status'], ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header">Notes</div>
          <div class="card-body">
            <?php if (!$receiveNotes): ?>
              <div class="text-muted small">No notes recorded.</div>
            <?php else: ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($receiveNotes as $note): ?>
                  <li class="list-group-item px-0">
                    <div class="small text-muted"><?= htmlspecialchars((string)$note['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div><?= nl2br(htmlspecialchars((string)$note['note_text'], ENT_QUOTES, 'UTF-8')) ?></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="/modules/transfers/receive/css/receive.css?v=2">
<script src="/modules/transfers/receive/js/receive.js?v=2" defer></script>
