<?php
declare(strict_types=1);
/**
 * File: modules/transfers/stock/blocks/pack_items_table.php
 * Purpose: Items table for Pack view
 */
?>
<section class="stx-table" aria-labelledby="tbl-h">
  <h3 id="tbl-h">Items</h3>
  <div class="table-responsive">
    <table class="table table-sm table-striped">
      <thead>
        <tr>
          <th>Product</th>
          <th>SKU</th>
          <th class="text-end">Qty</th>
          <th class="text-end">Supply Cost</th>
          <th class="text-end">Line Cost</th>
        </tr>
      </thead>
      <tbody id="items-body">
        <tr><td colspan="5">Loading…</td></tr>
      </tbody>
    </table>
  </div>
</section>
