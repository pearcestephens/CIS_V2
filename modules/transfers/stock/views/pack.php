<?php
/** @var int $tidVar */
/** @var array $packItems */
/** @var array $packMetrics */
/** @var array $packConfigVar */
/** @var array $carrierSupportVar */

$configJson   = htmlspecialchars(json_encode($packConfigVar, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$transferId   = (int) $tidVar;
$transferCode = $transferVar['public_code']
  ?? $transferVar['vend_number']
  ?? ('Transfer #' . $transferId);
$statusLabel  = strtolower((string) ($transferVar['status'] ?? 'draft'));
$statusClass  = match ($statusLabel) {
  'sent'      => 'primary',
  'partial'   => 'info',
  'received'  => 'success',
  'cancelled' => 'danger',
  'open'      => 'warning',
  default     => 'secondary',
};
$outletFromId   = $transferVar['origin_outlet_id'] ?? null;
$outletFromName = $transferVar['origin_outlet_name'] ?? $outletFromId;
$outletToId     = $transferVar['dest_outlet_id'] ?? null;
$outletToName   = $transferVar['dest_outlet_name'] ?? $outletToId;
$createdAt      = $transferVar['created_at'] ?? null;
?>

<?php if (!$transferVar): ?>
  <div class="alert alert-warning">No transfer selected or not found.</div>
<?php else: ?>
  <div class="pack-screen" data-pack-config="<?= $configJson ?>" data-transfer-id="<?= $transferId ?>">
    <div class="card pack-header shadow-sm mb-3">
      <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <h5 class="mb-1">
            <?= htmlspecialchars($transferCode, ENT_QUOTES, 'UTF-8') ?>
            <span class="badge bg-<?= $statusClass ?> ms-2 text-uppercase small"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
          </h5>
          <div class="text-muted small">
            <span class="me-3">From: <strong><?= htmlspecialchars((string) $outletFromName, ENT_QUOTES, 'UTF-8') ?: 'N/A' ?></strong></span>
            <span class="me-3">To: <strong><?= htmlspecialchars((string) $outletToName, ENT_QUOTES, 'UTF-8') ?: 'N/A' ?></strong></span>
            <span>Created: <strong><?= htmlspecialchars((string) $createdAt, ENT_QUOTES, 'UTF-8') ?: 'Unknown' ?></strong></span>
          </div>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-pack-refresh" data-action="refresh">Refresh Data</button>
          <button type="button" class="btn btn-outline-primary btn-sm" id="btnFinalizePack" data-action="finalize-pack" <?= $transferId > 0 ? '' : 'disabled' ?>>Finalize Pack</button>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-xl-8">
        <?php $packItemsData = $packItems; require __DIR__ . '/components/pack_items_table.php'; ?>
        <?php require __DIR__ . '/components/pack_notes.php'; ?>
      </div>
      <div class="col-xl-4">
        <?php $packMetricsData = $packMetrics; require __DIR__ . '/components/pack_summary.php'; ?>
        <?php
          $packCarrierSupport = $carrierSupportVar;
          $packConfigData     = $packConfigVar;
          require __DIR__ . '/components/pack_shipping_plugin.php';
        ?>
      </div>
    </div>
  </div>

  <script>
  (function(){
    const screenEl = document.querySelector('.pack-screen');
    if (!screenEl) return;
    let cfg = {};
    try {
      cfg = JSON.parse(screenEl.dataset.packConfig || '{}');
    } catch (err) {
      cfg = {};
    }

    const finalizeBtn = document.getElementById('btnFinalizePack');
    if (finalizeBtn) {
      finalizeBtn.addEventListener('click', async () => {
        if (!cfg.transferId) {
          return;
        }
        finalizeBtn.disabled = true;
        try {
          const endpoint = (cfg.endpoints && cfg.endpoints.finalize_pack)
            ? cfg.endpoints.finalize_pack
            : '/cisv2/modules/transfers/stock/ajax/actions/finalize_pack.php';
          const url = endpoint.includes('?')
            ? `${endpoint}&transfer=${cfg.transferId}`
            : `${endpoint}?transfer=${cfg.transferId}`;
          const res = await fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': cfg.csrf || '',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ transfer_pk: cfg.transferId })
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok || data.success !== true) {
            const err = data?.error || 'Failed to finalize pack.';
            throw new Error(err);
          }
          alert('Pack finalized successfully.');
          window.dispatchEvent(new CustomEvent('pack:finalized', { detail: { transferId: cfg.transferId, payload: data } }));
          window.location.reload();
        } catch (err) {
          alert('Error: ' + (err && err.message ? err.message : String(err)));
        } finally {
          finalizeBtn.disabled = false;
        }
      });
    }

    const refreshBtn = document.getElementById('btn-pack-refresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('pack:refresh', { detail: { transferId: cfg.transferId } }));
      });
    }

    window.addEventListener('pack:labels-updated', () => {
      window.location.reload();
    });
  })();
  </script>
<?php endif; ?>
