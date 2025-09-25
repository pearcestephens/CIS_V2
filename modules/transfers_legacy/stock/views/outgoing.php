<?php
// modules/transfers/stock/views/outgoing.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';

$transferIdParam = (int)($_GET['transfer'] ?? 0);
if ($transferIdParam <= 0) { echo '<div class="alert alert-danger">Missing transfer ID.</div>'; return; }
if (!isset($transferData)) { try { $transferData = getTransferData($transferIdParam, true); } catch (Throwable $e) { $transferData = null; } }
if (!$transferData) { echo '<div class="alert alert-warning">Transfer not found.</div>'; return; }

$csrfToken = function_exists('getCSRFToken') ? (string)getCSRFToken() : ((string)($_SESSION['csrf_token'] ?? ''));

// Load shared assets only if not already running inside CIS_TEMPLATE (which may preload assets)
if (empty($GLOBALS['TPL_RENDERING_IN_CIS_TEMPLATE'])) {
  tpl_shared_assets();
}
?>
<div class="stx-outgoing" data-module="stock" data-view="outgoing">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
  <input type="hidden" id="transferID" value="<?= (int)$transferIdParam; ?>">
  <?php tpl_block('header_bar'); ?>
  <?php tpl_block('draft_status_bar'); ?>
  <?php tpl_block('draft_toolbar'); ?>
  <?php tpl_block('items_table'); ?>
  <?php tpl_block('add_products_modal'); ?>
  <?php tpl_block('shipping_tabs'); ?>
  <?php tpl_block('box_labels'); ?>
  <?php if (!empty($PACKONLY)) tpl_block('packonly_banner'); ?>
  <?php tpl_block('footer_actions'); ?>

  <?php
  // Register assets when not rendered via CIS_TEMPLATE (which will load via meta)
  if (empty($GLOBALS['TPL_RENDERING_IN_CIS_TEMPLATE'])) {
    tpl_style('/modules/transfers/stock/assets/css/stock.css');
    tpl_script('/modules/transfers/stock/assets/js/core.js', ['defer' => true]);
    tpl_script('/modules/transfers/stock/assets/js/outgoing.init.js', ['defer' => true]);
    tpl_script('/modules/transfers/stock/assets/js/printer.js', ['defer' => true]);
    tpl_render_styles();
    tpl_render_scripts();
  }
  ?>
</div>
<?php
// When not inside CIS_TEMPLATE, render a minimal breadcrumb; otherwise CIS_TEMPLATE handles it
if (empty($GLOBALS['TPL_RENDERING_IN_CIS_TEMPLATE'])) {
  $tid = (int)($_GET['transfer'] ?? 0);
  tpl_breadcrumb([
    ['label' => 'Home', 'href' => tpl_base_url()],
    ['label' => 'Transfers'],
    ['label' => 'Outgoing Transfer #'.$tid],
  ]);
}
?>
