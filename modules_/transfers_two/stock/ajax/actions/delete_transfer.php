<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/DevState.php';
require_once __DIR__ . '/../tools.php';

$transferId = (int)($_POST['transfer_id'] ?? 0);
if ($transferId <= 0) { jresp(false, 'Invalid transfer_id', 400); }

$all = DevState::loadAll();
if (!isset($all[$transferId])) { jresp(false, 'Transfer not found', 404); }
$row = $all[$transferId];
$state = strtolower((string)($row['state'] ?? ''));
if ($state !== 'cancelled') { jresp(false, 'Only cancelled transfers can be deleted', 409); }

$before = $row; // capture before deletion
$ok = DevState::deleteOne($transferId);
if (!$ok) { jresp(false, 'Failed to delete', 500); }

if (function_exists('stx_set_audit_snapshots')) { stx_set_audit_snapshots($before, ['deleted' => true]); }

jresp(true, [ 'transfer_id' => $transferId, 'deleted' => true ]);
