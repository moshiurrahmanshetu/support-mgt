<?php
/**
 * Main Application Dashboard
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

// Protect Dashboard - requires authentication
require_login();

$user = current_user();
$pageTitle = 'Dashboard';
$pageHeader = 'Dashboard Overview';
$activePage = 'index.php';

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Welcome Header Banner -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: #ffffff;">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= e(get_avatar_url($user['avatar'] ?? null)); ?>" 
                         alt="<?= e($user['name']); ?>" 
                         class="avatar-img avatar-lg flex-shrink-0">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h4 mb-0 fw-bold"><?= e($user['name']); ?></h2>
                            <span class="badge badge-role-<?= e($user['role']); ?>"><?= e($user['role']); ?></span>
                        </div>
                        <p class="text-secondary-custom mb-0 small">
                            <i class="bi bi-envelope me-1"></i> <?= e($user['email']); ?>
                            <?php if (!empty($user['phone'])): ?>
                                <span class="mx-2">&bull;</span>
                                <i class="bi bi-telephone me-1"></i> <?= e($user['phone']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('modules/profile/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-person"></i> View Profile
                    </a>
                    <a href="<?= url('modules/profile/edit.php'); ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil-square"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Details Cards -->
    <div class="row g-3 mb-4">
        <!-- Account Status Card -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary-custom small fw-medium">Account Status</span>
                        <div class="p-2 rounded bg-light text-primary">
                            <i class="bi bi-shield-check fs-5"></i>
                        </div>
                    </div>
                    <div class="h5 fw-bold mb-1">
                        <span class="badge badge-status-<?= e($user['status']); ?>">
                            <?= e($user['status']); ?>
                        </span>
                    </div>
                    <span class="text-muted small">Account is active and verified</span>
                </div>
            </div>
        </div>

        <!-- Assigned Role Card -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary-custom small fw-medium">Assigned Role</span>
                        <div class="p-2 rounded bg-light text-primary">
                            <i class="bi bi-person-badge fs-5"></i>
                        </div>
                    </div>
                    <div class="h5 fw-bold mb-1 text-capitalize">
                        <?= e($user['role']); ?>
                    </div>
                    <span class="text-muted small">Access level permissions</span>
                </div>
            </div>
        </div>

        <!-- Last Login Card -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary-custom small fw-medium">Last Login</span>
                        <div class="p-2 rounded bg-light text-primary">
                            <i class="bi bi-clock-history fs-5"></i>
                        </div>
                    </div>
                    <div class="h6 fw-bold mb-1 text-truncate">
                        <?= e(format_datetime($user['last_login_at'])); ?>
                    </div>
                    <span class="text-muted small">Recorded session time</span>
                </div>
            </div>
        </div>

        <!-- Member Since Card -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary-custom small fw-medium">Member Since</span>
                        <div class="p-2 rounded bg-light text-primary">
                            <i class="bi bi-calendar-check fs-5"></i>
                        </div>
                    </div>
                    <div class="h6 fw-bold mb-1 text-truncate">
                        <?= e(format_datetime($user['created_at'], 'M d, Y')); ?>
                    </div>
                    <span class="text-muted small">Registration date</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts & Foundation Information -->
    <div class="row g-3">
        <!-- Quick Profile Actions -->
        <div class="col-12 col-lg-6">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-lightning-charge me-2 text-primary"></i>Quick Account Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="<?= url('modules/profile/edit.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded bg-light text-secondary">
                                    <i class="bi bi-person-lines-fill"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">Update Personal Information</div>
                                    <div class="small text-muted">Change your name and contact phone number</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>

                        <a href="<?= url('modules/profile/change_avatar.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded bg-light text-secondary">
                                    <i class="bi bi-image"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">Change Profile Avatar</div>
                                    <div class="small text-muted">Upload a new photo (JPG, PNG, WebP max 2MB)</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>

                        <a href="<?= url('modules/profile/change_password.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded bg-light text-secondary">
                                    <i class="bi bi-shield-lock"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">Change Security Password</div>
                                    <div class="small text-muted">Update your login password regularly for security</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Foundation Status -->
        <div class="col-12 col-lg-6">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-cpu me-2 text-primary"></i>System Environment & Phase 01 Status
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 40%;">Application Name:</td>
                                <td class="fw-semibold"><?= e(APP_NAME); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Phase 01 Status:</td>
                                <td><span class="badge bg-success">Foundation Active</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">PHP Engine:</td>
                                <td class="fw-semibold">PHP <?= phpversion(); ?> (Raw PHP PDO)</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Database Connection:</td>
                                <td><span class="badge bg-success">MySQL Connected</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">CSRF Protection:</td>
                                <td><span class="badge bg-primary">Enabled</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Session Security:</td>
                                <td><span class="badge bg-primary">Active (HttpOnly)</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
