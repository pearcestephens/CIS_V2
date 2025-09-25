<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
header('Content-Type: application/json');

try {
  cis_require_login();
  $key = ($_SERVER['REMOTE_ADDR']??'0').'|'.($_SESSION['user_id']??0);
  if (!cis_rate_limit('pack:set_ship_qty', $key, 120, 60)) { http_response_code(429); exit('{"ok":false,"error":"Rate limit"}'); }

  $in = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $lineId = (int)($in['line_id'] ?? 0);
  $ship   = (int)($in['ship_qty'] ?? -1);
  if ($lineId<=0 || $ship<0 || $ship>100000) throw new InvalidArgumentException('Invalid qty');

  $pdo = db_rw();
  $pdo->beginTransaction();

  // Pull the line and limits
  $q = $pdo->prepare("SELECT id, transfer_id, qty_requested, qty_sent_total FROM transfer_items WHERE id=:id FOR UPDATE");
  $q->execute([':id'=>$lineId]);
  $ti = $q->fetch();
  if (!$ti) throw new RuntimeException('Line not found');

  // Guard: cannot exceed requested
  if ($ship > (int)$ti['qty_requested']) throw new InvalidArgumentException('Ship qty exceeds requested');

  // We store ship total at item level (qty_sent_total); per-box allocations are separate endpoints
  $u = $pdo->prepare("UPDATE transfer_items SET qty_sent_total=:s, updated_at=NOW() WHERE id=:id");
  $u->execute([':s'=>$ship, ':id'=>$lineId]);

  // log
  cis_log('INFO','transfers','pack.set_ship_qty', ['item_id'=>$lineId,'transfer_id'=>$ti['transfer_id'],'ship_qty'=>$ship]);

  $pdo->commit();
  echo json_encode(['ok'=>true], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
