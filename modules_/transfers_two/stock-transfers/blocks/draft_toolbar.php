<?php
// modules/transfers/stock-transfers/blocks/draft_toolbar.php
?>
<div class="stx-draft-toolbar d-flex justify-content-between align-items-start w-100 mb-2" id="table-action-toolbar">
  <div class="d-flex flex-column" style="gap:4px;">
    <div class="d-flex align-items-center" style="gap:8px;">
      <span class="badge badge-pill badge-secondary" id="draft-status">Draft: Off</span>
      <span class="text-muted small" id="draft-last-saved">Not saved</span>
    </div>
    <div class="d-flex align-items-center" style="gap:12px;">
      <div class="d-flex" style="gap:8px;" role="group" aria-label="Draft actions">
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-save-draft" title="Save to this browser only">Save now (Ctrl+S)</button>
        <button type="button" class="btn btn-sm btn-outline-success" id="btn-restore-draft" disabled>Restore</button>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-discard-draft" disabled>Discard</button>
      </div>
      <div class="custom-control custom-switch" title="Auto-save to this browser only">
        <input type="checkbox" class="custom-control-input" id="toggle-autosave">
        <label class="custom-control-label" for="toggle-autosave">Autosave</label>
      </div>
    </div>
  </div>
  <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
    <button class="btn btn-outline-primary d-flex align-items-center" type="button" data-toggle="modal" data-target="#addProductsModal">
      <i class="fa fa-plus mr-2" aria-hidden="true"></i> Add Products
    </button>
    <button type="button" class="btn btn-outline-secondary d-flex align-items-center" id="tbl-print" title="Print picking sheet" data-action="print">
      <i class="fa fa-print mr-2" aria-hidden="true"></i> Print
    </button>
  </div>
</div>
