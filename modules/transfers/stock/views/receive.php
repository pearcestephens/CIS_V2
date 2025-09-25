<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
tpl_shared_assets();

// CSRF meta (for JS)
if (function_exists('cis_csrf_token')) {
    echo '<meta name="csrf-token" content="' . htmlspecialchars(cis_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

$transferId = (int)($_GET['transfer_id'] ?? $_GET['id'] ?? 0);

tpl_breadcrumb([
    ['label' => 'Transfers'],
    ['label' => 'Receive'],
]);
?>
<div class="container mt-3 transfers-receive">
  <?php if (!$transferId): ?>
    <div class="alert alert-danger">transfer_id is required. Append <code>?transfer_id=123</code> to the URL.</div>
  <?php else: ?>
    <div class="row">
      <div class="col-md-8">
        <div class="card mb-3">
          <div class="card-header">Items</div>
          <div class="card-body p-0">
            <table class="table table-striped table-sm mb-0" id="receive-items">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Name</th>
                  <th class="text-end">Expected</th>
                  <th class="text-end">Received</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <button class="btn btn-primary btn-sm" id="btn-save-receipt">Save Receipt</button>
      </div>
      <div class="col-md-4">
        <div class="card mb-3">
          <div class="card-header">Parcels</div>
          <div class="card-body p-2" id="receive-parcels"></div>
        </div>
        <div class="card mb-3">
          <div class="card-header">Discrepancies</div>
          <div class="card-body p-2" id="receive-discrepancies"><div class="text-muted small">Created automatically when quantities differ.</div></div>
        </div>
        <div class="card">
          <div class="card-header">Scan</div>
          <div class="card-body">
            <input type="text" class="form-control" id="scan-input" placeholder="Scan tracking or SKU" autofocus>
            <small class="text-muted" id="request-id"></small>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<link rel="stylesheet" href="/modules/transfers/receive/css/receive.css">
<script src="/modules/transfers/receive/js/receive.js" defer></script>
