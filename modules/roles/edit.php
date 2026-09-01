<?php
/**
 * Role Management - Edit Role (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('roles.edit');

$db = get_db();
$currentUser = current_user();
$roleId = (int)($_GET['id'] ?? 0);

// Fetch role record
$stmt = $db->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
$stmt->execute([$roleId]);
$role = $stmt->fetch();

if (!$role) {
    flash('danger', 'Role not found.');
    redirect('modules/roles/index.php');
}

$isSystem = ((int)$role['is_system'] === 1);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? $role['status']);
        $slug = $isSystem ? $role['slug'] : trim($_POST['slug'] ?? $role['slug']);

        if (empty($name)) {
            $errors[] = 'Please enter role name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Role name must be between 2 and 100 characters.';
        }

        if (!$isSystem) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $slug)));
            if (empty($slug)) {
                $errors[] = 'Invalid slug identifier.';
            } else {
                $sStmt = $db->prepare("SELECT id FROM roles WHERE slug = ? AND id != ? LIMIT 1");
                $sStmt->execute([$slug, $roleId]);
                if ($sStmt->fetch()) {
                    $errors[] = "A role with the slug '{$slug}' already exists.";
                }
            }
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $errors[] = 'Invalid status selected.';
        }

        if (empty($errors)) {
            $updateStmt = $db->prepare("
                UPDATE roles 
                SET name = ?, slug = ?, description = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$name, $slug, empty($description) ? null : $description, $status, $roleId]);

            log_activity($currentUser['id'], 'roles', 'role_updated', "Updated role '{$name}' ({$slug})", 'role', $roleId);

            flash('success', "Role '{$name}' updated successfully.");
            redirect('modules/roles/index.php');
        }
    }
}

$pageTitle = 'Edit Role: ' . $role['name'];
$pageHeader = 'Edit Role Details';
$activePage = 'roles';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 650px;">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= url('modules/roles/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Roles Directory
                </a>
            </div>
            <h1 class="h4 fw-bold mb-0">Edit Role: <?= e($role['name']); ?></h1>
        </div>
        <?php if (has_permission('permissions.assign')): ?>
            <a href="<?= url('modules/roles/permissions.php?id=' . $role['id']); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-key me-1"></i> Permissions
            </a>
        <?php endif; ?>
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
            <form action="<?= url('modules/roles/edit.php?id=' . $role['id']); ?>" method="POST">
                <?= csrf_field(); ?>

                <div class="mb-3">
                    <label for="name" class="form-label fs-7 fw-semibold text-dark">Role Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control" value="<?= e($role['name']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label fs-7 fw-semibold text-dark">Slug Identifier <span class="text-danger">*</span></label>
                    <input type="text" name="slug" id="slug" class="form-control font-monospace" value="<?= e($role['slug']); ?>" <?= $isSystem ? 'readonly' : 'required'; ?>>
                    <?php if ($isSystem): ?>
                        <div class="form-text fs-8 text-muted">System core role slugs are protected and cannot be changed.</div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label fs-7 fw-semibold text-dark">Description</label>
                    <textarea name="description" id="description" rows="3" class="form-control"><?= e($role['description'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label for="status" class="form-label fs-7 fw-semibold text-dark">Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select" required>
                        <option value="active" <?= ($role['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= ($role['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                    <a href="<?= url('modules/roles/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Save Role Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
