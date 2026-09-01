<?php
/**
 * Role Management - Role Permissions Matrix Editor (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('permissions.assign');

$db = get_db();
$currentUser = current_user();
$roleId = (int)($_GET['id'] ?? 0);

// Fetch role
$stmt = $db->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
$stmt->execute([$roleId]);
$role = $stmt->fetch();

if (!$role) {
    flash('danger', 'Role not found.');
    redirect('modules/roles/index.php');
}

// Fetch all permissions grouped
$groupedPermissions = get_all_permissions_grouped();

// Fetch currently assigned permission IDs for this role
$curPermStmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$curPermStmt->execute([$roleId]);
$assignedPermissionIds = $curPermStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } else {
        $selectedPermIds = isset($_POST['permissions']) && is_array($_POST['permissions']) 
            ? array_map('intval', $_POST['permissions']) 
            : [];

        // Save Permissions inside a transaction
        $db->beginTransaction();
        try {
            // 1. Remove old assignments
            $delStmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $delStmt->execute([$roleId]);

            // 2. Insert selected assignments
            if (!empty($selectedPermIds)) {
                $insStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
                foreach ($selectedPermIds as $pId) {
                    if ($pId > 0) {
                        $insStmt->execute([$roleId, $pId]);
                    }
                }
            }

            $db->commit();

            // Clear permission cache
            PermissionCache::$permissionsByUser = [];

            log_activity($currentUser['id'], 'roles', 'role_permissions_updated', "Updated permissions for role '{$role['name']}' (" . count($selectedPermIds) . " permissions assigned)", 'role', $roleId);

            flash('success', "Permissions for role '{$role['name']}' updated successfully.");
            redirect('modules/roles/view.php?id=' . $roleId);
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to save permissions: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Permissions: ' . $role['name'];
$pageHeader = 'Role Permissions Matrix';
$activePage = 'roles';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= url('modules/roles/view.php?id=' . $role['id']); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Role Profile
                </a>
            </div>
            <h1 class="h4 fw-bold mb-0">Configure Permissions: <?= e($role['name']); ?></h1>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllCheckboxes(true)">
                <i class="bi bi-check-all me-1"></i> Select All
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllCheckboxes(false)">
                <i class="bi bi-x me-1"></i> Deselect All
            </button>
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

    <form action="<?= url('modules/roles/permissions.php?id=' . $role['id']); ?>" method="POST">
        <?= csrf_field(); ?>

        <div class="row g-4 mb-4">
            <?php foreach ($groupedPermissions as $moduleName => $permissions): 
                $moduleId = 'mod_' . preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower($moduleName));
            ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card border shadow-sm h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                            <h2 class="h6 fw-bold mb-0 text-dark">
                                <?= e($moduleName); ?>
                            </h2>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fs-8" onclick="toggleModuleGroup('<?= $moduleId; ?>')">
                                Toggle All
                            </button>
                        </div>
                        <div class="card-body p-3 <?= $moduleId; ?>">
                            <?php foreach ($permissions as $p): 
                                $isChecked = in_array((int)$p['id'], $assignedPermissionIds, true);
                            ?>
                                <div class="form-check py-2 border-bottom">
                                    <input class="form-check-input perm-checkbox <?= $moduleId; ?>-cb" type="checkbox" name="permissions[]" value="<?= $p['id']; ?>" id="perm_<?= $p['id']; ?>" <?= $isChecked ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100" for="perm_<?= $p['id']; ?>" style="cursor: pointer;">
                                        <div class="fw-semibold text-dark fs-7"><?= e($p['name']); ?></div>
                                        <div class="text-muted fs-8 text-truncate"><?= e($p['description'] ?: $p['slug']); ?></div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card border shadow-sm position-sticky bottom-0 bg-white py-3 px-4 mb-4 shadow">
            <div class="d-flex align-items-center justify-content-between">
                <span class="small text-muted">Review selected permissions and save to apply immediately.</span>
                <div class="d-flex gap-2">
                    <a href="<?= url('modules/roles/view.php?id=' . $role['id']); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Save Permissions Matrix
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleAllCheckboxes(checked) {
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = checked;
    });
}

function toggleModuleGroup(moduleClass) {
    const checkboxes = document.querySelectorAll('.' + moduleClass + '-cb');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
