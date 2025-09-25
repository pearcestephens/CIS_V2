<?php
declare(strict_types=1);

/**
 * /core/security.php
 * Shared security headers for HTML pages (AJAX handler uses middleware).
 */
function cis_send_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
