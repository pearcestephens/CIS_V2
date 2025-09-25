<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
header('Content-Type: application/json');
try {
  cis_require_login();
  $tid = (int)($_GET['transfer_id'] ?? 0);
  if ($tid<=0) throw new InvalidArgumentException('Bad transfer id');
  $pdo = db_ro();
  $q = $pdo->prepare("SELECT id, note_text, created_by, created_at, updated_at
                      FROM transfer_notes
                      WHERE transfer_id=:t AND deleted_at IS NULL
                      ORDER BY id DESC");
  $q->execute([':t'=>$tid]);
  echo json_encode(['ok'=>true,'notes'=>$q->fetchAll()], JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
