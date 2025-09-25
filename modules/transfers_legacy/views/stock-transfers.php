<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
// Render the stock dashboard view inline (no redirect)
// phpcs:ignore
include $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock/views/dashboard.php';
