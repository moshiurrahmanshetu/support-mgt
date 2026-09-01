<?php
/**
 * Role Management - Create Custom Role (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('roles.create');

$db = get_db();
$currentUser = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? STATUS_ACTIVE);

        if (empty($name)) {
            $errors[] = 'Please enter role name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Role name must be between 2 and 100 characters.';
        }

        // Auto-generate slug if left blank
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '_', $name)));
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $slug)));
        }

        if (empty($slug)) {
            $errors[] = 'Invalid slug identifier generated.';
        } else {
            $sStmt = $db->prepare("SELECT id FROM roles WHERE slug = ? LIMIT 1");
            $sStmt->execute([$slug]);
            if ($sStmt->fetch()) {
                $errors[] = "A role with the slug '{$slug}' already exists.";
            }
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $errors[] = 'Invalid status selected.';
        }

        if (empty($errors)) {
            $insertStmt = $db->prepare("
                INSERT INTO roles (name, slug, description, status, is_system, created_at, updated_at)
                VALUES (?, ?, ?, ?, 0, NOW(), NOW())
            ");
            $insertStmt->execute([$name, $slug, empty($description) ? null : $description, $status]);
            $newRoleId = (int)$db->lastInsertId();

            log_activity($currentUser['id'], 'roles', 'role_created', "Created custom role '{$name}' ({$slug})", 'role', $newRoleId);

            clear_old_input();
            flash('success', "Custom role '{$name}' created successfully. You can now configure its permissions.");
            redirect('modules/roles/permissions.php?id=' . $newRoleId);
        } else {
            set_old_input([
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
                'status'      => $status
            ]);
        }
    }
}

$old = get_old_input();
$pageTitle = 'Create Custom Role';
$pageHeader = 'Add System Role';
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
            <h1 class="h4 fw-bold mb-0">Create Custom Role</h1>
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
            <form action="<?= url('modules/roles/create.php'); ?>" method="POST">
                <?= csrf_field(); ?>

                <div class="mb-3">
                    <label for="name" class="form-label fs-7 fw-semibold text-dark">Role Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Senior Support Lead" value="<?= e($old['name'] ?? ''); ?>" required oninput="generateSlug(this.value)">
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label fs-7 fw-semibold text-dark">Slug Identifier <span class="text-danger">*</span></label>
                    <input type="text" name="slug" id="slug" class="form-control font-monospace" placeholder="senior_support_lead" value="<?= e($old['slug'] ?? ''); ?>" required>
                    <div class="form-text fs-8">Unique lowercase identifier used for system permission checks.</div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label fs-7 fw-semibold text-dark">Description</label>
                    <textarea name="description" id="description" rows="3" class="form-control" placeholder="Describe the responsibilities and scope of this role..."><?= e($old['description'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label for="status" class="form-label fs-7 fw-semibold text-dark">Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select" required>
                        <option value="active" <?= (($old['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= (($old['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                    <a href="<?= url('modules/roles/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-shield-plus me-1"></i> Create Role & Configure Permissions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function generateSlug(val) {
    const slugInput = document.getElementById('slug');
    if (!slugInput.dataset.manual) {
        slugInput.value = val.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
    }
}
document.getElementById('slug').addEventListener('input', function() {
    this.dataset.manual = 'true';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
