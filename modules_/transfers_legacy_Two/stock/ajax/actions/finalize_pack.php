<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
require_once dirname(__DIR__, 6).'/core/queue/queue.php';
header('Content-Type: application/json');

try {
  cis_require_login();
  if (cis_vend_writes_disabled()) throw new RuntimeException('Temporarily disabled due to system health (Vend)');

  $in = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $tid = (int)($in['transfer_id'] ?? 0);
  if ($tid<=0) throw new InvalidArgumentException('Bad transfer id');

  $jobId = queue_enqueue('transfer.finalize_pack', (string)$tid, [
    'transfer_id'=>$tid, 'requested_by'=>(int)($_SESSION['user_id']??0), 'requested_at'=>gmdate('c')
  ], maxAttempts:10, priority:3);

  echo json_encode([
    'ok'=>true,
    'job'=>['id'=>$jobId,'type'=>'transfer.finalize_pack','ref'=>(string)$tid,'label'=>"Finalize Pack #$tid",'created_at'=>gmdate('c')],
    'next_check_in'=>2
  ]);
} catch (Throwable $e) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
