<?php
declare(strict_types=1);
// READY variant: reuse pack view, but force PACKONLY banner off unless explicitly requested
// and expose a hint that this is the fast-path ready experience.

// Allow common transfer id keys
$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$tid = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $tid = (int)$_GET[$__k]; break; } }
if ($tid <= 0) { echo '<div class="alert alert-danger">Missing transfer ID.</div>'; return; }

// Set a flag consumed by pack.php to optionally show a banner or tweak behavior
$READY_VARIANT = true;
// PACKONLY defaults to false here; can be toggled via query (?packonly=1)
if (!isset($PACKONLY)) { $PACKONLY = ((int)($_GET['packonly'] ?? 0) === 1); }

// The core rendering lives in pack.php (CIS-templated composition). We include it.
require __DIR__ . '/pack.php';
