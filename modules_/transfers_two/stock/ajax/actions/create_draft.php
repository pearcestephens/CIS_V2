<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/TransferService.php';
$svc = new TransferService();
$from = (int)($_POST['from_outlet'] ?? 0);
$to   = (int)($_POST['to_outlet'] ?? 0);
if ($from<=0 || $to<=0) jresp(false, 'from_outlet and to_outlet required', 400);
$res = $svc->createDraft($from, $to, (int)$__ajax_context['uid']);
jresp(true, $res, 200);
