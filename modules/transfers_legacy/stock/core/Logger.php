<?php
declare(strict_types=1);

/**
 * Stock Transfers Logger – page view logging into transfer_logs and transfer_audit_log
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

if (!function_exists('stx_db')) {
    function stx_db(): \PDO {
        static $pdo = null;
        if ($pdo instanceof \PDO) return $pdo;
        if (function_exists('cis_pdo')) {
            /** @var \PDO $p */
            $p = cis_pdo();
            $p->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo = $p;
        }
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
            $GLOBALS['pdo']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo = $GLOBALS['pdo'];
        }
        throw new \RuntimeException('No PDO available');
    }
}

if (!function_exists('stx_log_page_view')) {
    /**
     * Log a UI page view to both logs.
     * @param array $ctx Keys: view (dashboard|pack|receive|...), transfer_id?, actor_user_id?, actor_role?, entity_type?
     */
    function stx_log_page_view(array $ctx = []): void {
        try {
            if (!isset($_SESSION)) session_start();
            $pdo   = stx_db();
            $view  = (string)($ctx['view'] ?? 'unknown');
            $tid   = isset($ctx['transfer_id']) ? (int)$ctx['transfer_id'] : (int)($_GET['transfer'] ?? 0);
            $uid   = isset($ctx['actor_user_id']) ? (int)$ctx['actor_user_id'] : (int)($_SESSION['userID'] ?? 0);
            $role  = (string)($ctx['actor_role'] ?? ($_SESSION['role'] ?? $_SESSION['userRole'] ?? 'user'));
            $etype = (string)($ctx['entity_type'] ?? 'transfer');
            $trace = bin2hex(random_bytes(8));
            $sess  = session_id();
            $ip    = $_SERVER['REMOTE_ADDR']     ?? '';
            $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ref   = $_SERVER['HTTP_REFERER']    ?? '';
            $uri   = $_SERVER['REQUEST_URI']     ?? '';

            // transfer_logs
            $sql1 = 'INSERT INTO transfer_logs (
                transfer_id, shipment_id, item_id, parcel_id, staff_transfer_id, event_type, event_data,
                actor_user_id, actor_role, severity, source_system, trace_id, customer_id, created_at
            ) VALUES (
                :transfer_id, NULL, NULL, NULL, NULL, :event_type, :event_data,
                :actor_user_id, :actor_role, :severity, :source_system, :trace_id, NULL, NOW()
            )';
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([
                ':transfer_id'  => $tid ?: null,
                ':event_type'   => 'page_view:' . $view,
                ':event_data'   => json_encode(['uri'=>$uri,'referer'=>$ref], JSON_UNESCAPED_SLASHES),
                ':actor_user_id'=> $uid ?: null,
                ':actor_role'   => $role,
                ':severity'     => 'info',
                ':source_system'=> 'cis.transfers.ui',
                ':trace_id'     => $trace,
            ]);

            // transfer_audit_log
            $sql2 = 'INSERT INTO transfer_audit_log (
                entity_type, entity_pk, transfer_pk, transfer_id, vend_consignment_id, vend_transfer_id,
                action, status, actor_type, actor_id, outlet_from, outlet_to, data_before, data_after,
                metadata, error_details, processing_time_ms, api_response, session_id, ip_address, user_agent, created_at
            ) VALUES (
                :entity_type, :entity_pk, :transfer_pk, :transfer_id, NULL, NULL,
                :action, :status, :actor_type, :actor_id, NULL, NULL, NULL, NULL,
                :metadata, NULL, :processing_time_ms, NULL, :session_id, :ip_address, :user_agent, NOW()
            )';
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([
                ':entity_type'        => $etype,
                ':entity_pk'          => $tid ?: null,
                ':transfer_pk'        => $tid ?: null,
                ':transfer_id'        => $tid ?: null,
                ':action'             => 'view',
                ':status'             => 'ok',
                ':actor_type'         => 'user',
                ':actor_id'           => (string)$uid,
                ':metadata'           => json_encode(['view'=>$view,'uri'=>$uri,'referer'=>$ref,'trace_id'=>$trace], JSON_UNESCAPED_SLASHES),
                ':processing_time_ms' => 0,
                ':session_id'         => $sess,
                ':ip_address'         => $ip,
                ':user_agent'         => $ua,
            ]);
        } catch (\Throwable $e) {
            error_log('[stx_log_page_view] '.$e->getMessage());
        }
    }
}

if (!function_exists('stx_log_page_perf')) {
    /**
     * Log server-side page performance into audit log.
     * @param array $ctx Keys: view, ms, transfer_id?
     */
    function stx_log_page_perf(array $ctx = []): void {
        try {
            $pdo = stx_db();
            $view = (string)($ctx['view'] ?? 'unknown');
            $ms   = (int)($ctx['ms'] ?? 0);
            $tid  = $ctx['transfer_id'] ?? null;
            $sql = 'INSERT INTO transfer_audit_log (
                entity_type, entity_pk, transfer_pk, transfer_id,
                action, status, actor_type, actor_id, metadata, processing_time_ms, created_at,
                vend_consignment_id, vend_transfer_id, outlet_from, outlet_to, data_before, data_after, error_details, api_response, session_id, ip_address, user_agent
            ) VALUES (
                :entity_type, :entity_pk, :transfer_pk, :transfer_id,
                :action, :status, :actor_type, :actor_id, :metadata, :processing_time_ms, NOW(),
                NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, :session_id, :ip_address, :user_agent
            )';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':entity_type' => 'transfer',
                ':entity_pk'   => $tid ?: null,
                ':transfer_pk' => $tid ?: null,
                ':transfer_id' => $tid ?: null,
                ':action'      => 'page_perf',
                ':status'      => 'ok',
                ':actor_type'  => 'system',
                ':actor_id'    => (string)($_SESSION['userID'] ?? 0),
                ':metadata'    => json_encode(['view'=>$view], JSON_UNESCAPED_SLASHES),
                ':processing_time_ms' => $ms,
                ':session_id'  => session_id(),
                ':ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
                ':user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        } catch (\Throwable $e) {
            error_log('[stx_log_page_perf] '.$e->getMessage());
        }
    }
}
