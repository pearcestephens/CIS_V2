<?php
/** Stock Transfers Pack (browse-safe bridge) */
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/base/init.php';
$view = $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock/views/pack.php';
if (is_file($view)) {
	// phpcs:ignore
	include $view; return;
}
echo '<div class="container my-3"><div class="alert alert-info">Pack view moved. Use <a href="https://staff.vapeshed.co.nz/modules/module.php?module=transfers&view=stock-transfers">Transfers → Stock</a>.</div></div>';
