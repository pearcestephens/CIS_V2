<?php
declare(strict_types=1);

// /modules/purchase-orders/tools/selftest.php
// Usage: /modules/purchase-orders/tools/selftest.php?product_id=123&outlet_id=1&target=42
header('Content-Type:application/json;charset=utf-8');

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/purchase-orders/includes/queue_bridge.php';

    $pid    = (string)($_GET['product_id'] ?? '');
    $oid    = (string)($_GET['outlet_id']  ?? '');
    $target = (int)($_GET['target']        ?? 0);
    $uid    = (int)($_SESSION['userID']    ?? 0);

    if ($pid === '' || $oid === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'product_id and outlet_id required']);
        exit;
    }

    $idem = 'po:selftest:' . $pid . ':' . $oid . ':set:' . $target;
    $res  = POQueueBridge::enqueueInventorySet($pid, $oid, $target, $uid, $idem);
    $ok   = (bool)($res['ok'] ?? false);

    http_response_code($ok ? 200 : 500);
    echo json_encode([
        'ok'   => $ok,
        'note' => 'enqueued inventory.command(set) via queue bridge',
        'data' => $res['data'] ?? null,
        'links' => [
            'queue_status'  => 'https://staff.vapeshed.co.nz/assets/services/queue/public/queue.status.php',
            'pipeline_trace'=> isset($res['data']['job_id']) ? ('https://staff.vapeshed.co.nz/assets/services/queue/public/pipeline.trace.php?job=' . urlencode((string)$res['data']['job_id'])) : null,
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
