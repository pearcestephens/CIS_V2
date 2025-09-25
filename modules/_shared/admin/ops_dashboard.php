<?php
declare(strict_types=1);

/** role gate */
$role = $_SESSION['role'] ?? ($_SESSION['userRole'] ?? '');
$allowed = in_array($role, ['admin','owner','director'], true);
if (!$allowed) {
  echo '<div class="alert alert-danger">Access denied.</div>';
  return;
}
$meta = include __DIR__ . '/ops_dashboard.meta.php';
?>

<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
    <div class="d-flex align-items-center gap-2">
      <h4 class="mb-0">Observability</h4>
      <span class="badge bg-secondary" id="lastUpdated">-</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <select id="sinceSelect" class="form-select form-select-sm" style="width:auto;">
        <option value="15 MINUTE" selected>Last 15m</option>
        <option value="60 MINUTE">Last 60m</option>
        <option value="24 HOUR">Last 24h</option>
      </select>
      <button class="btn btn-sm btn-outline-primary" id="btnRefresh">Refresh</button>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Total Errors</div>
          <div class="h3 mb-0" id="kpiErrors">-</div>
          <div class="small text-muted" id="kpiErrorsHint">-</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Slowest Endpoint (avg PHP ms)</div>
          <div class="h6 mb-0" id="kpiSlowEndpoint">-</div>
          <div class="small text-muted" id="kpiSlowMs">-</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Avg SQL / Request</div>
          <div class="h3 mb-0" id="kpiSqlAvg">-</div>
          <div class="small text-muted">queries</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Profiles</div>
          <div class="h3 mb-0" id="kpiProfiles">-</div>
          <div class="small text-muted">requests sampled</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts -->
  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header"><strong>Error Mix (by action)</strong></div>
        <div class="card-body"><canvas id="chartErrors" height="140"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header"><strong>Top Slow Endpoints (avg PHP ms)</strong></div>
        <div class="card-body"><canvas id="chartPerf" height="140"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Tables -->
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Recent Error Aggregates</strong>
          <small class="text-muted" id="errSinceHint">-</small>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead><tr>
                <th>Action</th><th>Status</th><th>Count</th><th>First</th><th>Last</th>
              </tr></thead>
              <tbody id="tblErrors"><tr><td colspan="5" class="text-muted p-3">Loading...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Top Endpoints</strong>
          <small class="text-muted" id="perfSinceHint">-</small>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead><tr>
                <th>Endpoint</th><th>Req</th><th>PHP ms (avg)</th><th>SQL ms (avg)</th><th>SQL q (avg)</th><th>Last</th>
              </tr></thead>
              <tbody id="tblPerf"><tr><td colspan="6" class="text-muted p-3">Loading...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
