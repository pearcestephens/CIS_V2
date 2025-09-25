<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
header('Content-Type: application/json');
try {
  cis_require_login();
  $in = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $tid = (int)($in['transfer_id'] ?? 0);
  $txt = trim((string)($in['note'] ?? ''));
  if ($tid<=0 || $txt==='') throw new InvalidArgumentException('Bad note');

  $pdo = db_rw();
  $ins = $pdo->prepare("INSERT INTO transfer_notes (transfer_id,note_text,created_by) VALUES (:t,:n,:u)");
  $ins->execute([':t'=>$tid, ':n'=>$txt, ':u'=>(int)($_SESSION['user_id']??0)]);
  echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
} catch(Throwable $e){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
