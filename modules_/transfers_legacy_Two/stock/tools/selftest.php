<?php
declare(strict_types=1);

// /modules/transfers/stock/tools/selftest.php
// Usage: /modules/transfers/stock/tools/selftest.php?transfer_pk=12345[&product_id=1001&qty=2]
header('Content-Type:application/json;charset=utf-8');

try {
    require_once dirname(__DIR__, 2) . '/_shared/QueueClient.php';

    $tid = (int)($_GET['transfer_pk'] ?? 0);
    if ($tid <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'transfer_pk required']);
        exit;
    }

    $qc = new TransferQueueClient(); // uses /modules/transfers/stock/ajax base + X-API-Key

    // Optional product item to include in the parcel (MVP handler allows auto-attach if omitted)
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
    $qty       = isset($_GET['qty']) ? (int)$_GET['qty'] : null;

    $plan = ['parcels' => [['weight_g' => 100]]];
    if ($productId && $qty && $qty > 0) {
        $plan['parcels'][0]['items'] = [['product_id' => $productId, 'qty' => $qty]];
    }

    // Generate MVP label (idempotent)
    $label = $qc->label($tid, $plan, 'MVP', 'tx:' . $tid . ':label:' . md5(json_encode($plan)));
    $hint = null;
    if (is_array($label) && isset($label['ok']) && $label['ok'] === false) {
        $hint = 'If this is a fresh environment, ensure transfer_* tables exist and transfer_id has rows in transfer_items. Run module migrations and retry with a real transfer_id.';
    }

    http_response_code(200);
    echo json_encode([
        'ok'     => true,
        'flow'   => 'label (MVP)',
        'inputs' => ['transfer_pk' => $tid, 'product_id' => $productId, 'qty' => $qty],
        'label'  => $label,
        'hint'   => $hint,
        'links'  => [
            'queue_status'   => 'https://staff.vapeshed.co.nz/assets/services/queue/public/queue.status.php',
            'pipeline_trace' => 'https://staff.vapeshed.co.nz/assets/services/queue/public/pipeline.trace.php',
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
