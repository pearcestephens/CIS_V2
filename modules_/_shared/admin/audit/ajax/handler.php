<?php
declare(strict_types=1);
/**
 * Admin-only JSON API for audit viewer
 * New location: modules/_shared/admin/audit/ajax/handler.php
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
header('Content-Type: application/json; charset=utf-8');

function jresp($ok, $payload = [], $code = 200){
  http_response_code($code);
  echo json_encode(['success'=>(bool)$ok] + ($ok?['data'=>$payload]:['error'=>(string)$payload]), JSON_UNESCAPED_SLASHES); exit;
}

if (!isset($_SESSION)) session_start();
$role = $_SESSION['role'] ?? ($_SESSION['userRole'] ?? '');
$env = '';
if (defined('APP_ENV')) { $env = strtolower((string)APP_ENV); }
elseif (defined('ENV')) { $env = strtolower((string)ENV); }
elseif (!empty($_ENV['APP_ENV'])) { $env = strtolower((string)$_ENV['APP_ENV']); }
$isNonProd = !in_array($env, ['prod','production','live'], true);
$expectedToken = (string)($_ENV['INTERNAL_API_TOKEN'] ?? getenv('INTERNAL_API_TOKEN') ?: '');
$headerToken = (string)($_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '');
$internalOK = $expectedToken !== '' && $headerToken !== '' && hash_equals($expectedToken, $headerToken);
$roleOK = in_array($role, ['admin','owner','director'], true);
if (!($isNonProd || $roleOK || $internalOK)) { jresp(false, 'Forbidden', 403); }

if (!function_exists('verifyCSRFToken')) {
  function verifyCSRFToken($token): bool {
    if (!isset($_SESSION)) session_start();
    $t = (string)$token; $s = (string)($_SESSION['csrf_token'] ?? '');
    return $t !== '' && $s !== '' && hash_equals($s, $t);
  }
}
$csrf = $_POST['csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCSRFToken($csrf)) { jresp(false, 'Invalid CSRF', 400); }

if (!function_exists('cis_pdo')) { jresp(false,'DB unavailable', 500); }
$pdo = cis_pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_POST['ajax_action'] ?? '';

if ($action === 'list') {
  $page = max(1, (int)($_POST['page'] ?? 1));
  $size = min(200, max(1, (int)($_POST['size'] ?? 25)));
  $off = ($page-1) * $size;
  $where = [];$bind=[];
  $from = trim((string)($_POST['from'] ?? ''));
  $to = trim((string)($_POST['to'] ?? ''));
  $entity = trim((string)($_POST['entity'] ?? ''));
  $status = trim((string)($_POST['status'] ?? ''));
  $actionF = trim((string)($_POST['action'] ?? ''));
  $actor = trim((string)($_POST['actor'] ?? ''));
  $transfer = trim((string)($_POST['transfer_id'] ?? ''));
  $q = trim((string)($_POST['q'] ?? ''));
  if ($from !== '') { $where[] = 'created_at >= :from'; $bind[':from'] = $from.' 00:00:00'; }
  if ($to !== '') { $where[] = 'created_at <= :to'; $bind[':to'] = $to.' 23:59:59'; }
  if ($entity !== '') { $where[] = 'entity_type = :entity'; $bind[':entity'] = $entity; }
  if ($status !== '') { $where[] = 'status = :status'; $bind[':status'] = $status; }
  if ($actionF !== '') { $where[] = 'action = :action'; $bind[':action'] = $actionF; }
  if ($actor !== '') { $where[] = '(actor_type = :actor OR actor_id = :actor)'; $bind[':actor'] = $actor; }
  if ($transfer !== '') { $where[] = 'transfer_id = :transfer'; $bind[':transfer'] = $transfer; }
  if ($q !== '') { $where[] = '(session_id = :q OR ip_address = :q)'; $bind[':q'] = $q; }
  $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';
  $sql = "SELECT SQL_CALC_FOUND_ROWS id, entity_type, action, status, actor_type, actor_id, transfer_id, created_at FROM transfer_audit_log $whereSql ORDER BY id DESC LIMIT :off,:lim";
  $stmt = $pdo->prepare($sql);
  foreach($bind as $k=>$v){ $stmt->bindValue($k, $v); }
  $stmt->bindValue(':off', (int)$off, PDO::PARAM_INT);
  $stmt->bindValue(':lim', (int)$size, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $total = (int)$pdo->query('SELECT FOUND_ROWS()')->fetchColumn();
  $start = $total?($off+1):0; $end = min($off+$size, $total);
  jresp(true, ['rows'=>$rows, 'total'=>$total, 'range'=>"$start\u2013$end of $total" ]);
}

if ($action === 'get') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { jresp(false, 'Invalid id', 400); }
  $stmt = $pdo->prepare('SELECT * FROM transfer_audit_log WHERE id = :id LIMIT 1');
  $stmt->execute([':id'=>$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) { jresp(false, 'Not found', 404); }
  $before = json_decode($row['data_before'] ?? 'null', true);
  $after = json_decode($row['data_after'] ?? 'null', true);
  jresp(true, ['row'=>$row,'before'=>$before,'after'=>$after]);
}

jresp(false, 'Unknown action', 400);
