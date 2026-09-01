<?php
/**
 * Authentication - Login Page
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/guest_check.php';

// Only unauthenticated guests can access
require_guest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        // 2. Validate Inputs
        if (empty($email)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($password)) {
            $errors[] = 'Please enter your password.';
        }

        // 3. Process Login
        if (empty($errors)) {
            $db = get_db();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Check if account is active
                if ($user['status'] !== STATUS_ACTIVE) {
                    $errors[] = 'Your account is inactive. Please contact an administrator.';
                } else {
                    // Successful authentication: Regenerate session ID
                    session_regenerate_id(true);

                    // Update last login timestamp
                    $updateStmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);

                    // Store user data in session (exclude sensitive password hash)
                    unset($user['password']);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['user'] = $user;

                    clear_old_input();
                    flash('success', 'Welcome back, ' . $user['name'] . '!');

                    // Redirect to intended URL or dashboard
                    $redirectUrl = $_SESSION['intended_url'] ?? url('index.php');
                    unset($_SESSION['intended_url']);
                    redirect($redirectUrl);
                }
            } else {
                // Generic error for security
                $errors[] = 'Invalid email address or password.';
            }
        }

        if (!empty($errors)) {
            set_old_input(['email' => $email]);
        }
    }
}

$pageTitle = 'Sign In';
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
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to manage your support account</p>
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

        <!-- Login Form -->
        <form action="<?= url('auth/login.php'); ?>" method="POST" novalidate>
            <?= csrf_field(); ?>

            <!-- Email Field -->
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
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

            <!-- Password Field -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label mb-0">Password</label>
                    <a href="<?= url('auth/forgot_password.php'); ?>" class="small text-decoration-none">Forgot password?</a>
                </div>
                <div class="input-group mt-1">
                    <span class="input-group-text bg-white text-secondary border-end-0">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" 
                           class="form-control border-start-0" 
                           id="password" 
                           name="password" 
                           placeholder="Enter your password" 
                           required 
                           autocomplete="current-password">
                    <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="password" title="Show/Hide Password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Remember Me -->
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small text-secondary-custom" for="remember">
                    Remember my login on this device
                </label>
            </div>

            <!-- Submit Button (Solid Color) -->
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <!-- Register Link -->
        <div class="text-center mt-4 pt-2 border-top">
            <p class="small text-secondary-custom mb-0">
                Don't have an account? 
                <a href="<?= url('auth/register.php'); ?>" class="fw-semibold text-decoration-none">Create account</a>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
