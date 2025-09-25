<?php
/**
 * File: modules/transfers/stock/ajax/actions/finalize_pack.php
 * Purpose: Handle finalize action for CIS v2 pack prototype.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: CIS\Core\Response
 */
declare(strict_types=1);

use CIS\Core\Response;

/**
 * Finalize the packing operation for a transfer.
 *
 * @param array $input Request payload containing transfer and line data.
 */
function finalize_pack(array $input): void
{
    $transferId = (int) ($input['transfer_id'] ?? 0);
    if ($transferId <= 0) {
        Response::json(['ok' => false, 'error' => 'Missing transfer_id'], 400);
    }

    $linesRaw = $input['lines'] ?? [];
    if (!is_array($linesRaw)) {
        Response::json(['ok' => false, 'error' => 'Invalid lines payload'], 400);
    }

    $validated = [];
    foreach ($linesRaw as $line) {
        if (!isset($line['sku'])) {
            continue;
        }
        $sku = (string) $line['sku'];
        $packed = (int) ($line['packed'] ?? 0);
        if ($packed < 0) {
            Response::json(['ok' => false, 'error' => 'Negative qty not allowed'], 422);
        }
        $validated[] = ['sku' => $sku, 'packed' => $packed];
    }

    Response::json([
        'ok'           => true,
        'transfer_id'  => $transferId,
        'saved_lines'  => count($validated),
        'status'       => 'packed',
    ]);
}
