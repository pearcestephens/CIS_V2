<?php
// modules/transfers/stock-transfers/views/dashboard.php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
tpl_shared_assets();
?>
<div class="container-fluid">
  <?php if (empty($GLOBALS['TPL_RENDERING_IN_CIS_TEMPLATE'])): ?>
    <?php tpl_breadcrumb([
      ['label' => 'Home', 'href' => tpl_base_url()],
      ['label' => 'Transfers Dashboard', 'href' => tpl_asset_url('/modules/transfers/dashboard.php')],
      ['label' => 'Stock Transfers'],
    ]); ?>
  <?php endif; ?>

  <div class="animated fadeIn">
    <div class="row">
      <div class="col-lg-8 mb-3">
        <div class="card shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Stock Transfers</strong>
            <div class="btn-group">
              <a class="btn btn-primary btn-sm" href="<?= tpl_asset_url('/modules/transfers/stock-transfers/outgoing.php'); ?>">New Outgoing</a>
              <a class="btn btn-outline-primary btn-sm" href="<?= tpl_asset_url('/modules/transfers/stock-transfers/pack.php'); ?>?transfer=">Open Pack</a>
            </div>
          </div>
          <div class="card-body">
            <p class="text-muted mb-2">Control panel for stock transfer operations. Use quick links or pick a transfer to continue.</p>
            <form class="form-inline" action="<?= tpl_asset_url('/modules/transfers/stock-transfers/outgoing.php'); ?>" method="GET" onsubmit="return this.transfer.value!='';">
              <div class="input-group mr-2 mb-2">
                <div class="input-group-prepend"><span class="input-group-text">#</span></div>
                <input type="number" class="form-control" name="transfer" placeholder="Enter Transfer ID" min="1" required>
              </div>
              <button type="submit" class="btn btn-outline-secondary mb-2">Open Outgoing</button>
            </form>
            <form class="form-inline" action="<?= tpl_asset_url('/modules/transfers/stock-transfers/pack.php'); ?>" method="GET" onsubmit="return this.transfer.value!='';">
              <div class="input-group mr-2 mb-2">
                <div class="input-group-prepend"><span class="input-group-text">#</span></div>
                <input type="number" class="form-control" name="transfer" placeholder="Enter Transfer ID" min="1" required>
              </div>
              <button type="submit" class="btn btn-outline-secondary mb-2">Open Pack</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-4 mb-3">
        <div class="card shadow-sm h-100">
          <div class="card-header"><strong>Shortcuts</strong></div>
          <div class="list-group list-group-flush">
            <a class="list-group-item list-group-item-action" href="<?= tpl_asset_url('/modules/transfers/stock-transfers/outgoing.php'); ?>">Create New Outgoing Transfer</a>
            <a class="list-group-item list-group-item-action" href="<?= tpl_asset_url('/modules/transfers/stock-transfers/dashboard.php'); ?>">Stock Transfers Dashboard</a>
            <a class="list-group-item list-group-item-action" href="<?= tpl_asset_url('/modules/transfers/dashboard.php'); ?>">All Transfers Dashboard</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
