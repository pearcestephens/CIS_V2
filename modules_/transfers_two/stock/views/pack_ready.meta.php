<?php
declare(strict_types=1);
$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$tid = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $tid = (int)$_GET[$__k]; break; } }

$title = $tid > 0 ? ('Pack Transfer #' . $tid . ' — Ready') : 'Pack Transfer — Ready';
return [
  'title' => $title,
  'subtitle' => 'Fast-path packing flow',
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
    ['label' => 'Stock Transfers', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=stock'],
    ['label' => $tid > 0 ? ('Pack Ready #' . $tid) : 'Pack Ready'],
  ],
  'layout' => 'card',
  'page_title' => $title . ' — CIS',
  'assets' => [],
];
