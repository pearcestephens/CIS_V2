<?php
declare(strict_types=1);
/** @var array|null $transferVar */
/** @var int $tidVar */
?>
<div class="card shadow-sm mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><?= htmlspecialchars($transferVar['ref_code'] ?? ('Transfer #' . $tidVar)) ?></strong>
      <span class="badge bg-<?= ($transferVar['status'] ?? 'draft') === 'ready' ? 'success' : 'secondary' ?>">
        <?= htmlspecialchars($transferVar['status'] ?? 'draft') ?>
      </span>
    </div>
    <div>
      <button class="btn btn-sm btn-outline-primary" id="btnFinalizePack" <?= $tidVar > 0 ? '' : 'disabled' ?>>
        Finalize Pack
      </button>
    </div>
  </div>
  <div class="card-body">
    <?php if (!$transferVar): ?>
      <div class="alert alert-warning">No transfer selected or not found.</div>
    <?php else: ?>
      <dl class="row mb-0">
        <dt class="col-sm-3">From</dt><dd class="col-sm-9"><?= (int) ($transferVar['origin_outlet_id'] ?? 0) ?></dd>
        <dt class="col-sm-3">To</dt><dd class="col-sm-9"><?= (int) ($transferVar['dest_outlet_id'] ?? 0) ?></dd>
        <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= htmlspecialchars($transferVar['created_at'] ?? '') ?></dd>
      </dl>
      <hr>
      <div id="pack-items">
        <!-- TODO: render items table; keep lean to avoid blocking -->
        <em>Items table goes here…</em>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.getElementById('btnFinalizePack')?.addEventListener('click', async () => {
  const tid = <?= (int) $tidVar ?>;
  if (!tid) return;
  const btn = document.getElementById('btnFinalizePack');
  btn.disabled = true;

  try {
    const res = await fetch('/cisv2/modules/transfers/stock/ajax/actions/finalize_pack.php?transfer=' + tid, {
      method: 'POST',
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Failed');
    alert('Pack finalized');
    location.reload();
  } catch (e) {
    alert('Error: ' + e.message);
  } finally {
    btn.disabled = false;
  }
});
</script>
