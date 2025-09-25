<?php
declare(strict_types=1);
// Content-only view for CIS template: /modules/module.php?module=purchase-orders&view=receive&po_id=123
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
tpl_shared_assets();

$po_id = (int)($_GET['po_id'] ?? $_GET['id'] ?? 0);
?>
<div class="container-fluid po-receive" data-po-id="<?= htmlspecialchars((string)$po_id, ENT_QUOTES) ?>">
  <?php if (!$po_id): ?>
    <div class="alert alert-danger">PO ID required to receive. Include <code>?po_id=123</code> in the URL.</div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-1">Receive Purchase Order #<?= htmlspecialchars((string)$po_id) ?></h4>
          <div class="text-muted small">Scan items or update quantities; partial and final submit supported.</div>
        </div>
        <div class="d-flex align-items-center" id="po-admin-controls">
          <?php if (!empty($_SESSION['is_admin'])): ?>
            <a class="small text-muted mr-2" href="https://staff.vapeshed.co.nz/modules/purchase-orders/docs/KNOWLEDGE_BASE.md" target="_blank" rel="noopener">Docs</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <div id="po-scan" class="row mb-3">
          <div class="col-md-6">
            <div class="input-group">
              <input id="barcode_input" class="form-control form-control-lg" placeholder="Scan barcode here…" autocomplete="off" />
              <div class="input-group-append">
                <button id="manual_search" class="btn btn-primary"><i class="fa fa-search"></i> Search</button>
              </div>
            </div>
            <small class="text-muted">Scan product barcode or manually search to receive.</small>
          </div>
          <div class="col-md-6">
            <div class="receiving-stats">
              <h6>Receiving Progress</h6>
              <div class="progress mb-2" style="height:25px;">
                <div class="progress-bar" role="progressbar" id="progress_bar"><span id="progress_text">0% Complete</span></div>
              </div>
              <small class="text-muted"><span id="items_received">0</span> of <span id="total_items">0</span> items received</small>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-striped table-sm" id="receiving_table">
            <thead class="thead-dark">
              <tr>
                <th style="width:60px;">Image</th>
                <th>Product</th>
                <th>Expected</th>
                <th class="text-center d-none" id="th-live-stock">In Stock</th>
                <th>Received</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
            <tfoot class="thead-light">
              <tr>
                <th colspan="2" style="color:#fff;">TOTALS</th>
                <th><span style="color:#fff;font-weight:600;font-size:14px;" id="total_expected">0</span></th>
                <th class="text-center d-none" id="tf-live-stock"></th>
                <th class="text-center"><span style="color:#fff;font-weight:600;font-size:14px;" id="total_received_display">0</span></th>
                <th colspan="2"></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="row mt-4" id="action-bar">
          <div class="col-md-12 text-center">
            <button class="btn btn-secondary" id="btn-quick-save">Quick Save</button>
            <button class="btn btn-warning" id="btn-submit-partial">Submit Partial</button>
            <button class="btn btn-success" id="btn-submit-final">Submit Final</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (function_exists('tpl_style')): ?>
    <?php tpl_style('https://staff.vapeshed.co.nz/modules/purchase-orders/assets/css/receive.css'); ?>
  <?php endif; ?>
  <?php if (function_exists('tpl_script')): ?>
    <?php tpl_script('https://staff.vapeshed.co.nz/modules/purchase-orders/assets/js/receive.core.js', ['defer' => true]); ?>
    <?php tpl_script('https://staff.vapeshed.co.nz/modules/purchase-orders/assets/js/receive.table.js', ['defer' => true]); ?>
    <?php tpl_script('https://staff.vapeshed.co.nz/modules/purchase-orders/assets/js/receive.actions.js', ['defer' => true]); ?>
  <?php else: ?>
    <script src="https://staff.vapeshed.co.nz/modules/purchase-orders/assets/js/receive.core.js" defer></script>
    <script src="https://staff.vapeshed.co.nz/modules/purchase-orders/assets/js/receive.table.js" defer></script>
    <script src="https://staff.vapeshed.co.nz/modules/purchase-orders/assets/js/receive.actions.js" defer></script>
  <?php endif; ?>
</div>
