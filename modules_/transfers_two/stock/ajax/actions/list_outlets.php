<?php
declare(strict_types=1);

// Returns outlets as [{ id, name }]
try {
  if (function_exists('cis_pdo')){
    $pdo = cis_pdo();
    $stmt = $pdo->query('SELECT id, name FROM vend_outlets ORDER BY name ASC');
    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)){
      $rows[] = [ 'id' => (string)($r['id'] ?? ''), 'name' => (string)($r['name'] ?? '') ];
    }
    jresp(true, [ 'outlets' => $rows ], 200);
  } else {
    jresp(true, [ 'outlets' => [] ], 200);
  }
} catch (Throwable $e) {
  jresp(true, [ 'outlets' => [] ], 200);
}
