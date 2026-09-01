<?php
/**
 * Master Sidebar Include (Collapsible & Role-Aware)
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_check.php';

$user = current_user();
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$activePage = $activePage ?? '';

// Helper for active navigation link
function is_nav_active(string $target, string $currentScript, string $activePage): bool {
    if (!empty($activePage) && $activePage === $target) {
        return true;
    }
    return strpos($currentScript, $target) !== false;
}
?>
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand Header -->
    <a href="<?= url('index.php'); ?>" class="sidebar-brand">
        <img src="<?= url('assets/images/logo.svg'); ?>" alt="Logo" width="28" height="28" class="flex-shrink-0">
        <span class="sidebar-brand-text"><?= e(APP_NAME); ?></span>
    </a>

    <!-- Navigation Items -->
    <ul class="sidebar-nav">
        <!-- Main Section -->
        <li class="nav-header">
            <span class="nav-header-text">Navigation</span>
        </li>

        <!-- Dashboard Link -->
        <li class="nav-item">
            <a href="<?= url('index.php'); ?>" 
               class="nav-link-custom <?= (is_nav_active('index.php', $currentScript, $activePage) && !is_nav_active('profile', $currentScript, $activePage)) ? 'active' : ''; ?>" 
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               title="Dashboard">
                <i class="bi bi-speedometer2"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>

        <!-- Profile Link -->
        <li class="nav-item">
            <a href="<?= url('modules/profile/index.php'); ?>" 
               class="nav-link-custom <?= is_nav_active('profile', $currentScript, $activePage) ? 'active' : ''; ?>" 
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               title="My Profile">
                <i class="bi bi-person-circle"></i>
                <span class="nav-text">My Profile</span>
            </a>
        </li>

        <!-- User Role-specific Menu Slots (Ready for Future Modules) -->
        <?php if ($user && $user['role'] === ROLE_ADMIN): ?>
            <!-- Admin Role Area (Extensible for Phase 02+) -->
        <?php elseif ($user && $user['role'] === ROLE_AGENT): ?>
            <!-- Agent Role Area (Extensible for Phase 02+) -->
        <?php elseif ($user && $user['role'] === ROLE_CUSTOMER): ?>
            <!-- Customer Role Area (Extensible for Phase 02+) -->
        <?php endif; ?>
    </ul>

    <!-- Sidebar Footer / Account Info -->
    <?php if ($user): ?>
    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <img src="<?= e(get_avatar_url($user['avatar'] ?? null)); ?>" alt="<?= e($user['name']); ?>" class="avatar-img avatar-sm flex-shrink-0">
            <div class="sidebar-footer-text overflow-hidden">
                <div class="text-white text-truncate small fw-medium"><?= e($user['name']); ?></div>
                <div class="text-muted-custom fs-8 text-capitalize"><?= e($user['role']); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</aside>
