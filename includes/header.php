<?php
/**
 * Master Header Include
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME;
$isAuthLayout = isset($isAuthLayout) && $isAuthLayout === true;
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/images/logo.svg'); ?>">

    <!-- Bootstrap 5 CSS (Local with CDN fallback) -->
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>" onerror="this.onerror=null;this.href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';">
    
    <!-- Bootstrap Icons (Local with CDN fallback) -->
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css'); ?>" onerror="this.onerror=null;this.href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css';">
    
    <!-- Custom Theme CSS (Solid Colors Only) -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css'); ?>">
</head>
<body class="<?= $isAuthLayout ? 'auth-body' : 'app-body'; ?>">

<?php if (!$isAuthLayout): ?>
<div class="app-wrapper">
    <!-- Sidebar Backdrop for Mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    
    <!-- Collapsible Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Application Area -->
    <div class="app-main">
        <!-- Sticky Topbar -->
        <?php include __DIR__ . '/topbar.php'; ?>

        <!-- Content Area -->
        <main class="app-content">
            <?php include __DIR__ . '/flash_messages.php'; ?>
<?php endif; ?>
