<?php
/**
 * Department Management - Create Department
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$errors = [];
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? STATUS_ACTIVE);

        // Validation
        if (empty($name)) {
            $errors[] = 'Please enter a department name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Department name must be between 2 and 100 characters.';
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $status = STATUS_ACTIVE;
        }

        // Uniqueness check
        if (empty($errors)) {
            $checkStmt = $db->prepare("SELECT id FROM departments WHERE name = ? LIMIT 1");
            $checkStmt->execute([$name]);
            if ($checkStmt->fetch()) {
                $errors[] = 'A department with this name already exists.';
            }
        }

        // Insert
        if (empty($errors)) {
            $insertStmt = $db->prepare("
                INSERT INTO departments (name, description, status, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $insertStmt->execute([
                $name,
                empty($description) ? null : $description,
                $status
            ]);
            $deptId = (int)$db->lastInsertId();

            require_once __DIR__ . '/../../includes/activity_log.php';
            $currentUser = current_user();
            log_activity($currentUser['id'], 'department', 'department_created', "Created department: {$name}", 'department', $deptId);

            clear_old_input();
            flash('success', "Department <strong>" . e($name) . "</strong> has been created successfully!");
            redirect('modules/departments/index.php');
        }

        if (!empty($errors)) {
            set_old_input([
                'name'        => $name,
                'description' => $description,
                'status'      => $status
            ]);
        }
    }
}

$pageTitle = 'Create Department';
$pageHeader = 'Create Department';
$activePage = 'departments';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 720px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/departments/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Departments
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-plus-circle me-2 text-primary"></i>Create New Support Department
            </h5>
        </div>
        <div class="card-body p-4">
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

            <form action="<?= url('modules/departments/create.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>

                <!-- Department Name -->
                <div class="mb-3">
                    <label for="name" class="form-label">Department Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= e(old('name')); ?>" 
                           placeholder="e.g. Technical Support, Billing & Payment" 
                           required 
                           autofocus>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label for="description" class="form-label">Description <span class="text-muted small">(Optional)</span></label>
                    <textarea class="form-control" 
                              id="description" 
                              name="description" 
                              rows="3" 
                              placeholder="Brief summary of duties handled by this department..."><?= e(old('description')); ?></textarea>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select" style="max-width: 200px;">
                        <option value="active" <?= (old('status', 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= (old('status') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save Department
                    </button>
                    <a href="<?= url('modules/departments/index.php'); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
