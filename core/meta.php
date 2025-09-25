<?php
declare(strict_types=1);

/**
 * /core/meta.php
 * Resolve page metadata (title, breadcrumb, assets).
 */

function cis_resolve_meta(string $module, string $view): array {
    $meta = [
        'title' => '',
        'subtitle' => '',
        'breadcrumb' => [],
        'right' => '',
        'layout' => 'card',
        'suppress_breadcrumb' => false,
        'assets' => ['css' => [], 'js' => []],
        'page_title' => '',
        'meta_description' => '',
        'meta_keywords' => '',
        'noindex' => false,
        'tabs' => [],
        'active_tab' => '',
    ];

    if ($module && $view) {
        $base = $_SERVER['DOCUMENT_ROOT'] . "/modules/{$module}";
        foreach ([$base . "/views/{$view}.meta.php", $base . "/{$view}.meta.php"] as $mf) {
            if (is_file($mf)) {
                $ret = include $mf;
                if (is_array($ret)) $meta = array_merge($meta, $ret);
                break;
            }
        }
    }

    if (empty($meta['title']) && $view) {
        $meta['title'] = ucwords(str_replace(['-','_'], ' ', $view));
    }

    if (empty($meta['page_title'])) {
        $parts = [];
        if (!empty($meta['title'])) $parts[] = $meta['title'];
        if ($module) {
            $mh = ucwords(str_replace(['-','_'], ' ', $module));
            if ($mh && stripos($meta['title'], $mh) === false) $parts[] = $mh;
        }
        $parts[] = 'CIS';
        $meta['page_title'] = implode(' — ', $parts);
    }

    if (empty($meta['breadcrumb'])) {
        $bc = [['label'=>'Home','href'=>'/']];
        if ($module) $bc[] = ['label'=>ucwords(str_replace(['-','_'], ' ', $module))];
        if ($view) $bc[] = ['label'=>ucwords(str_replace(['-','_'], ' ', $view))];
        $meta['breadcrumb'] = $bc;
    }

    $GLOBALS['PAGE_TITLE'] = $meta['page_title'];
    $GLOBALS['META_DESCRIPTION'] = (string)($meta['meta_description'] ?? '');
    $GLOBALS['META_KEYWORDS'] = (string)($meta['meta_keywords'] ?? '');
    $GLOBALS['NOINDEX'] = !empty($meta['noindex']);

    return $meta;
}
