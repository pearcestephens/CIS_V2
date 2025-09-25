<?php
declare(strict_types=1);

$transferId = 0;
foreach (['transfer', 'transfer_id', 'id', 't'] as $param) {
    if (isset($_GET[$param]) && (int) $_GET[$param] > 0) {
        $transferId = (int) $_GET[$param];
        break;
    }
}

$titleSuffix = $transferId > 0 ? ' #' . $transferId : '';

return [
    'title'      => 'Pack Transfer' . $titleSuffix,
    'breadcrumb' => [
        ['label' => 'Transfers', 'href' => '/cisv2/router.php?module=transfers'],
        ['label' => 'Stock',     'href' => '/cisv2/router.php?module=transfers/stock'],
        ['label' => $transferId > 0 ? 'Pack #' . $transferId : 'Pack'],
    ],
    'assets'     => [
        'css' => [
            '/cisv2/modules/transfers/stock/css/pack.css',
            '/cisv2/modules/transfers/stock/css/pack.shipping.css',
        ],
        'js'  => [
            '/cisv2/modules/transfers/stock/js/pack.shipping.js',
        ],
    ],
];
