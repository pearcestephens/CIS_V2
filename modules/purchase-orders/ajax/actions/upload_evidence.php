<?php
declare(strict_types=1);

$ctx  = $GLOBALS['__po_ctx'] ?? ['uid' => 0];
po_verify_csrf();

$poId  = (int)($_POST['po_id'] ?? 0);
$etype = (string)($_POST['evidence_type'] ?? 'delivery');
$desc  = isset($_POST['description']) ? (string)$_POST['description'] : null;

if ($poId <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id required'], 422);
if (!isset($_FILES['file'])) po_jresp(false, ['code'=>'bad_request','message'=>'file required'], 422);

// Validate upload error
$err = (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err !== UPLOAD_ERR_OK) {
  $map = [
    UPLOAD_ERR_INI_SIZE => 'file too large (server limit)',
    UPLOAD_ERR_FORM_SIZE => 'file too large (form limit)',
    UPLOAD_ERR_PARTIAL => 'partial upload',
    UPLOAD_ERR_NO_FILE => 'no file',
    UPLOAD_ERR_NO_TMP_DIR => 'missing temp dir',
    UPLOAD_ERR_CANT_WRITE => 'cannot write',
    UPLOAD_ERR_EXTENSION => 'blocked by extension',
  ];
  $msg = $map[$err] ?? 'upload error';
  po_jresp(false, ['code'=>'upload_error','message'=>$msg], 400);
}

// Enforce basic type/size
$maxBytes = 8 * 1024 * 1024; // 8MB
$size = (int)($_FILES['file']['size'] ?? 0);
if ($size <= 0 || $size > $maxBytes) {
  po_jresp(false, ['code'=>'too_large','message'=>'file exceeds size limit'], 413);
}

// Validate extension/mime (allow common doc/image types)
$origName = (string)($_FILES['file']['name'] ?? 'upload');
$ext   = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
$allowExt = ['pdf','jpg','jpeg','png','webp','heic','gif'];
if ($ext === '') $ext = 'bin';
if (!in_array($ext, $allowExt, true)) {
  po_jresp(false, ['code'=>'invalid_type','message'=>'unsupported file type'], 415);
}

// Try to detect mime
$tmpPath = (string)($_FILES['file']['tmp_name'] ?? '');
if (!is_uploaded_file($tmpPath)) {
  po_jresp(false, ['code'=>'upload_invalid','message'=>'invalid uploaded file'], 400);
}

try {
  $pdo = po_pdo();
  if (!po_table_exists($pdo,'po_evidence')) {
    po_jresp(false, ['code'=>'not_supported','message'=>'po_evidence table missing'], 400);
  }

  $base = $_SERVER['DOCUMENT_ROOT'] . '/uploads/po_evidence';
  $sub  = date('Y/m');
  $dir  = $base . '/' . $sub;
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  if (!is_dir($dir) || !is_writable($dir)) {
    po_jresp(false, ['code'=>'storage_unavailable','message'=>'upload path not writable'], 500);
  }

  $fname = 'po'.$poId.'_'.bin2hex(random_bytes(8));
  $safeExt = preg_replace('/[^a-z0-9]/i', '', (string)$ext);
  $dest  = $dir . '/' . $fname . ($safeExt ? ('.'.$safeExt) : '');

  if (!is_uploaded_file($_FILES['file']['tmp_name']) || !move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    po_jresp(false, ['code'=>'upload_failed','message'=>'Failed to move uploaded file'], 500);
  }

  $relPath = '/uploads/po_evidence/' . $sub . '/' . basename($dest);
  $ins = $pdo->prepare('INSERT INTO po_evidence(purchase_order_id, evidence_type, file_path, description, uploaded_by, uploaded_at)
                        VALUES(?,?,?,?,?,NOW())');
  $ins->execute([$poId, $etype, $relPath, $desc, (int)$ctx['uid']]);
  $eid = (int)$pdo->lastInsertId();

  po_insert_event($pdo, $poId, 'evidence.upload', ['path'=>$relPath,'type'=>$etype,'id'=>$eid,'size'=>$size], (int)$ctx['uid']);
  po_jresp(true, [
    'id' => $eid,
    'po_id' => $poId,
    'path' => $relPath,
    'type' => $etype,
    'size' => $size,
    'description' => $desc
  ]);
} catch (Throwable $e) {
  po_jresp(false, ['code'=>'internal_error','message'=>$e->getMessage()], 500);
}
