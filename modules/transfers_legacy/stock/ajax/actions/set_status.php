<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/DevState.php';
require_once __DIR__ . '/../tools.php';

$transferId = (int)($_POST['transfer_id'] ?? 0);
$status = strtolower(trim((string)($_POST['status'] ?? '')));
if ($transferId <= 0) { jresp(false, 'Invalid transfer_id', 400); }

$allowed = ['draft','packing','ready_to_send','sent','in_transit','receiving','partial','received','cancelled'];
if (!in_array($status, $allowed, true)) { jresp(false, 'Invalid status', 400); }

$all = DevState::loadAll();
$row = $all[$transferId] ?? [
  'state' => 'draft',
  'outlet_from' => '',
  'outlet_to' => '',
  'updated_at' => '',
];
$before = $row; // capture before
$row['state'] = $status;
$row['updated_at'] = date('c');
$row['last_edited_at'] = $row['updated_at'];
$row['last_touched_at'] = $row['updated_at'];

if (!DevState::saveOne($transferId, $row)) { jresp(false, 'Failed to save', 500); }

$after = $row;
if (function_exists('stx_set_audit_snapshots')) { stx_set_audit_snapshots($before, $after); }

jresp(true, [
  'transfer_id' => $transferId,
  'state' => $status,
  'updated_at' => $row['updated_at'],
]);
