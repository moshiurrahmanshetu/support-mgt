<?php
/**
 * Agent Management - Agent List
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$db = get_db();

// Filters & Search
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$departmentFilter = (int)($_GET['department_id'] ?? 0);

$whereClauses = ["u.role = 'agent'"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($statusFilter) && in_array($statusFilter, VALID_STATUSES, true)) {
    $whereClauses[] = 'u.status = ?';
    $params[] = $statusFilter;
}

if ($departmentFilter > 0) {
    $whereClauses[] = 'u.department_id = ?';
    $params[] = $departmentFilter;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

// Count Total matching records
$countSql = "SELECT COUNT(*) FROM users u $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Safe Pagination
$pagination = get_pagination_params($totalRecords, 15, [15, 30, 50]);
$page = $pagination['page'];
$limit = $pagination['per_page'];
$offset = $pagination['offset'];
$totalPages = $pagination['total_pages'];

// Fetch Agents Query
$agentsSql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.avatar,
        u.status,
        u.created_at,
        u.last_login_at,
        d.name AS department_name,
        COUNT(DISTINCT t.id) AS assigned_tickets,
        COUNT(DISTINCT CASE WHEN t.status = 'resolved' THEN t.id END) AS resolved_tickets
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN tickets t ON t.assigned_to = u.id
    $whereSql
    GROUP BY u.id
    ORDER BY u.name ASC
    LIMIT $limit OFFSET $offset
";

$agentsStmt = $db->prepare($agentsSql);
$agentsStmt->execute($params);
$agents = $agentsStmt->fetchAll();

// Fetch active departments for filter
$deptListStmt = $db->query("SELECT id, name FROM departments ORDER BY name ASC");
$allDepartments = $deptListStmt->fetchAll();

$pageTitle = 'Agent Management';
$pageHeader = 'Support Agents';
$activePage = 'agents';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-headset me-2 text-primary"></i>Support Agents
            </h1>
            <p class="text-secondary-custom small mb-0">
                Manage support staff, department team assignments, and workload metrics
            </p>
        </div>

        <div>
            <a href="<?= url('modules/agents/create.php'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Agent
            </a>
        </div>
    </div>

    <!-- Filter and Search Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/agents/index.php'); ?>" method="GET" class="row g-2 align-items-center">
                <!-- Search Box -->
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0" 
                               name="q" 
                               value="<?= e($search); ?>" 
                               placeholder="Search agent name, email, phone...">
                    </div>
                </div>

                <!-- Department Filter -->
                <div class="col-6 col-md-3">
                    <select name="department_id" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($allDepartments as $dept): ?>
                            <option value="<?= $dept['id']; ?>" <?= ($departmentFilter === (int)$dept['id']) ? 'selected' : ''; ?>>
                                <?= e($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?= ($statusFilter === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="col-12 col-md d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter) || $departmentFilter > 0): ?>
                        <a href="<?= url('modules/agents/index.php'); ?>" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Agents Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3">Agent</th>
                            <th class="py-3">Department</th>
                            <th class="py-3">Contact</th>
                            <th class="py-3 text-center" style="width: 120px;">Assigned</th>
                            <th class="py-3 text-center" style="width: 120px;">Resolved</th>
                            <th class="py-3" style="width: 120px;">Status</th>
                            <th class="py-3" style="width: 140px;">Joined Date</th>
                            <th class="pe-3 py-3 text-end" style="width: 150px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($agents)): ?>
                            <?php foreach ($agents as $agent): ?>
                                <tr>
                                    <!-- Agent Profile -->
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= e(get_avatar_url($agent['avatar'])); ?>" 
                                                 alt="<?= e($agent['name']); ?>" 
                                                 class="avatar-img avatar-sm flex-shrink-0">
                                            <div>
                                                <a href="<?= url('modules/agents/view.php?id=' . $agent['id']); ?>" class="fw-bold text-dark text-decoration-none">
                                                    <?= e($agent['name']); ?>
                                                </a>
                                                <div class="text-muted fs-8"><?= e($agent['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Department -->
                                    <td>
                                        <?php if (!empty($agent['department_name'])): ?>
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-building me-1 text-secondary"></i><?= e($agent['department_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">No Department</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Contact Phone -->
                                    <td class="small text-secondary">
                                        <?= !empty($agent['phone']) ? e($agent['phone']) : '<span class="text-muted fst-italic">Not provided</span>'; ?>
                                    </td>

                                    <!-- Assigned Tickets -->
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <?= (int)$agent['assigned_tickets']; ?>
                                        </span>
                                    </td>

                                    <!-- Resolved Tickets -->
                                    <td class="text-center">
                                        <span class="badge bg-light text-success border">
                                            <i class="bi bi-check2 me-1"></i><?= (int)$agent['resolved_tickets']; ?>
                                        </span>
                                    </td>

                                    <!-- Status Badge -->
                                    <td>
                                        <span class="badge badge-status-<?= e($agent['status']); ?>">
                                            <?= ucfirst(e($agent['status'])); ?>
                                        </span>
                                    </td>

                                    <!-- Joined Date -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($agent['created_at'], 'M d, Y')); ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-3 text-end">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="<?= url('modules/agents/view.php?id=' . $agent['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="View Agent Profile">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= url('modules/agents/edit.php?id=' . $agent['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Edit Agent">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <!-- Status Toggle Form -->
                                            <form action="<?= url('modules/agents/status.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to change this agent account status?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?= $agent['id']; ?>">
                                                <input type="hidden" name="status" value="<?= ($agent['status'] === STATUS_ACTIVE) ? STATUS_INACTIVE : STATUS_ACTIVE; ?>">
                                                
                                                <?php if ($agent['status'] === STATUS_ACTIVE): ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Deactivate Agent">
                                                        <i class="bi bi-slash-circle"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2" title="Activate Agent">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-headset fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="h6 fw-bold">No agents found</h5>
                                    <p class="small mb-3">Create agent accounts to assign tickets and resolve customer issues.</p>
                                    <a href="<?= url('modules/agents/create.php'); ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create First Agent
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white d-flex align-items-center justify-content-between py-3">
                <span class="small text-muted">
                    Showing <strong><?= ($offset + 1); ?></strong> to <strong><?= min($offset + $limit, $totalRecords); ?></strong> of <strong><?= $totalRecords; ?></strong> agents
                </span>
                <nav aria-label="Agents pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/agents/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= url('modules/agents/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/agents/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
