<?php
/**
 * https://staff.vapeshed.co.nz/modules/purchase-orders/views/admin/dashboard.php
 * Purpose: Content-only Admin dashboard for PO receipts, events, inventory queue, and evidence upload/list.
 * Renders inside CIS template via /modules/module.php?module=purchase-orders&view=admin
 */
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
tpl_shared_assets();

// CSRF token source for AJAX handlers
$csrfToken = $_SESSION['csrf_token'] ?? (function_exists('generateCSRFToken') ? generateCSRFToken() : bin2hex(random_bytes(16)));
$_SESSION['csrf_token'] = $csrfToken;
?>
<div class="container-fluid my-3 po-admin" data-csrf="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Purchase Orders – Admin Dashboard</h3>
    <div class="d-flex align-items-center">
      <?php if (!empty($_SESSION['is_admin'])): ?>
        <a class="small text-muted mr-3" href="https://staff.vapeshed.co.nz/modules/purchase-orders/docs/KNOWLEDGE_BASE.md" target="_blank" rel="noopener">Docs</a>
      <?php endif; ?>
      <div class="input-group input-group-sm" style="max-width: 360px;">
      <div class="input-group-prepend"><span class="input-group-text">PO ID</span></div>
      <input type="number" class="form-control" id="po-filter-id" placeholder="Optional filter" />
      <div class="input-group-append"><button class="btn btn-primary" id="btn-apply-filter">Apply</button></div>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-receipts" role="tab">Receipts</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-events" role="tab">Events</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-queue" role="tab">Inventory Queue</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-evidence" role="tab">Evidence</a></li>
  </ul>

  <div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="tab-receipts" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <button class="btn btn-sm btn-outline-secondary" id="btn-refresh-receipts">Refresh</button>
        </div>
        <div class="small text-muted" id="receipts-meta"></div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0" id="tbl-receipts">
          <thead><tr>
            <th>ID</th><th>PO</th><th>Outlet</th><th>Final</th><th>Items</th><th>By</th><th>Created</th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="d-flex justify-content-end align-items-center mt-2">
        <button class="btn btn-sm btn-light" id="rcp-prev">Prev</button>
        <span class="mx-2" id="rcp-page"></span>
        <button class="btn btn-sm btn-light" id="rcp-next">Next</button>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-events" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <button class="btn btn-sm btn-outline-secondary" id="btn-refresh-events">Refresh</button>
        </div>
        <div class="small text-muted" id="events-meta"></div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0" id="tbl-events">
          <thead><tr>
            <th>ID</th><th>PO</th><th>Type</th><th>Data</th><th>By</th><th>Created</th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="d-flex justify-content-end align-items-center mt-2">
        <button class="btn btn-sm btn-light" id="evt-prev">Prev</button>
        <span class="mx-2" id="evt-page"></span>
        <button class="btn btn-sm btn-light" id="evt-next">Next</button>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-queue" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="form-inline">
          <select class="form-control form-control-sm mr-2" id="queue-status">
            <option value="">All</option>
            <option>pending</option>
            <option>queued</option>
            <option>processing</option>
            <option>done</option>
            <option>failed</option>
          </select>
          <input class="form-control form-control-sm mr-2" id="queue-outlet" placeholder="Outlet ID" />
          <button class="btn btn-sm btn-outline-secondary" id="btn-refresh-queue">Refresh</button>
        </div>
        <div class="small text-muted" id="queue-meta"></div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0" id="tbl-queue">
          <thead><tr>
            <th>ID</th><th>Outlet</th><th>Product</th><th>Delta</th><th>Status</th><th>Reason</th><th>When</th><th></th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="d-flex justify-content-end align-items-center mt-2">
        <button class="btn btn-sm btn-light" id="q-prev">Prev</button>
        <span class="mx-2" id="q-page"></span>
        <button class="btn btn-sm btn-light" id="q-next">Next</button>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-evidence" role="tabpanel">
      <form id="evidence-form" class="mb-3">
        <div class="form-row">
          <div class="col-md-3 mb-2">
            <label class="small mb-1">PO ID</label>
            <input type="number" class="form-control form-control-sm" id="ev-po-id" placeholder="required" />
          </div>
          <div class="col-md-3 mb-2">
            <label class="small mb-1">Type</label>
            <select class="form-control form-control-sm" id="ev-type">
              <option value="delivery">delivery</option>
              <option value="invoice">invoice</option>
              <option value="packing_slip">packing_slip</option>
              <option value="other">other</option>
            </select>
          </div>
          <div class="col-md-6 mb-2">
            <label class="small mb-1">Description</label>
            <input type="text" class="form-control form-control-sm" id="ev-desc" />
          </div>
        </div>
        <div class="form-row align-items-center">
          <div class="col-md-6 mb-2">
            <input type="file" id="ev-file" class="form-control-file" />
          </div>
          <div class="col-md-6 mb-2 text-right">
            <button type="submit" class="btn btn-sm btn-primary">Upload</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-refresh-evidence">Refresh List</button>
          </div>
        </div>
      </form>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0" id="tbl-evidence">
          <thead><tr>
            <th>ID</th><th>Path</th><th>Type</th><th>By</th><th>When</th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php if (function_exists('tpl_style')): ?>
  <?php tpl_style('https://staff.vapeshed.co.nz/modules/purchase-orders/assets/css/admin.dashboard.css'); ?>
<?php endif; ?>
<?php if (function_exists('tpl_script')): ?>
  <?php tpl_script('https://staff.vapeshed.co.nz/modules/purchase-orders/assets/js/admin.dashboard.js', ['defer' => true]); ?>
<?php else: ?>
  <script src="https://staff.vapeshed.co.nz/modules/purchase-orders/assets/js/admin.dashboard.js" defer></script>
<?php endif; ?>
