<?php
?>
<section class="card mb-3 stx-printer-card">
  <div class="card-header py-2 d-flex align-items-center">
    <i class="fa fa-print text-muted mr-2"></i>
    <small class="text-muted text-uppercase">Printer</small>
    <span class="ml-auto badge badge-light stx-printer__status" aria-live="polite">Idle</span>
  </div>
  <div class="card-body py-2">
    <div class="stx-printer">
      <div class="form-row">
        <div class="form-group col-md-3">
          <label for="stx-carrier">Carrier</label>
          <select id="stx-carrier" class="form-control">
            <option value="nzpost">NZ Post</option>
            <option value="gss">GSS</option>
            <option value="manual">Manual</option>
          </select>
        </div>
        <div class="form-group col-md-6 d-flex align-items-end">
          <div class="ml-3 small text-muted">
            <span class="mr-2">NZ Post: <span class="badge badge-light" id="stx-nzpost-status" aria-live="polite">checking…</span></span>
            <span>GSS: <span class="badge badge-light" id="stx-gss-status" aria-live="polite">checking…</span></span>
          </div>
        </div>
      </div>

      <!-- NZ Post configuration -->
      <div class="form-row stx-block stx-block-nzpost">
        <div class="form-group col-md-3">
          <label for="stx-nzpost-service">NZ Post Service</label>
          <select class="form-control" id="stx-nzpost-service">
            <option value="courier">Courier</option>
            <option value="overnight">Overnight</option>
            <option value="economy">Economy</option>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label for="stx-nzpost-ref">Reference</label>
          <input type="text" class="form-control" id="stx-nzpost-ref" placeholder="#<?= (int)($_GET['transfer'] ?? 0); ?>">
        </div>
      </div>

      <!-- GSS configuration -->
      <div class="form-row stx-block stx-block-gss">
        <div class="form-group col-md-3">
          <label for="stx-gss-service">GSS Service</label>
          <select class="form-control" id="stx-gss-service">
            <option value="standard">Standard</option>
            <option value="overnight">Overnight</option>
            <option value="saturday">Saturday</option>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label for="stx-gss-ref">Reference</label>
          <input type="text" class="form-control" id="stx-gss-ref" placeholder="#<?= (int)($_GET['transfer'] ?? 0); ?>">
        </div>
      </div>

      <!-- Flags common to NZ Post and GSS -->
      <div class="form-row align-items-end stx-block stx-block-nzpost stx-block-gss">
        <div class="form-group col-md-2">
          <div class="custom-control custom-checkbox mt-4">
            <input type="checkbox" class="custom-control-input" id="stx-signature">
            <label class="custom-control-label" for="stx-signature">Signature</label>
          </div>
        </div>
        <div class="form-group col-md-2">
          <div class="custom-control custom-checkbox mt-4">
            <input type="checkbox" class="custom-control-input" id="stx-saturday">
            <label class="custom-control-label" for="stx-saturday">Saturday</label>
          </div>
        </div>
      </div>

      <!-- Manual tracking block -->
      <div class="form-row stx-block stx-block-manual">
        <div class="form-group col-md-3">
          <label for="stx-manual-number">Tracking Number</label>
          <input type="text" class="form-control" id="stx-manual-number" placeholder="e.g., ABC1234567NZ">
        </div>
        <div class="form-group col-md-3">
          <label for="stx-manual-carrier">Carrier Name</label>
          <input type="text" class="form-control" id="stx-manual-carrier" placeholder="e.g., NZ Post / Aramex / DHL">
        </div>
      </div>

      <!-- Parcels table (NZ Post & GSS only) -->
      <div class="table-responsive stx-block stx-block-nzpost stx-block-gss">
        <table class="table table-sm mb-2 stx-parcels-table">
          <thead>
            <tr>
              <th style="width:70px;">Qty</th>
              <th style="min-width:160px;">Preset</th>
              <th style="width:120px;">Weight (kg)</th>
              <th style="width:100px;">W (cm)</th>
              <th style="width:100px;">H (cm)</th>
              <th style="width:100px;">D (cm)</th>
              <th style="min-width:120px;">Contents</th>
            </tr>
          </thead>
          <tbody class="stx-parcels">
            <tr>
              <td><input type="number" class="form-control form-control-sm stx-qty" value="1" min="1"></td>
              <td>
                <div class="input-group input-group-sm">
                  <select class="form-control stx-preset-select">
                    <option value="">Select preset…</option>
                  </select>
                  <div class="input-group-append">
                    <button type="button" class="btn btn-outline-secondary" data-action="preset-apply" title="Apply preset">Apply</button>
                  </div>
                </div>
              </td>
              <td><input type="number" step="0.01" class="form-control form-control-sm stx-weight" value="1.00" min="0"></td>
              <td><input type="number" class="form-control form-control-sm stx-width" value="30" min="0"></td>
              <td><input type="number" class="form-control form-control-sm stx-height" value="20" min="0"></td>
              <td><input type="number" class="form-control form-control-sm stx-depth" value="10" min="0"></td>
              <td>
                <div class="d-flex align-items-center" style="gap:8px;">
                  <button type="button" class="btn btn-outline-secondary btn-sm" data-action="parcel-contents"><i class="fa fa-box-open mr-1"></i>Assign</button>
                  <span class="badge badge-light stx-contents-badge" title="Items assigned to this box">0 items</span>
                  <button type="button" class="btn btn-link text-danger p-0 ml-auto" data-action="parcel-remove" aria-label="Remove"><i class="fa fa-trash"></i></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mb-3 d-flex align-items-center stx-block stx-block-nzpost stx-block-gss">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="parcel-add"><i class="fa fa-plus mr-1"></i>Add Row</button>
        <span class="ml-3 stx-cost-pill" aria-live="polite">
          <i class="fa fa-calculator mr-1" aria-hidden="true"></i><span class="stx-cost-estimate">Estimated Cost: $0.00 NZD</span>
        </span>
      </div>

      <div class="btn-group btn-group-sm" role="group" aria-label="Labels">
        <button type="button" class="btn btn-outline-secondary stx-action stx-block stx-block-nzpost" data-action="nzpost.create"><i class="fa fa-tag mr-1"></i>Create NZ Post Label</button>
        <button type="button" class="btn btn-outline-secondary stx-action stx-block stx-block-gss" data-action="gss.create"><i class="fa fa-tag mr-1"></i>Create GSS Label</button>
        <button type="button" class="btn btn-outline-secondary stx-action stx-block stx-block-manual" data-action="manual.save"><i class="fa fa-save mr-1"></i>Save Manual Tracking</button>
      </div>
      
      <!-- Modal: Assign Box Contents -->
      <div class="modal fade" id="stx-contents-modal" tabindex="-1" role="dialog" aria-labelledby="stx-contents-title" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header py-2">
              <h6 class="modal-title" id="stx-contents-title"><i class="fa fa-box-open mr-1"></i>Assign Items to Box</h6>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead class="thead-light">
                    <tr>
                      <th>Product</th>
                      <th style="width:120px;" class="text-right pr-3">Remaining</th>
                      <th style="width:140px;">Put in box</th>
                    </tr>
                  </thead>
                  <tbody id="stx-contents-body">
                    <!-- rows generated by JS from #productSearchBody -->
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer py-2">
              <div class="mr-auto small text-muted">Tip: Use arrow keys to adjust, Tab to move, Enter to save.</div>
              <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary" id="stx-contents-save"><i class="fa fa-check mr-1"></i>Save</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
