<?php
// modules/transfers/stock-transfers/views/pack.php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';

$transferIdParam = (int)($_GET['transfer'] ?? 0);
if ($transferIdParam <= 0) {
  http_response_code(404);
  echo '<div class="container my-3"><div class="alert alert-danger">Missing transfer ID. <a href="https://staff.vapeshed.co.nz/modules/module.php?module=transfers&view=stock-transfers">Back to Transfers</a></div></div>';
  return;
}

if (!isset($transferData)) {
  try { $transferData = getTransferData($transferIdParam, true); } catch (Throwable $e) { $transferData = null; }
}
if (!$transferData) {
  http_response_code(404);
  echo '<div class="container my-3"><div class="alert alert-warning">Transfer not found. <a href="https://staff.vapeshed.co.nz/modules/module.php?module=transfers&view=stock-transfers">Back to Transfers</a></div></div>';
  return;
}

$csrfToken = '';
if (function_exists('getCSRFToken')) { $csrfToken = (string)getCSRFToken(); }
elseif (!empty($_SESSION['csrf_token'])) { $csrfToken = (string)$_SESSION['csrf_token']; }

tpl_shared_assets();
?>
<div class="stx-outgoing" data-module="stock-transfers" data-view="pack">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
  <?php tpl_block('header_bar'); ?>
  <?php tpl_block('draft_toolbar'); ?>
  <?php tpl_block('items_table'); ?>
  <?php tpl_block('shipping_tabs'); ?>
  <?php tpl_block('box_labels'); ?>
  <?php if (!empty($PACKONLY)) tpl_block('packonly_banner'); ?>
  <?php tpl_block('printer'); ?>
  <?php tpl_block('pack_actions'); ?>

  <?php tpl_block('add_products_modal'); ?>

  <?php tpl_style('/modules/transfers/stock-transfers/assets/css/stock_transfers.css'); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/core.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/pack.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/pack.init.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/printer.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/shipping.np.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/shipping.gss.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/shipping.manual.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/history.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/add_products.js', ['defer' => true]); ?>
  <?php tpl_render_styles(); ?>
  <?php tpl_render_scripts(); ?>
</div>
<?php
$tid = (int)($_GET['transfer'] ?? 0);
tpl_breadcrumb([
  ['label' => 'Home', 'href' => tpl_base_url()],
  ['label' => 'Admin'],
  ['label' => 'Pack Transfer #'.$tid],
]);
?>
