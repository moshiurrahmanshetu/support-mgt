<?php
/**
 * Ticket Management - Ticket List
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

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count Total matching records
$countSql = "
    SELECT COUNT(*) 
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    $whereSql
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
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
        u.id AS customer_id,
        u.name AS customer_name,
        u.email AS customer_email,
        a.name AS agent_name
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    $whereSql
    ORDER BY t.updated_at DESC
    LIMIT $limit OFFSET $offset
";

$ticketsStmt = $db->prepare($ticketsSql);
$ticketsStmt->execute($params);
$tickets = $ticketsStmt->fetchAll();

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

    <!-- Filter and Search Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/tickets/index.php'); ?>" method="GET" class="row g-2 align-items-center">
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
                               placeholder="<?= ($user['role'] === ROLE_CUSTOMER) ? 'Search ticket # or subject...' : 'Search ticket #, subject, customer...'; ?>">
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach (VALID_TICKET_STATUSES as $status): ?>
                            <option value="<?= e($status); ?>" <?= ($statusFilter === $status) ? 'selected' : ''; ?>>
                                <?= ucfirst(str_replace('_', ' ', $status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Priority Filter -->
                <div class="col-6 col-md-2">
                    <select name="priority" class="form-select">
                        <option value="">All Priorities</option>
                        <?php foreach (VALID_PRIORITIES as $priority): ?>
                            <option value="<?= e($priority); ?>" <?= ($priorityFilter === $priority) ? 'selected' : ''; ?>>
                                <?= ucfirst($priority); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Agent View Mode (for Agents) -->
                <?php if ($user['role'] === ROLE_AGENT): ?>
                <div class="col-6 col-md-2">
                    <select name="view" class="form-select">
                        <option value="assigned" <?= (($_GET['view'] ?? 'assigned') === 'assigned') ? 'selected' : ''; ?>>Assigned to Me</option>
                        <option value="all" <?= (($_GET['view'] ?? '') === 'all') ? 'selected' : ''; ?>>All Tickets</option>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Buttons -->
                <div class="col-12 col-md d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($priorityFilter) || isset($_GET['view'])): ?>
                        <a href="<?= url('modules/tickets/index.php'); ?>" class="btn btn-outline-secondary" title="Reset Filters">
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
                            <th class="ps-3 py-3" style="width: 130px;">Ticket #</th>
                            <th class="py-3">Subject</th>
                            <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                                <th class="py-3">Customer</th>
                            <?php endif; ?>
                            <th class="py-3" style="width: 100px;">Priority</th>
                            <th class="py-3" style="width: 120px;">Status</th>
                            <th class="py-3">Assigned To</th>
                            <th class="py-3" style="width: 150px;">Last Activity</th>
                            <th class="pe-3 py-3 text-end" style="width: 90px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tickets)): ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <!-- Ticket Number -->
                                    <td class="ps-3 fw-bold">
                                        <a href="<?= url('modules/tickets/view.php?id=' . $ticket['id']); ?>" class="text-decoration-none">
                                            <?= e($ticket['ticket_number']); ?>
                                        </a>
                                    </td>

                                    <!-- Subject -->
                                    <td>
                                        <a href="<?= url('modules/tickets/view.php?id=' . $ticket['id']); ?>" class="text-dark fw-semibold text-decoration-none">
                                            <?= e($ticket['subject']); ?>
                                        </a>
                                    </td>

                                    <!-- Customer (Admin/Agent only) -->
                                    <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                                        <td>
                                            <div class="fw-medium"><?= e($ticket['customer_name']); ?></div>
                                            <div class="text-muted fs-8"><?= e($ticket['customer_email']); ?></div>
                                        </td>
                                    <?php endif; ?>

                                    <!-- Priority Badge -->
                                    <td>
                                        <?= render_priority_badge($ticket['priority']); ?>
                                    </td>

                                    <!-- Status Badge -->
                                    <td>
                                        <?= render_status_badge($ticket['status']); ?>
                                    </td>

                                    <!-- Assigned Agent -->
                                    <td>
                                        <?php if (!empty($ticket['agent_name'])): ?>
                                            <span class="d-inline-flex align-items-center gap-1 small text-dark">
                                                <i class="bi bi-person text-secondary"></i> <?= e($ticket['agent_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Unassigned</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Last Activity Date -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($ticket['updated_at'])); ?>
                                    </td>

                                    <!-- Action -->
                                    <td class="pe-3 text-end">
                                        <a href="<?= url('modules/tickets/view.php?id=' . $ticket['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="View Ticket Details">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= ($user['role'] !== ROLE_CUSTOMER) ? 8 : 7; ?>" class="text-center py-5 text-muted">
                                    <div class="mb-2">
                                        <i class="bi bi-inbox fs-1 text-secondary"></i>
                                    </div>
                                    <h5 class="h6 fw-bold">No support tickets found</h5>
                                    <p class="small mb-3">
                                        <?php if (!empty($search) || !empty($statusFilter) || !empty($priorityFilter)): ?>
                                            No tickets match your filter criteria. Try resetting the filters.
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
