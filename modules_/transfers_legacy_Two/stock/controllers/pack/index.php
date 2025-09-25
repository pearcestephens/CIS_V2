<?php declare(strict_types=1);

/**
 * Pack controller — ALWAYS renders inside standard CISV2 chrome.
 * Folder-only endpoint.
 */

// Resolve TID once for meta/breadcrumbs (non-fatal if missing)
$tid = 0;
foreach (['transfer','transfer_id','id','t'] as $k) {
  if (isset($_GET[$k]) && (int)$_GET[$k] > 0) { $tid = (int)$_GET[$k]; break; }
}

$meta = [
  'title'      => $tid > 0 ? ('Pack Transfer #'.$tid) : 'Pack Transfer',
  'breadcrumb' => [
    ['label' => 'Transfers', 'href' => '/cisv2/router.php?module=transfers'],
    ['label' => 'Stock',     'href' => '/cisv2/router.php?module=transfers/stock'],
    ['label' => $tid > 0 ? ('Pack #'.$tid) : 'Pack'],
  ],
  'assets'     => [
    // enqueue any page JS here if you need
    // 'js' => ['/cisv2/modules/transfers/stock/assets/pack.js'],
  ],
];

// Render the pack view into $content
ob_start();
require __DIR__ . '/../../views/pack.php';     // your existing view (content-only)
$content = (string)ob_get_clean();

require_once CISV2_ROOT . '/core/layout.php';
cis_render_layout($meta, $content);
