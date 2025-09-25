<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type:application/json;charset=utf-8');

/**
 * Self-test consumer:
 * - Pulls up to N rows from inventory_adjust_requests with status IN ('pending','queued')
 * - Applies delta to vend_inventory.inventory_level (safe ≥ 0)
 * - Sets status='done', fills processed_at, clears error_msg
 */

if (defined('SELFTEST_TOKEN') && SELFTEST_TOKEN !== '') {
    $tok = $_GET['token'] ?? '';
    if ($tok !== SELFTEST_TOKEN) {
        json_error('forbidden', 'forbidden', [], 403);
    }
}

$pdo   = db();
$limit = 20;
$done  = 0;
$errors = [];

try {
    for ($i = 0; $i < $limit; $i++) {
        // Lock one row
        $row = $pdo->query("
            SELECT request_id, outlet_id, product_id, delta
            FROM inventory_adjust_requests
            WHERE status IN ('pending','queued')
            ORDER BY requested_at ASC
            LIMIT 1
            FOR UPDATE
        ")->fetch();

        if (!$row) break;

        $id  = (int)$row['request_id'];
        $pid = (string)$row['product_id'];
        $oid = (string)$row['outlet_id'];
        $dlt = (int)$row['delta'];

        $upd = $pdo->prepare("UPDATE inventory_adjust_requests SET status='processing', updated_at=NOW() WHERE request_id=:id AND status IN ('pending','queued') LIMIT 1");
        $upd->execute([':id' => $id]);
        if ($upd->rowCount() !== 1) continue;

        try {
            // Current level
            $curQ = $pdo->prepare("SELECT inventory_level FROM vend_inventory WHERE product_id=:p AND outlet_id=:o LIMIT 1");
            $curQ->execute([':p' => $pid, ':o' => $oid]);
            $cur  = (int)($curQ->fetchColumn() ?: 0);
            $next = max(0, $cur + $dlt);

            // Upsert
            $ins = $pdo->prepare("
                INSERT INTO vend_inventory (product_id, outlet_id, inventory_level, updated_at)
                VALUES (:p, :o, :q, NOW())
                ON DUPLICATE KEY UPDATE inventory_level = VALUES(inventory_level), updated_at = NOW()
            ");
            $ins->execute([':p' => $pid, ':o' => $oid, ':q' => $next]);

            $ok = $pdo->prepare("UPDATE inventory_adjust_requests SET status='done', error_msg=NULL, processed_at=NOW(), updated_at=NOW() WHERE request_id=:id LIMIT 1");
            $ok->execute([':id' => $id]);
            $done++;
        } catch (Throwable $e) {
            $msg = mb_substr($e->getMessage(), 0, 2000);
            $pdo->prepare("UPDATE inventory_adjust_requests SET status='failed', error_msg=:e, updated_at=NOW() WHERE request_id=:id LIMIT 1")
                ->execute([':e' => $msg, ':id' => $id]);
            $errors[] = ['request_id' => $id, 'error' => $msg];
        }
    }

    json_success([
        'processed' => $done,
        'errors'    => $errors,
        'remaining' => (int)$pdo->query("SELECT COUNT(*) c FROM inventory_adjust_requests WHERE status IN ('pending','queued')")->fetch()['c']
    ]);
} catch (Throwable $e) {
    json_error('consumer_error', 'server_error', ['message' => $e->getMessage()], 500);
}
