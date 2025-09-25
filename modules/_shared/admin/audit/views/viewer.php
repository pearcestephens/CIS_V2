<?php
/**
 * https://staff.vapeshed.co.nz/modules/module.php?module=_shared/admin/audit&view=viewer
 * Admin-only audit viewer for transfer_audit_log
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
if (!isset($_SESSION)) session_start();

// Allow in non-production even without roles; in production require admin/owner/director or internal token
$role = $_SESSION['role'] ?? ($_SESSION['userRole'] ?? '');
$env = '';
if (defined('APP_ENV')) { $env = strtolower((string)APP_ENV); }
elseif (defined('ENV')) { $env = strtolower((string)ENV); }
elseif (!empty($_ENV['APP_ENV'])) { $env = strtolower((string)$_ENV['APP_ENV']); }
$isNonProd = !in_array($env, ['prod','production','live'], true);

$expectedToken = (string)($_ENV['INTERNAL_API_TOKEN'] ?? getenv('INTERNAL_API_TOKEN') ?: '');
$headerToken = (string)($_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '');
$internalOK = $expectedToken !== '' && $headerToken !== '' && hash_equals($expectedToken, $headerToken);

$roleOK = in_array($role, ['admin','owner','director'], true);
if (!($isNonProd || $roleOK || $internalOK)) {
  echo '<div class="alert alert-danger">Access denied</div>';
  return;
}
?>
<div class="audit-viewer">
  <div class="card mb-3">
    <div class="card-body">
      <div class="form-row">
        <div class="form-group col-md-2">
          <label>From</label>
          <input type="date" class="form-control" id="av-from">
        </div>
        <div class="form-group col-md-2">
          <label>To</label>
          <input type="date" class="form-control" id="av-to">
        </div>
        <div class="form-group col-md-2">
          <label>Entity</label>
          <input type="text" class="form-control" id="av-entity" placeholder="transfer|purchase_order.page">
        </div>
        <div class="form-group col-md-2">
          <label>Action</label>
          <input type="text" class="form-control" id="av-action" placeholder="set_status|page_view">
        </div>
        <div class="form-group col-md-2">
          <label>Status</label>
          <select class="form-control" id="av-status">
            <option value="">Any</option>
            <option>success</option>
            <option>error</option>
            <option>info</option>
          </select>
        </div>
        <div class="form-group col-md-2">
          <label>Actor ID</label>
          <input type="text" class="form-control" id="av-actor">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-md-2">
          <label>Transfer ID</label>
          <input type="text" class="form-control" id="av-transfer">
        </div>
        <div class="form-group col-md-3">
          <label>Trace/Session/IP</label>
          <input type="text" class="form-control" id="av-q" placeholder="trace_id, session, ip">
        </div>
        <div class="form-group col-md-2 align-self-end">
          <button class="btn btn-primary btn-block" id="av-search">Search</button>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive" style="max-height: 500px; overflow:auto;">
        <table class="table table-sm table-striped mb-0">
          <thead class="thead-light">
            <tr>
              <th>Time</th>
              <th>Entity</th>
              <th>Action</th>
              <th>Status</th>
              <th>Actor</th>
              <th>Transfer</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody id="av-tbody"></tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between mt-2">
        <div id="av-pg-status" class="text-muted small">0–0 of 0</div>
        <div>
          <button class="btn btn-outline-secondary btn-sm" id="av-prev">Prev</button>
          <button class="btn btn-outline-secondary btn-sm" id="av-next">Next</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Detail Modal -->
<div class="modal" tabindex="-1" role="dialog" id="av-modal">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Audit Detail</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <pre id="av-json" class="bg-light p-2" style="max-height:400px;overflow:auto;"></pre>
        <hr>
        <div class="row">
          <div class="col">
            <h6>Before</h6>
            <pre id="av-before" class="bg-light p-2" style="max-height:300px;overflow:auto;"></pre>
          </div>
          <div class="col">
            <h6>After</h6>
            <pre id="av-after" class="bg-light p-2" style="max-height:300px;overflow:auto;"></pre>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
