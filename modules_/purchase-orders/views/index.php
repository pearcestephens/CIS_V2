<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
tpl_shared_assets();
?>
<div class="container-fluid">
  <div class="card shadow-sm">
    <div class="card-body">
      <p class="text-muted mb-3">Start a receiving session or open the admin dashboard.</p>
      <div class="list-group">
  <a class="list-group-item list-group-item-action" href="https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=receive">Receive Purchase Order</a>
  <a class="list-group-item list-group-item-action" href="https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin">Admin Dashboard</a>
      </div>
    </div>
  </div>
  <div class="mt-3">
    <a class="btn btn-sm btn-outline-secondary" href="https://staff.vapeshed.co.nz/modules/_shared/diagnostics.php">Diagnostics</a>
  </div>
</div>
