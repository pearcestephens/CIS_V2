<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
$transferIdParam = (int)($_GET['transfer'] ?? 0);
if ($transferIdParam <= 0) { echo '<div class="alert alert-danger">Missing transfer ID.</div>'; return; }
$csrfToken = function_exists('getCSRFToken') ? (string)getCSRFToken() : ((string)($_SESSION['csrf_token'] ?? ''));
tpl_shared_assets();
?>
<div class="stx-receive" data-module="stock" data-view="receive">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Receive Transfer #<?= (int)$transferIdParam ?></strong>
      <div class="btn-group">
        <a class="btn btn-outline-secondary btn-sm" href="<?= tpl_asset_url('/modules/transfers/stock/dashboard.php'); ?>">Dashboard</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= tpl_asset_url('/modules/transfers/stock/pack.php'); ?>?transfer=<?= (int)$transferIdParam ?>">Pack</a>
      </div>
    </div>
    <div class="card-body">
      <form id="receive-form" onsubmit="return STXReceive.submit(false)">
        <div class="form-group">
          <label for="receive-items">Items (SKU:QTY comma-separated)</label>
          <input id="receive-items" class="form-control" type="text" placeholder="SKU123:1,SKU456:2">
          <small class="form-text text-muted">Use comma to separate. Example: SKU123:1,SKU456:2</small>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-primary mr-2" onclick="STXReceive.submit(false)">Receive Partial</button>
          <button type="button" class="btn btn-success" onclick="STXReceive.submit(true)">Receive Final</button>
        </div>
      </form>
    </div>
  </div>

  <?php tpl_style('/modules/transfers/stock/assets/css/stock.css'); ?>
  <?php tpl_script('/modules/transfers/stock/assets/js/core.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock/assets/js/receive.js', ['defer' => true]); ?>
  <?php tpl_render_styles(); ?>
  <?php tpl_render_scripts(); ?>
</div>
<?php $tid = (int)($_GET['transfer'] ?? 0); tpl_breadcrumb([
  ['label' => 'Home', 'href' => tpl_base_url()],
  ['label' => 'Admin'],
  ['label' => 'Receive Transfer #'.$tid],
]); ?>
