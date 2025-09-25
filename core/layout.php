<?php
declare(strict_types=1);

/**
 * cisv2/core/layout.php
 * cis_render_layout(array $meta, string $content): void
 */

function cis_render_layout(array $meta, string $content): void {
    $tplBase = CISV2_ROOT.'/assets/templates/cisv2';
    $title   = $meta['title'] ?? 'CIS';
    $breadcrumb = $meta['breadcrumb'] ?? [];

    // Expose for template parts
    $GLOBALS['cisv2_view'] = [
        'meta'       => $meta,
        'content'    => $content,
        'breadcrumb' => $breadcrumb,
        'title'      => $title,
    ];

    require $tplBase.'/html-header.php';
    require $tplBase.'/header.php';
    require $tplBase.'/sidemenu.php';
    require $tplBase.'/personalisation-menu.php';

    // Main shell
    echo '<main class="cis-main container-fluid">';
    // Optional breadcrumb container supplied by template
    if (function_exists('cisv2_breadcrumb')) { cisv2_breadcrumb($breadcrumb); }
    echo $content;
    echo '</main>';

    require $tplBase.'/quick-product-search.php';
    require $tplBase.'/footer.php';
    $postIntegration = $tplBase.'/post-integration.php';
    if (is_file($postIntegration)) {
        require $postIntegration;
    }
    require $tplBase.'/html-footer.php';
}
