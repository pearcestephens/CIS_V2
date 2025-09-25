<?php
declare(strict_types=1);
$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$tid = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $tid = (int)$_GET[$__k]; break; } }

$title = $tid > 0 ? ('Pack V3 — Transfer #'.$tid) : 'Pack V3';
return [
  'title' => $title,
  'subtitle' => 'Modern packing UI',
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
    ['label' => 'Stock Transfers', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=stock'],
    ['label' => $title],
  ],
  'layout' => 'card',
  'page_title' => $title.' — CIS',
  'assets' => [],
];
