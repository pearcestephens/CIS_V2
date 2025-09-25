<?php
declare(strict_types=1);

/**
 * Purchase Orders – shared tools for AJAX actions
 * Response envelope: { success, data?|error, request_id }
 */

header('Content-Type: application/json; charset=utf-8');

$__PO_REQ_ID = $GLOBALS['__po_ctx']['request_id']
  ?? ($_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8)));
// Propagate request id to clients for correlation
header('X-Request-ID: ' . $__PO_REQ_ID);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

/* -------------------- JSON responder -------------------- */
function po_jresp($ok, $payload = [], int $code = 200): never {
  global $__PO_REQ_ID;
  http_response_code($code);
  $body = ['success' => (bool)$ok, 'request_id' => $__PO_REQ_ID];
  if ($ok) {
    $body['data'] = $payload;
  } else {
    $body['error'] = is_array($payload) ? $payload : ['message' => (string)$payload];
  }
  echo json_encode($body, JSON_UNESCAPED_SLASHES);
  exit;
}

/* -------------------- Auth/CSRF -------------------- */
function po_require_login(): int {
  if (empty($_SESSION['userID'])) {
    po_jresp(false, ['code' => 'auth_required', 'message' => 'Login required'], 401);
  }
  return (int)$_SESSION['userID'];
}

function po_verify_csrf(): void {
  $t = (string)($_POST['csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  $s = (string)($_SESSION['csrf_token'] ?? '');
  if ($t === '' || $s === '' || !hash_equals($s, $t)) {
    po_jresp(false, ['code' => 'csrf_failed', 'message' => 'Invalid CSRF'], 403);
  }
}

/* -------------------- PDO + helpers -------------------- */
function po_pdo(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;
  if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    throw new RuntimeException('DB config missing (DB_HOST/DB_NAME/DB_USER/DB_PASS)');
  }
  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => true,
  ]);
  $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
  return $pdo;
}

function po_table_exists(PDO $pdo, string $table): bool {
  $q = $pdo->prepare('SHOW TABLES LIKE ?');
  $q->execute([$table]);
  return (bool)$q->fetchColumn();
}

function po_has_column(PDO $pdo, string $table, string $column): bool {
  $q = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`','``',$table) . '` LIKE ?');
  $q->execute([$column]);
  return (bool)$q->fetchColumn();
}

function po_retry(callable $fn, int $tries = 3, int $sleepMs = 120) {
  attempt:
  try { return $fn(); }
  catch (Throwable $e) {
    if (--$tries > 0 && stripos($e->getMessage(), 'deadlock') !== false) {
      usleep($sleepMs * 1000);
      goto attempt;
    }
    throw $e;
  }
}

/* -------------------- Events & receipts -------------------- */
function po_insert_event(PDO $pdo, int $poId, string $type, array $data = [], ?int $userId = null): void {
  if (!po_table_exists($pdo, 'po_events')) return;
  $stmt = $pdo->prepare('INSERT INTO po_events (purchase_order_id, event_type, event_data, created_by, created_at) VALUES (?,?,?,?,NOW())');
  $stmt->execute([$poId, $type, $data ? json_encode($data, JSON_UNESCAPED_SLASHES) : null, $userId]);
}

/**
 * Creates a receipt header + items for a snapshot of lines.
 * Lines format: [['product_id'=>..., 'expected'=>int, 'received'=>int, 'line_note'?=>string], ...]
 */
function po_create_receipt(PDO $pdo, int $poId, string $outletId, bool $isFinal, ?int $userId, array $lines): ?int {
  if (!po_table_exists($pdo, 'po_receipts') || !po_table_exists($pdo, 'po_receipt_items')) return null;

  $pdo->beginTransaction();
  try {
    $ins = $pdo->prepare('INSERT INTO po_receipts (purchase_order_id, outlet_id, is_final, created_by, created_at) VALUES (?,?,?,?,NOW())');
    $ins->execute([$poId, $outletId, $isFinal ? 1 : 0, $userId]);
    $rid = (int)$pdo->lastInsertId();

    if ($lines) {
      $li = $pdo->prepare('INSERT INTO po_receipt_items (receipt_id, product_id, expected_qty, received_qty, line_note) VALUES (?,?,?,?,?)');
      foreach ($lines as $ln) {
        $li->execute([
          $rid,
          (string)($ln['product_id'] ?? ''),
          (int)($ln['expected'] ?? 0),
          (int)($ln['received'] ?? 0),
          (string)($ln['line_note'] ?? ''),
        ]);
      }
    }

    $pdo->commit();
    return $rid;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

/* -------------------- Optional idempotency (no-op if table missing) -------------------- */
function po_request_hash(array $params, array $exclude = ['csrf','csrf_token','idempotency_key']): string {
  foreach ($exclude as $ex) { unset($params[$ex]); }
  // Also include script and action for stability
  $params['__script'] = $_SERVER['SCRIPT_NAME'] ?? '';
  if (isset($_POST['ajax_action'])) $params['__action'] = (string)$_POST['ajax_action'];
  ksort($params);
  $canon = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
  return hash('sha256', (string)$canon);
}

function po_idem_get(PDO $pdo, string $key): ?array {
  if (!po_table_exists($pdo, 'idempotency_keys')) return null;
  $q = $pdo->prepare('SELECT request_hash, response_json FROM idempotency_keys WHERE idem_key = ? LIMIT 1');
  $q->execute([$key]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) return null;
  return [
    'request_hash' => (string)($row['request_hash'] ?? ''),
    'response'     => $row['response_json'] ? json_decode((string)$row['response_json'], true) : null,
  ];
}

function po_idem_store(PDO $pdo, string $key, string $requestHash, array $response): void {
  if (!po_table_exists($pdo, 'idempotency_keys')) return;
  $q = $pdo->prepare('INSERT INTO idempotency_keys (idem_key, request_hash, response_json) VALUES (?, ?, ?)
                      ON DUPLICATE KEY UPDATE request_hash = VALUES(request_hash), response_json = VALUES(response_json)');
  $q->execute([$key, $requestHash, json_encode($response, JSON_UNESCAPED_SLASHES)]);
}
