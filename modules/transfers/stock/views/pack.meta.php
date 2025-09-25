<?php
declare(strict_types=1);

$tid = 0;
foreach (['transfer', 'transfer_id', 'id', 't'] as $k) {
    if (isset($_GET[$k]) && (int) $_GET[$k] > 0) {
        $tid = (int) $_GET[$k];
        break;
    }
}

return [
    'title'      => $tid > 0 ? ('Pack Transfer #' . $tid) : 'Pack Transfer',
    'breadcrumb' => [
        ['label' => 'Transfers', 'href' => '/module/transfers'],
        ['label' => 'Stock',     'href' => '/module/transfers/stock'],
        ['label' => $tid > 0 ? ('Pack #' . $tid) : 'Pack'],
    ],
];
