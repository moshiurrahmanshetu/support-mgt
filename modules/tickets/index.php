<?php
/**
 * Ticket Management - Advanced Search, Filters, Sorting & Pagination (Phase 04)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_login();

$user = current_user();
$db = get_db();

// Filter & Search inputs
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$priorityFilter = trim($_GET['priority'] ?? '');
$departmentFilter = (int)($_GET['department_id'] ?? 0);
$agentFilter = (int)($_GET['agent_id'] ?? 0);
$tagFilter = (int)($_GET['tag'] ?? 0);
$sort = trim($_GET['sort'] ?? 'newest');
$perPage = in_array((int)($_GET['per_page'] ?? 20), [20, 50, 100], true) ? (int)$_GET['per_page'] : 20;

// Build Query based on User Role & Filters
$whereClauses = [];
$params = [];

// Role-based Access Control
if ($user['role'] === ROLE_CUSTOMER) {
    // Customer sees only own tickets
    $whereClauses[] = 't.user_id = ?';
    $params[] = $user['id'];
} elseif ($user['role'] === ROLE_AGENT) {
    // Agent sees assigned tickets by default, or all if specified
    $viewMode = $_GET['view'] ?? 'assigned';
    if ($viewMode === 'assigned') {
        $whereClauses[] = 't.assigned_to = ?';
        $params[] = $user['id'];
    }
}

// Search Filter
if (!empty($search)) {
    if ($user['role'] === ROLE_CUSTOMER) {
        $whereClauses[] = '(t.ticket_number LIKE ? OR t.subject LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    } else {
        $whereClauses[] = '(t.ticket_number LIKE ? OR t.subject LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

// Status Filter
if (!empty($statusFilter) && in_array($statusFilter, VALID_TICKET_STATUSES, true)) {
    $whereClauses[] = 't.status = ?';
    $params[] = $statusFilter;
}

// Priority Filter
if (!empty($priorityFilter) && in_array($priorityFilter, VALID_PRIORITIES, true)) {
    $whereClauses[] = 't.priority = ?';
    $params[] = $priorityFilter;
}

// Department Filter
if ($departmentFilter > 0) {
    $whereClauses[] = 't.department_id = ?';
    $params[] = $departmentFilter;
}

// Agent Filter (Admin only or unassigned)
if ($agentFilter > 0) {
    $whereClauses[] = 't.assigned_to = ?';
    $params[] = $agentFilter;
} elseif ($agentFilter === -1) {
    $whereClauses[] = 't.assigned_to IS NULL';
}

// Tag Filter
if ($tagFilter > 0) {
    $whereClauses[] = 'EXISTS (SELECT 1 FROM ticket_tag_relations ttr_sub WHERE ttr_sub.ticket_id = t.id AND ttr_sub.tag_id = ?)';
    $params[] = $tagFilter;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Sorting whitelist
$orderBySql = 'ORDER BY t.created_at DESC';
switch ($sort) {
    case 'oldest':
        $orderBySql = 'ORDER BY t.created_at ASC';
        break;
    case 'updated':
        $orderBySql = 'ORDER BY t.updated_at DESC';
        break;
    case 'priority':
        $orderBySql = "ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.created_at DESC";
        break;
    case 'newest':
    default:
        $orderBySql = 'ORDER BY t.created_at DESC';
        break;
}

// Count Total matching records
$countSql = "
    SELECT COUNT(*) 
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN users a ON t.assigned_to = a.id
    $whereSql
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = $perPage;
$offset = ($page - 1) * $limit;
$totalPages = ceil($totalRecords / $limit);

// Fetch Tickets Query
$ticketsSql = "
    SELECT 
        t.id,
        t.ticket_number,
        t.subject,
        t.priority,
        t.status,
        t.created_at,
        t.updated_at,
        t.first_response_at,
        t.resolved_at,
        d.name AS department_name,
        u.id AS customer_id,
        u.name AS customer_name,
        u.email AS customer_email,
        a.name AS agent_name,
        (
            SELECT GROUP_CONCAT(CONCAT(tt.name, ':::', tt.color) SEPARATOR '|||')
            FROM ticket_tags tt
            JOIN ticket_tag_relations ttr ON ttr.tag_id = tt.id
            WHERE ttr.ticket_id = t.id
        ) AS tag_list
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN users a ON t.assigned_to = a.id
    $whereSql
    $orderBySql
    LIMIT $limit OFFSET $offset
";

$ticketsStmt = $db->prepare($ticketsSql);
$ticketsStmt->execute($params);
$tickets = $ticketsStmt->fetchAll();

// Fetch active departments, active agents, and tags for filter controls
$deptListStmt = $db->query("SELECT id, name FROM departments ORDER BY name ASC");
$allDepartments = $deptListStmt->fetchAll();

$agentListStmt = $db->query("SELECT id, name FROM users WHERE role = 'agent' AND status = 'active' ORDER BY name ASC");
$allAgents = $agentListStmt->fetchAll();

$tagListStmt = $db->query("SELECT id, name, color FROM ticket_tags ORDER BY name ASC");
$allTags = $tagListStmt->fetchAll();

$pageTitle = 'Support Tickets';
$pageHeader = 'Ticket Management';
$activePage = 'tickets';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-ticket-perforated me-2 text-primary"></i>
                <?= ($user['role'] === ROLE_CUSTOMER) ? 'My Support Tickets' : 'Support Tickets'; ?>
            </h1>
            <p class="text-secondary-custom small mb-0">
                <?= ($user['role'] === ROLE_CUSTOMER) ? 'View and track your support inquiries' : 'Manage, triage, and resolve customer support tickets'; ?>
            </p>
        </div>

        <div>
            <a href="<?= url('modules/tickets/create.php'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Ticket
            </a>
        </div>
    </div>

    <!-- Advanced Filter and Search Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/tickets/index.php'); ?>" method="GET" class="row g-2 align-items-center">
                <!-- Search Box -->
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0" 
                               name="q" 
                               value="<?= e($search); ?>" 
                               placeholder="<?= ($user['role'] === ROLE_CUSTOMER) ? 'Search # or subject...' : 'Search #, subject, customer...'; ?>">
                    </div>
                </div>

                <!-- Department Filter -->
                <div class="col-6 col-md-2">
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
                        <?php foreach (VALID_TICKET_STATUSES as $st): ?>
                            <option value="<?= e($st); ?>" <?= ($statusFilter === $st) ? 'selected' : ''; ?>>
                                <?= ucfirst(str_replace('_', ' ', $st)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Priority Filter -->
                <div class="col-6 col-md-2">
                    <select name="priority" class="form-select">
                        <option value="">All Priorities</option>
                        <?php foreach (VALID_PRIORITIES as $pr): ?>
                            <option value="<?= e($pr); ?>" <?= ($priorityFilter === $pr) ? 'selected' : ''; ?>>
                                <?= ucfirst($pr); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tag Filter -->
                <div class="col-6 col-md-2">
                    <select name="tag" class="form-select">
                        <option value="">All Tags</option>
                        <?php foreach ($allTags as $tg): ?>
                            <option value="<?= $tg['id']; ?>" <?= ($tagFilter === (int)$tg['id']) ? 'selected' : ''; ?>>
                                <?= e($tg['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Agent Filter (Admin only) -->
                <?php if ($user['role'] === ROLE_ADMIN): ?>
                    <div class="col-6 col-md-2">
                        <select name="agent_id" class="form-select">
                            <option value="">All Agents</option>
                            <option value="-1" <?= ($agentFilter === -1) ? 'selected' : ''; ?>>-- Unassigned --</option>
                            <?php foreach ($allAgents as $ag): ?>
                                <option value="<?= $ag['id']; ?>" <?= ($agentFilter === (int)$ag['id']) ? 'selected' : ''; ?>>
                                    <?= e($ag['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Sort Filter -->
                <div class="col-6 col-md-2">
                    <select name="sort" class="form-select">
                        <option value="newest" <?= ($sort === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?= ($sort === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="updated" <?= ($sort === 'updated') ? 'selected' : ''; ?>>Recently Updated</option>
                        <option value="priority" <?= ($sort === 'priority') ? 'selected' : ''; ?>>Highest Priority</option>
                    </select>
                </div>

                <!-- Per Page -->
                <div class="col-6 col-md-1">
                    <select name="per_page" class="form-select" title="Rows per page">
                        <option value="20" <?= ($perPage === 20) ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?= ($perPage === 50) ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?= ($perPage === 100) ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>

                <!-- Filter & Reset Buttons -->
                <div class="col-6 col-md d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($priorityFilter) || $departmentFilter > 0 || $agentFilter !== 0 || $tagFilter > 0 || $sort !== 'newest' || $perPage !== 20): ?>
                        <a href="<?= url('modules/tickets/index.php'); ?>" class="btn btn-outline-secondary" title="Reset All Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tickets Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3" style="width: 120px;">Ticket #</th>
                            <th class="py-3">Subject & Tags</th>
                            <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                                <th class="py-3">Customer</th>
                            <?php endif; ?>
                            <th class="py-3">Department</th>
                            <th class="py-3" style="width: 100px;">Priority</th>
                            <th class="py-3" style="width: 120px;">Status</th>
                            <th class="py-3">Assigned To</th>
                            <th class="py-3" style="width: 140px;">Created</th>
                            <th class="pe-3 py-3 text-end" style="width: 90px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tickets)): ?>
                            <?php foreach ($tickets as $tkt): ?>
                                <tr>
                                    <!-- Ticket Number -->
                                    <td class="ps-3 fw-bold">
                                        <a href="<?= url('modules/tickets/view.php?id=' . $tkt['id']); ?>" class="text-decoration-none font-monospace">
                                            <?= e($tkt['ticket_number']); ?>
                                        </a>
                                    </td>

                                    <!-- Subject & Tags -->
                                    <td>
                                        <div class="d-flex flex-column">
                                            <a href="<?= url('modules/tickets/view.php?id=' . $tkt['id']); ?>" class="text-dark fw-semibold text-decoration-none">
                                                <?= e($tkt['subject']); ?>
                                            </a>
                                            <!-- Tag Pills -->
                                            <?php if (!empty($tkt['tag_list'])): ?>
                                                <div class="d-flex flex-wrap gap-1 mt-1">
                                                    <?php 
                                                        $rawTags = explode('|||', $tkt['tag_list']);
                                                        foreach ($rawTags as $rTag) {
                                                            $parts = explode(':::', $rTag);
                                                            if (count($parts) === 2) {
                                                                echo '<span class="badge" style="background-color: ' . e($parts[1]) . '; color: #ffffff; font-size: 0.7rem;">' . e($parts[0]) . '</span>';
                                                            }
                                                        }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Customer (Admin/Agent only) -->
                                    <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                                        <td>
                                            <div class="fw-medium"><?= e($tkt['customer_name']); ?></div>
                                            <div class="text-muted fs-8"><?= e($tkt['customer_email']); ?></div>
                                        </td>
                                    <?php endif; ?>

                                    <!-- Department -->
                                    <td>
                                        <?php if (!empty($tkt['department_name'])): ?>
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-building me-1 text-secondary"></i><?= e($tkt['department_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">General</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Priority Badge -->
                                    <td>
                                        <?= render_priority_badge($tkt['priority']); ?>
                                    </td>

                                    <!-- Status Badge -->
                                    <td>
                                        <?= render_status_badge($tkt['status']); ?>
                                    </td>

                                    <!-- Assigned Agent -->
                                    <td>
                                        <?php if (!empty($tkt['agent_name'])): ?>
                                            <span class="d-inline-flex align-items-center gap-1 small text-dark">
                                                <i class="bi bi-person text-secondary"></i> <?= e($tkt['agent_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Unassigned</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Created Date -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($tkt['created_at'], 'M d, Y')); ?>
                                    </td>

                                    <!-- Action -->
                                    <td class="pe-3 text-end">
                                        <a href="<?= url('modules/tickets/view.php?id=' . $tkt['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="View Ticket Details">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= ($user['role'] !== ROLE_CUSTOMER) ? 9 : 8; ?>" class="text-center py-5 text-muted">
                                    <div class="mb-2">
                                        <i class="bi bi-inbox fs-1 text-secondary"></i>
                                    </div>
                                    <h5 class="h6 fw-bold">No support tickets found</h5>
                                    <p class="small mb-3">
                                        <?php if (!empty($search) || !empty($statusFilter) || !empty($priorityFilter) || $departmentFilter > 0 || $agentFilter !== 0 || $tagFilter > 0): ?>
                                            No tickets match your active filter criteria. Try resetting the filters.
                                        <?php else: ?>
                                            You do not have any support tickets yet.
                                        <?php endif; ?>
                                    </p>
                                    <a href="<?= url('modules/tickets/create.php'); ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create New Ticket
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
                    Showing <strong><?= ($offset + 1); ?></strong> to <strong><?= min($offset + $limit, $totalRecords); ?></strong> of <strong><?= $totalRecords; ?></strong> tickets
                </span>
                <nav aria-label="Tickets navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/tickets/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= url('modules/tickets/index.php?' . http_build_query(array_merge($_GET, ['page' => $p]))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/tickets/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
