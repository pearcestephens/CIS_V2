<?php
declare(strict_types=1);
/**
 * modules/purchase-orders/core/Logger.php
 * Minimal PO telemetry sink for system-wide dispatcher.
 */

if (!function_exists('po_log_page_view')) {
    /**
     * @param array{view:string,actor_user_id?:int,actor_role?:string} $ctx
     */
    function po_log_page_view(array $ctx): void {
        try {
            // For now, just forward to transfer_audit_log as a generic module event if available
            if (!function_exists('cis_pdo')) { return; }
            $pdo = cis_pdo();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = 'INSERT INTO transfer_audit_log (
                entity_type, action, status, actor_type, actor_id, metadata, created_at
            ) VALUES (
                :entity_type, :action, :status, :actor_type, :actor_id, :metadata, NOW()
            )';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':entity_type' => 'purchase_order.page',
                ':action' => 'page_view:' . ($ctx['view'] ?? ''),
                ':status' => 'info',
                ':actor_type' => 'user',
                ':actor_id' => (string)($ctx['actor_user_id'] ?? ''),
                ':metadata' => json_encode($ctx, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $e) {
            error_log('[po_log_page_view] '.$e->getMessage());
        }
    }
}

if (!function_exists('po_log_page_perf')) {
    /**
     * @param array{view:string,ms:int} $ctx
     */
    function po_log_page_perf(array $ctx): void {
        try {
            if (!function_exists('cis_pdo')) { return; }
            $pdo = cis_pdo();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = 'INSERT INTO transfer_audit_log (
                entity_type, action, status, actor_type, metadata, processing_time_ms, created_at
            ) VALUES (
                :entity_type, :action, :status, :actor_type, :metadata, :processing_time_ms, NOW()
            )';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':entity_type' => 'purchase_order.page',
                ':action' => 'page_perf:' . ($ctx['view'] ?? ''),
                ':status' => 'info',
                ':actor_type' => 'system',
                ':metadata' => json_encode($ctx, JSON_UNESCAPED_SLASHES),
                ':processing_time_ms' => (int)($ctx['ms'] ?? 0),
            ]);
        } catch (Throwable $e) {
            error_log('[po_log_page_perf] '.$e->getMessage());
        }
    }
}
