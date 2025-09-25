<?php
// modules/transfers/stock-transfers/blocks/items_table.php
$fromName = tpl_e($transferData->outlet_from->name ?? '');
$toName   = tpl_e($transferData->outlet_to->name ?? '');
$tidForCounter = (int)($_GET['transfer'] ?? 0);
?>
<section class="card w-100 mb-3" aria-labelledby="items-summary-title" id="table-card">
  <div class="card-body py-2">
    <div id="items-summary-title" class="sr-only">Transfer Items Summary</div>
    <div class="d-flex align-items-center flex-wrap mb-2" style="gap:16px;">
      <span>Items: <strong id="itemsToTransfer"><?php echo isset($transferData->products) ? (int)count($transferData->products) : 0; ?></strong></span>
      <span>Planned total: <strong id="plannedTotal">0</strong></span>
      <span>Counted total: <strong id="countedTotal">0</strong></span>
      <span>Diff: <strong id="diffTotal">0</strong></span>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-action="fill-planned">Fill counted = planned</button>
    </div>

    <div class="table-responsive">
      <table class="table table-responsive-sm table-bordered table-striped table-sm" id="transfer-table" aria-describedby="items-summary-title">
        <thead class="thead-light">
          <tr>
            <th scope="col" style="width:40px;"></th>
            <th scope="col">Name</th>
            <th scope="col">Qty In Stock</th>
            <th scope="col">Planned Qty</th>
            <th scope="col">Counted Qty</th>
            <th scope="col">Source</th>
            <th scope="col">Destination</th>
            <th scope="col">ID</th>
          </tr>
        </thead>
        <tbody id="productSearchBody">
          <?php if (!empty($transferData->products) && is_iterable($transferData->products)) {
            $i = 0; foreach ($transferData->products as $p) { $i++; $inv=(int)($p->inventory_level ?? 0); $planned=(int)($p->qty_to_transfer ?? 0); if ($planned <= 0) continue; $pid=tpl_e($p->product_id ?? ''); $pname=tpl_e($p->product_name ?? ''); ?>
            <tr data-inventory="<?php echo $inv; ?>" data-planned="<?php echo $planned; ?>">
              <td class='text-center align-middle'>
                <button type='button' class='btn btn-link p-0' aria-label='Remove product' data-action="remove-product">
                  <i class='fa fa-times text-danger' aria-hidden='true'></i><span class='sr-only'> Remove</span>
                </button>
                <input type='hidden' class='productID' value='<?php echo $pid; ?>'>
              </td>
              <td><?php echo $pname; ?><?php echo (isset($p->staff_added_product) && (int)$p->staff_added_product>0) ? ' <span class="badge badge-warning">Manually Ordered By Staff</span>' : ''; ?></td>
              <td class='inv'><?php echo $inv; ?></td>
              <td class='planned'><?php echo $planned; ?></td>
              <td class='counted-td'>
                <label class='sr-only' for='counted-<?php echo $pid; ?>'>Counted Qty</label>
                <input id='counted-<?php echo $pid; ?>' type='number' min='0' max='<?php echo $inv; ?>' value='' style='width:6em;' aria-describedby='help-<?php echo $pid; ?>' data-behavior="counted-input">
                <span class='counted-print-value d-none'>0</span>
              </td>
              <td><?php echo $fromName; ?></td>
              <td><?php echo $toName; ?></td>
              <td><span class='id-counter'><?php echo $tidForCounter.'-'.$i; ?></span></td>
            </tr>
          <?php } } ?>
        </tbody>
      </table>
    </div>
  </div>
  </section>
