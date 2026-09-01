<?php
/**
 * Role Management - Roles Directory (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';

// Strict Authorization Guard
require_permission('roles.view');

$db = get_db();
$currentUser = current_user();

// Fetch roles with user count and permission count
$roleSql = "
    SELECT 
        r.*,
        COUNT(DISTINCT ur.user_id) AS user_count,
        COUNT(DISTINCT rp.permission_id) AS permission_count
    FROM roles r
    LEFT JOIN user_roles ur ON r.id = ur.role_id
    LEFT JOIN role_permissions rp ON r.id = rp.role_id
    GROUP BY r.id
    ORDER BY r.is_system DESC, r.name ASC
";
$roleStmt = $db->query($roleSql);
$roles = $roleStmt->fetchAll();

$pageTitle = 'Role Management';
$pageHeader = 'Role Management';
$activePage = 'roles';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header & Action Buttons -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-shield-lock me-2 text-primary"></i>System Roles & Permissions
            </h1>
            <p class="text-secondary-custom small mb-0">Configure operational roles, module access levels, and granular permissions</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if (has_permission('users.view')): ?>
                <a href="<?= url('modules/users/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-people me-1"></i> User Directory
                </a>
            <?php endif; ?>
            <?php if (has_permission('roles.create')): ?>
                <a href="<?= url('modules/roles/create.php'); ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Add Custom Role
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Roles Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3">Role Name</th>
                            <th class="py-3">Slug Identifier</th>
                            <th class="py-3 text-center" style="width: 110px;">Assigned Users</th>
                            <th class="py-3 text-center" style="width: 120px;">Permissions</th>
                            <th class="py-3 text-center" style="width: 100px;">Status</th>
                            <th class="pe-3 py-3 text-end" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($roles)): ?>
                            <?php foreach ($roles as $role): 
                                $isSystem = ((int)$role['is_system'] === 1);
                                $isActive = ($role['status'] === STATUS_ACTIVE);
                            ?>
                                <tr>
                                    <!-- Name & Description -->
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge badge-role-<?= e($role['slug']); ?>"><?= e($role['name']); ?></span>
                                            <?php if ($isSystem): ?>
                                                <span class="badge bg-light text-secondary border fs-8" title="Built-in core system role">System</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small mt-1 text-truncate" style="max-width: 320px;">
                                            <?= e($role['description'] ?: 'No description provided.'); ?>
                                        </div>
                                    </td>

                                    <!-- Slug -->
                                    <td class="font-monospace small text-muted">
                                        <code><?= e($role['slug']); ?></code>
                                    </td>

                                    <!-- User Count -->
                                    <td class="text-center font-monospace fw-bold text-dark">
                                        <a href="<?= url('modules/users/index.php?role=' . urlencode($role['slug'])); ?>" class="text-decoration-none" title="View Users with this Role">
                                            <?= (int)$role['user_count']; ?>
                                        </a>
                                    </td>

                                    <!-- Permission Count -->
                                    <td class="text-center font-monospace text-primary fw-medium">
                                        <?= (int)$role['permission_count']; ?>
                                    </td>

                                    <!-- Status -->
                                    <td class="text-center">
                                        <span class="badge bg-<?= $isActive ? 'success' : 'secondary'; ?>">
                                            <?= e(ucfirst($role['status'])); ?>
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-3 text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= url('modules/roles/view.php?id=' . $role['id']); ?>" class="btn btn-outline-secondary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if (has_permission('permissions.assign')): ?>
                                                <a href="<?= url('modules/roles/permissions.php?id=' . $role['id']); ?>" class="btn btn-outline-primary" title="Configure Permissions">
                                                    <i class="bi bi-key"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (has_permission('roles.edit')): ?>
                                                <a href="<?= url('modules/roles/edit.php?id=' . $role['id']); ?>" class="btn btn-outline-secondary" title="Edit Role">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (has_permission('roles.delete') && !$isSystem): ?>
                                                <form action="<?= url('modules/roles/delete.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this custom role?');">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="id" value="<?= $role['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete Role">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-shield-x fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No system roles found</h5>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
