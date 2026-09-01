<?php
/**
 * Authentication - Forgot Password Request
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/guest_check.php';

require_guest();

$errors = [];
$successMessage = '';
$devResetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($errors)) {
            $db = get_db();
            $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate secure random token
                $token = generate_token(32);
                
                // Expire existing unused tokens for this email
                $invalidateStmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE email = ?");
                $invalidateStmt->execute([$email]);

                // Store new reset token (valid for 1 hour)
                $insertStmt = $db->prepare("
                    INSERT INTO password_resets (email, token, created_at, expires_at, used)
                    VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)
                ");
                $insertStmt->execute([$email, $token]);

                // Prepare development simulation link
                $devResetLink = url('auth/reset_password.php?token=' . urlencode($token));
            }

            // Always show standard user-facing message to prevent email enumeration
            $successMessage = 'If the email is associated with an active account, a password reset link has been generated.';
            clear_old_input();
        }
    }
}

$pageTitle = 'Forgot Password';
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
            <h1 class="auth-title">Reset Password</h1>
            <p class="auth-subtitle">Enter your registered email to receive password reset instructions</p>
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

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <strong>Reset Request Processed</strong>
                </div>
                <p class="mb-2 small"><?= e($successMessage); ?></p>
                
                <?php if (!empty($devResetLink)): ?>
                    <div class="p-2 bg-white border rounded mt-3">
                        <span class="badge bg-secondary mb-1">Development / Demo Link:</span>
                        <p class="small text-muted mb-1">Click the button below to proceed to the password reset form directly:</p>
                        <a href="<?= e($devResetLink); ?>" class="btn btn-sm btn-primary w-100 mt-1">
                            <i class="bi bi-key"></i> Proceed to Reset Password
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Request Form -->
            <form action="<?= url('auth/forgot_password.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>

                <div class="mb-4">
                    <label for="email" class="form-label">Registered Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-secondary border-end-0">
                            <i class="bi bi-envelope"></i>
                        </span>
                        <input type="email" 
                               class="form-control border-start-0" 
                               id="email" 
                               name="email" 
                               value="<?= e(old('email')); ?>" 
                               placeholder="name@example.com" 
                               required 
                               autocomplete="email" 
                               autofocus>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-send"></i> Send Reset Link
                </button>
            </form>
        <?php endif; ?>

        <!-- Return to Login Link -->
        <div class="text-center mt-4 pt-2 border-top">
            <p class="small text-secondary-custom mb-0">
                Remember your credentials? 
                <a href="<?= url('auth/login.php'); ?>" class="fw-semibold text-decoration-none">Back to sign in</a>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
