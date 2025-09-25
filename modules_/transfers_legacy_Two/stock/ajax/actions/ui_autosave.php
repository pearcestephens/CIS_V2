<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
header('Content-Type: application/json');
try {
  cis_require_login();
  $in = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $tid = (int)($in['transfer_id'] ?? 0);
  $state = $in['state'] ?? null;
  if ($tid<=0 || !is_array($state)) throw new InvalidArgumentException('Bad state');

  $pdo = db_rw();
  $stmt = $pdo->prepare("INSERT INTO transfer_ui_sessions (transfer_id,user_id,state_json,autosave_at,expires_at)
                         VALUES (:t,:u,:s,NOW(),DATE_ADD(NOW(),INTERVAL 2 DAY))
                         ON DUPLICATE KEY UPDATE state_json=VALUES(state_json), autosave_at=NOW(), expires_at=VALUES(expires_at)");
  $stmt->execute([':t'=>$tid, ':u'=>(int)($_SESSION['user_id']??0), ':s'=>json_encode($state, JSON_UNESCAPED_SLASHES)]);
  echo json_encode(['ok'=>true]);
} catch(Throwable $e){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
