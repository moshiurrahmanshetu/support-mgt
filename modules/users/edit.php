<?php
/**
 * User Management - Edit User (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('users.edit');

$db = get_db();
$currentUser = current_user();
$userId = (int)($_GET['id'] ?? 0);

// Fetch user record
$stmt = $db->prepare("
    SELECT u.*, ur.role_id, r.name AS role_name, r.slug AS role_slug
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.id = ? AND u.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'User account not found or has been deleted.');
    redirect('modules/users/index.php');
}

$roles = $db->query("SELECT id, name, slug FROM roles WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$departments = $db->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $status = trim($_POST['status'] ?? $user['status']);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';

        // Validation
        if (empty($name)) {
            $errors[] = 'Please enter user full name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (empty($email)) {
            $errors[] = 'Please enter user email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $eStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $eStmt->execute([$email, $userId]);
            if ($eStmt->fetch()) {
                $errors[] = 'Another user with this email address already exists.';
            }
        }

        // Validate Role
        $selectedRole = null;
        foreach ($roles as $r) {
            if ((int)$r['id'] === $roleId) {
                $selectedRole = $r;
                break;
            }
        }
        if (!$selectedRole) {
            $errors[] = 'Please select a valid system role.';
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $errors[] = 'Invalid account status selected.';
        }

        // Last Admin Safety Protection
        if ($selectedRole && !can_modify_user_role_or_status($userId, $selectedRole['slug'], $status)) {
            $errors[] = 'You cannot demote or deactivate the last active Administrator in the system.';
        }

        // Optional Password Change
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = 'New password must be at least 8 characters long.';
            }
            if ($password !== $confirmPassword) {
                $errors[] = 'Password confirmation does not match.';
            }
        }

        // Avatar Handling
        $avatarFilename = $user['avatar'];
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
                        if ($avatarFilename) {
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

        // Update Record
        if (empty($errors)) {
            $primaryRoleSlug = $selectedRole['slug'];

            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $uStmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, avatar = ?, department_id = ?, status = ?, password = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $uStmt->execute([
                    $name,
                    $email,
                    empty($phone) ? null : $phone,
                    $avatarFilename,
                    $departmentId,
                    $status,
                    $hashedPassword,
                    $userId
                ]);
            } else {
                $uStmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, avatar = ?, department_id = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $uStmt->execute([
                    $name,
                    $email,
                    empty($phone) ? null : $phone,
                    $avatarFilename,
                    $departmentId,
                    $status,
                    $userId
                ]);
            }

            // Sync role assignment
            assign_user_role($userId, (int)$selectedRole['id']);

            log_activity($currentUser['id'], 'users', 'user_updated', "Updated profile for user {$name} ({$email})", 'user', $userId);

            flash('success', "User '{$name}' updated successfully.");
            redirect('modules/users/index.php');
        }
    }
}

// Current effective role ID
$currentRoleId = (int)($user['role_id'] ?? 0);
if ($currentRoleId === 0) {
    foreach ($roles as $r) {
        if ($r['slug'] === $user['role'] || ($user['role'] === 'admin' && $r['slug'] === 'administrator') || ($user['role'] === 'agent' && $r['slug'] === 'support_agent')) {
            $currentRoleId = (int)$r['id'];
            break;
        }
    }
}

$pageTitle = 'Edit User: ' . $user['name'];
$pageHeader = 'Edit User Details';
$activePage = 'users';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 800px;">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= url('modules/users/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Users Directory
                </a>
            </div>
            <h1 class="h4 fw-bold mb-0">Edit User: <?= e($user['name']); ?></h1>
        </div>
        <a href="<?= url('modules/users/view.php?id=' . $user['id']); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye me-1"></i> View Profile
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm border-0 mb-4">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please correct the following errors:</div>
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card border shadow-sm">
        <div class="card-body p-4">
            <form action="<?= url('modules/users/edit.php?id=' . $user['id']); ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field(); ?>

                <!-- Profile Information -->
                <h2 class="h6 fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="bi bi-person me-1 text-primary"></i>Profile Details
                </h2>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label fs-7 fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" value="<?= e($user['name']); ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label fs-7 fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" value="<?= e($user['email']); ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="phone" class="form-label fs-7 fw-semibold text-dark">Phone Number</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="<?= e($user['phone'] ?? ''); ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="avatar" class="form-label fs-7 fw-semibold text-dark">Profile Picture</label>
                        <input type="file" name="avatar" id="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($user['avatar'])): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_avatar" id="remove_avatar" value="1">
                                <label class="form-check-label fs-8 text-danger" for="remove_avatar">
                                    Remove current avatar
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Access & Role Assignment -->
                <h2 class="h6 fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="bi bi-shield-check me-1 text-primary"></i>Role & Status
                </h2>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="role_id" class="form-label fs-7 fw-semibold text-dark">System Role <span class="text-danger">*</span></label>
                        <select name="role_id" id="role_id" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id']; ?>" <?= ($currentRoleId === (int)$r['id']) ? 'selected' : ''; ?>>
                                    <?= e($r['name']); ?> (<?= e($r['slug']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="department_id" class="form-label fs-7 fw-semibold text-dark">Department</label>
                        <select name="department_id" id="department_id" class="form-select">
                            <option value="">-- None / General Support --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id']; ?>" <?= ((int)($user['department_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>>
                                    <?= e($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label fs-7 fw-semibold text-dark">Account Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="active" <?= ($user['status'] === 'active') ? 'selected' : ''; ?>>Active (Can Sign In)</option>
                            <option value="inactive" <?= ($user['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive (Access Blocked)</option>
                        </select>
                    </div>
                </div>

                <!-- Optional Password Change -->
                <h2 class="h6 fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="bi bi-key me-1 text-primary"></i>Change Password (Optional)
                </h2>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="password" class="form-label fs-7 fw-semibold text-dark">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep current password">
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="confirm_password" class="form-label fs-7 fw-semibold text-dark">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Repeat new password">
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                    <a href="<?= url('modules/users/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
