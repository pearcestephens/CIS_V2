<?php
declare(strict_types=1);
/**
 * Meta for transfers/stock:pack view.
 * Return an array only. No helper functions here.
 */

$tid = 0;
foreach (['transfer','transfer_id','id','tid','t'] as $k) {
    if (isset($_GET[$k]) && (int)$_GET[$k] > 0) { $tid = (int)$_GET[$k]; break; }
}

$title = $tid > 0 ? ('Pack Transfer #'.$tid) : 'Pack Transfer';

return [
    'title' => $title,
    'subtitle' => '',
    'breadcrumb' => [
        ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
        ['label' => 'Transfers', 'href' => 'https://staff.vapeshed.co.nz/modules/transfers/dashboard.php'],
        ['label' => 'Stock', 'href' => 'https://staff.vapeshed.co.nz/modules/transfers/stock/dashboard.php'],
        ['label' => $tid > 0 ? ('Pack #'.$tid) : 'Pack'],
    ],
    'layout' => 'card',
    'assets' => [
        'css' => [],
        'js' => [],
    ],
];
