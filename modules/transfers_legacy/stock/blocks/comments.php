<?php
/**
 * blocks/comments.php
 * Transfer comments thread UI: list + add form
 */
?>
<section class="card mb-3">
  <div class="card-header py-2 d-flex align-items-center justify-content-between">
    <small class="text-muted text-uppercase">Notes</small>
    <small class="text-muted">Multiple notes per transfer</small>
  </div>
  <div class="card-body py-2">
    <div id="stx-comments" class="stx-comments" data-transfer-id="<?= (int)($_GET['transfer'] ?? $_GET['transfer_id'] ?? $_GET['id'] ?? $_GET['tid'] ?? $_GET['t'] ?? 0) ?>">
      <div class="stx-comments-list" role="list" aria-live="polite"></div>
      <form class="stx-comments-form mt-2" autocomplete="off">
        <div class="input-group input-group-sm">
          <input type="text" class="form-control" name="note" maxlength="500" placeholder="Add a note..." aria-label="Add a note">
          <div class="input-group-append">
            <button class="btn btn-primary" type="submit"><i class="fa fa-comment"></i> Post</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</section>
