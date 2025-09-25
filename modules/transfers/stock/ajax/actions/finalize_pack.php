<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/csrf.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/middleware/kernel.php';
require_once dirname(__DIR__, 3) . '/lib/PackHelper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$ctx = ['request_id' => null];

try {
    $pipeline = mw_pipeline([
        mw_trace(),
        mw_security_headers(),
        mw_json_or_form_normalizer(),
        mw_csrf_or_api_key(getenv('TEST_CLI_API_KEY') ?: 'TEST-CLI-KEY-123'),
        mw_validate_content_type(['application/json', 'application/x-www-form-urlencoded']),
        mw_content_length_limit(128 * 1024),
        mw_rate_limit('transfers.stock.finalize', 90, 60),
    ]);
    $ctx = $pipeline([]);

    $input      = $ctx['input'] ?? [];
    $query      = $ctx['query'] ?? [];
    $transferId = (int) ($input['transfer_pk'] ?? $input['transfer'] ?? $query['transfer'] ?? 0);

    if ($transferId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success'    => false,
            'error'      => 'transfer_pk required',
            'request_id' => $ctx['request_id'] ?? null,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $payload = cisv2_finalize_pack($transferId);
    $code    = ($payload['success'] ?? false) ? 200 : 422;
    http_response_code($code);
    $payload['request_id'] = $ctx['request_id'] ?? null;
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success'    => false,
        'error'      => 'exception',
        'hint'       => $e->getMessage(),
        'request_id' => $ctx['request_id'] ?? null,
    ], JSON_UNESCAPED_SLASHES);
}

/**
 * Perform the finalize pack transition on the canonical transfers table.
 */
function cisv2_finalize_pack(int $transferId): array
{
    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, status, type FROM transfers WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $transferId]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transfer) {
            throw new \RuntimeException('Transfer not found');
        }

        if ((string) ($transfer['type'] ?? '') !== 'stock') {
            throw new \RuntimeException('Transfer type not eligible for stock packing');
        }

        $currentStatus = (string) ($transfer['status'] ?? 'draft');
        if ($currentStatus === 'sent') {
            $pdo->commit();
            return ['success' => true, 'status' => 'sent', 'info' => 'already_sent'];
        }

        $nextStatus = 'sent';
        $update = $pdo->prepare('UPDATE transfers SET status = :status, updated_at = NOW() WHERE id = :id');
        $update->execute([
            ':status' => $nextStatus,
            ':id'     => $transferId,
        ]);

        $pdo->commit();

        $helper = new \CIS\Transfers\Stock\PackHelper();
        $helper->log($transferId, 'pack.finalized', [
            'previous_status' => $currentStatus,
            'new_status'      => $nextStatus,
        ]);
        $helper->audit($transferId, 'pack.finalized', [
            'from' => $currentStatus,
            'to'   => $nextStatus,
        ]);

        if (function_exists('cisv2_queue_enqueue')) {
            $payload = ['transfer_id' => $transferId];
            cisv2_queue_enqueue('transfer.after_sent', $payload);
            cisv2_queue_enqueue('transfer.after_packed', $payload);
        }

        return [
            'success'         => true,
            'status'          => $nextStatus,
            'previous_status' => $currentStatus,
            'changed'         => $currentStatus !== $nextStatus,
        ];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Finalize pack error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'finalize_failed'];
    }
}
