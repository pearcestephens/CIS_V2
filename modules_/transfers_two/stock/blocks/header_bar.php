<?php
$tid = (int)($_GET['transfer'] ?? 0);
$fromName = tpl_e($transferData->outlet_from->name ?? '');
$toName   = tpl_e($transferData->outlet_to->name ?? '');
?>
<header class="stx-header d-flex justify-content-between align-items-center py-2">
  <div>
    <h1 class="h5 mb-0 stx-header__title" style="line-height:1.2;">
      Stock Transfer #<?php echo $tid; ?>
      <span class="text-nowrap"><?php echo $fromName; ?></span>
      <span aria-hidden="true"> → </span>
      <span class="text-nowrap"><?php echo $toName; ?></span>
    </h1>
    <p class="text-muted mb-0" style="font-size:12px;">These products need to be gathered and prepared for delivery</p>
  </div>
  <div class="btn-group" role="group" aria-label="Transfer options">
    <button class="btn btn-outline-primary btn-sm dropdown-toggle d-flex align-items-center" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <i class="fa fa-cog mr-2" aria-hidden="true"></i> Options
    </button>
    <div class="dropdown-menu dropdown-menu-right shadow border-0">
      <?php if (!empty($mergeTransfers)): ?>
        <button class="dropdown-item" type="button" data-toggle="modal" data-target="#mergeTransferModal"><i class="fa fa-code-fork mr-2" aria-hidden="true"></i> Merge Transfer</button>
      <?php else: ?>
        <button class="dropdown-item" type="button" disabled><i class="fa fa-code-fork mr-2 text-muted" aria-hidden="true"></i> Merge Transfer (None Available)</button>
      <?php endif; ?>
      <button class="dropdown-item" type="button" data-toggle="modal" data-target="#addProductsModal"><i class="fa fa-plus mr-2" aria-hidden="true"></i> Add Products</button>
      <button id="editModeButton" class="dropdown-item" type="button" data-action="edit-mode"><i class="fa fa-edit mr-2" aria-hidden="true"></i> Edit Transfer</button>
      <div class="dropdown-divider" role="separator"></div>
      <button class="dropdown-item text-danger" type="button" data-action="delete-transfer" data-transfer-id="<?php echo $tid; ?>"><i class="fa fa-trash mr-2" aria-hidden="true"></i> Delete Transfer</button>
    </div>
  </div>
</header>
<div class="modal fade" id="mergeTransferModal" tabindex="-1" role="dialog" aria-labelledby="mergeTransferModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" aria-modal="true" style="padding:5px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mergeTransferModalLabel">Merge Transfer</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Merging a Transfer will delete both transfers and create a new transfer with a new Transfer ID.</p>
        <p>Please note: you can only merge transfers to and from the same outlet.</p>
        <select id="transferMergeOptions" class="form-control" aria-label="Select a transfer to merge">
          <?php if (!empty($mergeTransfers)) { foreach ($mergeTransfers as $m) { echo '<option value="'.(int)$m['transfer_id'].'">Transfer To '.tpl_e($m['destinationOutlet']->name).' #'.(int)$m['transfer_id'].'</option>'; } } ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <form action="" method="POST" id="mergeTransferForm" name="mergeTransferForm">
          <button type="submit" class="btn btn-primary">Merge Transfer</button>
          <input type="hidden" id="currentTransferIDHidden" name="currentTransferIDHidden" value="<?php echo $tid; ?>">
          <input type="hidden" id="TransferToMergeIDHidden" name="TransferToMergeIDHidden">
          <input type="hidden" id="outletFromIDHidden" name="outletFromIDHidden" value="<?php echo tpl_e($transferData->outlet_from->id ?? ''); ?>">
          <input type="hidden" id="outletToIDHidden" name="outletToIDHidden" value="<?php echo tpl_e($transferData->outlet_to->id ?? ''); ?>">
        </form>
      </div>
    </div>
  </div>
</div>
