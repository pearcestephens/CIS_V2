<?php
declare(strict_types=1);
/**
 * File: modules/transfers/stock/blocks/pack_delivery.php
 * Purpose: Delivery method selection and shipping summary block
 */
?>
<section class="stx-delivery" aria-labelledby="deliv-h">
  <h3 id="deliv-h">Delivery Method</h3>
  <div class="stx-chips mb-2">
    <span class="chip chip--nzpost d-none" id="chip-nzpost">NZ Post</span>
    <span class="chip chip--gss d-none" id="chip-gss">NZ Couriers (GSS)</span>
    <span class="chip chip--manual" id="chip-manual">Manual</span>
  </div>
  <div class="row g-3">
    <div class="col-12 col-md-7">
      <div class="mt-0">
        <div class="row g-2 align-items-center">
          <div class="col-auto">
            <label for="stx-boxes-input" class="col-form-label">Box count (optional)</label>
          </div>
          <div class="col-auto">
            <input type="number" id="stx-boxes-input" class="form-control form-control-sm" min="1" step="1" placeholder="auto" />
          </div>
          <div class="col-auto">
            <small class="text-muted">Defaults from weight estimate; you can override here.</small>
          </div>
        </div>
        <button class="btn btn-sm btn-secondary mt-2" id="btn-print-box-slips">Print Box Slips</button>
      </div>
    </div>
    <div class="col-12 col-md-5">
      <div class="stx-shipping-summary" aria-labelledby="ship-h">
        <h4 id="ship-h" class="h6 mb-2">Shipping Summary</h4>
        <div id="ship-summary" class="card card-body p-2">
          <div class="small text-muted">Calculating…</div>
          <div class="row g-2 align-items-center mt-1">
            <div class="col-auto"><strong>Total Weight:</strong> <span id="ship-total-kg">—</span></div>
            <div class="col-auto"><strong>Best Option:</strong> <span id="ship-best">—</span></div>
          </div>
          <div class="mt-2">
            <button class="btn btn-sm btn-outline-primary" id="btn-refresh-shipping">Refresh</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
