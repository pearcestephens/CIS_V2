<?php
$fromName = tpl_e($transferData->outlet_from->name ?? '');
$toName   = tpl_e($transferData->outlet_to->name ?? '');
$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$tidForCounter = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $tidForCounter = (int)$_GET[$__k]; break; } }
?>
<section class="card w-100 mb-3" aria-labelledby="items-summary-title" id="table-card">
  <div class="card-body py-2">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div class="d-flex align-items-center flex-wrap summary-bar" style="gap:12px;">
        <span>Items: <strong id="itemsToTransfer"><?php echo isset($transferData->products) ? (int)count($transferData->products) : 0; ?></strong></span>
        <span>Remaining total: <strong id="plannedTotal">0</strong></span>
        <span>Counted total: <strong id="countedTotal">0</strong></span>
        <span>Diff: <strong id="diffTotal">0</strong></span>
        <button type="button" class="btn btn-outline-secondary btn-xs" data-action="fill-all-planned" title="Set counted equal to planned for all rows">Fill counted = planned</button>
      </div>
      <div>
        <button type="button" class="btn btn-sm btn-primary" id="btn-add-products" data-toggle="modal" data-target="#stx-add-products"><i class="fa fa-plus mr-1"></i> Add Products</button>
      </div>
    </div>
    <div id="items-summary-title" class="sr-only">Transfer Items Summary</div>
    <div class="table-responsive stx-table">
      <table class="table table-striped table-sm" id="transfer-table" aria-describedby="items-summary-title">
        <thead class="thead-light">
          <tr>
            <th scope="col" style="width:40px;"></th>
            <th scope="col">Product</th>
            <th scope="col">In Stock</th>
            <th scope="col">Remaining</th>
            <th scope="col">Counted</th>
            <th scope="col">Source</th>
            <th scope="col">Destination</th>
            <th scope="col">Line ID</th>
          </tr>
        </thead>
        <tbody id="productSearchBody">
          <?php if (!empty($transferData->products) && is_iterable($transferData->products)) { $i = 0; foreach ($transferData->products as $p) { $i++; $inv=(int)($p->inventory_level ?? 0); $planned=(int)($p->qty_to_transfer ?? 0); $pid=tpl_e($p->product_id ?? ''); $pname=tpl_e($p->product_name ?? ''); ?>
            <tr data-inventory="<?php echo $inv; ?>" data-planned="<?php echo $planned; ?>">
              <td class='text-center align-middle'>
                <button type='button' class='btn btn-link p-0' aria-label='Remove product' title='Remove this product from the pack (does not delete the transfer line in DB)' data-action="remove-product">
                  <i class='fa fa-times text-danger' aria-hidden='true'></i><span class='sr-only'> Remove</span>
                </button>
                <input type='hidden' class='productID' value='<?php echo $pid; ?>'>
              </td>
              <td><?php echo $pname; ?><?php echo (isset($p->staff_added_product) && (int)$p->staff_added_product>0) ? ' <span class="badge badge-warning">Manually Ordered By Staff</span>' : ''; ?></td>
              <td class='inv'><?php echo $inv; ?></td>
              <td class='planned'><?php echo $planned; ?></td>
              <td class='counted-td'>
                <label class='sr-only' for='counted-<?php echo $pid; ?>'>Counted Qty</label>
                <input id='counted-<?php echo $pid; ?>' class="form-control form-control-sm d-inline-block" type='number' min='0' max='<?php echo $inv; ?>' value='' style='width:6em;' aria-describedby='help-<?php echo $pid; ?>' data-behavior="counted-input">
                <span class='counted-print-value d-none'>0</span>
              </td>
              <td><?php echo $fromName; ?></td>
              <td><?php echo $toName; ?></td>
              <td><span class='id-counter'><?php echo $tidForCounter.'-'.$i; ?></span></td>
            </tr>
          <?php } } else { ?>
            <tr class="text-muted"><td colspan="8" class="text-center py-4">
              No items found for this transfer. If this is unexpected, try:
              <ul class="mb-0 mt-2" style="list-style: disc; text-align:left; display:inline-block;">
                <li>Check that the correct Transfer ID is in the URL (?transfer=123) — accepted keys: transfer, transfer_id, id.</li>
                <li>Verify that lines exist in transfer_items or stock_transfer_lines for this Transfer ID.</li>
                <li>Use “Add Products” to insert items if this is a new draft.</li>
              </ul>
            </td></tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
