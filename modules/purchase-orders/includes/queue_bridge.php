<?php
declare(strict_types=1);

/**
 * POQueueBridge — DB-backed queue bridge for inventory "set to target" ops.
 *
 * Instead of including any external queue library, we enqueue into
 * inventory_adjust_requests using the module schema (delta-based).
 * The consumer (selftest/consume.php or your worker) applies deltas
 * to vend_inventory.inventory_level.
 */
final class POQueueBridge
{
    /**
     * Enqueue an inventory "set target" command.
     *
     * @param string      $productId        vend_products.id
     * @param string      $outletId         vend_outlets.id
     * @param int         $targetQty        target on-hand quantity
     * @param int|null    $actorUserId      audit-only
     * @param string|null $idempotencyKey   idempotency for this request
     * @return array { ok, data: { job_id, idempotency_key, request_id }, error? }
     */
    public static function enqueueInventorySet(
        string $productId,
        string $outletId,
        int $targetQty,
        ?int $actorUserId = null,
        ?string $idempotencyKey = null
    ): array {
        try {
            $pdo = db();

            // Resolve current level
            $curQ = $pdo->prepare("SELECT inventory_level FROM vend_inventory WHERE product_id=:p AND outlet_id=:o LIMIT 1");
            $curQ->execute([':p' => $productId, ':o' => $outletId]);
            $current = (int)($curQ->fetchColumn() ?: 0);

            $delta = max(0, $targetQty) - $current;
            $idk   = $idempotencyKey ?: sprintf('inv:set:%s:%s:%d', $productId, $outletId, max(0, $targetQty));

            // Upsert into inventory_adjust_requests (status pending)
            $q = $pdo->prepare("
                INSERT INTO inventory_adjust_requests
                    (transfer_id, outlet_id, product_id, delta, reason, source, status, idempotency_key, requested_by, requested_at)
                VALUES (NULL, :o, :p, :d, 'po-bridge-set', 'po.receive', 'pending', :k, :u, NOW())
                ON DUPLICATE KEY UPDATE delta=VALUES(delta), reason=VALUES(reason), source=VALUES(source),
                                        status='pending', requested_at=NOW()
            ");
            $q->execute([
                ':o' => $outletId,
                ':p' => $productId,
                ':d' => $delta,
                ':k' => $idk,
                ':u' => (int)($actorUserId ?? 0),
            ]);

            $jobId = (int)$pdo->lastInsertId();
            if ($jobId === 0) {
                $sel = $pdo->prepare("SELECT request_id FROM inventory_adjust_requests WHERE idempotency_key=:k ORDER BY request_id DESC LIMIT 1");
                $sel->execute([':k' => $idk]);
                $jobId = (int)($sel->fetchColumn() ?: 0);
            }

            // Fabricate a request id for trace parity
            $trace = function_exists('cis_request_id') ? cis_request_id() : bin2hex(random_bytes(8));

            return ['ok' => true, 'data' => ['job_id' => $jobId ?: null, 'idempotency_key' => $idk, 'request_id' => $trace]];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => ['code' => 'queue_bridge_error', 'message' => $e->getMessage()]];
        }
    }
}
