<?php
/**
 * blocks/add_products_modal.php
 * Modal for adding products to transfer with search scaffold
 */
?>
<div class="modal fade" id="stx-add-products" tabindex="-1" role="dialog" aria-labelledby="stxAddProductsTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stxAddProductsTitle">Add Products to Transfer</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <div class="input-group input-group-lg">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search"></i></span></div>
            <input type="text" class="form-control" id="stx-add-search" placeholder="Search by product name, SKU, or ID...">
          </div>
          <small class="form-text text-muted">Type at least 2 characters to search...</small>
        </div>
        <div class="form-row align-items-end">
          <div class="form-group col-md-3">
            <div class="custom-control custom-checkbox mt-4">
              <input type="checkbox" class="custom-control-input" id="stx-add-instock">
              <label class="custom-control-label" for="stx-add-instock">In-stock only</label>
            </div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th style="width:32px;"><input type="checkbox" id="stx-add-select-all" aria-label="Select all"></th>
                <th>Product Details</th>
                <th>Stock</th>
                <th>Price</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="stx-add-results" tabindex="0">
              <tr><td colspan="5" class="text-center text-muted py-4">Ready to search<br><small>Start typing to find products...</small></td></tr>
            </tbody>
          </table>
        </div>
        <div class="text-muted small mt-1">Tip: Use Arrow keys to navigate results, Space to select, Enter to add.</div>
        <div class="d-flex justify-content-center">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="stx-add-more" disabled>Load more</button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="stx-add-insert-selected"><i class="fa fa-plus mr-1"></i>Add Selected</button>
        <button type="button" class="btn btn-outline-secondary" id="stx-add-clear">Clear Search</button>
      </div>
    </div>
  </div>
</div>
