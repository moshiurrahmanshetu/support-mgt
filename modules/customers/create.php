<?php
/**
 * Customer Management - Create Customer Account (Phase 08 Completion)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('customers.create');

$db = get_db();
$currentUser = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = trim($_POST['status'] ?? STATUS_ACTIVE);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // 1. Validate Input
        if (empty($name)) {
            $errors[] = 'Please enter the customer full name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (empty($email)) {
            $errors[] = 'Please enter the customer email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Check Duplicate Email
            $eStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $eStmt->execute([$email]);
            if ($eStmt->fetch()) {
                $errors[] = 'An account with this email address already exists.';
            }
        }

        if (!empty($phone) && (mb_strlen($phone) < 6 || mb_strlen($phone) > 30)) {
            $errors[] = 'Phone number must be between 6 and 30 characters.';
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $status = STATUS_ACTIVE;
        }

        if (empty($password)) {
            $errors[] = 'Please enter a password for this customer account.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        // 2. Avatar Upload Processing
        $avatarFilename = null;
        if (!empty($_FILES['avatar']['name']) && empty($errors)) {
            $file = $_FILES['avatar'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Avatar upload failed with error code ' . $file['error'];
            } elseif ($file['size'] > MAX_AVATAR_SIZE) {
                $errors[] = 'Avatar size exceeds the 2MB limit.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($mime, ALLOWED_AVATAR_MIMES, true) || !in_array($ext, ALLOWED_AVATAR_EXTENSIONS, true)) {
                    $errors[] = 'Invalid avatar image type. Only JPG, PNG, and WebP are allowed.';
                } else {
                    $avatarFilename = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    $targetPath = __DIR__ . '/../../uploads/avatars/' . $avatarFilename;
                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $errors[] = 'Failed to save uploaded avatar.';
                        $avatarFilename = null;
                    }
                }
            }
        }

        // 3. Database Transaction: Create User with Customer Role
        if (empty($errors)) {
            // Find role record for 'customer'
            $custRole = get_role_by_slug('customer');
            if (!$custRole) {
                $errors[] = 'Customer system role configuration is missing. Please contact system administrator.';
            } else {
                $db->beginTransaction();
                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $roleSlug = 'customer'; // Strictly enforced server-side

                    $insertStmt = $db->prepare("
                        INSERT INTO users (role, name, email, phone, avatar, password, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $insertStmt->execute([
                        $roleSlug,
                        $name,
                        $email,
                        empty($phone) ? null : $phone,
                        $avatarFilename,
                        $hashedPassword,
                        $status
                    ]);
                    $newCustomerId = (int)$db->lastInsertId();

                    // Assign in user_roles table
                    $urStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())");
                    $urStmt->execute([$newCustomerId, (int)$custRole['id']]);

                    // Send Welcome In-App Notification
                    create_notification(
                        $newCustomerId,
                        'Welcome to Customer Support!',
                        'Your customer support account has been provisioned. You can submit and track support inquiries anytime.',
                        'system',
                        'user',
                        $newCustomerId
                    );

                    // Audit Log
                    log_activity(
                        $currentUser['id'],
                        'customer',
                        'customer_created',
                        "Created new customer account: {$name} ({$email})",
                        'user',
                        $newCustomerId
                    );

                    $db->commit();

                    clear_old_input();
                    flash('success', "Customer <strong>" . e($name) . "</strong> created successfully!");
                    redirect('modules/customers/view.php?id=' . $newCustomerId);
                } catch (Exception $e) {
                    $db->rollBack();
                    if ($avatarFilename) {
                        @unlink(__DIR__ . '/../../uploads/avatars/' . $avatarFilename);
                    }
                    error_log("Failed to create customer: " . $e->getMessage());
                    $errors[] = 'An error occurred while creating the customer account. Please try again.';
                }
            }
        }

        if (!empty($errors)) {
            set_old_input([
                'name'   => $name,
                'email'  => $email,
                'phone'  => $phone,
                'status' => $status
            ]);
        }
    }
}

$old = get_old_input();
$pageTitle = 'Add Customer';
$pageHeader = 'Create Customer Account';
$activePage = 'customers';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 760px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/customers/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Customer Directory
        </a>
    </div>

    <!-- Error Alert -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm border-0 mb-4">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please resolve the following errors:</div>
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card border shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
            <h1 class="h6 fw-bold mb-0 text-dark">
                <i class="bi bi-person-plus me-2 text-primary"></i>New Customer Account
            </h1>
        </div>

        <div class="card-body p-4">
            <form action="<?= url('modules/customers/create.php'); ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field(); ?>

                <!-- Profile Information Section -->
                <h2 class="h7 fw-bold text-dark text-uppercase fs-8 border-bottom pb-1 mb-3">
                    <i class="bi bi-person me-1 text-primary"></i>Customer Information
                </h2>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label fs-7 fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Emily Davis" value="<?= e($old['name'] ?? ''); ?>" required autofocus>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label fs-7 fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="emily@company.com" value="<?= e($old['email'] ?? ''); ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="phone" class="form-label fs-7 fw-semibold text-dark">Phone Number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= e($old['phone'] ?? ''); ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label fs-7 fw-semibold text-dark">Account Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="active" <?= (($old['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active (Can Sign In & Submit Tickets)</option>
                            <option value="inactive" <?= (($old['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive (Access Blocked)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="avatar" class="form-label fs-7 fw-semibold text-dark">Profile Picture</label>
                        <input type="file" name="avatar" id="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text fs-8">Optional. Maximum file size: 2MB (JPG, PNG, WebP).</div>
                    </div>
                </div>

                <!-- Password Credentials Section -->
                <h2 class="h7 fw-bold text-dark text-uppercase fs-8 border-bottom pb-1 mb-3">
                    <i class="bi bi-key me-1 text-primary"></i>Security & Credentials
                </h2>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="password" class="form-label fs-7 fw-semibold text-dark">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Minimum 8 characters" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="confirm_password" class="form-label fs-7 fw-semibold text-dark">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Repeat password" required>
                    </div>
                </div>

                <!-- Form Submit Actions -->
                <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                    <a href="<?= url('modules/customers/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-check me-1"></i> Create Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
