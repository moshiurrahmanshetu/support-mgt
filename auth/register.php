<?php
/**
 * Authentication - Customer Registration
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/guest_check.php';

// Only guests can register
require_guest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // 2. Validate Inputs
        if (empty($name)) {
            $errors[] = 'Please enter your full name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (empty($email)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 191) {
            $errors[] = 'Email address is too long.';
        }

        if (!empty($phone) && (mb_strlen($phone) < 6 || mb_strlen($phone) > 30)) {
            $errors[] = 'Phone number must be between 6 and 30 characters.';
        }

        if (empty($password)) {
            $errors[] = 'Please enter a password.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        // 3. Check for Existing Email
        if (empty($errors)) {
            $db = get_db();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email address already exists.';
            }
        }

        // 4. Create User (Strictly Server-Side Customer Role)
        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $role = 'customer'; // Strict Customer Role for public registration - ignores any POST parameters
            $status = STATUS_ACTIVE;

            $insertStmt = $db->prepare("
                INSERT INTO users (role, name, email, phone, password, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $insertStmt->execute([
                $role,
                $name,
                $email,
                empty($phone) ? null : $phone,
                $hashedPassword,
                $status
            ]);
            $newUserId = (int)$db->lastInsertId();

            // Link user in user_roles table
            require_once __DIR__ . '/../includes/permissions.php';
            $custRole = get_role_by_slug('customer');
            if ($custRole) {
                assign_user_role($newUserId, (int)$custRole['id']);
            }

            // Create Welcome In-App Notification
            require_once __DIR__ . '/../includes/notifications.php';
            create_notification(
                $newUserId,
                'Welcome to Support Desk!',
                'Your account has been created. You can browse our Knowledge Base or submit support inquiries at any time.',
                'system',
                'user',
                $newUserId
            );

            require_once __DIR__ . '/../includes/activity_log.php';
            log_activity($newUserId, 'auth', 'customer_registered', "New customer {$name} ({$email}) registered");

            clear_old_input();
            flash('success', 'Registration successful! You can now sign in with your credentials.');
            redirect('auth/login.php');
        }

        if (!empty($errors)) {
            set_old_input([
                'name'  => $name,
                'email' => $email,
                'phone' => $phone
            ]);
        }
    }
}

$pageTitle = 'Create Account';
$isAuthLayout = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 480px;">
        <!-- Brand Header -->
        <div class="auth-header">
            <a href="<?= url(); ?>" class="auth-logo">
                <img src="<?= url('assets/images/logo.svg'); ?>" alt="Logo" width="48" height="48">
            </a>
            <h1 class="auth-title">Create an Account</h1>
            <p class="auth-subtitle">Join us to access support, submit tickets, and more</p>
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

        <!-- Registration Form -->
        <form action="<?= url('auth/register.php'); ?>" method="POST" novalidate>
            <?= csrf_field(); ?>

            <!-- Full Name -->
            <div class="mb-3">
                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-secondary border-end-0">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" 
                           class="form-control border-start-0" 
                           id="name" 
                           name="name" 
                           value="<?= e(old('name')); ?>" 
                           placeholder="John Doe" 
                           required 
                           autocomplete="name" 
                           autofocus>
                </div>
            </div>

            <!-- Email Address -->
            <div class="mb-3">
                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
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
                           autocomplete="email">
                </div>
            </div>

            <!-- Phone Number -->
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number <span class="text-muted small">(Optional)</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-secondary border-end-0">
                        <i class="bi bi-telephone"></i>
                    </span>
                    <input type="tel" 
                           class="form-control border-start-0" 
                           id="phone" 
                           name="phone" 
                           value="<?= e(old('phone')); ?>" 
                           placeholder="+1 (555) 000-0000" 
                           autocomplete="tel">
                </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
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
                           autocomplete="new-password">
                    <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="password" title="Show/Hide Password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-4">
                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-secondary border-end-0">
                        <i class="bi bi-shield-check"></i>
                    </span>
                    <input type="password" 
                           class="form-control border-start-0" 
                           id="confirm_password" 
                           name="confirm_password" 
                           placeholder="Re-enter your password" 
                           required 
                           autocomplete="new-password">
                    <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="confirm_password" title="Show/Hide Password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button (Solid Color) -->
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-person-plus"></i> Create Account
            </button>
        </form>

        <!-- Login Link -->
        <div class="text-center mt-4 pt-2 border-top">
            <p class="small text-secondary-custom mb-0">
                Already have an account? 
                <a href="<?= url('auth/login.php'); ?>" class="fw-semibold text-decoration-none">Sign in</a>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
