<?php
declare(strict_types=1);
/**
 * items_table_v4.php — Slim packing table block
 * Inputs: ['transferData' => object{id:int, products:array[{product_id, product_name, qty_to_transfer, inventory_level}]}]
 */
$td = $transferData ?? (object)['id'=>0,'products'=>[]];
$rows = is_array($td->products ?? []) ? $td->products : [];
?>
<div class="stxv4-items card">
  <div class="card-header py-2 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center" style="gap:8px">
      <strong>Items</strong>
      <small class="text-muted">Compact, keyboard-friendly</small>
    </div>
    <div class="d-flex align-items-center" style="gap:8px">
      <input type="text" id="stxv4-scan" class="form-control form-control-sm" placeholder="Scan or type product…" autocomplete="off" aria-label="Scan or type product">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="stxv4-add">Add</button>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive" style="max-height:65vh">
      <table class="table table-sm table-striped mb-0" id="stxv4-items-table">
        <thead>
          <tr>
            <th style="width:48px">#</th>
            <th>Product</th>
            <th class="text-right" style="width:120px">Planned</th>
            <th class="text-right" style="width:140px">Packed</th>
            <th class="text-right" style="width:120px">Remain</th>
            <th class="text-right" style="width:140px">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php $i=0; foreach ($rows as $r): $i++; $pid = (string)($r->product_id ?? ''); $name = (string)($r->product_name ?? $pid); $plan = (int)($r->qty_to_transfer ?? 0); ?>
          <tr data-pid="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>">
            <td class="text-muted"><?= $i ?></td>
            <td><div class="d-flex align-items-center" style="gap:6px"><span class="stxv4-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span><small class="text-muted" title="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?></small></div></td>
            <td class="text-right stxv4-planned" data-val="<?= $plan ?>"><?= number_format($plan) ?></td>
            <td class="text-right">
              <div class="input-group input-group-sm justify-content-end" style="gap:4px">
                <input type="number" class="form-control form-control-sm stxv4-packed" value="0" min="0" inputmode="numeric" pattern="[0-9]*" style="max-width:90px">
                <div class="btn-group btn-group-sm" role="group">
                  <button class="btn btn-outline-secondary stxv4-dec" aria-label="Decrement">−</button>
                  <button class="btn btn-outline-secondary stxv4-inc" aria-label="Increment">＋</button>
                </div>
              </div>
            </td>
            <td class="text-right stxv4-remain"><?= number_format(max(0,$plan)) ?></td>
            <td class="text-right">
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-primary stxv4-packrow">Pack</button>
                <button class="btn btn-outline-danger stxv4-remove">Remove</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="text-right">Totals</th>
            <th class="text-right" id="stxv4-total-plan">0</th>
            <th class="text-right" id="stxv4-total-pack">0</th>
            <th class="text-right" id="stxv4-total-remain">0</th>
            <th class="text-right">
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-success" id="stxv4-pack-all">Pack All</button>
                <button class="btn btn-outline-dark" id="stxv4-save">Save</button>
              </div>
            </th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
