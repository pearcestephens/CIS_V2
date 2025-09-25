<?php
/**
 * blocks/printer_v2.php
 * Fully remodelled printer/shipping UI (V2).
 */
?>
<section id="stx-printer-v2" class="card mb-3">
  <div class="card-header py-2 d-flex align-items-center">
    <i class="fa fa-shipping-fast text-muted mr-2" aria-hidden="true"></i>
    <small class="text-muted text-uppercase">Shipping & Labels</small>
    <span class="ml-auto badge badge-light" id="stxv2-status" aria-live="polite">Idle</span>
  </div>
  <div class="card-body py-3">
    <!-- Stepper -->
    <div class="d-flex align-items-center mb-3" style="gap:12px;">
      <div class="badge badge-primary" data-step="1">1</div>
      <div class="text-muted">Choose Method</div>
      <div class="mx-2 text-muted">→</div>
      <div class="badge badge-secondary" data-step="2">2</div>
      <div class="text-muted">Build Boxes</div>
      <div class="mx-2 text-muted">→</div>
      <div class="badge badge-secondary" data-step="3">3</div>
      <div class="text-muted">Create & Print</div>
    </div>

    <!-- Step 1: Method -->
    <div class="stxv2-step" data-step-panel="1">
      <div class="row">
        <div class="col-md-4 mb-2">
          <div class="card h-100 border-0 shadow-sm stxv2-method" data-method="nzpost" role="button" tabindex="0" aria-label="NZ Post">
            <div class="card-body py-3 d-flex align-items-center" style="gap:10px;">
              <i class="fa fa-tag text-danger"></i>
              <div>
                <div><strong>NZ Post</strong></div>
                <div class="small text-muted">Carrier label via NZ Post</div>
              </div>
              <span class="ml-auto badge badge-light" id="stxv2-chip-nzpost">Checking…</span>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-2">
          <div class="card h-100 border-0 shadow-sm stxv2-method" data-method="gss" role="button" tabindex="0" aria-label="GSS">
            <div class="card-body py-3 d-flex align-items-center" style="gap:10px;">
              <i class="fa fa-tag text-info"></i>
              <div>
                <div><strong>GSS</strong></div>
                <div class="small text-muted">Carrier label via GSS</div>
              </div>
              <span class="ml-auto badge badge-light" id="stxv2-chip-gss">Checking…</span>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-2">
          <div class="card h-100 border-0 shadow-sm stxv2-method" data-method="manual" role="button" tabindex="0" aria-label="Manual">
            <div class="card-body py-3 d-flex align-items-center" style="gap:10px;">
              <i class="fa fa-edit text-muted"></i>
              <div>
                <div><strong>Manual</strong></div>
                <div class="small text-muted">Enter tracking number yourself</div>
              </div>
              <span class="ml-auto badge badge-success">Always Available</span>
            </div>
          </div>
        </div>
      </div>
      <div class="mt-2 d-flex align-items-center">
        <button type="button" class="btn btn-primary btn-sm" id="stxv2-next-1">Continue</button>
        <span class="small text-muted ml-2">You can print internal Box Slips later regardless of carrier availability.</span>
      </div>
    </div>

    <!-- Step 2: Boxes -->
    <div class="stxv2-step d-none" data-step-panel="2">
      <div class="form-row align-items-end">
        <div class="form-group col-md-3">
          <label>Preset</label>
          <select class="form-control" id="stxv2-default-preset"><option value="">Select preset…</option></select>
        </div>
        <div class="form-group col-md-2">
          <label>Signature</label>
          <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox" class="custom-control-input" id="stxv2-signature">
            <label class="custom-control-label" for="stxv2-signature">Required</label>
          </div>
        </div>
        <div class="form-group col-md-2">
          <label>Saturday</label>
          <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox" class="custom-control-input" id="stxv2-saturday">
            <label class="custom-control-label" for="stxv2-saturday">Yes</label>
          </div>
        </div>
        <div class="form-group col-md-5 text-right">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="stxv2-suggest"><i class="fa fa-magic mr-1"></i>Suggest Boxes</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="stxv2-add-row"><i class="fa fa-plus mr-1"></i>Add Row</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-2">
          <thead>
            <tr>
              <th style="width:70px;">Qty</th>
              <th>Preset</th>
              <th style="width:120px;">Weight (kg)</th>
              <th style="width:100px;">W</th>
              <th style="width:100px;">H</th>
              <th style="width:100px;">D</th>
              <th style="min-width:140px;">Contents</th>
            </tr>
          </thead>
          <tbody class="stxv2-parcels"><tr>
            <td><input type="number" class="form-control form-control-sm stxv2-qty" value="1" min="1"></td>
            <td><select class="form-control form-control-sm stxv2-preset"><option value="">Select preset…</option></select></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm stxv2-weight" value="1.00" min="0"></td>
            <td><input type="number" class="form-control form-control-sm stxv2-w" value="30" min="0"></td>
            <td><input type="number" class="form-control form-control-sm stxv2-h" value="20" min="0"></td>
            <td><input type="number" class="form-control form-control-sm stxv2-d" value="10" min="0"></td>
            <td>
              <div class="d-flex align-items-center" style="gap:8px;">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-action="stxv2-contents"><i class="fa fa-box-open mr-1"></i>Assign</button>
                <span class="badge badge-light stxv2-contents-badge">Unassigned</span>
                <button type="button" class="btn btn-link text-danger p-0 ml-auto" data-action="stxv2-remove" aria-label="Remove"><i class="fa fa-trash"></i></button>
              </div>
            </td>
          </tr></tbody>
        </table>
      </div>
      <div class="d-flex align-items-center">
        <span class="mr-3"><i class="fa fa-calculator mr-1"></i><span id="stxv2-cost">Estimated Cost: $0.00 NZD</span></span>
        <button type="button" class="btn btn-primary btn-sm ml-auto" id="stxv2-next-2">Continue</button>
      </div>
    </div>

    <!-- Step 3: Create & Print -->
    <div class="stxv2-step d-none" data-step-panel="3">
      <div class="form-row">
        <div class="form-group col-md-3" data-only="nzpost gss">
          <label>Reference</label>
          <input type="text" class="form-control" id="stxv2-ref" placeholder="#<?= (int)($_GET['transfer'] ?? 0); ?>">
        </div>
        <div class="form-group col-md-3" data-only="manual">
          <label>Tracking Number</label>
          <input type="text" class="form-control" id="stxv2-manual-num" placeholder="e.g., ABC1234567NZ">
        </div>
        <div class="form-group col-md-3" data-only="manual">
          <label>Carrier Name</label>
          <input type="text" class="form-control" id="stxv2-manual-car" placeholder="e.g., NZ Post / Aramex / DHL">
        </div>
      </div>
      <div class="btn-toolbar" role="toolbar" aria-label="Actions" style="gap:8px;">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-action="stxv2-nzpost"><i class="fa fa-tag mr-1"></i>Create NZ Post Label</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-action="stxv2-gss"><i class="fa fa-tag mr-1"></i>Create GSS Label</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-action="stxv2-manual"><i class="fa fa-save mr-1"></i>Save Manual Tracking</button>
        <button type="button" class="btn btn-primary btn-sm" data-action="stxv2-slips"><i class="fa fa-print mr-1"></i>Print Box Slips</button>
        <button type="button" class="btn btn-success btn-sm" data-action="stxv2-stickers"><i class="fa fa-sticky-note mr-1"></i>Print Sticker Labels</button>
      </div>
    </div>
  </div>

  <!-- Modal: Assign Contents -->
  <div class="modal fade" id="stxv2-contents-modal" tabindex="-1" role="dialog" aria-labelledby="stxv2-contents-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="stxv2-contents-title"><i class="fa fa-box-open mr-1"></i>Assign Items to Box</h6>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="thead-light">
                <tr><th>Product</th><th style="width:120px;" class="text-right pr-3">Remaining</th><th style="width:140px;">Put in box</th></tr>
              </thead>
              <tbody id="stxv2-contents-body"></tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer py-2">
          <div class="mr-auto small text-muted">Tip: Arrow keys to adjust, Enter to save.</div>
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="stxv2-contents-save"><i class="fa fa-check mr-1"></i>Save</button>
        </div>
      </div>
    </div>
  </div>
</section>
