<?php
// modules/transfers/stock-transfers/views/outgoing.php
// Preconditions
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';

$transferIdParam = (int)($_GET['transfer'] ?? 0);
if ($transferIdParam <= 0) { echo '<div class="alert alert-danger">Missing transfer ID.</div>'; return; }

if (!isset($transferData)) {
  try { $transferData = getTransferData($transferIdParam, true); } catch (Throwable $e) { $transferData = null; }
}
if (!$transferData) { echo '<div class="alert alert-warning">Transfer not found.</div>'; return; }

$csrfToken = '';
if (function_exists('getCSRFToken')) { $csrfToken = (string)getCSRFToken(); }
elseif (!empty($_SESSION['csrf_token'])) { $csrfToken = (string)$_SESSION['csrf_token']; }

// Ensure shared assets/components available
tpl_shared_assets();

?>
<div class="stx-outgoing" data-module="stock-transfers" data-view="outgoing">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
  <?php tpl_block('header_bar'); ?>
  <?php tpl_block('draft_toolbar'); ?>
  <?php tpl_block('items_table'); ?>
  <?php tpl_block('shipping_tabs'); ?>
  <?php tpl_block('box_labels'); ?>
  <?php if (!empty($PACKONLY)) tpl_block('packonly_banner'); ?>
  <?php tpl_block('footer_actions'); ?>

  <?php tpl_style('/modules/transfers/stock-transfers/assets/css/stock_transfers.css'); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/core.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/outgoing.init.js', ['defer' => true]); ?>
  <?php tpl_script('/modules/transfers/stock-transfers/assets/js/printer.js', ['defer' => true]); ?>
  <?php tpl_render_styles(); ?>
  <?php tpl_render_scripts(); ?>
</div>
<?php
$tid = (int)($_GET['transfer'] ?? 0);
tpl_breadcrumb([
  ['label' => 'Home', 'href' => tpl_base_url()],
  ['label' => 'Admin'],
  ['label' => 'Outgoing Transfer #'.$tid],
]);
?>
