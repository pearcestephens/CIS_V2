<?php
/**
 * blocks/draft_status_bar.php
 * Professional toolbar: draft indicator, save/restore/discard/autosave, options with add products/print
 */
?>
<section class="card mb-2" aria-live="polite">
  <div class="card-body py-2 d-flex align-items-center flex-wrap" style="gap:12px;">
    <div class="d-flex align-items-center" style="gap:8px;">
      <span class="badge badge-pill" id="stx-live-badge" style="background:#e8f4ff;color:#0b5ed7;border:1px solid #b6d7ff;">Live</span>
      <span id="stx-live-status" class="small text-muted">Idle</span>
    </div>
    <div class="ml-auto d-flex align-items-center" style="gap:10px;">
      <div class="small" id="stx-lock-status" aria-live="polite">Read-only</div>
      <div class="small text-muted" id="stx-lock-owner" style="display:none"></div>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="stx-request-lock" style="display:none;">Request Edit</button>
    </div>
  </div>
</section>
