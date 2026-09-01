<?php
/**
 * User Management - User Directory (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('users.view');

$db = get_db();
$currentUser = current_user();

// 1. Fetch available roles for filter dropdown
$rolesStmt = $db->query("SELECT id, name, slug FROM roles ORDER BY name ASC");
$allRoles = $rolesStmt->fetchAll();

// 2. Filters & Search Parameters
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$where = ["u.deleted_at IS NULL"];
$params = [];

if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($roleFilter !== '') {
    $where[] = "(u.role = ? OR r.slug = ?)";
    $params[] = $roleFilter;
    $params[] = $roleFilter;
}

if ($statusFilter !== '' && in_array($statusFilter, VALID_STATUSES, true)) {
    $where[] = "u.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

// 3. Count Total Records for Pagination
$countSql = "
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE $whereClause
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// 4. Safe Pagination
$pagination = get_pagination_params($totalRecords, 20, [20, 50, 100]);
$page = $pagination['page'];
$limit = $pagination['per_page'];
$offset = $pagination['offset'];
$totalPages = $pagination['total_pages'];

// 5. Fetch User Records
$userSql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.avatar,
        u.role AS primary_role,
        u.status,
        u.last_login_at,
        u.created_at,
        r.name AS role_name,
        r.slug AS role_slug,
        d.name AS department_name
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
";
$userStmt = $db->prepare($userSql);
$userStmt->execute($params);
$users = $userStmt->fetchAll();

$pageTitle = 'User Management';
$pageHeader = 'User Management';
$activePage = 'users';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header & Action Buttons -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-people me-2 text-primary"></i>User Directory & Accounts
            </h1>
            <p class="text-secondary-custom small mb-0">Manage system users, primary roles, and access credentials</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if (has_permission('roles.view')): ?>
                <a href="<?= url('modules/roles/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-shield-lock me-1"></i> Manage Roles
                </a>
            <?php endif; ?>
            <?php if (has_permission('users.create')): ?>
                <a href="<?= url('modules/users/create.php'); ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Add New User
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/users/index.php'); ?>" method="GET" class="row g-2 align-items-end">
                <!-- Search Input -->
                <div class="col-12 col-md-4">
                    <label for="search" class="form-label fs-8 fw-semibold text-secondary-custom mb-1">Search User</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Name, email, or phone..." value="<?= e($search); ?>">
                    </div>
                </div>

                <!-- Role Filter -->
                <div class="col-6 col-md-3">
                    <label for="role" class="form-label fs-8 fw-semibold text-secondary-custom mb-1">System Role</label>
                    <select name="role" id="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        <?php foreach ($allRoles as $rOption): ?>
                            <option value="<?= e($rOption['slug']); ?>" <?= ($roleFilter === $rOption['slug']) ? 'selected' : ''; ?>>
                                <?= e($rOption['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-md-3">
                    <label for="status" class="form-label fs-8 fw-semibold text-secondary-custom mb-1">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="active" <?= ($statusFilter === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if ($search !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
                        <a href="<?= url('modules/users/index.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3">User Profile</th>
                            <th class="py-3">Role</th>
                            <th class="py-3">Contact</th>
                            <th class="py-3 text-center" style="width: 100px;">Status</th>
                            <th class="py-3" style="width: 150px;">Last Activity</th>
                            <th class="pe-3 py-3 text-end" style="width: 110px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $uRow): 
                                $displayRole = $uRow['role_name'] ?: ucfirst(str_replace('_', ' ', $uRow['primary_role']));
                                $roleSlug = strtolower($uRow['role_slug'] ?: $uRow['primary_role']);
                                $isActive = ($uRow['status'] === STATUS_ACTIVE);
                                $isSelf = ((int)$uRow['id'] === (int)$currentUser['id']);
                            ?>
                                <tr>
                                    <!-- User Avatar & Name -->
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= e(get_avatar_url($uRow['avatar'] ?? null)); ?>" alt="<?= e($uRow['name']); ?>" class="avatar-img avatar-sm flex-shrink-0">
                                            <div class="overflow-hidden">
                                                <a href="<?= url('modules/users/view.php?id=' . $uRow['id']); ?>" class="fw-semibold text-dark text-decoration-none d-block text-truncate">
                                                    <?= e($uRow['name']); ?>
                                                    <?php if ($isSelf): ?>
                                                        <span class="badge bg-light text-primary border fs-8 ms-1">You</span>
                                                    <?php endif; ?>
                                                </a>
                                                <span class="text-muted fs-8 d-block text-truncate"><?= e($uRow['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Role Badge -->
                                    <td>
                                        <span class="badge badge-role-<?= e($roleSlug); ?>">
                                            <?= e($displayRole); ?>
                                        </span>
                                        <?php if (!empty($uRow['department_name'])): ?>
                                            <div class="text-muted fs-8 mt-1">
                                                <i class="bi bi-building me-1"></i><?= e($uRow['department_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Phone -->
                                    <td class="small text-muted font-monospace">
                                        <?= e($uRow['phone'] ?: '—'); ?>
                                    </td>

                                    <!-- Status -->
                                    <td class="text-center">
                                        <span class="badge bg-<?= $isActive ? 'success' : 'secondary'; ?>">
                                            <?= e(ucfirst($uRow['status'])); ?>
                                        </span>
                                    </td>

                                    <!-- Last Activity -->
                                    <td class="small text-muted">
                                        <?= !empty($uRow['last_login_at']) ? e(format_datetime($uRow['last_login_at'], 'M d, Y')) : '<span class="text-muted fst-italic">Never</span>'; ?>
                                        <div class="fs-8 text-muted">Joined <?= e(format_datetime($uRow['created_at'], 'M Y')); ?></div>
                                    </td>

                                    <!-- Actions Dropdown -->
                                    <td class="pe-3 text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Manage
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1">
                                                <li>
                                                    <a class="dropdown-item py-1 fs-7" href="<?= url('modules/users/view.php?id=' . $uRow['id']); ?>">
                                                        <i class="bi bi-eye text-secondary me-2"></i> View Profile
                                                    </a>
                                                </li>
                                                <?php if (has_permission('users.edit')): ?>
                                                    <li>
                                                        <a class="dropdown-item py-1 fs-7" href="<?= url('modules/users/edit.php?id=' . $uRow['id']); ?>">
                                                            <i class="bi bi-pencil text-secondary me-2"></i> Edit Details
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item py-1 fs-7" href="<?= url('modules/users/roles.php?id=' . $uRow['id']); ?>">
                                                            <i class="bi bi-shield-check text-secondary me-2"></i> Change Role
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <form action="<?= url('modules/users/status.php'); ?>" method="POST" class="m-0">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="id" value="<?= $uRow['id']; ?>">
                                                            <button type="submit" class="dropdown-item py-1 fs-7 text-<?= $isActive ? 'warning' : 'success'; ?>" onclick="return confirm('Are you sure you want to <?= $isActive ? 'deactivate' : 'activate'; ?> this user?');">
                                                                <i class="bi bi-<?= $isActive ? 'pause-circle' : 'play-circle'; ?> me-2"></i> <?= $isActive ? 'Deactivate' : 'Activate'; ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (has_permission('users.delete') && !$isSelf): ?>
                                                    <li>
                                                        <form action="<?= url('modules/users/delete.php'); ?>" method="POST" class="m-0">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="id" value="<?= $uRow['id']; ?>">
                                                            <button type="submit" class="dropdown-item py-1 fs-7 text-danger" onclick="return confirm('Are you sure you want to delete this user? This will soft delete their account while preserving historical ticket records.');">
                                                                <i class="bi bi-trash me-2"></i> Delete User
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No users match your criteria</h5>
                                    <p class="small mb-0">Try clearing filters or search terms.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Safe Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white d-flex align-items-center justify-content-between py-3 border-top">
                <span class="small text-muted">
                    Showing <strong><?= ($offset + 1); ?></strong> to <strong><?= min($offset + $limit, $totalRecords); ?></strong> of <strong><?= $totalRecords; ?></strong> users
                </span>
                <nav aria-label="Users pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/users/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= url('modules/users/index.php?' . http_build_query(array_merge($_GET, ['page' => $p]))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/users/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
