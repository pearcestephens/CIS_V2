<?php
declare(strict_types=1);

/**
 * update_live_stock.php
 *
 * Sets target stock for a product@outlet and enqueues an inventory_adjust_requests row
 * using the module’s schema (delta-based, status ENUM).
 * - Reads product by id or sku; outlet by id or name
 * - Computes delta = requested - current inventory_level
 * - Optionally writes live vend_inventory.inventory_level if live=1
 * - Idempotent (Idempotency-Key header)
 *
 * Requires: includes/bootstrap.php (db/json helpers, idem_store)
 */

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_post();
require_csrf();

$reqId = req_id();
$pdo   = db();

$productKey = trim((string)($_POST['product_id'] ?? ''));
$outletKey  = trim((string)($_POST['outlet_id'] ?? ''));
$stockRaw   = (string)($_POST['stock'] ?? '');
$liveRaw    = (string)($_POST['live'] ?? '0');

if ($productKey === '' || $outletKey === '' || $stockRaw === '') {
    json_error('Missing one or more required fields: product_id, outlet_id, stock', 'bad_request');
}

if (!preg_match('/^-?\d+$/', $stockRaw)) {
    json_error('stock must be an integer', 'validation_error');
}

$requestedQty = max(0, (int)$stockRaw);
$live         = ($liveRaw === '1' || strtolower($liveRaw) === 'true') ? 1 : 0;
$idemKey      = header_get('Idempotency-Key') ?? '';

$intent = canonical_json([
    'action'     => 'update_live_stock',
    'product_in' => $productKey,
    'outlet_in'  => $outletKey,
    'stock'      => $requestedQty,
    'live'       => $live,
]);
$intentHash = hash('sha256', $intent);

// Idempotency replay
if ($idemKey !== '') {
    $found = idem_lookup($pdo, $idemKey);
    if ($found) {
        if (hash_equals($found['request_hash'], $intentHash)) {
            header('Content-Type:application/json;charset=utf-8');
            echo $found['response_json'];
            exit;
        }
        json_error('Idempotency key conflict', 'idem_conflict', ['hint' => 'Use a new Idempotency-Key for a changed request'], 409);
    }
}

try {
    $pdo->beginTransaction();

    // Resolve product (id → fallback sku)
    $pStmt = $pdo->prepare("SELECT id, sku, name FROM vend_products WHERE id=:x LIMIT 1");
    $pStmt->execute([':x' => $productKey]);
    $prod = $pStmt->fetch();
    if (!$prod) {
        $pStmt = $pdo->prepare("SELECT id, sku, name FROM vend_products WHERE sku=:x LIMIT 1");
        $pStmt->execute([':x' => $productKey]);
        $prod = $pStmt->fetch();
    }
    if (!$prod) {
        throw new RuntimeException('Unknown product');
    }
    $prodIdDb = (string)$prod['id'];

    // Resolve outlet (id → fallback name)
    $oStmt = $pdo->prepare("SELECT id, name FROM vend_outlets WHERE id=:y LIMIT 1");
    $oStmt->execute([':y' => $outletKey]);
    $out = $oStmt->fetch();
    if (!$out) {
        $oStmt = $pdo->prepare("SELECT id, name FROM vend_outlets WHERE name=:y LIMIT 1");
        $oStmt->execute([':y' => $outletKey]);
        $out = $oStmt->fetch();
    }
    if (!$out) {
        throw new RuntimeException('Unknown outlet');
    }
    $outletIdDb = (string)$out['id'];

    // Current inventory_level
    $iStmt = $pdo->prepare("SELECT inventory_level FROM vend_inventory WHERE product_id=:p AND outlet_id=:o LIMIT 1");
    $iStmt->execute([':p' => $prodIdDb, ':o' => $outletIdDb]);
    $inv     = $iStmt->fetch();
    $prevQty = $inv ? (int)$inv['inventory_level'] : 0;

    $delta = $requestedQty - $prevQty;

    // Enqueue delta change into inventory_adjust_requests (schema/002)
    $qStmt = $pdo->prepare("
        INSERT INTO inventory_adjust_requests
            (transfer_id, outlet_id, product_id, delta, reason, source, status, idempotency_key, requested_by, requested_at)
        VALUES (NULL, :o, :p, :d, 'manual_adjust_via_module', 'po.update_live_stock', 'pending', :k, :uid, NOW())
        ON DUPLICATE KEY UPDATE
            delta = VALUES(delta),
            reason = VALUES(reason),
            source = VALUES(source),
            status = 'pending',
            requested_at = NOW()
    ");
    $qStmt->execute([
        ':o'   => $outletIdDb,
        ':p'   => $prodIdDb,
        ':d'   => $delta,
        ':k'   => ($idemKey !== '' ? $idemKey : null),
        ':uid' => (int)($_SESSION['userID'] ?? 0),
    ]);
    $queueId = (int)$pdo->lastInsertId();

    // Optional live upsert into vend_inventory
    if ($live === 1) {
        $invUp = $pdo->prepare("
            INSERT INTO vend_inventory (product_id, outlet_id, inventory_level, updated_at)
            VALUES (:p, :o, :q, NOW())
            ON DUPLICATE KEY UPDATE inventory_level = VALUES(inventory_level), updated_at = NOW()
        ");
        $invUp->execute([':p' => $prodIdDb, ':o' => $outletIdDb, ':q' => $requestedQty]);
    }

    $respData = [
        'product' => ['id' => $prodIdDb, 'sku' => $prod['sku'], 'name' => $prod['name']],
        'outlet'  => ['id' => $outletIdDb, 'name' => $out['name']],
        'previous_qty' => $prevQty,
        'new_qty'      => $requestedQty,
        'delta'        => $delta,
        'queued'       => true,
        'queue_id'     => $queueId ?: null,
        'live_upsert'  => ($live === 1),
    ];

    $envelope = build_success($respData, $reqId);
    if ($idemKey !== '') {
        idem_store($pdo, $idemKey, $intentHash, $envelope);
    }

    $pdo->commit();
    header('Content-Type:application/json;charset=utf-8');
    echo json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $http = ($e instanceof RuntimeException) ? 404 : 500;
    $code = ($e instanceof RuntimeException) ? 'not_found' : 'server_error';
    json_error($e->getMessage(), $code, [], $http);
}
