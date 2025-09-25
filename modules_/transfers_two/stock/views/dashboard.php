<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
tpl_shared_assets();
// Ensure CSRF token is present for AJAX calls; generate if missing
if (!isset($_SESSION)) { session_start(); }
$csrfToken = '';
if (function_exists('getCSRFToken')) { try { $csrfToken = (string)getCSRFToken(); } catch (Throwable $e) { $csrfToken = ''; } }
if ($csrfToken === '') { $csrfToken = (string)($_SESSION['csrf_token'] ?? ''); }
if ($csrfToken === '') { $csrfToken = bin2hex(random_bytes(16)); $_SESSION['csrf_token'] = $csrfToken; }
?>
<div class="container stx-dash">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <?php $___show_docs = isset($_SESSION['is_admin']) && $_SESSION['is_admin']; ?>
  <?php if ($___show_docs): ?>
    <div class="d-flex justify-content-end mb-1">
      <a class="small text-muted" href="https://staff.vapeshed.co.nz/modules/transfers/stock/docs/KNOWLEDGE_BASE.md" target="_blank" rel="noopener">Docs</a>
    </div>
  <?php endif; ?>
  <?php if (empty($GLOBALS['TPL_RENDERING_IN_CIS_TEMPLATE'])): ?>
    <?php tpl_breadcrumb([
      ['label' => 'Home', 'href' => tpl_base_url()],
      ['label' => 'Transfers Dashboard', 'href' => tpl_asset_url('/modules/transfers/dashboard.php')],
      ['label' => 'Stock'],
    ]); ?>
  <?php endif; ?>

  <div class="animated fadeIn">
    <div id="stx-stats" class="mb-2"></div>

    <div class="row align-items-stretch">
      <div class="col-lg-8 mb-2">
        <div class="card shadow-sm h-100">
          <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <small class="text-muted text-uppercase">Open Transfers</small>
            <a class="btn btn-primary btn-sm btn-xs" href="<?= tpl_asset_url('/modules/transfers/stock/outgoing.php'); ?>">Create Transfer</a>
          </div>
          <div class="card-body py-2 d-flex flex-column">
            <div class="table-responsive stx-table flex-grow-1" style="min-height:180px;">
              <table class="table table-sm table-striped mb-2">
                <thead><tr><th>ID</th><th>Status</th><th>From</th><th>To</th><th>Created</th><th>Updated</th><th class="text-right">Actions</th></tr></thead>
                <tbody id="stx-open-body"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 mb-2">
        <div class="card shadow-sm h-100">
          <div class="card-header py-2 d-flex justify-content-between align-items-center"><small class="text-muted text-uppercase">Latest Activity</small><button id="stx-activity-refresh" class="btn btn-outline-secondary btn-xs">Refresh</button></div>
          <div class="card-body py-2 d-flex flex-column">
            <div id="stx-activity" class="flex-grow-1" style="min-height:180px; overflow:auto;">
              <div class="text-muted">No recent activity.</div>
            </div>
            <div class="d-flex justify-content-center pt-1">
              <button id="stx-activity-more" class="btn btn-outline-secondary btn-xs">Load More</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row align-items-stretch">
      <div class="col-lg-8 mb-2">
        <div class="card shadow-sm h-100">
          <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <small class="text-muted text-uppercase">Freight & Weight Overview</small>
            <div class="btn-group btn-group-sm" role="group">
              <button class="btn btn-outline-secondary" id="fw-refresh">Refresh</button>
            </div>
          </div>
          <div class="card-body py-2">
            <div id="fw-totals" class="mb-2 small text-muted">Loading…</div>
            <div class="table-responsive">
              <table class="table table-sm table-striped">
                <thead><tr><th>Carrier</th><th class="text-right">Transfers</th><th class="text-right">Total kg</th><th class="text-right">Est. Boxes</th><th class="text-right">Est. Cost</th></tr></thead>
                <tbody id="fw-by-carrier"><tr><td colspan="5">Loading…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 mb-2">
        <div class="card shadow-sm h-100">
          <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <small class="text-muted text-uppercase">Heaviest Transfers</small>
            <span class="small text-muted" id="fw-updated"></span>
          </div>
          <div class="card-body py-2">
            <div class="table-responsive" style="max-height:240px; overflow:auto;">
              <table class="table table-sm table-striped mb-0">
                <thead><tr><th>ID</th><th class="text-right">kg</th></tr></thead>
                <tbody id="fw-heaviest"><tr><td colspan="2">Loading…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <small class="text-muted text-uppercase">Find / Search All Transfers</small>
      </div>
      <div class="card-body py-2">
  <div class="stx-filters d-flex flex-wrap align-items-end mb-2 mt-1" style="gap:4px">
          <div class="form-group mr-2">
            <label>Search</label>
            <input id="stx-filter-q" class="form-control" placeholder="#ID or Outlet Name/ID">
          </div>
          <div class="form-group mr-2">
            <label>Status</label>
            <select id="stx-filter-state" class="form-control"></select>
          </div>
          <div class="form-group mr-2 stx-typeahead">
            <label>From <span class="stx-chip"><span class="stx-chip-x" role="button" tabindex="0" data-clear="stx-filter-from" aria-label="Clear From Filter">×</span></span></label>
            <input id="stx-filter-from" class="form-control" placeholder="Outlet Name or ID" autocomplete="off">
            <div id="stx-ta-from" class="stx-typeahead-menu" style="display:none"></div>
          </div>
          <div class="form-group mr-2 stx-typeahead">
            <label>To <span class="stx-chip"><span class="stx-chip-x" role="button" tabindex="0" data-clear="stx-filter-to" aria-label="Clear To Filter">×</span></span></label>
            <input id="stx-filter-to" class="form-control" placeholder="Outlet Name or ID" autocomplete="off">
            <div id="stx-ta-to" class="stx-typeahead-menu" style="display:none"></div>
          </div>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 stx-controls">
          <div class="stx-controls-left d-flex align-items-center">
            <div class="btn-group btn-group-sm mr-2" role="group" aria-label="Bulk Actions">
              <button class="btn btn-outline-secondary" id="stx-bulk-select-all" title="Ctrl+A">Select All</button>
              <button class="btn btn-outline-secondary" id="stx-bulk-select-none" title="Ctrl+D">Select None</button>
              <button class="btn btn-outline-primary" id="stx-open-selected" title="Open selected in Pack (limit 10)">Open Selected</button>
              <button class="btn btn-outline-info" id="stx-open-selected-packonly" title="Open selected in Pack-only (limit 10)">Open Selected (Pack-only)</button>
            </div>
          </div>
          <div class="stx-controls-right d-flex align-items-center">
            <div class="btn-group btn-group-sm mr-2" role="group" aria-label="Set Status">
              <button class="btn btn-outline-primary" data-status="packing">Set Packing</button>
              <button class="btn btn-outline-primary" data-status="ready_to_send">Set Ready</button>
              <button class="btn btn-outline-info" data-status="sent">Set Sent</button>
              <button class="btn btn-outline-info" data-status="in_transit">Set In Transit</button>
              <button class="btn btn-outline-success" data-status="received">Set Received</button>
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="Danger Actions">
              <button class="btn btn-outline-warning" id="stx-bulk-cancel">Cancel</button>
              <button class="btn btn-outline-danger" id="stx-bulk-delete">Delete</button>
            </div>
          </div>
        </div>
        <div class="table-responsive stx-table" id="stx-list-wrap" style="max-height:500px;">
          <table class="table table-sm table-striped">
            <thead><tr><th><input type="checkbox" id="stx-select-all"></th><th>ID</th><th>Status</th><th>From</th><th>To</th><th>Created</th><th>Updated</th><th class="text-right">Actions</th></tr></thead>
            <tbody id="stx-table-body"></tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-1">
          <div class="small text-muted" id="stx-pg-status">Page 1</div>
          <div class="btn-group btn-group-sm" role="group" aria-label="Pagination">
            <button class="btn btn-outline-secondary" id="stx-pg-prev">Prev</button>
            <button class="btn btn-outline-secondary" id="stx-pg-next">Next</button>
          </div>
        </div>
      </div>
    </div>

    

    <?php
      $__v = function(string $rel): string {
        $abs = $_SERVER['DOCUMENT_ROOT'] . $rel;
        try { return is_file($abs) ? (string)filemtime($abs) : (string)time(); } catch (Throwable $e) { return (string)time(); }
      };
      $tokens_v = $__v('/modules/_shared/assets/css/cis-tokens.css');
      $utils_v = $__v('/modules/_shared/assets/css/cis-utilities.css');
  $css_v = $__v('/modules/transfers/stock/assets/css/dashboard.css');
  $stxui_v = $__v('/modules/transfers/stock/assets/css/stx-ui.css');
      $core_v = $__v('/modules/transfers/stock/assets/js/core.js');
      $dash_v = $__v('/modules/transfers/stock/assets/js/dashboard.js');
    ?>
    <?php tpl_style('/modules/_shared/assets/css/cis-tokens.css?v=' . $tokens_v); ?>
    <?php tpl_style('/modules/_shared/assets/css/cis-utilities.css?v=' . $utils_v); ?>
  <?php tpl_style('/modules/transfers/stock/assets/css/dashboard.css?v=' . $css_v); ?>
  <?php tpl_style('/modules/transfers/stock/assets/css/stx-ui.css?v=' . $stxui_v); ?>
    <?php tpl_script('/modules/transfers/stock/assets/js/core.js?v=' . $core_v, ['defer' => true]); ?>
    <?php tpl_script('/modules/transfers/stock/assets/js/dashboard.js?v=' . $dash_v, ['defer' => true]); ?>
    <?php tpl_render_styles(); ?>
    <?php tpl_render_scripts(); ?>
  </div>
</div>
