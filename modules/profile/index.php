<?php
/**
 * Profile Module - Overview
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_login();

$user = current_user();
$pageTitle = 'My Profile';
$pageHeader = 'User Profile';
$activePage = 'profile';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <div class="row g-4">
        <!-- Left Profile Summary Card -->
        <div class="col-12 col-lg-4">
            <div class="card border shadow-sm text-center p-4">
                <div class="mb-3 position-relative d-inline-block mx-auto">
                    <img src="<?= e(get_avatar_url($user['avatar'] ?? null)); ?>" 
                         alt="<?= e($user['name']); ?>" 
                         class="avatar-img avatar-xl shadow-sm">
                </div>
                
                <h3 class="h5 fw-bold mb-1"><?= e($user['name']); ?></h3>
                <p class="text-secondary-custom small mb-2"><?= e($user['email']); ?></p>

                <div class="d-flex justify-content-center gap-2 mb-4">
                    <span class="badge badge-role-<?= e($user['role']); ?>"><?= e($user['role']); ?></span>
                    <span class="badge badge-status-<?= e($user['status']); ?>"><?= e($user['status']); ?></span>
                </div>

                <div class="d-grid gap-2">
                    <a href="<?= url('modules/profile/change_avatar.php'); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-camera"></i> Change Avatar
                    </a>
                    <a href="<?= url('modules/profile/edit.php'); ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil-square"></i> Edit Profile
                    </a>
                    <a href="<?= url('modules/profile/change_password.php'); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-shield-lock"></i> Change Password
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Profile Information Table & Account Meta -->
        <div class="col-12 col-lg-8">
            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-person-lines-fill me-2 text-primary"></i>Personal Information
                    </h5>
                    <a href="<?= url('modules/profile/edit.php'); ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Full Name:</div>
                        <div class="col-sm-8 fw-semibold"><?= e($user['name']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Email Address:</div>
                        <div class="col-sm-8 fw-semibold d-flex align-items-center gap-2">
                            <span><?= e($user['email']); ?></span>
                            <?php if (!empty($user['email_verified_at'])): ?>
                                <span class="badge bg-success small"><i class="bi bi-patch-check"></i> Verified</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark small"><i class="bi bi-exclamation-circle"></i> Unverified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Phone Number:</div>
                        <div class="col-sm-8 fw-semibold">
                            <?= !empty($user['phone']) ? e($user['phone']) : '<span class="text-muted fst-italic">Not provided</span>'; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Assigned Role:</div>
                        <div class="col-sm-8">
                            <span class="badge badge-role-<?= e($user['role']); ?>"><?= e($user['role']); ?></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Account Status:</div>
                        <div class="col-sm-8">
                            <span class="badge badge-status-<?= e($user['status']); ?>"><?= e($user['status']); ?></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Member Since:</div>
                        <div class="col-sm-8 fw-semibold"><?= e(format_datetime($user['created_at'])); ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4 text-muted">Last Login:</div>
                        <div class="col-sm-8 fw-semibold"><?= e(format_datetime($user['last_login_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-shield-check me-2 text-primary"></i>Security & Authentication Details
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-secondary-custom small mb-3">
                        Your account password is encrypted using strong industry-standard hashing algorithms (`bcrypt` / `password_hash`). If you suspect any unauthorized activity, please change your password immediately.
                    </p>
                    <a href="<?= url('modules/profile/change_password.php'); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-key"></i> Update Security Password
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
