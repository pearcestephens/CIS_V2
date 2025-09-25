<?php
// modules/transfers/stock-transfers/blocks/packonly_banner.php
$tid = (int)($_GET['transfer'] ?? 0);
$fromName = tpl_e($transferData->outlet_from->name ?? '');
?>
<aside class="packonly-banner" role="alert" aria-live="assertive" aria-atomic="true">
  <div class="packonly-stripe" aria-hidden="true"></div>
  <div class="packonly-panel">
    <div class="w-icon" aria-hidden="true">⛔</div>
    <div class="w-text">
      <div class="w-title">DO NOT SEND OR DO ANYTHING WITH THIS TRANSFER UNTIL CONFIRMED.</div>
      <div class="w-body">Every box <b>MUST</b> be clearly labelled with:
        <span class="w-pill">TRANSFER #<?php echo $tid; ?></span>
        <span class="w-pill">FROM: <?php echo $fromName; ?></span>
        <span class="w-pill">BOX 1 OF X</span>
      </div>
    </div>
  </div>
  <div class="packonly-stripe" aria-hidden="true"></div>
</aside>
