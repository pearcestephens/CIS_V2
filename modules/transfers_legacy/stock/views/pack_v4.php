<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
require_once __DIR__ . '/pack.php'; // getTransferData + hydration helpers

$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$transferIdParam = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $transferIdParam = (int)$_GET[$__k]; break; } }
if ($transferIdParam <= 0) { echo '<div class="alert alert-danger">Missing transfer ID.</div>'; return; }
if (!isset($transferData)) { try { $transferData = getTransferData($transferIdParam, true); } catch (Throwable $e) { $transferData = null; } }
if (!$transferData) { echo '<div class="alert alert-warning">Transfer not found.</div>'; return; }

if (!isset($_SESSION)) { session_start(); }
$csrfToken = '';
if (function_exists('getCSRFToken')) { try { $csrfToken = (string)getCSRFToken(); } catch (Throwable $e) { $csrfToken = ''; } }
if ($csrfToken === '') { $csrfToken = (string)($_SESSION['csrf_token'] ?? ''); }
if ($csrfToken === '') { $csrfToken = bin2hex(random_bytes(16)); $_SESSION['csrf_token'] = $csrfToken; }

tpl_shared_assets();
?>
<div class="stx-pack-v4 container-fluid" data-module="stock" data-view="pack_v4">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="stx-ajax" content="https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php">
  <input type="hidden" id="transferID" value="<?= (int)($transferData->id ?? $transferIdParam) ?>">

  <!-- Top toolbar -->
  <div class="stxv4-toolbar u-surface u-round mb-2 p-2 d-flex flex-wrap align-items-center justify-content-between">
    <div class="d-flex align-items-center u-cluster" style="--gap: var(--space-2)">
      <strong class="mr-2">Pack #<?= (int)($transferData->id ?? $transferIdParam) ?></strong>
      <span class="u-muted"><?= htmlspecialchars(($transferData->outlet_from->name ?? '') . ' → ' . ($transferData->outlet_to->name ?? '')) ?></span>
    </div>
    <div class="d-flex align-items-center u-cluster" style="--gap: var(--space-2)">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="stxv4-print-pick"><i class="fa fa-print mr-1"></i>Picking Sheet</button>
      <button type="button" class="btn btn-outline-primary btn-sm" id="stxv4-open-printer"><i class="fa fa-tag mr-1"></i>Labels</button>
      <a href="https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=stock" class="btn btn-outline-dark btn-sm">Back</a>
    </div>
  </div>

  <!-- 2-column layout: items table + right rail -->
  <div class="row">
    <div class="col-lg-8 mb-2">
      <?php tpl_block('items_table_v4', ['transferData' => $transferData]); ?>
    </div>
    <div class="col-lg-4 mb-2">
      <?php tpl_block('planner_v3'); ?>
      <?php tpl_block('printer_v2'); ?>
      <?php tpl_block('delivery_notes'); ?>
      <?php tpl_block('comments'); ?>
    </div>
  </div>

  <?php tpl_block('pack_actions'); ?>
</div>
