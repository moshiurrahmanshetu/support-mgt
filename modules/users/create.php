<?php
/**
 * User Management - Create User (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';


// Strict Authorization Guard
require_permission('users.create');

$db = get_db();
$currentUser = current_user();
$errors = [];

// Fetch available roles and departments
$roles = $db->query("SELECT id, name, slug FROM roles WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$departments = $db->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $status = trim($_POST['status'] ?? STATUS_ACTIVE);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

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
            $eStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $eStmt->execute([$email]);
            if ($eStmt->fetch()) {
                $errors[] = 'A user with this email address already exists.';
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

        if (empty($password)) {
            $errors[] = 'Please enter a password for the new user.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        // Avatar Upload Processing
        $avatarFilename = null;
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
                    $avatarFilename = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    $targetPath = __DIR__ . '/../../uploads/avatars/' . $avatarFilename;
                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $errors[] = 'Failed to save uploaded avatar.';
                        $avatarFilename = null;
                    }
                }
            }
        }

        // Save User
        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $primaryRoleSlug = $selectedRole['slug'];

            $insertStmt = $db->prepare("
                INSERT INTO users (role, name, email, phone, avatar, department_id, password, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $insertStmt->execute([
                $primaryRoleSlug,
                $name,
                $email,
                empty($phone) ? null : $phone,
                $avatarFilename,
                $departmentId,
                $hashedPassword,
                $status
            ]);
            $newUserId = (int)$db->lastInsertId();

            // Link in user_roles table
            assign_user_role($newUserId, (int)$selectedRole['id']);

            log_activity($currentUser['id'], 'users', 'user_created', "Created user {$name} ({$email}) with role {$selectedRole['name']}", 'user', $newUserId);

            clear_old_input();
            flash('success', "User '{$name}' created successfully with role '{$selectedRole['name']}'.");
            redirect('modules/users/index.php');
        } else {
            set_old_input([
                'name'          => $name,
                'email'         => $email,
                'phone'         => $phone,
                'role_id'       => $roleId,
                'department_id' => $departmentId,
                'status'        => $status
            ]);
        }
    }
}

$old = get_old_input();
$pageTitle = 'Add New User';
$pageHeader = 'Create User Account';
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
            <h1 class="h4 fw-bold mb-0">Create New User Account</h1>
        </div>
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
            <form action="<?= url('modules/users/create.php'); ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field(); ?>

                <!-- Basic Profile Information -->
                <h2 class="h6 fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="bi bi-person me-1 text-primary"></i>Profile Details
                </h2>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label fs-7 fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Sarah Jenkins" value="<?= e($old['name'] ?? ''); ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label fs-7 fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="sarah@company.com" value="<?= e($old['email'] ?? ''); ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="phone" class="form-label fs-7 fw-semibold text-dark">Phone Number</label>
                        <input type="text" name="phone" id="phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= e($old['phone'] ?? ''); ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="avatar" class="form-label fs-7 fw-semibold text-dark">Profile Picture</label>
                        <input type="file" name="avatar" id="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text fs-8">Optional. Max 2MB (JPG, PNG, WebP).</div>
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
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id']; ?>" <?= ((int)($old['role_id'] ?? 0) === (int)$r['id']) ? 'selected' : ''; ?>>
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
                                <option value="<?= $d['id']; ?>" <?= ((int)($old['department_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>>
                                    <?= e($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text fs-8">Applicable for Support Agents and Managers.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label fs-7 fw-semibold text-dark">Account Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="active" <?= (($old['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active (Can Sign In)</option>
                            <option value="inactive" <?= (($old['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive (Access Blocked)</option>
                        </select>
                    </div>
                </div>

                <!-- Password Credentials -->
                <h2 class="h6 fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="bi bi-key me-1 text-primary"></i>Initial Password
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

                <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                    <a href="<?= url('modules/users/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-check me-1"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
