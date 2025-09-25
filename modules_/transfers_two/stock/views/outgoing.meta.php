<?php
/**
 * Per-view meta for Transfers › Stock › Outgoing
 * Used by modules/CIS_TEMPLATE.php to set title, breadcrumb, layout, and preload assets.
 */
return [
  'title' => 'Outgoing Transfer',
  'subtitle' => 'Pack, ship, and track outgoing stock transfers',
  'layout' => 'card',
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
    ['label' => 'Transfers'],
    ['label' => 'Outgoing'],
  ],
  'assets' => [
    'css' => [
      '/modules/transfers/stock/assets/css/stock.css',
    ],
    'js' => [
      ['/modules/transfers/stock/assets/js/core.js', ['defer' => true]],
      ['/modules/transfers/stock/assets/js/outgoing.init.js', ['defer' => true]],
      ['/modules/transfers/stock/assets/js/printer.js', ['defer' => true]],
    ],
  ],
];
