<?php
declare(strict_types=1);

/**
 * Controller: Pack page
 * Inputs: transfer (int)
 * Output: $content (HTML), $meta (array)
 * Uses: views/pack.php for the main content
 */

/** @var array $ctx from router */
$pdo     = $ctx['pdo'];
$params  = $ctx['params'];
$tid     = isset($params['transfer']) ? max(0, (int)$params['transfer']) : 0;

// Minimal transfer fetch (adjust to your schema)
$transfer = null;
if ($tid > 0) {
    $st = $pdo->prepare("SELECT id, ref_code, status, origin_outlet_id, dest_outlet_id, created_at
                         FROM stock_transfers WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$tid]);
    $transfer = $st->fetch() ?: null;
}

$meta = [
    'title' => $tid > 0 ? "Pack Transfer #{$tid}" : 'Pack Transfer',
    'breadcrumb' => [
        ['label'=>'Transfers','href'=>'/module/transfers'],
        ['label'=>'Stock','href'=>'/module/transfers/stock'],
        ['label'=>$tid > 0 ? "Pack #{$tid}" : 'Pack'],
    ],
];

/** Render the view */
$viewFile = __DIR__.'/../views/pack.php';
ob_start();
$transferVar = $transfer;  // expose as $transferVar to view
$tidVar      = $tid;
require $viewFile;
$content = (string)ob_get_clean();
