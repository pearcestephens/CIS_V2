<?php
declare(strict_types=1);
return [
  'title' => 'Purchase Orders',
  'subtitle' => 'Actions and shortcuts',
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
    ['label' => 'Purchase Orders'],
    ['label' => 'Index'],
  ],
  'layout' => 'card',
  'tabs' => [
  ['key' => 'index', 'label' => 'Overview', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index', 'active' => true],
  ['key' => 'admin', 'label' => 'Admin', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin'],
  ],
  'active_tab' => 'index',
  'page_title' => 'Purchase Orders — CIS',
  'meta_description' => '',
  'noindex' => false,
];
