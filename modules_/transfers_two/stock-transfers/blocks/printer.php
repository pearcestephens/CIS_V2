<?php
// modules/transfers/stock-transfers/blocks/printer.php
?>
<section class="stx-printer card border-0 shadow-sm" aria-labelledby="stx-printer-title">
  <div class="card-header bg-white border-bottom py-2 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center">
      <i class="fa fa-print text-secondary mr-2" aria-hidden="true"></i>
      <strong id="stx-printer-title" class="mb-0">Printer</strong>
      <span class="badge badge-light ml-2 stx-printer__status">Idle</span>
    </div>
    <div>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="stx-printer-retry" style="display:none;">
        <i class="fa fa-redo" aria-hidden="true"></i> Retry
      </button>
    </div>
  </div>
  <div class="card-body py-2">
    <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
      <div class="input-group input-group-sm" style="max-width: 340px;">
        <div class="input-group-prepend">
          <span class="input-group-text bg-light">Default printer</span>
        </div>
        <input type="text" class="form-control" id="stx-printer-name" placeholder="Browser/system default" aria-label="Printer name" readonly>
        <div class="input-group-append">
          <button class="btn btn-outline-secondary" type="button" id="stx-printer-detect">
            <i class="fa fa-search" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="stx-printer-auto">
        <label class="custom-control-label" for="stx-printer-auto">Auto-open label</label>
      </div>

      <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="stx-printer-autoprint" checked>
        <label class="custom-control-label" for="stx-printer-autoprint">Auto print</label>
      </div>

      <small class="text-muted" id="stx-printer-hint">Will open a new tab/window and call window.print() if enabled.</small>
    </div>
    <details class="mt-2">
      <summary class="small">Box stickers (optional)</summary>
      <div class="mt-2 d-flex align-items-center flex-wrap" style="gap:8px;">
        <div class="input-group input-group-sm" style="max-width: 220px;">
          <div class="input-group-prepend">
            <span class="input-group-text bg-light">Boxes</span>
          </div>
          <input type="number" class="form-control" id="stx-sticker-boxes" value="1" min="1" max="50" aria-label="Number of boxes">
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary stx-action" data-action="sticker.print">
          <i class="fa fa-receipt" aria-hidden="true"></i> Print Box Stickers
        </button>
        <small class="text-muted">80mm thermal roll • FROM/TO • Transfer # • Box X of N • Packed by • Date • Tracking • No auto-print</small>
      </div>
      <div class="mt-2 p-2 bg-light border rounded">
        <div class="small text-muted mb-1">Sticker settings (this session only)</div>
        <div class="d-flex align-items-center flex-wrap" style="gap:12px;">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="stx-sticker-show-tracking" checked>
            <label class="custom-control-label" for="stx-sticker-show-tracking">Show tracking</label>
          </div>
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="stx-sticker-show-packer" checked>
            <label class="custom-control-label" for="stx-sticker-show-packer">Show packed by</label>
          </div>
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="stx-sticker-show-date" checked>
            <label class="custom-control-label" for="stx-sticker-show-date">Show date</label>
          </div>
        </div>
      </div>
    </details>
  </div>
</section>
