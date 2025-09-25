<?php
// modules/transfers/stock-transfers/blocks/pack_actions.php
$tid = (int)($_GET['transfer'] ?? 0);
$src = tpl_e($transferData->outlet_from->id ?? '');
$dst = tpl_e($transferData->outlet_to->id ?? '');
$staff = (int)($_SESSION['userID'] ?? 0);
?>
<footer class="card-body">
  <div class="d-flex align-items-center" style="gap:8px; flex-wrap: wrap;">
    <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#addProductsModal"><i class="fa fa-plus" aria-hidden="true"></i> Add Products</button>
    <button type="button" class="btn btn-success" data-action="pack-goods"><i class="fa fa-box" aria-hidden="true"></i> Pack Goods</button>
    <button type="button" class="btn btn-primary" data-action="send-transfer"><i class="fa fa-paper-plane" aria-hidden="true"></i> Send Transfer</button>
    <button type="button" class="btn btn-outline-danger d-none" data-action="force-send"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Force Send</button>
  </div>
  <input type="hidden" id="transferID" value="<?php echo $tid; ?>">
  <input type="hidden" id="sourceID" value="<?php echo $src; ?>">
  <input type="hidden" id="destinationID" value="<?php echo $dst; ?>">
  <input type="hidden" id="staffID" value="<?php echo $staff; ?>">
  <input type="hidden" id="postingMethod" value="consignment">
  <input type="hidden" id="sendMode" value="live">
  <input type="hidden" id="tracking-number" name="tracking-number" value="">
</footer>
