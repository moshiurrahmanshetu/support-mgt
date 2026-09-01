<?php
/**
 * Master Sidebar Include (Collapsible, Role-Aware & Phase 05 Ready)
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
               class="nav-link-custom <?= (is_nav_active('index.php', $currentScript, $activePage) && !is_nav_active('profile', $currentScript, $activePage) && !is_nav_active('tickets', $currentScript, $activePage) && !is_nav_active('customers', $currentScript, $activePage) && !is_nav_active('agents', $currentScript, $activePage) && !is_nav_active('departments', $currentScript, $activePage) && !is_nav_active('tags', $currentScript, $activePage) && !is_nav_active('canned_responses', $currentScript, $activePage) && !is_nav_active('notifications', $currentScript, $activePage) && !is_nav_active('activity_logs', $currentScript, $activePage) && !is_nav_active('settings', $currentScript, $activePage)) ? 'active' : ''; ?>" 
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

        <!-- Knowledge Base Portal (All Roles) -->
        <li class="nav-item">
            <a href="<?= url('modules/knowledge_base/index.php'); ?>" 
               class="nav-link-custom <?= (strpos($currentScript, 'knowledge_base') !== false && !in_array($activePage, ['kb_articles', 'kb_categories', 'kb_faqs'], true)) ? 'active' : ''; ?>" 
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               title="Knowledge Base">
                <i class="bi bi-book"></i>
                <span class="nav-text">Help Center</span>
            </a>
        </li>

        <!-- Notifications Link (All Roles) -->
        <li class="nav-item">
            <a href="<?= url('modules/notifications/index.php'); ?>" 
               class="nav-link-custom <?= is_nav_active('notifications', $currentScript, $activePage) ? 'active' : ''; ?>" 
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="nav-text">Notifications</span>
            </a>
        </li>

        <?php if ($user && $user['role'] === ROLE_ADMIN): ?>
            <!-- Admin Management Section -->
            <li class="nav-header">
                <span class="nav-header-text">Administration</span>
            </li>

            <!-- KB Articles -->
            <li class="nav-item">
                <a href="<?= url('modules/knowledge_base/articles/index.php'); ?>" 
                   class="nav-link-custom <?= ($activePage === 'kb_articles' || strpos($currentScript, 'articles') !== false) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="KB Articles">
                    <i class="bi bi-file-earmark-text"></i>
                    <span class="nav-text">KB Articles</span>
                </a>
            </li>

            <!-- KB Categories -->
            <li class="nav-item">
                <a href="<?= url('modules/knowledge_base/categories/index.php'); ?>" 
                   class="nav-link-custom <?= ($activePage === 'kb_categories' || strpos($currentScript, 'categories') !== false) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="KB Categories">
                    <i class="bi bi-folder"></i>
                    <span class="nav-text">KB Categories</span>
                </a>
            </li>

            <!-- FAQs -->
            <li class="nav-item">
                <a href="<?= url('modules/knowledge_base/faqs/index.php'); ?>" 
                   class="nav-link-custom <?= ($activePage === 'kb_faqs' || strpos($currentScript, 'faqs') !== false) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Manage FAQs">
                    <i class="bi bi-question-circle"></i>
                    <span class="nav-text">FAQs</span>
                </a>
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

            <!-- System Activity Logs -->
            <li class="nav-item">
                <a href="<?= url('modules/activity_logs/index.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('activity_logs', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Activity Logs">
                    <i class="bi bi-clock-history"></i>
                    <span class="nav-text">Activity Logs</span>
                </a>
            </li>

            <!-- Reports & Analytics -->
            <li class="nav-item">
                <a href="<?= url('modules/reports/index.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('reports', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Reports & Analytics">
                    <i class="bi bi-graph-up"></i>
                    <span class="nav-text">Reports</span>
                </a>
            </li>

            <!-- System Settings -->
            <li class="nav-item">
                <a href="<?= url('modules/settings/index.php'); ?>" 
                   class="nav-link-custom <?= is_nav_active('settings', $currentScript, $activePage) ? 'active' : ''; ?>" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="System Settings">
                    <i class="bi bi-gear"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </li>
        <?php endif; ?>

        <!-- Account Section Header -->
        <li class="nav-header">
            <span class="nav-header-text">Account</span>
        </li>

        <?php if ($user): ?>
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
        <?php else: ?>
            <!-- Guest Sign In / Register -->
            <li class="nav-item">
                <a href="<?= url('auth/login.php'); ?>" 
                   class="nav-link-custom" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Sign In">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span class="nav-text">Sign In</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('auth/register.php'); ?>" 
                   class="nav-link-custom" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="right" 
                   title="Create Account">
                    <i class="bi bi-person-plus"></i>
                    <span class="nav-text">Create Account</span>
                </a>
            </li>
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
