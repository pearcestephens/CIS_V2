<?php
declare(strict_types=1);

$q = trim((string)($_POST['q'] ?? ($_GET['q'] ?? '')));
if ($q === '') { po_jresp(true, ['results'=>[]]); }

try {
	$pdo = po_pdo();
	if (!po_table_exists($pdo,'vend_products')) {
		po_jresp(true, ['results'=>[]]);
	}

	$like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
	$stmt = $pdo->prepare("SELECT id AS product_id, name, sku, image_url FROM vend_products WHERE id LIKE ? OR name LIKE ? OR sku LIKE ? LIMIT 20");
	$stmt->execute([$like, $like, $like]);
	$rows = $stmt->fetchAll() ?: [];
	$results = array_map(function($r){
		return [
			'product_id' => (string)$r['product_id'],
			'name'       => (string)($r['name'] ?? ''),
			'sku'        => (string)($r['sku'] ?? ''),
			'image'      => (string)($r['image_url'] ?? ''),
		];
	}, $rows);

	po_jresp(true, ['results'=>$results]);
} catch (Throwable $e) {
	po_jresp(false, ['code'=>'internal_error','message'=>'Failed to search'], 500);
}
