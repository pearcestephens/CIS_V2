<?php
declare(strict_types=1);

/**
 * save_progress.php
 * Line-level receive update + enqueue inventory.command("set") via POQueueBridge.
 *
 * Inputs (POST):
 *   po_id           int      (required)
 *   product_id      string   (required)
 *   qty_received    int      (>=0, required)
 *   live            bool     (optional; default true) if true, enqueue inventory set
 *   idempotency_key string   (optional)
 *   csrf / X-CSRF-Token     (required)
 *
 * Response:
 *   { success, request_id, data: { po_id, product_id, qty_received, delta, queue:{queued,job_id,idk,note} } }
 */

$ctx = $GLOBALS['__po_ctx'] ?? ['uid' => 0];
po_verify_csrf();

$poId  = (int)($_POST['po_id'] ?? 0);
$pid   = (string)($_POST['product_id'] ?? '');
$qtyIn = $_POST['qty_received'] ?? null;
$live  = isset($_POST['live']) ? (bool)filter_var($_POST['live'], FILTER_VALIDATE_BOOLEAN) : true;
$idem  = (string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $_POST['idempotency_key'] ?? '');

if ($poId <= 0)  po_jresp(false, ['code' => 'bad_request', 'message' => 'po_id required'], 422);
if ($pid === '') po_jresp(false, ['code' => 'bad_request', 'message' => 'product_id required'], 422);
if (!is_numeric($qtyIn) || (int)$qtyIn < 0) po_jresp(false, ['code' => 'bad_request', 'message' => 'qty_received must be >= 0'], 422);

$qtyNew = (int)$qtyIn;

try {
    $pdo = po_pdo();

    // ---------- idempotency replay ----------
    $reqParams = $_POST;
    unset($reqParams['csrf'], $reqParams['csrf_token'], $reqParams['idempotency_key']);
    $reqParams['__script'] = $_SERVER['SCRIPT_NAME'] ?? '';
    $reqParams['__action'] = 'save_progress';
    ksort($reqParams);
    $canon = json_encode($reqParams, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    $reqHash = hash('sha256', (string)$canon);

    if ($idem !== '') {
        $rec = po_idem_get($pdo, $idem);
        if ($rec) {
            if ($rec['request_hash'] === $reqHash && is_array($rec['response'])) {
                http_response_code(200);
                echo json_encode($rec['response'], JSON_UNESCAPED_SLASHES);
                exit;
            }
            po_jresp(false, ['code' => 'idem_conflict', 'message' => 'Idempotency key re-used with different request body'], 409);
        }
    }

    // ---------- header / line ----------
    $hdr = $pdo->prepare('SELECT status,outlet_id FROM purchase_orders WHERE purchase_order_id = ? LIMIT 1');
    $hdr->execute([$poId]);
    $header = $hdr->fetch();
    if (!$header) po_jresp(false, ['code' => 'not_found', 'message' => 'PO not found'], 404);
    if ((int)($header['status'] ?? 0) === 1) po_jresp(false, ['code' => 'readonly', 'message' => 'Purchase order is completed'], 409);

    $outletId = (string)($header['outlet_id'] ?? '');

    $orderQtyCol = po_has_column($pdo, 'purchase_order_line_items', 'order_qty') ? 'order_qty'
                  : (po_has_column($pdo, 'purchase_order_line_items', 'qty_ordered') ? 'qty_ordered' : 'order_qty');
    $qtyArrCol   = po_has_column($pdo, 'purchase_order_line_items', 'qty_arrived') ? 'qty_arrived' : 'qty_received';
    $recvAtCol   = po_has_column($pdo, 'purchase_order_line_items', 'received_at') ? 'received_at' : null;

    $sel = $pdo->prepare("SELECT {$orderQtyCol} AS expected, COALESCE({$qtyArrCol},0) AS current
                          FROM purchase_order_line_items
                          WHERE purchase_order_id = ? AND product_id = ? LIMIT 1");
    $sel->execute([$poId, $pid]);
    $line = $sel->fetch();
    if (!$line) po_jresp(false, ['code' => 'not_found', 'message' => 'Product not found in this PO'], 404);

    $expected = (int)$line['expected'];
    $current  = (int)$line['current'];

    // Clamp to [0, expected]
    $finalQty = min(max(0, $qtyNew), max(0, $expected));
    $delta    = $finalQty - $current;

    // ---------- update line ----------
    $set = "{$qtyArrCol} = :q" . ($recvAtCol ? ", {$recvAtCol} = NOW()" : '');
    $upd = $pdo->prepare("UPDATE purchase_order_line_items SET {$set}
                          WHERE purchase_order_id = :po AND product_id = :pid");
    $upd->execute([':q' => $finalQty, ':po' => $poId, ':pid' => $pid]);

    // ---------- authoritative target on-hand & queue (when live) ----------
    $queueInfo = ['queued' => false, 'job_id' => null, 'idk' => null, 'note' => null, 'target_on_hand' => null];

    if ($live && $delta !== 0) {
        // compute current CIS on-hand & target = current + delta (safe if CIS mirror is slightly off; queue verifies in Vend)
        $target = 0;
        try {
            $q = $pdo->prepare('SELECT inventory_level FROM vend_inventory WHERE product_id = :p AND outlet_id = :o LIMIT 1');
            $q->execute([':p' => $pid, ':o' => $outletId]);
            $curOnHand = (int)($q->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            $curOnHand = 0;
        }
        $target = max(0, $curOnHand + $delta);
        $queueInfo['target_on_hand'] = $target;

        // enqueue inventory.command(set)
        require_once __DIR__ . '/../includes/queue_bridge.php';
        $idemKey = $idem !== '' ? $idem : ('po:' . (int)$poId . ':product:' . (string)$pid . ':set:' . (int)$target);

        $qres = POQueueBridge::enqueueInventorySet((string)$pid, (string)$outletId, (int)$target, (int)($ctx['uid'] ?? 0), $idemKey);

        $queueInfo['queued'] = (bool)($qres['ok'] ?? false);
        $queueInfo['job_id'] = $qres['data']['job_id'] ?? null;
        $queueInfo['idk']    = $qres['data']['idempotency_key'] ?? $idemKey;
        $queueInfo['note']   = $queueInfo['queued'] ? 'queued:inventory.command' : 'enqueue_failed';
    }

    // ---------- event ----------
    po_insert_event(
        $pdo,
        $poId,
        'line.update',
        [
            'product_id'      => $pid,
            'qty_new'         => $finalQty,
            'qty_prev'        => $current,
            'delta'           => $delta,
            'capped'          => ($qtyNew > $expected),
            'target_on_hand'  => $queueInfo['target_on_hand'],
            'queue'           => $queueInfo,
        ],
        (int)$ctx['uid']
    );

    // ---------- response & idempotency store ----------
    $response = [
        'success'    => true,
        'request_id' => $GLOBALS['__PO_REQ_ID'] ?? ($_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8))),
        'data'       => [
            'po_id'        => $poId,
            'product_id'   => $pid,
            'qty_received' => $finalQty,
            'delta'        => $delta,
            'queue'        => $queueInfo,
        ],
    ];

    if ($idem !== '') {
        po_idem_store($pdo, $idem, $reqHash, $response);
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    po_jresp(false, ['code' => 'internal_error', 'message' => $e->getMessage()], 500);
}
