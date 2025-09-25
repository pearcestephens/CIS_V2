<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
require_once __DIR__ . '/pack.php'; // reuse getTransferData + helpers

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
$paths = [
  'css' => [
    '/modules/transfers/stock/assets/css/pack.v3.css',
    '/modules/transfers/stock/assets/css/printer.v2.css',
    '/modules/transfers/stock/assets/css/planner.v3.css',
  ],
  'js' => [
    '/modules/transfers/stock/assets/js/packages.presets.js',
    '/modules/transfers/stock/assets/js/items.table.v3.js',
    '/modules/transfers/stock/assets/js/printer.v2.js',
    '/modules/transfers/stock/assets/js/planner.v3.js',
  ],
];
$ver = function(string $rel): string { $abs = $_SERVER['DOCUMENT_ROOT'] . $rel; $q = is_file($abs) ? ('?v=' . rawurlencode((string)@filemtime($abs))) : ''; return $rel . $q; };
foreach ($paths['css'] as $rel) { tpl_style($ver($rel)); }
foreach ($paths['js'] as $rel)  { tpl_script($ver($rel), ['defer' => true]); }
?>
<div class="stx-pack-v3" data-module="stock" data-view="pack_v3">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="stx-ajax" content="https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php">
  <input type="hidden" id="transferID" value="<?= (int)($transferData->id ?? $transferIdParam) ?>">

  <!-- Sticky action bar -->
  <div class="stxv3-bar">
    <div class="left">
      <strong>Pack Transfer #<?= (int)($transferData->id ?? $transferIdParam) ?></strong>
      <span class="text-muted ml-2"><?= htmlspecialchars(($transferData->outlet_from->name ?? '') . ' → ' . ($transferData->outlet_to->name ?? '')) ?></span>
    </div>
    <div class="right">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="stxv3-print-pick"><i class="fa fa-print mr-1"></i>Picking Sheet</button>
      <a href="https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=stock" class="btn btn-outline-dark btn-sm">Back</a>
    </div>
  </div>

  <!-- 2-column layout: items + right rail -->
  <div class="row no-gutters">
    <div class="col-lg-8 pr-lg-2">
      <?php tpl_block('items_table_v3', ['transferData' => $transferData]); ?>
    </div>
    <div class="col-lg-4 pl-lg-2">
      <?php tpl_block('planner_v3'); ?>
      <?php tpl_block('printer_v2'); ?>
      <?php tpl_block('delivery_notes'); ?>
      <?php tpl_block('comments'); ?>
    </div>
  </div>

  <?php tpl_block('pack_actions'); ?>
</div>
