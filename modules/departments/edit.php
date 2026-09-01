<?php
/**
 * Department Management - Edit Department
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid department ID.');
    redirect('modules/departments/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM departments WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$department = $stmt->fetch();

if (!$department) {
    flash('danger', 'Department not found.');
    redirect('modules/departments/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? STATUS_ACTIVE);

        if (empty($name)) {
            $errors[] = 'Please enter a department name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Department name must be between 2 and 100 characters.';
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $status = STATUS_ACTIVE;
        }

        // Uniqueness check excluding current ID
        if (empty($errors)) {
            $checkStmt = $db->prepare("SELECT id FROM departments WHERE name = ? AND id != ? LIMIT 1");
            $checkStmt->execute([$name, $id]);
            if ($checkStmt->fetch()) {
                $errors[] = 'Another department with this name already exists.';
            }
        }

        if (empty($errors)) {
            $updateStmt = $db->prepare("
                UPDATE departments 
                SET name = ?, description = ?, status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->execute([
                $name,
                empty($description) ? null : $description,
                $status,
                $id
            ]);

            flash('success', "Department <strong>" . e($name) . "</strong> has been updated successfully!");
            redirect('modules/departments/index.php');
        }
    }
}

$pageTitle = 'Edit Department';
$pageHeader = 'Edit Department';
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
                <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Department
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

            <form action="<?= url('modules/departments/edit.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="id" value="<?= $department['id']; ?>">

                <!-- Department Name -->
                <div class="mb-3">
                    <label for="name" class="form-label">Department Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= e($_POST['name'] ?? $department['name']); ?>" 
                           required 
                           autofocus>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label for="description" class="form-label">Description <span class="text-muted small">(Optional)</span></label>
                    <textarea class="form-control" 
                              id="description" 
                              name="description" 
                              rows="3"><?= e($_POST['description'] ?? $department['description'] ?? ''); ?></textarea>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select" style="max-width: 200px;">
                        <option value="active" <?= (($_POST['status'] ?? $department['status']) === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $department['status']) === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Update Department
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
