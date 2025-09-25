<?php
declare(strict_types=1);
/**
 * Purchase Orders — Self-Test API (JSON)
 * Enqueues an inventory.set job via POQueueBridge and returns JSON.
 * GET/POST params: product_id, outlet_id, target [, idempotency_key]
 * Example:
 *   /modules/purchase-orders/selftest/api.php?product_id=1001&outlet_id=1&target=25
 */

header('Content-Type:application/json;charset=utf-8');
header('Cache-Control:no-store');
header('X-Content-Type-Options:nosniff');

/* request id (no core deps) */
(function () {
    static $rid = null;
    if ($rid === null) {
        try { $rid = bin2hex(random_bytes(16)); }
        catch (Throwable $e) { $rid = substr(bin2hex(uniqid('',true)),0,32); }
        header('X-Request-ID: '.$rid);
    }
    $GLOBALS['__REQ_ID'] = $rid;
})();

/* include bridge only */
$root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3);
$bridge = $root . '/modules/purchase-orders/includes/queue_bridge.php';
if (!is_file($bridge)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>['code'=>'bridge_missing'],'request_id'=>$GLOBALS['__REQ_ID']]); exit;
}
require_once $bridge;

/* read input */
$in = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? (json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST)
    : $_GET;

$productId = isset($in['product_id']) ? (string)$in['product_id'] : '';
$outletId  = isset($in['outlet_id'])  ? (string)$in['outlet_id']  : '';
$target    = isset($in['target'])     ? (int)$in['target']        : null;
$idk       = isset($in['idempotency_key']) ? (string)$in['idempotency_key'] : '';

if ($productId === '' || $outletId === '' || $target === null) {
    http_response_code(400);
    echo json_encode([
        'ok'=>false,
        'error'=>['code'=>'bad_request','message'=>'product_id, outlet_id, target required'],
        'request_id'=>$GLOBALS['__REQ_ID']
    ]); exit;
}

/* enqueue */
try {
    $actor = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
    $idem  = $idk !== '' ? $idk : ('po:selftest:'.$productId.':'.$outletId.':set:'.$target);
    $res   = POQueueBridge::enqueueInventorySet($productId,$outletId,(int)$target,$actor,$idem);

    $jobId = $res['data']['job_id'] ?? null;
    $trace = $jobId ? '/assets/services/queue/public/pipeline.trace.php?job='.rawurlencode((string)$jobId) : null;

    http_response_code(200);
    echo json_encode([
        'ok' => (bool)($res['ok'] ?? false),
        'request_id' => $GLOBALS['__REQ_ID'],
        'data' => [
            'job_id' => $jobId,
            'idempotency_key' => $res['data']['idempotency_key'] ?? $idem,
            'product_id' => $productId,
            'outlet_id'  => $outletId,
            'target'     => $target,
            'links' => [
                'queue_status' => '/assets/services/queue/public/queue.status.php',
                'trace'        => $trace
            ]
        ],
        'bridge_raw' => $res
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>['code'=>'enqueue_failed','message'=>$e->getMessage()],'request_id'=>$GLOBALS['__REQ_ID']]);
}
