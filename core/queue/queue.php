<?php declare(strict_types=1);

function queue_enqueue(string $jobType, ?string $refId, array $payload, int $maxAttempts=8, int $priority=5): int {
  $pdo = db_rw();
  $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $idem = hash('sha256', $jobType.'|'.(string)$refId.'|'.$payloadJson);
  $sql = "INSERT IGNORE INTO queue_jobs
          (idempotency_key, job_type, ref_id, payload_json, status, attempts, max_attempts, priority, available_at, created_at)
          VALUES (:k,:t,:r,:p,'queued',0,:m,:pri,NOW(),NOW())";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':k'=>$idem, ':t'=>$jobType, ':r'=>$refId, ':p'=>$payloadJson, ':m'=>$maxAttempts, ':pri'=>$priority]);

  if ($pdo->lastInsertId()) return (int)$pdo->lastInsertId();
  $q = $pdo->prepare("SELECT id FROM queue_jobs WHERE idempotency_key=:k ORDER BY id DESC LIMIT 1");
  $q->execute([':k'=>$idem]);
  return (int)$q->fetchColumn();
}

function queue_status(int $jobId): ?array {
  $pdo = db_ro();
  $q = $pdo->prepare("SELECT id, job_type, ref_id, status, attempts, max_attempts, created_at, updated_at, last_error
                      FROM queue_jobs WHERE id=:id");
  $q->execute([':id'=>$jobId]);
  $r = $q->fetch();
  return $r ?: null;
}
