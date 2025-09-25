<?php
declare(strict_types=1);

if (!function_exists('cis_render_layout')) {
    /**
     * Render the standard CISV2 layout chrome.
     */
    function cis_render_layout(array $meta, string $content): void
    {
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        $tplBase = $docRoot ? $docRoot . '/assets/templates/cisv2' : '';
        if ($tplBase === '' || !is_dir($tplBase)) {
            $tplBase = CISV2_ROOT . '/assets/templates/cisv2';
        }

        require $tplBase . '/html-header.php';
        require $tplBase . '/header.php';

        echo '<div class="container">';
        echo '<div class="cisv2-layout mt-4 mb-5">';
        echo '<div class="row g-4">';

        echo '<div class="col-12 col-lg-3">';
        try {
            require $tplBase . '/sidemenu.php';
        } catch (\Throwable $e) {
            error_log('cisv2 layout sidemenu include failed: ' . $e->getMessage());
            echo '<div class="alert alert-warning mb-3">Sidebar unavailable</div>';
        }
        echo '</div>';

        echo '<div class="col-12 col-lg-9">';
        if (!empty($meta['title'])) {
            echo '<h1 class="h4 mb-4">' . htmlspecialchars((string) $meta['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
        }
        echo $content;
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';

        require $tplBase . '/footer.php';
        require $tplBase . '/html-footer.php';
    }
}
