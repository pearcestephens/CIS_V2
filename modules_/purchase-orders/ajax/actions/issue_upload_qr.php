<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$po_id = (int)($_POST['po_id'] ?? 0);
if ($po_id<=0) po_jresp(false, ['code'=>'invalid_po_id','message'=>'po_id required'], 400);
// TODO: create QR token for mobile upload
po_jresp(true, ['qr_url'=>'https://staff.vapeshed.co.nz/modules/purchase-orders/upload?token=TODO']);
