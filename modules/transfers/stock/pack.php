<?php
declare(strict_types=1);

/**
 * File: modules/transfers/stock/pack.php
 * Purpose: Friendly entry point for the stock transfer packing screen.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: router.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/cisv2/bootstrap.php';

// Force router parameters to the stock/pack controller while preserving other query params (e.g. transfer).
$_GET['module'] = 'transfers/stock';
$_GET['view']   = 'pack';

require_once CISV2_ROOT . '/router.php';
