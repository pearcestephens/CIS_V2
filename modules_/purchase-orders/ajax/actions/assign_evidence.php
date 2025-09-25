<?php
declare(strict_types=1);

po_verify_csrf();
$po_id = (int)($_POST['po_id'] ?? 0);
$evidence_id = (int)($_POST['evidence_id'] ?? 0);
if ($po_id<=0 || $evidence_id<=0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id and evidence_id required'], 400);

try {
	$pdo = po_pdo();
	if (!po_table_exists($pdo,'po_evidence')) {
		po_jresp(false, ['code'=>'not_supported','message'=>'po_evidence table missing'], 400);
	}
	$upd = $pdo->prepare('UPDATE po_evidence SET purchase_order_id = ? WHERE id = ?');
	$upd->execute([$po_id, $evidence_id]);
	po_jresp(true, ['po_id'=>$po_id,'evidence_id'=>$evidence_id,'status'=>'assigned']);
} catch (Throwable $e) {
	po_jresp(false, ['code'=>'internal_error','message'=>'Failed to assign evidence'], 500);
}
