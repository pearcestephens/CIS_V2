<?php
$tid = (int)($_GET['transfer'] ?? 0);
$src = tpl_e($transferData->outlet_from->id ?? '');
$dst = tpl_e($transferData->outlet_to->id ?? '');
$staff = (int)($_SESSION['userID'] ?? 0);
?>
<section class="card mb-3">
  <div class="card-header py-2"><small class="text-muted text-uppercase">Finalize</small></div>
  <div class="card-body py-2">
    <div class="d-flex align-items-center" style="gap:8px; flex-wrap: wrap;">
      <button type="button" class="btn btn-success btn-sm" data-action="pack-goods"><i class="fa fa-box" aria-hidden="true"></i> Pack Goods</button>
      <button type="button" class="btn btn-primary btn-sm" data-action="send-transfer"><i class="fa fa-paper-plane" aria-hidden="true"></i> Send Transfer</button>
      <button type="button" class="btn btn-outline-danger btn-sm d-none" data-action="force-send"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Force Send</button>
    </div>
  </div>
  
  <input type="hidden" id="transferID" value="<?php echo $tid; ?>">
  <input type="hidden" id="sourceID" value="<?php echo $src; ?>">
  <input type="hidden" id="destinationID" value="<?php echo $dst; ?>">
  <input type="hidden" id="staffID" value="<?php echo $staff; ?>">
  <input type="hidden" id="postingMethod" value="consignment">
  <input type="hidden" id="sendMode" value="live">
  <input type="hidden" id="tracking-number" name="tracking-number" value="">
</section>
