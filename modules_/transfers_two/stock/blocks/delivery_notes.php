<?php
/**
 * blocks/delivery_notes.php
 * Delivery & Notes section with notes textarea and delivery method radios.
 */
?>
<section class="card mb-3">
  <div class="card-header py-2 d-flex align-items-center justify-content-between">
    <small class="text-muted text-uppercase">Delivery &amp; Notes</small>
    <small class="text-muted">Consignment-first | Send mode with server-side fallback</small>
  </div>
  <div class="card-body py-2">
    <div class="form-row">
      <div class="form-group col-md-8">
        <label for="stx-notes">Notes &amp; Discrepancies</label>
        <textarea id="stx-notes" class="form-control" rows="3" placeholder="Enter any notes, discrepancies, or special instructions..."></textarea>
      </div>
      <div class="form-group col-md-4">
        <label>Delivery Method</label>
        <div class="custom-control custom-radio">
          <input type="radio" id="stx-delivery-courier" name="delivery-mode" class="custom-control-input" value="courier" data-action="toggle-tracking" checked>
          <label class="custom-control-label" for="stx-delivery-courier">Courier delivery</label>
        </div>
        <div class="custom-control custom-radio">
          <input type="radio" id="stx-delivery-internal" name="delivery-mode" class="custom-control-input" value="internal" data-action="toggle-tracking">
          <label class="custom-control-label" for="stx-delivery-internal">Internal (drive/drop)</label>
        </div>
      </div>
    </div>
    <div id="tracking-section">
      <?php tpl_block('box_labels'); ?>
    </div>
  </div>
</section>
