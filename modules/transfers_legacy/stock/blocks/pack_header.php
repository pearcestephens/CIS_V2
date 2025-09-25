<?php
declare(strict_types=1);
/**
 * File: modules/transfers/stock/blocks/pack_header.php
 * Purpose: Header for Pack view (title + basic meta display)
 * Vars: int $transferId
 */
?>
<header class="stx-pack__header">
  <div class="stx-pack__title">Stock Transfer — Pack</div>
  <div class="stx-pack__meta">
    <span class="stx-pack__route" id="stx-route">Loading…</span>
    <span class="stx-pack__id" id="stx-transfer-id"><?= isset($transferId) ? '#' . (int)$transferId : '' ?></span>
    <span class="stx-pack__status" id="stx-status" aria-live="polite">Idle</span>
  </div>
  <div class="stx-lock" id="stx-lock" role="status" aria-live="polite">
    <span id="stx-lock-state">Checking lock…</span>
    <button class="btn btn-sm btn-outline-primary d-none" id="btn-request-edit">Request Edit</button>
  </div>
  <hr class="my-2" />
</header>
