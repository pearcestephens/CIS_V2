<?php
// blocks/add_products_modal.php
// Modal UI for searching and adding products to the current transfer
$tid = (int)($_GET['transfer'] ?? 0);
?>
<div class="modal fade" id="addProductsModal" tabindex="-1" role="dialog" aria-labelledby="addProductsTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="addProductsTitle">Add products to transfer #<?php echo $tid; ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-row align-items-end">
          <div class="form-group col-md-8">
            <label for="apm-search" class="small mb-1">Search products (name / SKU / ID)</label>
            <input type="text" class="form-control" id="apm-search" placeholder="Type at least 2 characters…" autocomplete="off">
          </div>
          <div class="form-group col-md-4">
            <label for="apm-qty" class="small mb-1">Default quantity</label>
            <input type="number" class="form-control" id="apm-qty" value="1" min="1">
          </div>
        </div>

        <details class="mb-3">
          <summary class="small text-muted">Apply to many transfers (optional)</summary>
          <div class="mt-2">
            <div class="d-flex align-items-center justify-content-between">
              <div class="small mb-1">Select from your outlet’s transfers <span id="apm-own-selected-count" class="badge badge-light ml-1">0</span></div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="apm-load-own">Load</button>
            </div>
            <div class="input-group input-group-sm mb-2">
              <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search" aria-hidden="true"></i></span></div>
              <input type="text" class="form-control" id="apm-own-search" placeholder="Filter by ID or number…">
              <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary" id="apm-select-all">Select all</button>
                <button type="button" class="btn btn-outline-secondary" id="apm-clear-all">Clear all</button>
              </div>
            </div>
            <div class="border rounded" style="max-height:180px;overflow:auto;">
              <ul id="apm-own-list" class="list-group list-group-flush">
                <li class="list-group-item small text-muted">Click Load to show transfers…</li>
              </ul>
            </div>
            <small class="form-text text-muted mt-2">If none selected, products will be added only to this transfer #<?php echo $tid; ?>.</small>
          </div>
        </details>

        <div class="row">
          <div class="col-md-7">
            <div class="card">
              <div class="card-header py-1"><small class="mb-0">Results</small></div>
              <div class="card-body p-0" style="max-height: 300px; overflow:auto;">
                <ul id="apm-results" class="list-group list-group-flush" aria-live="polite" aria-busy="false"></ul>
              </div>
            </div>
          </div>
          <div class="col-md-5">
            <div class="card">
              <div class="card-header py-1 d-flex justify-content-between align-items-center">
                <div>
                  <small class="mb-0">Selected</small>
                  <span id="apm-selected-count" class="badge badge-secondary ml-1">0</span>
                </div>
                <button class="btn btn-sm btn-outline-secondary" type="button" id="apm-clear">Clear</button>
              </div>
              <div class="card-body p-0" style="max-height: 300px; overflow:auto;">
                <ul id="apm-selected" class="list-group list-group-flush"></ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <div class="mr-auto small text-muted" id="apm-status" role="status" aria-live="polite"></div>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="apm-add">Add to transfer</button>
      </div>
    </div>
  </div>
</div>
