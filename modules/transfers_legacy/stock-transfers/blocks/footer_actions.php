<?php
// modules/transfers/stock-transfers/blocks/footer_actions.php
$fullname = tpl_e((($userDetails['first_name'] ?? '') . ' ' . ($userDetails['last_name'] ?? '')));
$tid = (int)($_GET['transfer'] ?? 0);
$src = tpl_e($transferData->outlet_from->id ?? '');
$dst = tpl_e($transferData->outlet_to->id ?? '');
$staff = (int)($_SESSION['userID'] ?? 0);
?>
<footer class="card-body">
  <p class="mb-2 font-weight-bold small">Counted &amp; Handled By: <?php echo $fullname; ?></p>
  <div class="mb-3">
    <?php // Place the NZ Post printer controls at the very bottom before submit (always visible) ?>
    <?php tpl_block('printer'); ?>
  </div>
  <?php if (empty($PACKONLY)): ?>
    <p class="mb-3 small text-muted">By setting this transfer "Ready For Delivery" you declare that you have individually counted all the products despatched in this transfer and verified inventory levels.</p>
    <button type="button" id="createTransferButton" class="btn btn-primary" data-action="mark-ready">Set Transfer Ready For Delivery</button>
  <?php endif; ?>
  <input type="hidden" id="transferID" value="<?php echo $tid; ?>">
  <input type="hidden" id="sourceID" value="<?php echo $src; ?>">
  <input type="hidden" id="destinationID" value="<?php echo $dst; ?>">
  <input type="hidden" id="staffID" value="<?php echo $staff; ?>">
  <input type="hidden" id="postingMethod" value="consignment">
  <input type="hidden" id="sendMode" value="live">
  <input type="hidden" id="tracking-number" name="tracking-number" value="">
</footer>
