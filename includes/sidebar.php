<?php
/**
 * Master Sidebar Include (Collapsible & Role-Aware - Phase 04)
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
               class="nav-link-custom <?= (is_nav_active('index.php', $currentScript, $activePage) && !is_nav_active('profile', $currentScript, $activePage) && !is_nav_active('tickets', $currentScript, $activePage) && !is_nav_active('customers', $currentScript, $activePage) && !is_nav_active('agents', $currentScript, $activePage) && !is_nav_active('departments', $currentScript, $activePage) && !is_nav_active('tags', $currentScript, $activePage) && !is_nav_active('canned_responses', $currentScript, $activePage)) ? 'active' : ''; ?>" 
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               title="Dashboard">
                <i class="bi bi-speedometer2"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>

        <!-- Support Section Header -->
        <li class="nav-header">
            <span class="nav-header-text">Support Desk</span>
        </li>

        <?php if ($user && $user['role'] === ROLE_CUSTOMER): ?>
            <!-- Customer Links -->
            <li class="nav-item">
                <a href="<?= url('modules/tickets/index.php'); ?>" 
                   class="nav-link-custom <?= (is_nav_active('tickets', $currentScript, $activePage) && !is_nav_active('create.php', $currentScript, $activePage)) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="My Tickets">
                    <i class="bi bi-ticket-perforated"></i>
                    <span class="nav-text">My Tickets</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('modules/tickets/create.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('create.php', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Create Ticket">
                    <i class="bi bi-plus-circle"></i>
                    <span class="nav-text">Create Ticket</span>
                </a>
            </li>
        <?php else: ?>
            <!-- Admin / Agent Support Links -->
            <li class="nav-item">
                <a href="<?= url('modules/tickets/index.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('tickets', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Support Tickets">
                    <i class="bi bi-ticket-perforated"></i>
                    <span class="nav-text">Support Tickets</span>
                </a>
            </li>

            <!-- Canned Responses (Admin & Agent) -->
            <li class="nav-item">
                <a href="<?= url('modules/canned_responses/index.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('canned_responses', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Canned Responses">
                    <i class="bi bi-chat-square-quote"></i>
                    <span class="nav-text">Canned Responses</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($user && $user['role'] === ROLE_ADMIN): ?>
            <!-- Admin Management Section -->
            <li class="nav-header">
                <span class="nav-header-text">Administration</span>
            </li>

            <!-- Customers -->
            <li class="nav-item">
                <a href="<?= url('modules/customers/index.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('customers', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Customers">
                    <i class="bi bi-people"></i>
                    <span class="nav-text">Customers</span>
                </a>
            </li>

            <!-- Agents -->
            <li class="nav-item">
                <a href="<?= url('modules/agents/index.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('agents', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Support Agents">
                    <i class="bi bi-headset"></i>
                    <span class="nav-text">Support Agents</span>
                </a>
            </li>

            <!-- Departments -->
            <li class="nav-item">
                <a href="<?= url('modules/departments/index.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('departments', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Departments">
                    <i class="bi bi-building"></i>
                    <span class="nav-text">Departments</span>
                </a>
            </li>

            <!-- Tags -->
            <li class="nav-item">
                <a href="<?= url('modules/tags/index.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('tags', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Ticket Tags">
                    <i class="bi bi-tags"></i>
                    <span class="nav-text">Ticket Tags</span>
                </a>
            </li>
        <?php endif; ?>

        <!-- Account Section Header -->
        <li class="nav-header">
            <span class="nav-header-text">Account</span>
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
