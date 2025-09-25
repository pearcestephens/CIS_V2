<?php
declare(strict_types=1);

// Route through CIS module template with view=pack_ready
$_GET['module'] = 'transfers/stock';
$_GET['view']   = 'pack_ready';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/module.php';
