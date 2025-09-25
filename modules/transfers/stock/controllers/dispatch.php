<?php
/**
 * File: modules/transfers/stock/controllers/dispatch.php
 * Purpose: Dispatch incoming transfers/stock requests to the correct controller.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: pack.php controller
 */
declare(strict_types=1);

$view = $_GET['view'] ?? '';

switch ($view) {
    case 'pack':
        require __DIR__ . '/pack.php';
        break;
    default:
        http_response_code(404);
        echo 'Not Found';
}
