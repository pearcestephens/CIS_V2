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
      <div class="card-body w-100 d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
        <div class="d-flex flex-column gap-2">
          <div class="d-flex align-items-center gap-2">
            <?php
            try {
                include $_SERVER['DOCUMENT_ROOT'] . '/cisv2/modules/system/health/partials/traffic_light_badge.php';
            } catch (Throwable $e) {
                error_log('traffic_light_badge include failed: ' . $e->getMessage());
            }
            ?>
            <h5 class="mb-0">
              <?= htmlspecialchars($transferCode, ENT_QUOTES, 'UTF-8') ?>
              <span class="badge bg-<?= $statusClass ?> ms-2 text-uppercase small"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </h5>
          </div>
          <div class="text-muted small">
            <span class="me-3">From: <strong><?= htmlspecialchars((string) $outletFromName, ENT_QUOTES, 'UTF-8') ?: 'N/A' ?></strong></span>
            <span class="me-3">To: <strong><?= htmlspecialchars((string) $outletToName, ENT_QUOTES, 'UTF-8') ?: 'N/A' ?></strong></span>
            <span>Created: <strong><?= htmlspecialchars((string) $createdAt, ENT_QUOTES, 'UTF-8') ?: 'Unknown' ?></strong></span>
          </div>
        </div>
        <div class="d-flex flex-column align-items-start gap-2">
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-requires-vend="1" id="btn-create-labels">Create Shipping Labels</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-pack-refresh" data-action="refresh">Refresh Data</button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnFinalizePack" data-action="finalize-pack" <?= $transferId > 0 ? '' : 'disabled' ?>>Finalize Pack</button>
          </div>
          <div id="job-status" class="small text-muted"></div>
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
          $packDestDefaultsData = $packDestDefaults ?? [];
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
    const refreshBtn = document.getElementById('btn-pack-refresh');
    const labelBtn = document.getElementById('btn-create-labels');
    const jobStatus = document.getElementById('job-status');
    const overrideForm = document.getElementById('dest-override-form');

    const setBusy = (busy) => {
      if (labelBtn) { labelBtn.disabled = busy; }
      if (finalizeBtn) { finalizeBtn.disabled = busy; }
      if (refreshBtn) { refreshBtn.disabled = busy; }
    };

    const collectOverride = () => {
      if (!overrideForm) { return {}; }
      const data = {};
      overrideForm.querySelectorAll('[data-dest-field]').forEach((input) => {
        const key = input.getAttribute('data-dest-field');
        if (!key) return;
        const value = input.value.trim();
        if (value !== '') {
          data[key] = value;
        }
      });
      return data;
    };

    if (finalizeBtn) {
      finalizeBtn.addEventListener('click', async () => {
        if (!cfg.transferId) { return; }
        setBusy(true);
        try {
          const endpoint = cfg?.endpoints?.finalize_pack || '/cisv2/modules/transfers/stock/ajax/actions/finalize_pack_sync.php';
          const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': cfg.csrf || '',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ transfer_id: cfg.transferId }),
          });
          const json = await response.json().catch(() => ({}));
          if (!response.ok || json.ok !== true) {
            throw new Error(json.error || 'Failed to finalize pack');
          }
          alert('Pack finalized successfully.');
          window.dispatchEvent(new CustomEvent('pack:finalized', { detail: { transferId: cfg.transferId, payload: json } }));
          window.location.reload();
        } catch (error) {
          alert('Error: ' + (error && error.message ? error.message : String(error)));
        } finally {
          setBusy(false);
        }
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('pack:refresh', { detail: { transferId: cfg.transferId } }));
      });
    }

    if (labelBtn) {
      labelBtn.addEventListener('click', async () => {
        if (!cfg.transferId) { return; }
        setBusy(true);
        if (jobStatus) {
          jobStatus.textContent = 'Creating labels…';
          jobStatus.className = 'small text-muted';
        }
        try {
          const endpoint = cfg?.endpoints?.labels_dispatch || '/cisv2/modules/transfers/stock/ajax/actions/labels_dispatch.php';
          const payload = {
            transfer_id: cfg.transferId,
            carrier: (document.querySelector('input[name="carrier_choice"]:checked')?.value || 'NZ_POST').toUpperCase(),
            service_code: document.getElementById('carrier-service-code')?.value || 'COURIER',
            boxes: window._precomputedBoxes || [],
            dest_override: collectOverride(),
          };
          const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': cfg.csrf || '',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
          });
          const json = await response.json().catch(() => ({}));
          if (!response.ok || json.ok !== true) {
            throw new Error(json.error || 'Dispatch failed');
          }
          if (jobStatus) {
            jobStatus.innerHTML = 'Labels created &#10004;';
            jobStatus.className = 'small text-success';
          }
          window.dispatchEvent(new CustomEvent('pack:labels-updated', { detail: { transferId: cfg.transferId, payload: json } }));
          setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
          if (jobStatus) {
            jobStatus.textContent = error && error.message ? error.message : 'Error dispatching labels';
            jobStatus.className = 'small text-danger';
          }
        } finally {
          setBusy(false);
        }
      });
    }

    if (cfg.transferId && !window._packAutosaveTimer) {
      window._packAutosaveTimer = setInterval(() => {
        const state = {
          scrollY: window.scrollY,
          filters: window.packFilters || {},
        };
        fetch('/cisv2/modules/transfers/stock/ajax/actions/ui_autosave.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(cfg.csrf ? { 'X-CSRF-Token': cfg.csrf } : {}),
          },
          body: JSON.stringify({ transfer_id: cfg.transferId, state }),
          keepalive: true,
        }).catch(() => {});
      }, 10000);
    }
  })();
  </script>
<?php endif; ?>
