<?php
declare(strict_types=1);

/**
 * core/layout.php
 *
 * CIS page layout renderer used by CIS_TEMPLATE.php and V2 controllers.
 * Loads shared template parts under assets/templates/<theme>/ (restored originals),
 * renders the original <body class="app ..."> shell, and injects $content.
 */

function cis_render_layout(array $meta, string $content = ''): void
{
    // Resolve theme path
    $tplRoot = defined('CIS_TEMPLATES_PATH')
        ? CIS_TEMPLATES_PATH
        : (rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/assets/templates');
    $theme = defined('CIS_TEMPLATE_ACTIVE') ? CIS_TEMPLATE_ACTIVE : 'cisv2';
    $tpl   = rtrim($tplRoot, '/') . '/' . $theme . '/';

    // Require these parts (your restored originals)
    $hasTpl = is_dir($tpl)
        && is_file($tpl . 'html-header.php')
        && is_file($tpl . 'header.php')
        && is_file($tpl . 'sidemenu.php')
        && is_file($tpl . 'personalisation-menu.php')
        && is_file($tpl . 'footer.php')
        && is_file($tpl . 'html-footer.php');

    // Map meta conveniences
    $pageTitle = (string)($meta['title'] ?? $meta['page_title'] ?? 'CIS');
    $pageBlurb = (string)($meta['blurb'] ?? '');
    $rightHtml = (string)($meta['right'] ?? '');

    if ($hasTpl) {
        // Some legacy partials expect this constant
        if (!defined('HTTPS_URL')) define('HTTPS_URL', getenv('HTTPS_URL') ?: '/');

        // HEAD + top bar
        include $tpl . 'html-header.php';
        include $tpl . 'header.php';

        // Original body + app shell
        echo '<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">';
        echo '<div class="app-body">';

        // Left menu
        include $tpl . 'sidemenu.php';

        // MAIN
        echo '<main class="main">';

        // Breadcrumb (original template showed Home + parent + active + quick search on right)
        echo '<ol class="breadcrumb">';
        echo '<li class="breadcrumb-item">Home</li>';
        if (!empty($meta['breadcrumb']) && empty($meta['suppress_breadcrumb'])) {
            $items = $meta['breadcrumb'];
            $last  = count($items) - 1;
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
        } else {
            echo '<li class="breadcrumb-item active">'.htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8').'</li>';
        }
        echo '<li class="breadcrumb-menu d-md-down-none">';
        // Right-of-breadcrumb content (optional), plus your quick search partial
        if ($rightHtml) { echo $rightHtml; }
        if (is_file($tpl . 'quick-product-search.php')) {
            include $tpl . 'quick-product-search.php';
        }
        echo '</li>';
        echo '</ol>';

        // Optional tabs row (keeps your helper)
        if (!empty($meta['tabs'])) {
            cis_render_tabs($meta['tabs'], $meta['active_tab'] ?? '');
        }

        // Carded content area (matches original)
        echo '<div class="container-fluid"><div class="animated fadeIn"><div class="row"><div class="col"><div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title mb-0">'.htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8').'</h4>';
        if ($pageBlurb !== '') {
            echo '<div class="small text-muted">'.htmlspecialchars($pageBlurb, ENT_QUOTES, 'UTF-8').'</div>';
        }
        echo '</div>'; // card-header

        echo '<div class="card-body"><div class="cis-content">';
        echo $content ?: '<div class="text-muted">No content provided</div>';
        echo '</div></div>'; // cis-content, card-body

        echo '</div></div></div></div></div>'; // card,col,row,animated,container
        echo '</main>'; // /main

        // Right-side personalisation drawer (original)
        include $tpl . 'personalisation-menu.php';

        echo '</div>'; // /.app-body

        // Footers & scripts
        include $tpl . 'html-footer.php';
        include $tpl . 'footer.php';
        if (is_file($tpl . 'notification-dropdown.php')) {
            include $tpl . 'notification-dropdown.php';
        }
        if (is_file($tpl . 'post-integration.php')) {
            include $tpl . 'post-integration.php';
        }

        echo '</body></html>';
        return;
    }

    // ---- Minimal fallback (if theme not installed) ----
    $title = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
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
 * Breadcrumb renderer (kept from your version)
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
 * Tabs renderer (kept from your version)
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
