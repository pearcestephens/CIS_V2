<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
header('Content-Type: application/json');
try {
  cis_require_login();
  $tid = (int)($_GET['transfer_id'] ?? 0);
  if ($tid<=0) throw new InvalidArgumentException('Bad transfer id');
  $pdo = db_ro();
  $q = $pdo->prepare("SELECT state_json FROM transfer_ui_sessions WHERE transfer_id=:t AND user_id=:u LIMIT 1");
  $q->execute([':t'=>$tid, ':u'=>(int)($_SESSION['user_id']??0)]);
  $row = $q->fetch();
  echo json_encode(['ok'=>true,'state'=> $row ? json_decode($row['state_json'], true) : null], JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
