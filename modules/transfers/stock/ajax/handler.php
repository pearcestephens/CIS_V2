<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/error.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/security.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/csrf.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/middleware/kernel.php';
require_once dirname(__DIR__) . '/lib/PackHelper.php';

header('Content-Type:application/json;charset=utf-8');
header('X-Content-Type-Options:nosniff');
header('Referrer-Policy:no-referrer');
header('Cache-Control:no-store,no-cache,must-revalidate,max-age=0');

$start = microtime(true);

if (isset($_GET['action']) && $_GET['action'] === 'health') {
    $rid = function_exists('cis_request_id') ? cis_request_id() : bin2hex(random_bytes(8));
    header('X-Request-ID:' . $rid);
    http_response_code(200);
    echo json_encode(['ok' => true, 'service' => 'transfers.stock', 'status' => 'healthy', 'time' => gmdate('c'), 'request_id' => $rid], JSON_UNESCAPED_SLASHES);
    exit;
}

function json_out_with_rid(array $ctx, array $payload, int $code = 200): void {
    $payload['request_id'] = $ctx['request_id'] ?? null;
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pipe = mw_pipeline([
        mw_trace(),
        mw_security_headers(),
        mw_json_or_form_normalizer(),
        mw_csrf_or_api_key(getenv('TEST_CLI_API_KEY') ?: 'TEST-CLI-KEY-123'),
        mw_validate_content_type(['application/json','multipart/form-data','application/x-www-form-urlencoded']),
        mw_content_length_limit(1024 * 1024),
        mw_rate_limit('transfers.stock.handler', 120, 60),
        mw_idempotency(),
    ]);

    $ctx    = $pipe([]);
    $in     = $ctx['input'];
    $hdr    = $ctx['headers'];
    $helper = new \CIS\Transfers\Stock\PackHelper();
    $action = trim((string)($in['action'] ?? ''));

    if ($action === '') {
        json_out_with_rid($ctx, ['ok' => false, 'error' => 'Missing action'], 400);
    }

    switch ($action) {
        case 'health': {
            json_out_with_rid($ctx, ['ok' => true, 'service' => 'transfers.stock', 'status' => 'healthy', 'time' => gmdate('c')]);
        }

        case 'calculate_ship_units': {
            $productId = (int)($in['product_id'] ?? 0);
            $qty       = (int)($in['qty'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                json_out_with_rid($ctx, ['ok' => false, 'error' => 'product_id and qty required'], 400);
            }
            $res = $helper->calculateShipUnits($productId, $qty);
            json_out_with_rid($ctx, ['ok' => true] + $res);
        }

        case 'validate_parcel_plan': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            $plan       = $in['parcel_plan'] ?? null;
            if ($transferId <= 0 || !is_array($plan)) {
                json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id and parcel_plan required'], 400);
            }
            $out = $helper->validateParcelPlan($transferId, $plan);
            json_out_with_rid($ctx, ['ok' => true] + $out);
        }

        case 'generate_label': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            if ($transferId <= 0) {
                json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            }
            $carrier = trim((string)($in['carrier'] ?? 'MVP'));
            $planRaw = $in['parcel_plan'] ?? ['parcels' => []];
            $plan    = is_array($planRaw) ? $planRaw : ['parcels' => []];

            $res = $helper->generateLabel($transferId, $carrier, $plan);
            if (!empty($hdr['idempotency-key'])) {
                mw_idem_store($ctx, $res);
            }
            json_out_with_rid($ctx, $res['ok'] ? $res : (['ok' => false] + $res), $res['ok'] ? 200 : 422);
        }

        case 'save_pack': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            $notes      = (string)($in['notes'] ?? '');
            if ($transferId <= 0) {
                json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            }
            $helper->addPackNote($transferId, $notes);
            json_out_with_rid($ctx, ['ok' => true]);
        }

        case 'list_items': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            if ($transferId <= 0) {
                json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            }
            $items = $helper->listItems($transferId);
            json_out_with_rid($ctx, ['ok' => true, 'items' => $items]);
        }

        case 'get_parcels': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            if ($transferId <= 0) {
                json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            }
            $out = $helper->getParcels($transferId);
            json_out_with_rid($ctx, ['ok' => true] + $out);
        }

        case 'set_parcel_tracking': {
            $transferId    = (int)($in['transfer_id'] ?? 0);
            $parcelId      = isset($in['parcel_id'])  ? (int)$in['parcel_id']  : null;
            $boxNumber     = isset($in['box_number']) ? (int)$in['box_number'] : null;
            $carrier       = trim((string)($in['carrier'] ?? 'internal_drive'));
            $trackingNo    = isset($in['tracking_number']) ? (string)$in['tracking_number'] : null;
            $trackingUrl   = isset($in['tracking_url'])    ? (string)$in['tracking_url']    : null;

            if ($transferId <= 0) {
                json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            }
            $res = $helper->setParcelTracking($transferId, $parcelId, $boxNumber, $carrier, $trackingNo, $trackingUrl);
            json_out_with_rid($ctx, $res, $res['ok'] ? 200 : 422);
        }

        case 'set_shipment_mode': {
            $transferId = (int)($in['transfer_id'] ?? 0);
            $mode       = (string)($in['mode'] ?? 'internal_drive');
            $status     = isset($in['status']) ? (string)$in['status'] : null;

            if ($transferId <= 0) {
                json_out_with_rid($ctx, ['ok' => false, 'error' => 'transfer_id required'], 400);
            }
            $res = $helper->setShipmentMode($transferId, $mode, $status);
            json_out_with_rid($ctx, $res, $res['ok'] ? 200 : 422);
        }

        default:
            json_out_with_rid($ctx, ['ok' => false, 'error' => 'Unknown action'], 404);
    }

} catch (\Throwable $e) {
    json_out_with_rid($ctx ?? [], ['ok' => false, 'error' => 'Unhandled exception', 'hint' => 'See server logs for details.'], 500);
} finally {
    if (function_exists('cis_profile_flush')) {
        cis_profile_flush(['endpoint' => 'transfers.stock.handler', 'ms' => (int)((microtime(true) - $start) * 1000)]);
    }
}
