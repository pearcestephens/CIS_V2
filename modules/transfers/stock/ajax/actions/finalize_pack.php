<?php
declare(strict_types=1);

if (!defined('CISV2_ROOT')) {
    require dirname(__DIR__, 5) . '/bootstrap.php';
}

/**
 * Perform the finalize pack transition.
 */
function cisv2_finalize_pack(int $transferId): array
{
    if ($transferId <= 0) {
        return ['success' => false, 'error' => 'Missing transfer'];
    }

    $pdo = $GLOBALS['cisv2']['pdo'] ?? null;
    if (!$pdo instanceof \PDO) {
        return ['success' => false, 'error' => 'Database unavailable'];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT status FROM stock_transfers WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $transferId]);
        $transfer = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$transfer) {
            throw new \RuntimeException('Transfer not found');
        }

        if (($transfer['status'] ?? '') === 'packed') {
            $pdo->commit();
            return ['success' => true, 'info' => 'Already packed'];
        }

        $update = $pdo->prepare("UPDATE stock_transfers SET status = 'packed', packed_at = NOW() WHERE id = :id");
        $update->execute([':id' => $transferId]);

        $pdo->commit();

        if (function_exists('cisv2_queue_enqueue')) {
            cisv2_queue_enqueue('transfer.after_packed', ['transfer_id' => $transferId]);
        }

        return ['success' => true];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Finalize pack error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Server error'];
    }
}

/**
 * Emit JSON response for direct access.
 */
function cisv2_finalize_pack_endpoint(): void
{
    header('Content-Type: application/json; charset=utf-8');
    $transferId = isset($_GET['transfer']) ? max(0, (int) $_GET['transfer']) : 0;
    $payload = cisv2_finalize_pack($transferId);
    http_response_code(($payload['success'] ?? false) ? 200 : 500);
    echo json_encode($payload);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    cisv2_finalize_pack_endpoint();
}
