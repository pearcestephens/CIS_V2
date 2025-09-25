<?php
/**
 * modules/transfers/stock-transfers/scripts/backfill_transfer_carrier_orders.php
 * Purpose: Backfill transfer_carrier_orders from DEV runtime JSON state (if any).
 * Safe to run multiple times. Only inserts rows that are missing or updates payload if changed.
 *
 * Usage (CLI only):
 *   php /home/master/applications/jcepnzzkmj/public_html/modules/transfers/stock-transfers/scripts/backfill_transfer_carrier_orders.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') { http_response_code(403); echo "Forbidden"; exit(1); }

// Locate app.php for consistent project context; fall back to env for DB.
$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
if (!is_string($docroot) || $docroot === '' || !is_file($docroot . '/app.php')) {
    $candidates = [ dirname(__DIR__, 4), dirname(__DIR__, 5) ];
    foreach ($candidates as $cand) { if ($cand && is_dir($cand) && is_file($cand . '/app.php')) { $docroot = $cand; break; } }
    if ($docroot) { $_SERVER['DOCUMENT_ROOT'] = $docroot; }
}
if ($docroot && is_file($docroot . '/app.php')) { require_once $docroot . '/app.php'; }

// Local helpers from module
require_once __DIR__ . '/../ajax/tools.php';

function _bf_json_path(): string { return dirname(__DIR__) . '/runtime/shipments_dev.json'; }

try {
    $pdo = stx_pdo();
    if (!stx_table_exists($pdo, 'transfer_carrier_orders')) {
        fwrite(STDERR, "transfer_carrier_orders missing; run apply_transfer_carrier_orders.php first.\n");
        exit(2);
    }
    $file = _bf_json_path();
    if (!is_file($file)) { echo "No DEV runtime file to backfill: {$file}\n"; exit(0); }
    $raw = file_get_contents($file);
    $state = json_decode((string)$raw, true);
    if (!is_array($state) || !isset($state['orders'])) { echo "No orders section in runtime JSON.\n"; exit(0); }
    $orders = $state['orders'];
    $ins = $pdo->prepare('INSERT INTO transfer_carrier_orders (transfer_id, carrier, order_id, order_number, payload) VALUES (?,?,?,?,?)
      ON DUPLICATE KEY UPDATE order_id=VALUES(order_id), order_number=VALUES(order_number), payload=VALUES(payload), updated_at=NOW()');
    $n=0; $u=0;
    foreach ($orders as $transferId => $byCarrier) {
        $tid = (int)$transferId; if ($tid<=0 || !is_array($byCarrier)) continue;
        foreach ($byCarrier as $carrier => $row) {
            $orderId = isset($row['order_id']) && $row['order_id']!=='' ? (string)$row['order_id'] : null;
            $orderNumber = isset($row['order_number']) ? (string)$row['order_number'] : ('TR-' . $tid);
            $payload = isset($row['payload']) && is_array($row['payload']) ? $row['payload'] : $row;
            $ins->execute([$tid, (string)$carrier, $orderId, $orderNumber, json_encode($payload, JSON_UNESCAPED_SLASHES)]);
            $n++;
        }
    }
    echo "Backfill complete. Rows upserted: {$n}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Backfill failed: ".$e->getMessage()."\n");
    exit(1);
}
