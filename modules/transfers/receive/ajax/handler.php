<?php
declare(strict_types=1);

/**
 * Transfers — Receive AJAX Handler
 * Base URL: /modules/transfers/receive/ajax/handler.php
 * Actions:
 *   - health
 *   - get_shipment         { transfer_id }
 *   - scan_or_select       { transfer_id, type:"item"|"tracking", value, qty? }
 *   - set_parcel_tracking  { transfer_id, parcel_id?, box_number?, carrier, tracking_number?, tracking_url? }
 *   - set_shipment_mode    { transfer_id, mode, status? }
 *   - save_receipt         { transfer_id, items:[{ transfer_item_id, qty_received, condition?, notes? }] }
 *   - list_discrepancies   { transfer_id }
 *   - resolve_discrepancy  { id, note? }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/error.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/security.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/csrf.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/middleware/kernel.php';
require_once dirname(__DIR__) . '/lib/ReceiveHelper.php';
require_once dirname(__DIR__, 1) . '/stock/lib/PackHelper.php'; // reuse tracking/shipment utilities

header('Content-Type:application/json;charset=utf-8');
header('X-Content-Type-Options:nosniff');
header('Referrer-Policy:no-referrer');
header('Cache-Control:no-store,no-cache,must-revalidate,max-age=0');

$start = microtime(true);

if (isset($_GET['action']) && $_GET['action'] === 'health') {
    $rid = function_exists('cis_request_id') ? cis_request_id() : bin2hex(random_bytes(8));
    header('X-Request-ID:' . $rid);
    http_response_code(200);
    echo json_encode(['ok' => true, 'service' => 'transfers.receive', 'status' => 'healthy', 'time' => gmdate('c'), 'request_id' => $rid], JSON_UNESCAPED_SLASHES);
    exit;
}

function json_out_with_rid(array $ctx, array $payload, int $code = 200): void {
    $payload['request_id'] = $ctx['request_id'] ?? null;
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // In prod, auth required; in non-prod, allow X-API-Key test bypass
    $env = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? ''));
    $requireAuth = in_array($env, ['prod','production','live'], true);

    $stack = [
        mw_trace(),
        mw_security_headers(),
        mw_json_or_form_normalizer(),
        mw_csrf_or_api_key(getenv('TEST_CLI_API_KEY') ?: 'TEST-CLI-KEY-123'),
        mw_validate_content_type(['application/json','multipart/form-data','application/x-www-form-urlencoded']),
        mw_content_length_limit(1024 * 1024),
        mw_rate_limit('transfers.receive.handler', 180, 60),
    ];
    if ($requireAuth && function_exists('mw_enforce_auth')) $stack[] = mw_enforce_auth();

    $pipe = mw_pipeline($stack);
    $ctx  = $pipe([]);
    $in   = $ctx['input'];

    $helper = new \CIS\Transfers\Receive\ReceiveHelper();
    $pack   = new \CIS\Transfers\Stock\PackHelper();

    $action = (string)($in['action'] ?? '');
    if ($action === '') json_out_with_rid($ctx, ['ok' => false, 'error' => 'Missing action'], 400);

    switch ($action) {

        case 'get_shipment': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            if ($transferId <= 0) json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            $out = $helper->getShipmentSummary($transferId);
            json_out_with_rid($ctx, ['ok' => true] + $out);
        }

        case 'scan_or_select': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            $type  = (string)($in['type'] ?? '');
            $value = (string)($in['value'] ?? '');
            $qty   = (int)($in['qty'] ?? 1);
            if ($transferId <= 0 || $type === '') json_out_with_rid($ctx, ['ok' => false, 'error' => 'invalid input'], 400);
            $res = $helper->scanOrSelect($transferId, $type, $value, $qty);
            json_out_with_rid($ctx, $res, $res['ok'] ? 200 : 422);
        }

        case 'set_parcel_tracking': {
            $transferId    = (int)($in['transfer_id'] ?? 0);
            $parcelId      = isset($in['parcel_id'])  ? (int)$in['parcel_id']  : null;
            $boxNumber     = isset($in['box_number']) ? (int)$in['box_number'] : null;
            $carrier       = (string)($in['carrier'] ?? 'internal_drive');
            $trackingNo    = isset($in['tracking_number']) ? (string)$in['tracking_number'] : null;
            $trackingUrl   = isset($in['tracking_url'])    ? (string)$in['tracking_url']    : null;
            if ($transferId <= 0) json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            $res = $pack->setParcelTracking($transferId, $parcelId, $boxNumber, $carrier, $trackingNo, $trackingUrl);
            json_out_with_rid($ctx, $res, $res['ok'] ? 200 : 422);
        }

        case 'set_shipment_mode': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            $mode       = (string)($in['mode'] ?? 'internal_drive');
            $status     = isset($in['status']) ? (string)$in['status'] : null;
            if ($transferId <= 0) json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            $res = $pack->setShipmentMode($transferId, $mode, $status);
            json_out_with_rid($ctx, $res, $res['ok'] ? 200 : 422);
        }

        case 'save_receipt': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            $items      = is_array($in['items'] ?? null) ? $in['items'] : [];
            if ($transferId <= 0) json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            $res = $helper->saveReceipt($transferId, $items);
            json_out_with_rid($ctx, $res, $res['ok'] ? 200 : 422);
        }

        case 'list_discrepancies': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            if ($transferId <= 0) json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            $list = $helper->listDiscrepancies($transferId);
            json_out_with_rid($ctx, ['ok' => true, 'discrepancies' => $list]);
        }

        case 'resolve_discrepancy': {
            $id   = (int)($in['id'] ?? 0);
            $note = (string)($in['note'] ?? '');
            if ($id <= 0) json_out_with_rid($ctx, ['ok' => false, 'error' => 'id required'], 400);
            $actorId = (int)($_SESSION['userID'] ?? ($_SESSION['user_id'] ?? 0));
            $res = $helper->resolveDiscrepancy($id, $actorId, $note);
            json_out_with_rid($ctx, $res, $res['ok'] ? 200 : 422);
        }

        default:
            json_out_with_rid($ctx, ['ok' => false, 'error' => 'Unknown action'], 404);
    }

} catch (Throwable $e) {
    json_out_with_rid($ctx ?? [], ['ok' => false, 'error' => 'Unhandled exception', 'hint' => 'See server logs'], 500);
} finally {
    if (function_exists('cis_profile_flush')) {
        cis_profile_flush(['endpoint' => 'transfers.receive.handler', 'ms' => (int)((microtime(true) - $start) * 1000)]);
    }
}
