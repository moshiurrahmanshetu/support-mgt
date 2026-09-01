<?php
/**
 * Role Management - View Role Details (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';

// Strict Authorization Guard
require_permission('roles.view');

$db = get_db();
$roleId = (int)($_GET['id'] ?? 0);

// Fetch role record
$stmt = $db->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
$stmt->execute([$roleId]);
$role = $stmt->fetch();

if (!$role) {
    flash('danger', 'Role not found.');
    redirect('modules/roles/index.php');
}

// Fetch assigned permissions for this role
$permStmt = $db->prepare("
    SELECT p.* 
    FROM permissions p
    JOIN role_permissions rp ON p.id = rp.permission_id
    WHERE rp.role_id = ?
    ORDER BY p.module ASC, p.name ASC
");
$permStmt->execute([$roleId]);
$assignedPermissions = $permStmt->fetchAll();

$groupedPerms = [];
foreach ($assignedPermissions as $p) {
    $groupedPerms[$p['module']][] = $p;
}

// Fetch assigned users
$userStmt = $db->prepare("
    SELECT u.*, d.name AS department_name
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE ur.role_id = ? AND u.deleted_at IS NULL
    ORDER BY u.name ASC
");
$userStmt->execute([$roleId]);
$users = $userStmt->fetchAll();

$isSystem = ((int)$role['is_system'] === 1);
$isActive = ($role['status'] === STATUS_ACTIVE);

$pageTitle = 'Role: ' . $role['name'];
$pageHeader = 'Role Profile';
$activePage = 'roles';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= url('modules/roles/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Roles Directory
                </a>
            </div>
            <h1 class="h4 fw-bold mb-0">Role: <?= e($role['name']); ?></h1>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if (has_permission('permissions.assign')): ?>
                <a href="<?= url('modules/roles/permissions.php?id=' . $role['id']); ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-key me-1"></i> Configure Permissions
                </a>
            <?php endif; ?>
            <?php if (has_permission('roles.edit')): ?>
                <a href="<?= url('modules/roles/edit.php?id=' . $role['id']); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Role
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Role Info & Assigned Users -->
        <div class="col-12 col-lg-5">
            <!-- Metadata Card -->
            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 fw-bold mb-0 text-dark">
                        <i class="bi bi-shield me-1 text-primary"></i>Role Metadata
                    </h2>
                </div>
                <div class="card-body p-3 small">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Role Name:</span>
                        <span class="fw-bold text-dark"><?= e($role['name']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Slug Identifier:</span>
                        <code class="font-monospace"><?= e($role['slug']); ?></code>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Role Type:</span>
                        <span class="badge bg-<?= $isSystem ? 'dark' : 'info'; ?>"><?= $isSystem ? 'System Role' : 'Custom Role'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Status:</span>
                        <span class="badge bg-<?= $isActive ? 'success' : 'secondary'; ?>"><?= e(ucfirst($role['status'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Permissions Count:</span>
                        <span class="fw-bold font-monospace text-primary"><?= count($assignedPermissions); ?></span>
                    </div>
                    <?php if (!empty($role['description'])): ?>
                        <div class="mt-3 p-2 bg-light rounded border">
                            <span class="text-muted d-block fs-8 mb-1">Description:</span>
                            <?= nl2br(e($role['description'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assigned Users List -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h2 class="h6 fw-bold mb-0 text-dark">
                        <i class="bi bi-people me-1 text-primary"></i>Assigned Users (<?= count($users); ?>)
                    </h2>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="max-height: 380px; overflow-y: auto;">
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $u): ?>
                                <a href="<?= url('modules/users/view.php?id=' . $u['id']); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <img src="<?= e(get_avatar_url($u['avatar'] ?? null)); ?>" alt="<?= e($u['name']); ?>" class="avatar-img avatar-sm flex-shrink-0">
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold text-dark text-truncate fs-7"><?= e($u['name']); ?></div>
                                            <div class="text-muted fs-8 text-truncate"><?= e($u['email']); ?></div>
                                        </div>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted small">No users currently assigned to this role.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Permissions Matrix -->
        <div class="col-12 col-lg-7">
            <div class="card border shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h2 class="h6 fw-bold mb-0 text-dark">
                        <i class="bi bi-key me-1 text-primary"></i>Assigned Permissions
                    </h2>
                    <?php if (has_permission('permissions.assign')): ?>
                        <a href="<?= url('modules/roles/permissions.php?id=' . $role['id']); ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil me-1"></i> Edit Matrix
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($groupedPerms)): ?>
                        <?php foreach ($groupedPerms as $moduleName => $perms): ?>
                            <div class="mb-4">
                                <h3 class="h7 fw-bold text-dark text-uppercase fs-8 border-bottom pb-1 mb-2">
                                    <?= e($moduleName); ?>
                                </h3>
                                <div class="row g-2">
                                    <?php foreach ($perms as $p): ?>
                                        <div class="col-12 col-sm-6">
                                            <div class="d-flex align-items-center gap-2 p-2 rounded bg-light border">
                                                <i class="bi bi-check-circle-fill text-success fs-7"></i>
                                                <div class="overflow-hidden">
                                                    <div class="fw-semibold text-dark fs-8 text-truncate"><?= e($p['name']); ?></div>
                                                    <code class="fs-9 text-muted d-block text-truncate"><?= e($p['slug']); ?></code>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-key fs-1 text-secondary mb-2 d-block"></i>
                            <h4 class="h6 fw-bold">No permissions assigned</h4>
                            <p class="small mb-3">This role currently has zero system permissions.</p>
                            <?php if (has_permission('permissions.assign')): ?>
                                <a href="<?= url('modules/roles/permissions.php?id=' . $role['id']); ?>" class="btn btn-primary btn-sm">
                                    Assign Permissions Now
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
