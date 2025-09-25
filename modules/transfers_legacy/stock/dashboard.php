<?php
// Stock dashboard — render via CIS_TEMPLATE to include standard chrome
declare(strict_types=1);
// Use nested module path so CIS template resolves base: /modules/transfers/stock
$_GET['module'] = 'transfers/stock';
$_GET['view'] = 'stock';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/module.php';
