<?php
/**
 * Profile Module - Change Password
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_login();

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword)) {
            $errors[] = 'Please enter your current password.';
        }

        if (empty($newPassword)) {
            $errors[] = 'Please enter a new password.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password confirmation does not match.';
        }

        if ($currentPassword === $newPassword) {
            $errors[] = 'New password cannot be identical to your current password.';
        }

        if (empty($errors)) {
            $db = get_db();
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user['id']]);
            $dbRecord = $stmt->fetch();

            if ($dbRecord && password_verify($currentPassword, $dbRecord['password'])) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$newHash, $user['id']]);

                // Regenerate session to protect session state
                session_regenerate_id(true);

                require_once __DIR__ . '/../../includes/activity_log.php';
                log_activity((int)$user['id'], 'profile', 'password_changed', 'Changed account password');

                flash('success', 'Your password has been changed successfully!');
                redirect('modules/profile/index.php');
            } else {
                $errors[] = 'The current password you provided is incorrect.';
            }
        }
    }
}

$pageTitle = 'Change Password';
$pageHeader = 'Change Password';
$activePage = 'profile';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 720px;">
    <!-- Breadcrumb / Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/profile/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Profile
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-shield-lock me-2 text-primary"></i>Change Account Password
            </h5>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="<?= url('modules/profile/change_password.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>

                <!-- Current Password -->
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-secondary border-end-0">
                            <i class="bi bi-key"></i>
                        </span>
                        <input type="password" 
                               class="form-control border-start-0" 
                               id="current_password" 
                               name="current_password" 
                               placeholder="Enter your current password" 
                               required 
                               autocomplete="current-password" 
                               autofocus>
                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="current_password" title="Show/Hide Password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- New Password -->
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-secondary border-end-0">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control border-start-0" 
                               id="new_password" 
                               name="new_password" 
                               placeholder="At least 8 characters" 
                               required 
                               autocomplete="new-password">
                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="new_password" title="Show/Hide Password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm New Password -->
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-secondary border-end-0">
                            <i class="bi bi-shield-check"></i>
                        </span>
                        <input type="password" 
                               class="form-control border-start-0" 
                               id="confirm_password" 
                               name="confirm_password" 
                               placeholder="Re-enter new password" 
                               required 
                               autocomplete="new-password">
                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="confirm_password" title="Show/Hide Password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> Update Password
                    </button>
                    <a href="<?= url('modules/profile/index.php'); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
