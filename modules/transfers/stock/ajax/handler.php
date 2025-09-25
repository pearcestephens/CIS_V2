<?php
/**
 * File: modules/transfers/stock/ajax/handler.php
 * Purpose: Serve simple AJAX actions for the CIS v2 pack prototype.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: bootstrap.php, core/src/Response.php, actions/finalize_pack.php
 */
declare(strict_types=1);

use CIS\Core\Response;

require_once __DIR__ . '/../../../../bootstrap.php';
require_once CIS_CORE_PATH . '/src/Response.php';

$input = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? (json_decode((string) file_get_contents('php://input'), true) ?? $_POST)
    : $_GET;

$action = $input['action'] ?? 'health';

switch ($action) {
    case 'health':
        Response::json(['ok' => true, 'status' => 'healthy']);
        break;

    case 'finalize':
        require __DIR__ . '/actions/finalize_pack.php';
        finalize_pack($input);
        break;

    default:
        Response::json(['ok' => false, 'error' => 'Unknown action'], 400);
}
