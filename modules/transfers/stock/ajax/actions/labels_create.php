<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
require_once dirname(__DIR__, 6).'/core/queue/queue.php';
header('Content-Type: application/json');

try {
  cis_require_login();
  if (cis_vend_writes_disabled()) throw new RuntimeException('Temporarily disabled due to system health (Vend)');

  $key = ($_SERVER['REMOTE_ADDR']??'0').'|'.($_SESSION['user_id']??0);
  if (!cis_rate_limit('labels:create', $key, 60, 60)) { http_response_code(429); exit('{"ok":false,"error":"Rate limit"}'); }

  $in = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $tid = (int)($in['transfer_id'] ?? 0);
  $carrier = strtoupper(trim((string)($in['carrier'] ?? '')));
  $boxes = $in['boxes'] ?? [];
  if ($tid<=0 || !$carrier || !is_array($boxes) || count($boxes)<1) throw new InvalidArgumentException('Bad payload');

  $jobId = queue_enqueue('labels.create', (string)$tid, [
    'transfer_id'=>$tid,
    'carrier'=>$carrier,
    'boxes'=>$boxes,
    'requested_by'=>(int)($_SESSION['user_id'] ?? 0),
    'requested_at'=>gmdate('c'),
  ], maxAttempts:10, priority:3);

  echo json_encode([
    'ok'=>true,
    'job'=>['id'=>$jobId,'type'=>'labels.create','ref'=>(string)$tid,'label'=>"Create Labels #$tid",'created_at'=>gmdate('c')],
    'next_check_in'=>2
  ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
