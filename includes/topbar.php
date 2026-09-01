<?php
/**
 * Master Topbar Include
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_check.php';

$user = current_user();
?>
<header class="app-topbar">
    <div class="d-flex align-items-center gap-3">
        <!-- Sidebar Hamburger Toggler (Desktop & Mobile) -->
        <button type="button" class="topbar-toggler" id="sidebarToggleBtn" aria-label="Toggle Sidebar" title="Toggle Sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>

        <span class="d-none d-sm-inline-block text-secondary-custom fw-medium fs-6">
            <?= isset($pageHeader) ? e($pageHeader) : 'Customer Support Management'; ?>
        </span>
    </div>

    <!-- Right Side Actions & Profile Menu -->
    <div class="d-flex align-items-center gap-3">
        <?php if ($user): ?>
            <!-- User Dropdown Menu -->
            <div class="dropdown">
                <button class="btn btn-link text-decoration-none dropdown-toggle p-0 d-flex align-items-center gap-2 border-0" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?= e(get_avatar_url($user['avatar'] ?? null)); ?>" alt="<?= e($user['name']); ?>" class="avatar-img avatar-sm">
                    <div class="d-none d-md-flex flex-column text-start">
                        <span class="fw-semibold text-dark fs-7 lh-sm"><?= e($user['name']); ?></span>
                        <span class="text-muted fs-8 text-capitalize"><?= e($user['role']); ?></span>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border" aria-labelledby="userMenuDropdown" style="min-width: 220px;">
                    <li class="px-3 py-2 border-bottom">
                        <div class="fw-bold text-truncate"><?= e($user['name']); ?></div>
                        <div class="small text-muted text-truncate"><?= e($user['email']); ?></div>
                        <div class="mt-1">
                            <span class="badge badge-role-<?= e($user['role']); ?>"><?= e($user['role']); ?></span>
                        </div>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('modules/profile/index.php'); ?>">
                            <i class="bi bi-person text-secondary"></i> My Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('modules/profile/change_password.php'); ?>">
                            <i class="bi bi-shield-lock text-secondary"></i> Change Password
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2 text-danger" href="<?= url('auth/logout.php'); ?>">
                            <i class="bi bi-box-arrow-right"></i> Sign Out
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</header>
