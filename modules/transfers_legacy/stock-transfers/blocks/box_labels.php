<?php
// modules/transfers/stock-transfers/blocks/box_labels.php
$tid = (int)($_GET['transfer'] ?? 0);
?>
<section class="card shadow-sm border-0 mb-3" aria-labelledby="box-labels-title">
  <div class="card-header bg-warning text-dark py-2 d-flex align-items-center justify-content-between">
    <div>
      <i class="fa fa-print mr-2" aria-hidden="true"></i>
      <strong id="box-labels-title">Box Label Printer</strong>
    </div>
    <small class="badge badge-dark">Quick Print</small>
  </div>
  <div class="card-body py-3">
    <div class="form-row align-items-center">
      <div class="form-group col-md-4">
        <label class="form-label small font-weight-bold mb-1" for="box-count-input">Number of Boxes</label>
        <input type="number" min="1" max="50" class="form-control form-control-sm" id="box-count-input" value="1" placeholder="Boxes">
      </div>
      <div class="form-group col-md-8 mb-0">
        <div class="d-flex align-items-center justify-content-end" style="gap: 8px;">
          <button class="btn btn-warning btn-sm" type="button" data-action="labels-preview"><i class="fa fa-eye" aria-hidden="true"></i> Preview</button>
          <button class="btn btn-success btn-sm" type="button" data-action="labels-print"><i class="fa fa-print" aria-hidden="true"></i> Print Now</button>
          <button class="btn btn-outline-secondary btn-sm" type="button" data-action="labels-open"><i class="fa fa-external-link" aria-hidden="true"></i> Open Window</button>
        </div>
      </div>
    </div>
    <p class="mt-2 mb-0 small text-muted"><i class="fa fa-info-circle" aria-hidden="true"></i> Labels include Transfer #<?php echo $tid; ?>, FROM/TO stores, and box numbers.</p>
  </div>
</section>
