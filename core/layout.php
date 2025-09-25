<?php
declare(strict_types=1);

/**
 * core/layout.php
 *
 * CIS page layout renderer used by CIS_TEMPLATE.php.
 * Looks for shared template parts under assets/templates/<theme>/.
 * If not found, falls back to a minimal HTML scaffold.
 */

function cis_render_layout(array $meta, string $content = ''): void
{
    $tplRoot = defined('CIS_TEMPLATES_PATH')
        ? CIS_TEMPLATES_PATH
        : (rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/assets/templates');
    $theme = defined('CIS_TEMPLATE_ACTIVE') ? CIS_TEMPLATE_ACTIVE : 'cisv2';
    $tpl   = rtrim($tplRoot, '/') . '/' . $theme . '/';

    $hasTpl = is_dir($tpl)
        && is_file($tpl . 'html-header.php')
        && is_file($tpl . 'header.php')
        && is_file($tpl . 'sidemenu.php')
        && is_file($tpl . 'personalisation-menu.php')
        && is_file($tpl . 'footer.php')
        && is_file($tpl . 'html-footer.php');

    if ($hasTpl) {
        include $tpl . 'html-header.php';
        include $tpl . 'header.php';
        include $tpl . 'sidemenu.php';

        echo '<main class="cis-main">';
        if (!empty($meta['breadcrumb']) && empty($meta['suppress_breadcrumb'])) {
            cis_render_breadcrumb($meta['breadcrumb'], $meta['right'] ?? '');
        }
        if (!empty($meta['tabs'])) {
            cis_render_tabs($meta['tabs'], $meta['active_tab'] ?? '');
        }
        echo $content ?: '<div class="text-muted">No content provided</div>';
        echo '</main>';

        include $tpl . 'personalisation-menu.php';
        include $tpl . 'footer.php';
        include $tpl . 'html-footer.php';
        if (is_file($tpl . 'post-integration.php')) {
            include $tpl . 'post-integration.php';
        }
        return;
    }

    // Minimal fallback layout (safe if templates are not installed)
    $title = htmlspecialchars((string)($meta['page_title'] ?? $meta['title'] ?? 'CIS'), ENT_QUOTES, 'UTF-8');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <title><?= $title ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"/>
    </head>
    <body>
    <nav class="navbar navbar-light bg-light"><span class="navbar-brand mb-0 h1">CIS</span></nav>
    <div class="container my-3">
        <?php
        if (!empty($meta['breadcrumb']) && empty($meta['suppress_breadcrumb'])) {
            cis_render_breadcrumb($meta['breadcrumb'], $meta['right'] ?? '');
        }
        if (!empty($meta['tabs'])) {
            cis_render_tabs($meta['tabs'], $meta['active_tab'] ?? '');
        }
        echo $content ?: '<div class="text-muted">No content provided</div>';
        ?>
    </div>
    </body>
    </html>
    <?php
}

/**
 * Breadcrumb renderer.
 */
function cis_render_breadcrumb(array $items, string $rightHtml = ''): void
{
    echo '<ol class="breadcrumb">';
    $last = count($items) - 1;
    foreach ($items as $i => $it) {
        $label = htmlspecialchars((string)($it['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $href  = $it['href'] ?? '';
        if ($i === $last) {
            echo "<li class='breadcrumb-item active'>{$label}</li>";
        } elseif ($href) {
            $href = htmlspecialchars((string)$href, ENT_QUOTES, 'UTF-8');
            echo "<li class='breadcrumb-item'><a href='{$href}'>{$label}</a></li>";
        } else {
            echo "<li class='breadcrumb-item'>{$label}</li>";
        }
    }
    if ($rightHtml) {
        echo "<li class='breadcrumb-menu d-md-down-none'>{$rightHtml}</li>";
    }
    echo '</ol>';
}

/**
 * Tabs renderer.
 */
function cis_render_tabs(array $tabs, string $activeKey = ''): void
{
    if (!$tabs) return;
    echo '<ul class="nav nav-tabs mb-3">';
    foreach ($tabs as $t) {
        $key    = $t['key'] ?? '';
        $label  = htmlspecialchars((string)($t['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $href   = htmlspecialchars((string)($t['href'] ?? '#'), ENT_QUOTES, 'UTF-8');
        $active = ($t['active'] ?? false) || ($key === $activeKey);
        echo "<li class='nav-item'><a class='nav-link" . ($active ? ' active' : '') . "' href='{$href}'>{$label}</a></li>";
    }
    echo '</ul>';
}
