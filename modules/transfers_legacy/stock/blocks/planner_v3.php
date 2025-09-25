<?php
/**
 * blocks/planner_v3.php — Next-gen packaging planner UI (balanced weights + constraints)
 */
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
?>
<div id="stx-planner-v3" class="stx-planner-card mb-3">
  <div class="stxv3-head">
    <div>
      <strong>Packaging Planner</strong>
      <span class="badge badge-theme ml-2">V3</span>
      <small id="stxv3-status" class="text-muted ml-2">Init…</small>
    </div>
    <div class="stxv3-actions">
      <button type="button" class="btn btn-outline-secondary" id="stxv3-save"><i class="fa fa-save mr-1"></i>Save Plan</button>
      <button type="button" class="btn btn-primary" id="stxv3-run"><i class="fa fa-magic mr-1"></i>Plan Boxes</button>
    </div>
  </div>
  <div class="p-3">
    <div class="alert alert-info mb-3">
      Evenly distributes weight across boxes, spreads battery products across different boxes, and respects your rules. Uses product weights and attributes.
    </div>
    <div class="mb-3">
      <div class="form-row">
        <div class="form-group col-md-3">
          <label class="small mb-1">Mode</label>
          <select id="stxv3-mode" class="form-control form-control-sm">
            <option value="balanced" selected>Balanced (even weight)</option>
            <option value="min_boxes">Minimise boxes</option>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label class="small mb-1">Max weight per box (kg)</label>
          <input type="number" step="0.1" min="0" id="stxv3-maxw" class="form-control form-control-sm" placeholder="auto">
        </div>
        <div class="form-group col-md-3">
          <label class="small mb-1">Max batteries per box</label>
          <input type="number" min="0" id="stxv3-maxbatt" class="form-control form-control-sm" value="1">
        </div>
        <div class="form-group col-md-3 d-flex align-items-center" style="gap:12px;">
          <div class="custom-control custom-switch mt-3">
            <input type="checkbox" class="custom-control-input" id="stxv3-nomix-batt-eliq" checked>
            <label class="custom-control-label" for="stxv3-nomix-batt-eliq">Don’t mix batteries with e‑liquid</label>
          </div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-md-3 d-flex align-items-center">
          <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="stxv3-autoplan">
            <label class="custom-control-label" for="stxv3-autoplan">Auto-plan on changes</label>
          </div>
        </div>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover mb-0">
        <thead>
          <tr>
            <th>Qty</th>
            <th>Preset</th>
            <th>Weight (kg)</th>
            <th>Items</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody class="stxv3-parcels">
          <tr><td colspan="5" class="text-muted">No plan yet. Click Plan Boxes.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
