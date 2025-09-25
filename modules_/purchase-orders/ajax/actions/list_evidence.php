<?php
declare(strict_types=1);

$poId = (int)($_POST['po_id'] ?? ($_GET['po_id'] ?? 0));
if ($poId <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id required'], 422);

try {
  $pdo = po_pdo();
  if (!po_table_exists($pdo,'po_evidence')) po_jresp(true, ['rows'=>[]]);

  // Optional filters and pagination
  $type   = isset($_POST['evidence_type']) ? (string)$_POST['evidence_type'] : (isset($_GET['evidence_type']) ? (string)$_GET['evidence_type'] : '');
  $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 50);
  $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : (isset($_GET['offset']) ? (int)$_GET['offset'] : 0);
  if ($limit <= 0) $limit = 50; if ($limit > 100) $limit = 100; if ($offset < 0) $offset = 0;

  $where = 'WHERE purchase_order_id = :po';
  $params = [':po' => $poId];
  if ($type !== '') { $where .= ' AND evidence_type = :t'; $params[':t'] = $type; }

  $sql = 'SELECT id, purchase_order_id, evidence_type, file_path, description, uploaded_by, uploaded_at '
       . 'FROM po_evidence ' . $where . ' ORDER BY id DESC LIMIT :lim OFFSET :off';
  $q = $pdo->prepare($sql);
  foreach ($params as $k => $v) { $q->bindValue($k, $v); }
  $q->bindValue(':lim', $limit, PDO::PARAM_INT);
  $q->bindValue(':off', $offset, PDO::PARAM_INT);
  $q->execute();
  $rows = $q->fetchAll() ?: [];

  // Count for pagination meta
  $qc = $pdo->prepare('SELECT COUNT(1) FROM po_evidence ' . $where);
  $qc->execute($params);
  $total = (int)($qc->fetchColumn() ?: 0);

  // Sanitize and cast types
  $rows = array_map(function($r){
    return [
      'id' => (int)$r['id'],
      'purchase_order_id' => (int)$r['purchase_order_id'],
      'evidence_type' => (string)$r['evidence_type'],
      'file_path' => (string)$r['file_path'],
      'description' => $r['description'] !== null ? (string)$r['description'] : null,
      'uploaded_by' => $r['uploaded_by'] !== null ? (int)$r['uploaded_by'] : null,
      'uploaded_at' => (string)$r['uploaded_at'],
    ];
  }, $rows);

  po_jresp(true, [
    'rows' => $rows,
    'pagination' => [ 'limit'=>$limit, 'offset'=>$offset, 'total'=>$total ]
  ]);
} catch (Throwable $e) {
  po_jresp(false, ['code'=>'internal_error','message'=>'Failed to list evidence'], 500);
}
