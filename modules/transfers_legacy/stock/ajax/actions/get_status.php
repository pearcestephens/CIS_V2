<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/TransferService.php';
$svc = new TransferService();
$tid = (int)($_GET['transfer_id'] ?? $_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false, 'transfer_id required', 400);
$res = $svc->status($tid);
jresp(true, $res, 200);
