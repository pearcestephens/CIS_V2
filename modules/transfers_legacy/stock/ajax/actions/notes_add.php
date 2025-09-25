<?php
declare(strict_types=1);
/**
 * notes_add.php — append a note to transfer_notes for a transfer
 */
if (!function_exists('jresp')) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'Router missing']); exit; }

$transferId = (int)($_POST['transfer_id'] ?? 0);
$note = trim((string)($_POST['note_text'] ?? ''));
$actor = (int)($_SESSION['user_id'] ?? ($_SESSION['userID'] ?? 0));

if ($transferId <= 0 || $note === '' || !function_exists('cis_pdo')) {
  jresp(false, 'Bad request', 400);
}

try {
  $pdo = cis_pdo();
  // Store plain text only; presentation layer handles escaping and anchorization
  $st = $pdo->prepare('INSERT INTO transfer_notes (transfer_id, note_text, created_by) VALUES (:t,:n,:u)');
  $st->execute([':t'=>$transferId, ':n'=>$note, ':u'=>$actor]);
  $id = (int)$pdo->lastInsertId();
  if (function_exists('stx_log_transfer_audit')) {
    stx_log_transfer_audit([
      'entity_type' => 'transfer',
      'entity_pk'   => $transferId,
      'transfer_pk' => $transferId,
      'action'      => 'notes_add',
      'status'      => 'success',
      'actor_type'  => 'user',
      'actor_id'    => (string)$actor,
      'data_after'  => ['id'=>$id,'note_text'=>$note],
    ]);
  }
  jresp(true, ['id'=>$id]);
} catch (Throwable $e) {
  jresp(false, 'Failed to add note', 500);
}
