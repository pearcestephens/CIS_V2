<?php declare(strict_types=1);
/**
 * File: modules/transfers/stock/ajax/actions/receive_set_qty.php
 * Purpose: Persist received quantity for a transfer line during receiving.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: cis bootstrap, transfer_items table
 */

require_once dirname(__DIR__, 6) . '/bootstrap.php';
header('Content-Type: application/json');

try {
    cis_require_login();

    $rateKey = ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . '|' . (int)($_SESSION['user_id'] ?? $_SESSION['userID'] ?? 0);
    if (!cis_rate_limit('transfers.receive.set_qty', $rateKey, 240, 60)) {
        throw new RuntimeException('Rate limited');
    }

    $input = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    $itemId = (int)($input['item_id'] ?? 0);
    $qty    = (int)($input['qty'] ?? -1);

    if ($itemId <= 0 || $qty < 0 || $qty > 100000) {
        throw new InvalidArgumentException('Invalid quantity payload');
    }

    $pdo = db_rw();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, transfer_id, qty_sent_total FROM transfer_items WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $itemId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Transfer item not found');
    }

    $sentTotal = (int)$row['qty_sent_total'];
    if ($qty > $sentTotal) {
        throw new InvalidArgumentException('Received quantity exceeds sent total');
    }

    $upd = $pdo->prepare('UPDATE transfer_items SET qty_received_total = :qty, updated_at = NOW() WHERE id = :id');
    $upd->execute([':qty' => $qty, ':id' => $itemId]);

    $pdo->commit();

    cis_log('INFO', 'transfers', 'receive.set_qty', [
        'item_id'     => $itemId,
        'transfer_id' => (int)$row['transfer_id'],
        'qty'         => $qty,
    ]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}
