<?php
/** Transfers Dashboard (Top-Level) — CIS_TEMPLATE wrapped */
declare(strict_types=1);
// Route through CIS_TEMPLATE so header/footer/sidebar are included
$_GET['module'] = 'transfers';
$_GET['view'] = 'dashboard';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/module.php';
