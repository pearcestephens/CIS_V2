<?php
/**
 * CIS Schema + Data Dumper (safe re: undefined constants)
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$START = microtime(true);

/* 1) Boot app and locate PDO */
$docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($docroot === '' || !is_file($docroot . '/app.php')) {
  $guess = realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../';
  if (is_file($guess . '/app.php')) {
    $_SERVER['DOCUMENT_ROOT'] = $guess;
    $docroot = $guess;
  }
}
require_once $docroot . '/app.php';

function locate_pdo(): PDO {
  if (function_exists('cis_pdo')) {
    try { $pdo = cis_pdo(); if ($pdo instanceof PDO) return $pdo; } catch (Throwable $e) {}
  }
  if (function_exists('po_pdo')) {
    try { $pdo = po_pdo(); if ($pdo instanceof PDO) return $pdo; } catch (Throwable $e) {}
  }
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    return $GLOBALS['pdo'];
  }
  if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    return new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
  }
  throw new RuntimeException('Unable to locate a PDO connection.');
}

$pdo = locate_pdo();

/* Safe database name (no undefined constant use) */
function current_database(PDO $pdo): ?string {
  try {
    $n = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (is_string($n) && $n !== '') return $n;
  } catch (Throwable $e) {}
  if (defined('DB_NAME')) return (string)DB_NAME;
  return null;
}

/* 2) Target tables (override with ?tables=) */
$defaultTables = [
  'purchase_orders','purchase_order_line_items',
  'po_events','po_receipts','po_receipt_items','po_evidence',
  'inventory_adjust_requests','idempotency_keys',
  'vend_products','vend_inventory','vend_outlets','vend_suppliers',
  'transfers','transfer_items','transfer_receipts','transfer_receipt_items',
  'transfer_parcels','transfer_shipments','transfer_audit_log','transfer_logs',
  'transfer_discrepancies','users',
];

$tablesParam = isset($_GET['tables']) ? trim((string)$_GET['tables']) : '';
$tables = $tablesParam !== ''
  ? array_values(array_filter(array_map('trim', explode(',', $tablesParam))))
  : $defaultTables;
$tables = array_values(array_unique($tables));

/* 3) Helpers */
function qident(string $s): string { return '`' . str_replace('`', '``', $s) . '`'; }

function table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables
                       WHERE table_schema = DATABASE() AND table_name = ?");
  $st->execute([$table]);
  return (int)$st->fetchColumn() > 0;
}

function show_create(PDO $pdo, string $table): ?string {
  try {
    $st = $pdo->query('SHOW CREATE TABLE ' . qident($table));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    foreach ($row as $k => $v) if (stripos($k, 'create') !== false) return (string)$v;
  } catch (Throwable $e) {}
  return null;
}

function list_columns(PDO $pdo, string $table): array {
  $sql = "SELECT column_name, ordinal_position, column_type, is_nullable, column_default,
                 extra, column_key, data_type, character_maximum_length, numeric_precision,
                 numeric_scale, datetime_precision, column_comment
          FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ?
          ORDER BY ordinal_position";
  $st = $pdo->prepare($sql);
  $st->execute([$table]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function list_indexes(PDO $pdo, string $table): array {
  $sql = "SELECT
            INDEX_NAME,
            NON_UNIQUE,
            SEQ_IN_INDEX,
            COLUMN_NAME,
            COLLATION,
            SUB_PART,
            PACKED,
            NULLABLE,         -- correct column name
            INDEX_TYPE,
            COMMENT,
            INDEX_COMMENT
          FROM information_schema.statistics
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
          ORDER BY INDEX_NAME, SEQ_IN_INDEX";
  $st = $pdo->prepare($sql);
  $st->execute([$table]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $out = [];
  foreach ($rows as $r) {
    $idx = (string)$r['INDEX_NAME'];
    if (!isset($out[$idx])) {
      $out[$idx] = [
        'name'         => $idx,
        'unique'       => !((int)$r['NON_UNIQUE']),
        'index_type'   => $r['INDEX_TYPE'],
        'nullable'     => $r['NULLABLE'],     // "YES" | "NO"
        'comment'      => $r['COMMENT'],
        'index_comment'=> $r['INDEX_COMMENT'],
        'columns'      => [],
      ];
    }
    $out[$idx]['columns'][] = [
      'seq'       => (int)$r['SEQ_IN_INDEX'],
      'column'    => $r['COLUMN_NAME'],
      'collation' => $r['COLLATION'],
      'sub_part'  => $r['SUB_PART'],
      'packed'    => $r['PACKED'],
    ];
  }
  return array_values($out);
}


function list_foreign_keys(PDO $pdo, string $table): array {
  $sql = "SELECT
            kcu.constraint_name,
            kcu.column_name,
            kcu.referenced_table_name,
            kcu.referenced_column_name,
            rc.update_rule,
            rc.delete_rule
          FROM information_schema.key_column_usage kcu
          LEFT JOIN information_schema.referential_constraints rc
            ON rc.constraint_schema = kcu.constraint_schema
           AND rc.table_name       = kcu.table_name
           AND rc.constraint_name  = kcu.constraint_name
          WHERE kcu.constraint_schema = DATABASE()
            AND kcu.table_name = ?
            AND kcu.referenced_table_name IS NOT NULL
          ORDER BY kcu.constraint_name, kcu.ordinal_position";
  $st = $pdo->prepare($sql);
  $st->execute([$table]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $out = [];
  foreach ($rows as $r) {
    $name = (string)$r['constraint_name'];
    if (!isset($out[$name])) {
      $out[$name] = [
        'name'        => $name,
        'update_rule' => $r['update_rule'],
        'delete_rule' => $r['delete_rule'],
        'references'  => [],
      ];
    }
    $out[$name]['references'][] = [
      'column'            => $r['column_name'],
      'referenced_table'  => $r['referenced_table_name'],
      'referenced_column' => $r['referenced_column_name'],
    ];
  }
  return array_values($out);
}

function primary_key_first_column(array $columns): ?string {
  foreach ($columns as $c) {
    if (isset($c['column_key']) && strtoupper((string)$c['column_key']) === 'PRI') {
      return (string)$c['column_name'];
    }
  }
  return null;
}

function row_count(PDO $pdo, string $table): int {
  try {
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . qident($table))->fetchColumn();
  } catch (Throwable $e) {
    return -1;
  }
}

function sample_rows(PDO $pdo, string $table, ?string $orderCol = null, int $limit = 25): array {
  try {
    $sql = 'SELECT * FROM ' . qident($table);
    if ($orderCol) $sql .= ' ORDER BY ' . qident($orderCol) . ' DESC';
    $sql .= ' LIMIT ' . (int)$limit;
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
      foreach ($row as $k => $v) {
        if (is_string($v) && strlen($v) > 2000) $row[$k] = substr($v, 0, 2000) . '…';
      }
    }
    return $rows;
  } catch (Throwable $e) {
    return [];
  }
}

/* 4) Build dump */
$result = [
  'ok'          => true,
  'database'    => current_database($pdo), // SAFE now
  'generated'   => date('c'),
  'duration_ms' => null,
  'tables'      => [],
];

foreach ($tables as $t) {
  $entry = [
    'table'        => $t,
    'exists'       => false,
    'row_count'    => null,
    'create_sql'   => null,
    'columns'      => [],
    'indexes'      => [],
    'foreign_keys' => [],
    'sample_rows'  => [],
    'error'        => null,
  ];
  try {
    if (!table_exists($pdo, $t)) {
      $entry['exists'] = false;
    } else {
      $entry['exists']       = true;
      $entry['create_sql']   = show_create($pdo, $t);
      $cols                  = list_columns($pdo, $t);
      $entry['columns']      = $cols;
      $entry['indexes']      = list_indexes($pdo, $t);
      $entry['foreign_keys'] = list_foreign_keys($pdo, $t);
      $entry['row_count']    = row_count($pdo, $t);
      $entry['sample_rows']  = sample_rows($pdo, $t, primary_key_first_column($cols), 25);
    }
  } catch (Throwable $e) {
    $entry['error'] = $e->getMessage();
  }
  $result['tables'][] = $entry;
}

$result['duration_ms'] = (int)round((microtime(true) - $START) * 1000);

/* 5) Persist + echo */
/* 5) Persist + echo — write next to this script */
try {
  $outDir  = __DIR__;
  $outfile = $outDir . DIRECTORY_SEPARATOR . sprintf('cis_schema_dump_%s.json', date('Ymd_His'));
  file_put_contents($outfile, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  $result['_file'] = $outfile;
} catch (Throwable $e) {
  $result['_file_error'] = $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
