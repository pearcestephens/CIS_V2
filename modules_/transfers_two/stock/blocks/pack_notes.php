<?php
declare(strict_types=1);
/**
 * File: modules/transfers/stock/blocks/pack_notes.php
 * Purpose: Notes UI for Pack view
 */
?>
<section class="stx-notes" aria-labelledby="notes-h">
  <h3 id="notes-h">Notes</h3>
  <div class="mb-2">
    <textarea id="note-text" class="form-control" rows="2" placeholder="Add a note…"></textarea>
    <div class="d-flex justify-content-between align-items-center mt-1">
      <button class="btn btn-sm btn-primary" id="btn-add-note">Add Note</button>
      <small id="save-indicator" class="text-muted">Idle</small>
    </div>
  </div>
  <ul class="list-group" id="notes-list" aria-live="polite"></ul>
</section>
