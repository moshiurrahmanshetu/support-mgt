<?php
/**
 * Agent Management - Create New Agent
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$db = get_db();
$errors = [];

// Fetch Active Departments
$deptStmt = $db->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC");
$activeDepartments = $deptStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $status = trim($_POST['status'] ?? STATUS_ACTIVE);

        // Validation
        if (empty($name)) {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }

        if (!empty($phone) && (mb_strlen($phone) < 6 || mb_strlen($phone) > 30)) {
            $errors[] = 'Phone number must be between 6 and 30 characters.';
        }

        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        if ($departmentId !== null) {
            $deptCheck = $db->prepare("SELECT id FROM departments WHERE id = ? AND status = 'active' LIMIT 1");
            $deptCheck->execute([$departmentId]);
            if (!$deptCheck->fetch()) {
                $errors[] = 'Selected department is invalid or inactive.';
            }
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $status = STATUS_ACTIVE;
        }

        // Email uniqueness check
        if (empty($errors)) {
            $emailCheck = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $emailCheck->execute([$email]);
            if ($emailCheck->fetch()) {
                $errors[] = 'An account with this email address already exists.';
            }
        }

        // Insert Agent
        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $role = ROLE_AGENT; // Strictly forced to agent

            $insertStmt = $db->prepare("
                INSERT INTO users (role, name, email, phone, password, department_id, status, email_verified_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
            ");
            $insertStmt->execute([
                $role,
                $name,
                $email,
                empty($phone) ? null : $phone,
                $hashedPassword,
                $departmentId,
                $status
            ]);

            clear_old_input();
            flash('success', "Agent account <strong>" . e($name) . "</strong> has been created successfully!");
            redirect('modules/agents/index.php');
        }

        if (!empty($errors)) {
            set_old_input([
                'name'          => $name,
                'email'         => $email,
                'phone'         => $phone,
                'department_id' => $departmentId,
                'status'        => $status
            ]);
        }
    }
}

$pageTitle = 'Create Agent';
$pageHeader = 'Create Support Agent';
$activePage = 'agents';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 760px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/agents/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Agents
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-person-plus me-2 text-primary"></i>Create New Support Agent
            </h5>
        </div>
        <div class="card-body p-4">
            <?php if (empty($activeDepartments)): ?>
                <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div>
                        No active departments available. Please <a href="<?= url('modules/departments/create.php'); ?>" class="alert-link">create a department</a> before assigning agents.
                    </div>
                </div>
            <?php endif; ?>

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

            <form action="<?= url('modules/agents/create.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>

                <!-- Full Name -->
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= e(old('name')); ?>" 
                           placeholder="Agent full name" 
                           required 
                           autofocus>
                </div>

                <!-- Email Address -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           value="<?= e(old('email')); ?>" 
                           placeholder="agent@supportmgt.local" 
                           required>
                </div>

                <!-- Phone -->
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" 
                           class="form-control" 
                           id="phone" 
                           name="phone" 
                           value="<?= e(old('phone')); ?>" 
                           placeholder="+1 (555) 000-0000">
                </div>

                <!-- Department -->
                <div class="mb-3">
                    <label for="department_id" class="form-label">Assigned Department</label>
                    <select name="department_id" id="department_id" class="form-select">
                        <option value="">-- General / No Department --</option>
                        <?php foreach ($activeDepartments as $dept): ?>
                            <option value="<?= $dept['id']; ?>" <?= (old('department_id') == $dept['id']) ? 'selected' : ''; ?>>
                                <?= e($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <!-- Password -->
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Initial Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="At least 8 characters" 
                                   required>
                            <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Confirm password" 
                                   required>
                            <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="confirm_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select" style="max-width: 200px;">
                        <option value="active" <?= (old('status', 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= (old('status') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-check"></i> Create Agent
                    </button>
                    <a href="<?= url('modules/agents/index.php'); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
