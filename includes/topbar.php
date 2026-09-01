<?php
/**
 * Master Topbar Include (with Live Notification Bell - Phase 05)
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/notifications.php';

$user = current_user();
$unreadNotifCount = $user ? get_unread_notifications_count($user['id']) : 0;
$recentNotifs = $user ? get_recent_notifications($user['id'], 5) : [];
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

    <!-- Right Side Actions & Notification Bell + Profile Menu -->
    <div class="d-flex align-items-center gap-3">
        <?php if ($user): ?>
            <!-- Notification Bell Dropdown -->
            <div class="dropdown">
                <button class="btn btn-link text-secondary-custom position-relative p-1 text-decoration-none border-0" type="button" id="notifBellDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="bi bi-bell fs-5"></i>
                    <?php if ($unreadNotifCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger fs-8">
                            <?= ($unreadNotifCount > 99) ? '99+' : $unreadNotifCount; ?>
                            <span class="visually-hidden">unread notifications</span>
                        </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-sm border p-0" aria-labelledby="notifBellDropdown" style="width: 320px; max-width: 90vw;">
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <span class="fw-bold fs-7 text-dark">Notifications</span>
                        <?php if ($unreadNotifCount > 0): ?>
                            <form action="<?= url('modules/notifications/mark_all_read.php'); ?>" method="POST" class="m-0">
                                <?= csrf_field(); ?>
                                <button type="submit" class="btn btn-link p-0 text-primary small text-decoration-none fs-8">
                                    Mark all read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="list-group list-group-flush" style="max-height: 280px; overflow-y: auto;">
                        <?php if (!empty($recentNotifs)): ?>
                            <?php foreach ($recentNotifs as $rNotif): 
                                $meta = format_notification_meta($rNotif);
                                $isUnread = ((int)$rNotif['is_read'] === 0);
                            ?>
                                <a href="<?= ($meta['url'] !== '#') ? $meta['url'] : url('modules/notifications/index.php'); ?>" 
                                   class="list-group-item list-group-item-action d-flex align-items-start gap-2 p-3 <?= $isUnread ? 'bg-light' : ''; ?>">
                                    <div class="p-1 rounded bg-white border <?= $meta['color_class']; ?> flex-shrink-0 mt-1">
                                        <i class="bi <?= $meta['icon']; ?> fs-6"></i>
                                    </div>
                                    <div class="overflow-hidden flex-grow-1">
                                        <div class="fw-semibold text-dark fs-8 text-truncate"><?= e($rNotif['title']); ?></div>
                                        <div class="text-muted fs-8 text-truncate"><?= e($rNotif['message']); ?></div>
                                        <div class="text-secondary fs-8 mt-1"><?= e(format_datetime($rNotif['created_at'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="bi bi-bell-slash fs-4 d-block mb-1 text-secondary"></i>
                                No notifications yet
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-2 border-top text-center bg-light">
                        <a href="<?= url('modules/notifications/index.php'); ?>" class="text-primary small fw-semibold text-decoration-none d-block">
                            View All Notifications <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

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
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('modules/profile/notifications.php'); ?>">
                            <i class="bi bi-sliders text-secondary"></i> Notification Settings
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
        <?php else: ?>
            <!-- Guest Links for Public Support Center -->
            <div class="d-flex align-items-center gap-2">
                <a href="<?= url('auth/login.php'); ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </a>
                <a href="<?= url('auth/register.php'); ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus"></i> Register
                </a>
            </div>
        <?php endif; ?>
    </div>
</header>
