<?php declare(strict_types=1);
$tid = 0;
foreach (['transfer','transfer_id','id','t'] as $k) {
  if (!empty($_GET[$k]) && (int)$_GET[$k] > 0) { $tid = (int)$_GET[$k]; break; }
}
return [
  'title'      => $tid ? ('Receive Transfer #'.$tid) : 'Receive Transfer',
  'breadcrumb' => [
    ['label'=>'Transfers','href'=>'/cisv2/router.php?module=transfers'],
    ['label'=>'Stock','href'=>'/cisv2/router.php?module=transfers/stock'],
    ['label'=> $tid ? ('Receive #'.$tid) : 'Receive'],
  ],
];
