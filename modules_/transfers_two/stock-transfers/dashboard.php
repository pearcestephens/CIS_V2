<?php
/** Stock Transfers Dashboard (browse-safe bridge) */
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/base/init.php';
// Prefer rendering the new view inline so it stays inside CIS_TEMPLATE
$view = $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock/views/dashboard.php';
if (is_file($view)) {
	// phpcs:ignore
	include $view; return;
}
echo '<div class="container my-3"><div class="alert alert-info">Stock dashboard has moved. Please use <a href="https://staff.vapeshed.co.nz/modules/module.php?module=transfers&view=stock-transfers">Transfers → Stock</a>.</div></div>';
