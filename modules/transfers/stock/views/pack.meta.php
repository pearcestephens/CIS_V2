<?php
declare(strict_types=1);

$tid = (int)($_GET['transfer'] ?? 0);

return [
    'title'      => $tid > 0 ? "Pack Transfer #{$tid}" : 'Pack Transfer',
    'breadcrumb' => [
        ['label' => 'Transfers', 'href' => '/modules/transfers'],
        ['label' => 'Stock',     'href' => '/modules/transfers/stock'],
        ['label' => $tid > 0 ? "Pack #{$tid}" : 'Pack'],
    ],

    /**
     * If your template supports enqueuing assets from meta:
     * - add these paths in your header/footer includes.
     * If not, we’ll add <link>/<script> at the end of Section 3 safely.
     */
    'assets'     => [
        'css' => ['/assets/css/transfers/pack.css'],
        'js'  => ['/assets/js/transfers/pack.js'],
    ],
];
