<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
header('Content-Type: application/json');
try {
  cis_require_login();
  $in = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $id = (int)($in['id'] ?? 0);
  if ($id<=0) throw new InvalidArgumentException('Bad id');

  $pdo = db_rw();
  $u = $pdo->prepare("UPDATE transfer_notes SET deleted_at=NOW(), deleted_by=:u WHERE id=:id AND deleted_at IS NULL");
  $u->execute([':u'=>(int)($_SESSION['user_id']??0), ':id'=>$id]);
  echo json_encode(['ok'=> ($u->rowCount()>0)]);
} catch(Throwable $e){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
