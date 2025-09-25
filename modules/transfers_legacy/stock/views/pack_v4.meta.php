<?php
declare(strict_types=1);
$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$tid = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $tid = (int)$_GET[$__k]; break; } }

$title = $tid > 0 ? ('Pack — Transfer #'.$tid.' (V4)') : 'Pack (V4)';
return [
  'title' => $title,
  'subtitle' => 'Table-driven, printer-integrated packing UI',
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
    ['label' => 'Stock Transfers', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=stock'],
    ['label' => $title],
  ],
  'layout' => 'plain',
  'page_title' => $title.' — CIS',
  'assets' => [
    'css' => [
      'https://staff.vapeshed.co.nz/modules/_shared/assets/css/cis-tokens.css',
      'https://staff.vapeshed.co.nz/modules/_shared/assets/css/cis-utilities.css',
      'https://staff.vapeshed.co.nz/modules/transfers/stock/assets/css/pack.v4.css',
      'https://staff.vapeshed.co.nz/modules/transfers/stock/assets/css/planner.v3.css',
      'https://staff.vapeshed.co.nz/modules/transfers/stock/assets/css/printer.v2.css',
    ],
    'js' => [
      ['https://staff.vapeshed.co.nz/modules/transfers/stock/assets/js/core.js', ['defer' => true]],
      ['https://staff.vapeshed.co.nz/modules/transfers/stock/assets/js/items.table.v3.js', ['defer' => true]],
      ['https://staff.vapeshed.co.nz/modules/transfers/stock/assets/js/packages.presets.js', ['defer' => true]],
      ['https://staff.vapeshed.co.nz/modules/transfers/stock/assets/js/printer.v2.js', ['defer' => true]],
      ['https://staff.vapeshed.co.nz/modules/transfers/stock/assets/js/planner.v3.js', ['defer' => true]],
      ['https://staff.vapeshed.co.nz/modules/transfers/stock/assets/js/pack.v4.js', ['defer' => true]],
    ],
  ],
];
