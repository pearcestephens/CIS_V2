<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

req_id();
$csrfMode = isset($_SESSION['csrf']) ? 'header' : 'missing_session_token';

json_success([
    'ok'        => true,
    'csrf'      => $csrfMode,
    'time'      => date('c'),
]);
