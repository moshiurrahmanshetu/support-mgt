<?php
/**
 * Authentication - Reset Password Handler
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/guest_check.php';

require_guest();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$tokenValid = false;
$resetRecord = null;

$db = get_db();

// Verify Token validity
if (!empty($token)) {
    $stmt = $db->prepare("
        SELECT * FROM password_resets 
        WHERE token = ? AND used = 0 AND expires_at > NOW() 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $resetRecord = $stmt->fetch();

    if ($resetRecord) {
        $tokenValid = true;
    } else {
        $errors[] = 'This password reset link is invalid or has expired. Please request a new one.';
    }
} else {
    $errors[] = 'No password reset token was provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($password)) {
            $errors[] = 'Please enter a new password.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Update user password
            $updateUserStmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
            $updateUserStmt->execute([$hashedPassword, $resetRecord['email']]);

            // Mark reset token as used
            $markTokenStmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $markTokenStmt->execute([$resetRecord['id']]);

            flash('success', 'Your password has been reset successfully. You can now sign in with your new password.');
            redirect('auth/login.php');
        }
    }
}

$pageTitle = 'Set New Password';
$isAuthLayout = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Brand Header -->
        <div class="auth-header">
            <a href="<?= url(); ?>" class="auth-logo">
                <img src="<?= url('assets/images/logo.svg'); ?>" alt="Logo" width="48" height="48">
            </a>
            <h1 class="auth-title">Set New Password</h1>
            <p class="auth-subtitle">Create a strong, secure password for your account</p>
        </div>

        <?php include __DIR__ . '/../includes/flash_messages.php'; ?>

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

        <?php if ($tokenValid): ?>
            <!-- Reset Password Form -->
            <form action="<?= url('auth/reset_password.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="token" value="<?= e($token); ?>">

                <!-- Password -->
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-secondary border-end-0">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control border-start-0" 
                               id="password" 
                               name="password" 
                               placeholder="At least 8 characters" 
                               required 
                               autocomplete="new-password" 
                               autofocus>
                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="password" title="Show/Hide Password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
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

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-check2-circle"></i> Update Password
                </button>
            </form>
        <?php else: ?>
            <div class="text-center mt-3">
                <a href="<?= url('auth/forgot_password.php'); ?>" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-clockwise"></i> Request New Reset Link
                </a>
            </div>
        <?php endif; ?>

        <!-- Return to Login Link -->
        <div class="text-center mt-4 pt-2 border-top">
            <p class="small text-secondary-custom mb-0">
                Back to 
                <a href="<?= url('auth/login.php'); ?>" class="fw-semibold text-decoration-none">Sign in</a>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
