<?php
/**
 * File: router.php
 * Purpose: Route CIS v2 HTTP requests to the appropriate module controller.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: bootstrap.php, modules/transfers/stock/controllers
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$module = $_GET['module'] ?? '';
$view   = $_GET['view'] ?? '';

switch ($module) {
    case 'transfers/stock':
        require CIS_MODULES_PATH . '/transfers/stock/controllers/dispatch.php';
        break;
    default:
        http_response_code(404);
        echo 'Not Found';
}
