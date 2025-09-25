<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <?php
    // Allow pages to override the <title> and inject extra <head> content safely
    $___defaultTitle = 'The Vape Shed - Central Information System';
    $___pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : $___defaultTitle;
    ?>
    <title><?php echo htmlspecialchars($___pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- CORE CSS - LOCAL COREUI v2.0.0 + BOOTSTRAP v4.1.1 (COMPATIBLE) -->
    <link href="<?php echo HTTPS_URL; ?>assets/css/style1.css?updated=224" rel="stylesheet">
    
    <!-- JQUERY UI COMPATIBLE WITH COREUI v2.0.0 -->
    <link href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
    
    <!-- PACE LOADER -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/themes/blue/pace-theme-minimal.min.css" rel="stylesheet">
    
    <!-- ICONS - COMPATIBLE WITH COREUI v2.0.0 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.5.5/css/simple-line-icons.min.css" rel="stylesheet">
    
    <!-- CUSTOM OVERRIDES -->
    <link href="<?php echo HTTPS_URL; ?>assets/css/bootstrap-compatibility.css?v=20250902" rel="stylesheet">
    <link href="<?php echo HTTPS_URL; ?>assets/css/custom.css?updated=222" rel="stylesheet">
    <link href="<?php echo HTTPS_URL; ?>assets/css/sidebar-fixes.css?v=20250904d" rel="stylesheet">
    <link href="<?php echo HTTPS_URL; ?>assets/css/sidebar-dark-theme-restore.css?v=20250904" rel="stylesheet">

    <?php if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === 'tickets_dashboard.php'): ?>
        <link href="<?php echo HTTPS_URL; ?>assets/css/tickets-dashboard.css?v=20250814" rel="stylesheet">
    <?php endif; ?>

    <!-- JAVASCRIPT - JQUERY FIRST -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    
    <!-- MOMENT.JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <?php if (isset($_SESSION["userID"])): ?>
        <script>
            var staffID = <?php echo (int)$_SESSION["userID"]; ?>;
        </script>
    <?php endif; ?>
    <?php if (isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && $_SESSION['csrf_token'] !== ''): ?>
        <script>
            // Expose session CSRF token for AJAX posts that require it
            window.CIS_CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        </script>
    <?php endif; ?>
    
    <?php
    // Optional: extra head markup from page (styles, meta, links). String only for safety.
    if (isset($extraHead) && is_string($extraHead)) {
        echo $extraHead;
    }
    ?>
</head>