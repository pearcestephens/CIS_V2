<?php
// modules/transfers/views/dashboard.php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
tpl_shared_assets();

$base = tpl_base_url();
?>
<div class="container-fluid">
  <div class="animated fadeIn">
    <div class="row">
      <div class="col-md-6 col-lg-3 mb-3">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Stock Transfers</h5>
            <p class="card-text text-muted small">Create, pack, ship, and receive store-to-store stock transfers.</p>
            <div class="mt-auto d-flex flex-wrap" style="gap:8px;">
              <a class="btn btn-primary" href="<?= tpl_asset_url('/modules/transfers/stock/dashboard.php'); ?>">Open Dashboard</a>
              <a class="btn btn-outline-primary" href="<?= tpl_asset_url('/modules/transfers/stock/outgoing.php'); ?>">New Outgoing</a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3 mb-3">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Juice Transfer</h5>
            <p class="card-text text-muted small">Manage inter-facility juice transfers.</p>
            <div class="mt-auto d-flex flex-wrap" style="gap:8px;">
              <a class="btn btn-primary" href="<?= tpl_asset_url('/modules/juice-transfer/juice_transfer_dashboard.php'); ?>">Open Dashboard</a>
              <a class="btn btn-outline-primary" href="<?= tpl_asset_url('/modules/juice-transfer/juice_transfer_create.php'); ?>">Create Transfer</a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3 mb-3">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">In-Store Transfers</h5>
            <p class="card-text text-muted small">Quick intra-store movements (bins, rooms, counters).</p>
            <div class="mt-auto d-flex flex-wrap" style="gap:8px;">
              <a class="btn btn-primary" href="<?= tpl_asset_url('/modules/stock-transfer/dashboard.php'); ?>">Open Dashboard</a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3 mb-3">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Purchase Orders</h5>
            <p class="card-text text-muted small">Create and track supplier purchase orders.</p>
            <div class="mt-auto d-flex flex-wrap" style="gap:8px;">
              <a class="btn btn-primary" href="https://staff.vapeshed.co.nz/purchase-orders/">Open Dashboard</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Optionally include quick tools modals if available under this module.
// Keep these as separate partials so they don't interfere with the template breadcrumb.
if (function_exists('tpl_block')) {
  if (tpl_block_exists(__FILE__, '../blocks/quick_float_count_modal.php')) {
    tpl_block(__FILE__, '../blocks/quick_float_count_modal.php');
  }
  if (tpl_block_exists(__FILE__, '../blocks/quick_qty_change_modal.php')) {
    tpl_block(__FILE__, '../blocks/quick_qty_change_modal.php');
  }
}
?>
