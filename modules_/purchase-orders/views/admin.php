<?php
declare(strict_types=1);
// Simple bridge to render the admin dashboard via /module/purchase-orders/admin
// Keeps CIS_TEMPLATE routing happy for 'admin' view.
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
// phpcs:ignore
include __DIR__ . '/admin/dashboard.php';
