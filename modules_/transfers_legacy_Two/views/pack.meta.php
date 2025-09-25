<?php
declare(strict_types=1);

return [
  'title'      => 'Pack Transfer',
  'subtitle'   => 'Cartonize · Label · Track · Or deliver internally',
  'breadcrumb' => [
    ['label'=>'Transfers','href'=>'/'],
    ['label'=>'Pack Transfer'],
  ],
  'layout'     => 'card',
  'tabs'       => [],
  'active_tab' => '',
  'page_title' => 'Pack Transfer — CIS',
  'noindex'    => true,
  // if you prefer to inject assets via meta, list them here
  'assets'     => [
    'css' => ['/modules/transfers/stock/css/pack.page.css'],
    'js'  => ['/modules/transfers/stock/js/pack.page.js'],
  ],
];
