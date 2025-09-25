<?php
declare(strict_types=1);
// Minimal port of Store Cashup Calculator modal
?>
<div class="modal fade" id="quickFloatCount" tabindex="-1" role="dialog" aria-labelledby="quickFloatCountLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickFloatCountLabel">Store Cashup Calculator</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="cashupContainer">
          <!-- Keep original tab shell for parity; content kept concise here. Adapt or replace with full port as needed. -->
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#cashupCalcTab" role="tab">Store Cashup Calculator</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cashUpnotesTab" role="tab">Autosaving NotePad</a></li>
          </ul>
          <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="cashupCalcTab" role="tabpanel">
              <p class="text-muted small mb-2">Use the original calculator page for the full workflow; this modal is a quick helper.</p>
              <div class="alert alert-info mb-0">Quick calculator coming soon…</div>
            </div>
            <div class="tab-pane fade" id="cashUpnotesTab" role="tabpanel">
              <p class="text-muted small">Any information written here is stored only in this browser.</p>
              <textarea onkeyup="localStorage.autoSavingPad = this.value;" id="autosavingPad" class="form-control" style="height: 240px;"></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
