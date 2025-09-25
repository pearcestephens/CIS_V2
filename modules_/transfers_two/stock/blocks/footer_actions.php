<?php
$tid = (int)($_GET['transfer'] ?? 0);
?>
<footer class="card-body">
  <div class="d-flex align-items-center" style="gap:8px; flex-wrap: wrap;">
    <a class="btn btn-outline-secondary" href="<?= tpl_asset_url('/modules/transfers/stock/dashboard.php'); ?>">Back to Dashboard</a>
    <a class="btn btn-outline-primary" href="<?= tpl_asset_url('/modules/transfers/stock/pack.php'); ?>?transfer=<?= (int)$tid; ?>">Go to Pack</a>
  </div>
</footer>
