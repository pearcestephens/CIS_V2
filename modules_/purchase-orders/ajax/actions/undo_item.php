<?php
declare(strict_types=1);

/**
 * undo_item.php
 * Reset a PO line’s received qty to 0 and enqueue inventory.command("set") to reflect the rollback.
 *
 * Inputs (POST):
 *   po_id        int      (required)
 *   product_id   string   (required)
 *   live         bool     (optional; default true)
 *   csrf / X-CSRF-Token  (required)
 *
 * Response:
 *   { success, request_id, data: { po_id, product_id, qty_received:0, delta, queue:{queued,job_id,idk,note} } }
 */

$ctx = $GLOBALS['__po_ctx'] ?? ['uid' => 0];

$poId = (int)($_POST['po_id'] ?? 0);
$pid  = isset($_POST['product_id']) ? (string)$_POST['product_id'] : '';
$live = isset($_POST['live']) ? (bool)filter_var($_POST['live'], FILTER_VALIDATE_BOOLEAN) : true;

if ($poId <= 0)  po_jresp(false, ['code' => 'bad_request', 'message' => 'po_id required'], 422);
if ($pid === '') po_jresp(false, ['code' => 'bad_request', 'message' => 'product_id required'], 422);

try {
    $pdo = po_pdo();

    $st = $pdo->prepare('SELECT status,outlet_id FROM purchase_orders WHERE purchase_order_id = ? LIMIT 1');
    $st->execute([$poId]);
    $hdr = $st->fetch();
    if (!$hdr) po_jresp(false, ['code' => 'not_found', 'message' => 'PO not found'], 404);
    if ((int)($hdr['status'] ?? 0) === 1) po_jresp(false, ['code' => 'readonly', 'message' => 'Purchase order is completed'], 409);

    $outletId = (string)($hdr['outlet_id'] ?? '');

    $orderQtyCol = po_has_column($pdo, 'purchase_order_line_items', 'order_qty') ? 'order_qty'
                  : (po_has_column($pdo, 'purchase_order_line_items', 'qty_ordered') ? 'qty_ordered' : 'order_qty');
    $qtyArrCol   = po_has_column($pdo, 'purchase_order_line_items', 'qty_arrived') ? 'qty_arrived' : 'qty_received';

    $sel = $pdo->prepare("SELECT {$orderQtyCol} AS expected, COALESCE({$qtyArrCol},0) AS current
                          FROM purchase_order_line_items
                          WHERE purchase_order_id = ? AND product_id = ? LIMIT 1");
    $sel->execute([$poId, $pid]);
    $line = $sel->fetch();
    if (!$line) po_jresp(false, ['code' => 'not_found', 'message' => 'Product not found in this purchase order'], 404);

    $current = (int)$line['current'];
    if ($current === 0) {
        po_jresp(true, [
            'po_id'        => $poId,
            'product_id'   => $pid,
            'qty_received' => 0,
            'delta'        => 0,
            'queue'        => ['queued' => false, 'note' => 'already_zero']
        ]);
    }

    // reset to zero
    $upd = $pdo->prepare("UPDATE purchase_order_line_items SET {$qtyArrCol} = 0 WHERE purchase_order_id = :po AND product_id = :pid");
    $upd->execute([':po' => $poId, ':pid' => $pid]);

    $delta = -$current;

    // queue inventory set (authoritative target = current_on_hand + delta)
    $queueInfo = ['queued' => false, 'job_id' => null, 'idk' => null, 'note' => null, 'target_on_hand' => null];

    if ($live && $delta !== 0) {
        try {
            $q = $pdo->prepare('SELECT inventory_level FROM vend_inventory WHERE product_id = :p AND outlet_id = :o LIMIT 1');
            $q->execute([':p' => $pid, ':o' => $outletId]);
            $curOnHand = (int)($q->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            $curOnHand = 0;
        }
        $target = max(0, $curOnHand + $delta);
        $queueInfo['target_on_hand'] = $target;

        require_once __DIR__ . '/../includes/queue_bridge.php';
        $idemKey = 'po:' . (int)$poId . ':product:' . (string)$pid . ':set:' . (int)$target;

        $qres = POQueueBridge::enqueueInventorySet((string)$pid, (string)$outletId, (int)$target, (int)($ctx['uid'] ?? 0), $idemKey);

        $queueInfo['queued'] = (bool)($qres['ok'] ?? false);
        $queueInfo['job_id'] = $qres['data']['job_id'] ?? null;
        $queueInfo['idk']    = $qres['data']['idempotency_key'] ?? $idemKey;
        $queueInfo['note']   = $queueInfo['queued'] ? 'queued:inventory.command' : 'enqueue_failed';
    }

    po_insert_event(
        $pdo,
        $poId,
        'line.undo',
        [
            'product_id'     => $pid,
            'qty_prev'       => $current,
            'delta'          => $delta,
            'target_on_hand' => $queueInfo['target_on_hand'],
            'queue'          => $queueInfo,
        ],
        (int)$ctx['uid']
    );

    po_jresp(true, [
        'po_id'        => $poId,
        'product_id'   => $pid,
        'qty_received' => 0,
        'delta'        => $delta,
        'queue'        => $queueInfo,
    ]);

} catch (Throwable $e) {
    po_jresp(false, ['code' => 'internal_error', 'message' => $e->getMessage()], 500);
}
