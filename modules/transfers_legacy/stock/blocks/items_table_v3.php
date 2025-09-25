<?php
/**
 * items_table_v3.php — modern, productive items table
 */
declare(strict_types=1);
$transferData = $transferData ?? (object)['products'=>[]];
?>
<div class="card stx-items-v3">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div>
      <strong>Items</strong>
      <small class="text-muted ml-2">Source stock shown is from origin outlet</small>
    </div>
    <div class="d-flex" style="gap:8px;">
      <div class="input-group input-group-sm" style="width:260px;">
        <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search"></i></span></div>
        <input id="stxv3-filter" type="search" class="form-control" placeholder="Filter products…" aria-label="Filter products">
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="stxv3-fill"><i class="fa fa-equals mr-1"></i>Counted = Planned</button>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive stxv3-table-wrap">
      <table class="table table-sm table-striped mb-0" id="stxv3-table">
        <thead class="thead-light">
          <tr>
            <th style="width:32px"></th>
            <th>Name</th>
            <th class="text-right" style="width:110px">In Stock</th>
            <th class="text-right" style="width:110px">Planned</th>
            <th style="width:140px">Counted</th>
            <th style="width:150px">Tags</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($transferData->products) && is_iterable($transferData->products)):
            foreach ($transferData->products as $p):
              $pid = htmlspecialchars((string)($p->product_id ?? ''));
              $pname = htmlspecialchars((string)($p->product_name ?? ''));
              $inv = (int)($p->inventory_level ?? 0);
              $planned = (int)($p->qty_to_transfer ?? 0);
              if ($planned <= 0) continue; ?>
              <tr data-pid="<?= $pid ?>" data-name="<?= $pname ?>" data-inv="<?= $inv ?>" data-planned="<?= $planned ?>">
                <td class="text-center align-middle"><button type="button" class="btn btn-link text-danger p-0 stxv3-remove" aria-label="Remove"><i class="fa fa-trash"></i></button></td>
                <td class="align-middle"><div class="d-flex align-items-center" style="gap:8px;"><div class="stxv3-dot"></div><div><?= $pname ?></div></div></td>
                <td class="text-right align-middle stxv3-inv"><?= $inv ?></td>
                <td class="text-right align-middle stxv3-planned"><?= $planned ?></td>
                <td class="align-middle"><input type="number" class="form-control form-control-sm stxv3-counted" min="0" max="<?= $inv ?>" inputmode="numeric" pattern="[0-9]*" value=""></td>
                <td class="align-middle"><span class="stxv3-tags text-muted">-</span></td>
                <input type="hidden" class="productID" value="<?= $pid ?>">
              </tr>
            <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="p-2 d-flex align-items-center justify-content-between small text-muted">
      <div>Items: <strong id="stxv3-items">0</strong> • Planned: <strong id="stxv3-plan">0</strong> • Counted: <strong id="stxv3-count">0</strong> • Diff: <strong id="stxv3-diff">0</strong></div>
      <div class="text-right">Tip: Use Arrow keys to navigate, Enter to move down</div>
    </div>
  </div>
</div>
