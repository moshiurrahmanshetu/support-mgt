<?php
/**
 * Customer Management - Edit Customer Account (Phase 08 Completion)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('customers.edit');

$customerId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($customerId <= 0) {
    flash('danger', 'Invalid customer identifier.');
    redirect('modules/customers/index.php');
}

$db = get_db();
$currentUser = current_user();

// Fetch Customer Record
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer account not found.');
    redirect('modules/customers/index.php');
}

if (!empty($customer['deleted_at'])) {
    flash('warning', 'This customer account is deleted. Please restore it before making edits.');
    redirect('modules/customers/view.php?id=' . $customerId);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = trim($_POST['status'] ?? $customer['status']);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';

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
            $eStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $eStmt->execute([$email, $customerId]);
            if ($eStmt->fetch()) {
                $errors[] = 'Another account with this email address already exists.';
            }
        }

        if (!empty($phone) && (mb_strlen($phone) < 6 || mb_strlen($phone) > 30)) {
            $errors[] = 'Phone number must be between 6 and 30 characters.';
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $status = $customer['status'];
        }

        // 2. Optional Password Update Validation
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = 'New password must be at least 8 characters long.';
            }
            if ($password !== $confirmPassword) {
                $errors[] = 'Password confirmation does not match.';
            }
        }

        // 3. Avatar Processing
        $avatarFilename = $customer['avatar'];
        if ($removeAvatar && $avatarFilename) {
            $oldPath = __DIR__ . '/../../uploads/avatars/' . $avatarFilename;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
            $avatarFilename = null;
        }

        if (!empty($_FILES['avatar']['name']) && empty($errors)) {
            $file = $_FILES['avatar'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Avatar upload failed with error code ' . $file['error'];
            } elseif ($file['size'] > MAX_AVATAR_SIZE) {
                $errors[] = 'Avatar size exceeds 2MB limit.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($mime, ALLOWED_AVATAR_MIMES, true) || !in_array($ext, ALLOWED_AVATAR_EXTENSIONS, true)) {
                    $errors[] = 'Invalid avatar image type. Only JPG, PNG, and WebP are allowed.';
                } else {
                    $newFilename = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    $targetPath = __DIR__ . '/../../uploads/avatars/' . $newFilename;
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        if ($avatarFilename && $avatarFilename !== $newFilename) {
                            $oldPath = __DIR__ . '/../../uploads/avatars/' . $avatarFilename;
                            if (file_exists($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $avatarFilename = $newFilename;
                    } else {
                        $errors[] = 'Failed to save uploaded avatar.';
                    }
                }
            }
        }

        // 4. Update Database
        if (empty($errors)) {
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, avatar = ?, status = ?, password = ?, updated_at = NOW() 
                    WHERE id = ? AND role = 'customer'
                ");
                $updateStmt->execute([
                    $name,
                    $email,
                    empty($phone) ? null : $phone,
                    $avatarFilename,
                    $status,
                    $hashedPassword,
                    $customerId
                ]);
            } else {
                $updateStmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, avatar = ?, status = ?, updated_at = NOW() 
                    WHERE id = ? AND role = 'customer'
                ");
                $updateStmt->execute([
                    $name,
                    $email,
                    empty($phone) ? null : $phone,
                    $avatarFilename,
                    $status,
                    $customerId
                ]);
            }

            log_activity(
                $currentUser['id'],
                'customer',
                'customer_updated',
                "Updated customer details for {$name} ({$email})",
                'user',
                $customerId
            );

            flash('success', "Customer <strong>" . e($name) . "</strong> updated successfully!");
            redirect('modules/customers/view.php?id=' . $customerId);
        }
    }
}

$pageTitle = 'Edit Customer: ' . $customer['name'];
$pageHeader = 'Edit Customer Account';
$activePage = 'customers';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 760px;">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= url('modules/customers/view.php?id=' . $customer['id']); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Customer Details
                </a>
            </div>
            <h1 class="h4 fw-bold mb-0">Edit Customer: <?= e($customer['name']); ?></h1>
        </div>
        <a href="<?= url('modules/customers/view.php?id=' . $customer['id']); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye me-1"></i> View Profile
        </a>
    </div>

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
        <div class="card-body p-4">
            <form action="<?= url('modules/customers/edit.php?id=' . $customer['id']); ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field(); ?>
                <input type="hidden" name="id" value="<?= $customer['id']; ?>">

                <!-- Profile Information -->
                <h2 class="h7 fw-bold text-dark text-uppercase fs-8 border-bottom pb-1 mb-3">
                    <i class="bi bi-person me-1 text-primary"></i>Customer Information
                </h2>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label fs-7 fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" value="<?= e($_POST['name'] ?? $customer['name']); ?>" required autofocus>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label fs-7 fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" value="<?= e($_POST['email'] ?? $customer['email']); ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="phone" class="form-label fs-7 fw-semibold text-dark">Phone Number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" value="<?= e($_POST['phone'] ?? $customer['phone'] ?? ''); ?>" placeholder="+1 (555) 000-0000">
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label fs-7 fw-semibold text-dark">Account Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="active" <?= (($_POST['status'] ?? $customer['status']) === 'active') ? 'selected' : ''; ?>>Active (Can Sign In)</option>
                            <option value="inactive" <?= (($_POST['status'] ?? $customer['status']) === 'inactive') ? 'selected' : ''; ?>>Inactive (Access Blocked)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="avatar" class="form-label fs-7 fw-semibold text-dark">Profile Picture</label>
                        <input type="file" name="avatar" id="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($customer['avatar'])): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_avatar" id="remove_avatar" value="1">
                                <label class="form-check-label fs-8 text-danger" for="remove_avatar">
                                    Remove current avatar picture
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Optional Password Reset -->
                <h2 class="h7 fw-bold text-dark text-uppercase fs-8 border-bottom pb-1 mb-3">
                    <i class="bi bi-key me-1 text-primary"></i>Change Password (Optional)
                </h2>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="password" class="form-label fs-7 fw-semibold text-dark">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to preserve current password">
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="confirm_password" class="form-label fs-7 fw-semibold text-dark">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Repeat new password">
                    </div>
                </div>

                <!-- Actions -->
                <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                    <a href="<?= url('modules/customers/view.php?id=' . $customer['id']); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
