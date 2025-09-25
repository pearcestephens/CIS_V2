<?php
$tid = (int)($_GET['transfer'] ?? 0);
?>
<section class="card mb-3">
  <div class="card-body py-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
      <div class="small text-muted">Packing workflow tools</div>
      <div class="d-flex align-items-center" style="gap:8px;">
  <a class="btn btn-outline-primary btn-sm" href="<?= tpl_asset_url('/modules/transfers/stock/dashboard.php'); ?>" title="Go to Transfers Dashboard">Back to Transfers</a>
  <button class="btn btn-outline-secondary btn-sm" data-action="mark-ready" title="Set this transfer status to Ready for dispatch">Mark as Ready</button>
  <a class="btn btn-primary btn-sm" href="<?= tpl_asset_url('/modules/module.php'); ?>?module=transfers/stock&view=pack_v3&transfer=<?= (int)$tid; ?>" title="Open the new Pack V3 view">Open Pack View</a>
      </div>
    </div>
  </div>
  <div class="card-footer py-1"><small class="text-muted">Tip: "Open Pack View" loads the new packing interface. "Back to Transfers" opens the dashboard. "Mark as Ready" updates status only.</small></div>
</section>
