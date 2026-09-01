<?php
/**
 * Authentication - Account Verification Endpoint
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');
$verified = false;
$message = '';

if (!empty($email)) {
    $db = get_db();
    $stmt = $db->prepare("SELECT id, name, email, email_verified_at FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if (!empty($user['email_verified_at'])) {
            $verified = true;
            $message = 'Your email address is already verified.';
        } else {
            // Update email_verified_at
            $updateStmt = $db->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            $verified = true;
            $message = 'Thank you! Your email address has been successfully verified.';
            
            // Refresh session if currently logged in
            if (is_logged_in() && (int)$_SESSION['user_id'] === (int)$user['id']) {
                refresh_user_session((int)$user['id']);
            }
        }
    } else {
        $message = 'Unable to find an account associated with this verification request.';
    }
} else {
    $message = 'Invalid verification link or parameters.';
}

$pageTitle = 'Account Verification';
$isAuthLayout = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card text-center">
        <a href="<?= url(); ?>" class="auth-logo">
            <img src="<?= url('assets/images/logo.svg'); ?>" alt="Logo" width="48" height="48">
        </a>
        <h1 class="auth-title">Account Verification</h1>
        
        <div class="my-4">
            <?php if ($verified): ?>
                <div class="text-success mb-3">
                    <i class="bi bi-patch-check-fill" style="font-size: 3rem;"></i>
                </div>
                <div class="alert alert-success" role="alert">
                    <?= e($message); ?>
                </div>
            <?php else: ?>
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-octagon-fill" style="font-size: 3rem;"></i>
                </div>
                <div class="alert alert-danger" role="alert">
                    <?= e($message); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-3">
            <?php if (is_logged_in()): ?>
                <a href="<?= url('index.php'); ?>" class="btn btn-primary w-100">
                    <i class="bi bi-speedometer2"></i> Return to Dashboard
                </a>
            <?php else: ?>
                <a href="<?= url('auth/login.php'); ?>" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In to Account
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
