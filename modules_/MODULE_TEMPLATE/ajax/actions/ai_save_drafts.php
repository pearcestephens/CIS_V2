<?php
declare(strict_types=1);

function mt_ai_save_drafts(): array {
    $slug = basename(dirname(__DIR__, 2));
    $base = dirname(__DIR__, 2) . '/' . $slug . '/ai_drafts';
    if (!is_dir($base)) @mkdir($base, 0775, true);
    $files = [
        'index_view.html' => (string)($_POST['index_view'] ?? ''),
        'admin_view.html' => (string)($_POST['admin_view'] ?? ''),
        'README_addendum.md' => (string)($_POST['readme'] ?? ''),
        'module.js' => (string)($_POST['js'] ?? ''),
        'module.css' => (string)($_POST['css'] ?? ''),
    ];
    foreach ($files as $name => $content) {
        file_put_contents($base . '/' . $name, $content);
    }
    return ['dir' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $base)];
}
