<?php
/**
 * add_comment.php - inserts a new comment for a transfer
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

$tid = (int)($_POST['transfer_id'] ?? 0);
$note = trim((string)($_POST['note'] ?? ''));
if ($tid <= 0) jresp(false, 'transfer_id required', 400);
if ($note === '') jresp(false, 'note required', 400);

try {
  if (!function_exists('cis_pdo')) { jresp(false, 'DB unavailable', 500); }
  $pdo = cis_pdo();
  $uid = (int)($_SESSION['userID'] ?? 0);
  // Persist into existing transfer_notes schema
  $st = $pdo->prepare('INSERT INTO transfer_notes (transfer_id, note_text, created_by, created_at) VALUES (?, ?, ?, NOW())');
  $st->execute([$tid, $note, $uid]);
  $id = (int)$pdo->lastInsertId();
  // Log to transfer_logs for auditing
  try {
    $st2 = $pdo->prepare('INSERT INTO transfer_logs (transfer_id, event_type, event_data, actor_user_id, source_system, created_at) VALUES (:tid, :type, :data, :uid, :src, NOW())');
    $st2->execute([
      ':tid' => $tid,
      ':type'=> 'comment_added',
      ':data'=> json_encode(['id'=>$id,'user_id'=>$uid], JSON_UNESCAPED_SLASHES),
      ':uid' => $uid ?: null,
      ':src' => 'cis.transfers.ui',
    ]);
  } catch (Throwable $e) { /* non-fatal */ }
  jresp(true, [ 'id' => $id ]);
} catch (Throwable $e) {
  jresp(false, 'Server error', 500);
}
