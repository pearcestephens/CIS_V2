<?php
/**
 * File: modules/transfers/stock/views/pack.meta.php
 * Purpose: Provide metadata for the CIS v2 pack transfer view.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: None
 */
declare(strict_types=1);

$transferId = 0;
foreach (['transfer', 'transfer_id', 'id', 'tid', 't'] as $key) {
    if (isset($_GET[$key]) && (int) $_GET[$key] > 0) {
        $transferId = (int) $_GET[$key];
        break;
    }
}

return [
    'title'      => $transferId ? "Pack Transfer #{$transferId}" : 'Pack Transfer',
    'breadcrumb' => [
        ['label' => 'Transfers', 'href' => '/module/transfers'],
        ['label' => 'Stock', 'href' => '/module/transfers/stock'],
        ['label' => $transferId ? "Pack #{$transferId}" : 'Pack'],
    ],
];
