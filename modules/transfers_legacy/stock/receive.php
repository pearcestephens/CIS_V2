<?php
declare(strict_types=1);
/**
 * File: modules/transfers/stock/receive.php
 * Purpose: Entry endpoint for receiving stock against a transfer (partial/final).
 * Behavior: Requires ?transfer=ID. If missing: AJAX → 404 JSON, otherwise redirect to dashboard.
 */

$transferIdParam = isset($_GET['transfer']) ? (int) $_GET['transfer'] : 0;
if ($transferIdParam <= 0) {
    $isAjax = (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        isset($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false
    );

    if ($isAjax) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'missing_transfer_id',
                'message' => 'Transfer ID is required',
            ],
            'request_id' => $requestId,
        ]);
        exit;
    }

    header('Location: https://staff.vapeshed.co.nz/modules/transfers/stock/dashboard.php');
    exit;
}

require $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock/views/receive.php';
