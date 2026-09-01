<?php
/**
 * Customer Management - Customer Directory (Phase 08 Completion)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';

// Strict Authorization Guard
require_permission('customers.view');

$db = get_db();
$currentUser = current_user();

// Filters & Search
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$whereClauses = ["u.role = 'customer'"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter === 'deleted') {
    $whereClauses[] = 'u.deleted_at IS NOT NULL';
} elseif ($statusFilter === 'active' || $statusFilter === 'inactive') {
    $whereClauses[] = 'u.status = ? AND u.deleted_at IS NULL';
    $params[] = $statusFilter;
} elseif ($statusFilter === 'all_with_deleted') {
    // Show all including soft-deleted
} else {
    // Default: Show non-deleted customers
    $whereClauses[] = 'u.deleted_at IS NULL';
}

$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

// Count Total matching records
$countSql = "SELECT COUNT(*) FROM users u $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Safe Pagination (15, 30, 50)
$pagination = get_pagination_params($totalRecords, 15, [15, 30, 50]);
$page = $pagination['page'];
$limit = $pagination['per_page'];
$offset = $pagination['offset'];
$totalPages = $pagination['total_pages'];

// Fetch Customers Query with Ticket Counts
$customersSql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.avatar,
        u.status,
        u.deleted_at,
        u.created_at,
        u.last_login_at,
        COUNT(DISTINCT t.id) AS total_tickets,
        COUNT(DISTINCT CASE WHEN t.status IN ('open', 'in_progress', 'pending') THEN t.id END) AS open_tickets,
        COUNT(DISTINCT CASE WHEN t.status IN ('resolved', 'closed') THEN t.id END) AS resolved_tickets,
        MAX(t.created_at) AS last_ticket_at
    FROM users u
    LEFT JOIN tickets t ON t.user_id = u.id
    $whereSql
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
";

$customersStmt = $db->prepare($customersSql);
$customersStmt->execute($params);
$customers = $customersStmt->fetchAll();

$pageTitle = 'Customer Management';
$pageHeader = 'Customers';
$activePage = 'customers';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-people me-2 text-primary"></i>Customer Accounts
            </h1>
            <p class="text-secondary-custom small mb-0">
                View customer account records, ticket history, and account activity
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if (has_permission('customers.create')): ?>
                <a href="<?= url('modules/customers/create.php'); ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Add Customer
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter and Search Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/customers/index.php'); ?>" method="GET" class="row g-2 align-items-center">
                <!-- Search Box -->
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               name="q" 
                               value="<?= e($search); ?>" 
                               placeholder="Search customer name, email, phone...">
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Active & Inactive (Default)</option>
                        <option value="active" <?= ($statusFilter === 'active') ? 'selected' : ''; ?>>Active Only</option>
                        <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Inactive Only</option>
                        <option value="deleted" <?= ($statusFilter === 'deleted') ? 'selected' : ''; ?>>Deleted Customers</option>
                        <option value="all_with_deleted" <?= ($statusFilter === 'all_with_deleted') ? 'selected' : ''; ?>>All (Including Deleted)</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="col-6 col-md d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                        <a href="<?= url('modules/customers/index.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3">Customer</th>
                            <th class="py-3">Contact</th>
                            <th class="py-3 text-center" style="width: 100px;">Total Tickets</th>
                            <th class="py-3 text-center" style="width: 100px;">Open</th>
                            <th class="py-3 text-center" style="width: 100px;">Resolved</th>
                            <th class="py-3 text-center" style="width: 110px;">Status</th>
                            <th class="py-3" style="width: 140px;">Joined Date</th>
                            <th class="pe-3 py-3 text-end" style="width: 130px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $cust): 
                                $isDeleted = !empty($cust['deleted_at']);
                                $isActive = ($cust['status'] === STATUS_ACTIVE && !$isDeleted);
                            ?>
                                <tr class="<?= $isDeleted ? 'bg-light text-muted' : ''; ?>">
                                    <!-- Customer Profile -->
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= e(get_avatar_url($cust['avatar'])); ?>" 
                                                 alt="<?= e($cust['name']); ?>" 
                                                 class="avatar-img avatar-sm flex-shrink-0">
                                            <div class="overflow-hidden">
                                                <a href="<?= url('modules/customers/view.php?id=' . $cust['id']); ?>" class="fw-bold text-dark text-decoration-none d-block text-truncate">
                                                    <?= e($cust['name']); ?>
                                                </a>
                                                <div class="text-muted fs-8 d-block text-truncate"><?= e($cust['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Contact Phone -->
                                    <td class="small text-secondary font-monospace">
                                        <?= !empty($cust['phone']) ? e($cust['phone']) : '<span class="text-muted fst-italic">—</span>'; ?>
                                    </td>

                                    <!-- Total Tickets -->
                                    <td class="text-center font-monospace">
                                        <span class="badge bg-light text-dark border">
                                            <?= (int)$cust['total_tickets']; ?>
                                        </span>
                                    </td>

                                    <!-- Open Tickets -->
                                    <td class="text-center font-monospace">
                                        <?php if ((int)$cust['open_tickets'] > 0): ?>
                                            <span class="badge bg-primary">
                                                <?= (int)$cust['open_tickets']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">0</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Resolved Tickets -->
                                    <td class="text-center font-monospace">
                                        <span class="badge bg-light text-success border">
                                            <?= (int)$cust['resolved_tickets']; ?>
                                        </span>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="text-center">
                                        <?php if ($isDeleted): ?>
                                            <span class="badge bg-danger">Deleted</span>
                                        <?php elseif ($isActive): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Joined Date -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($cust['created_at'], 'M d, Y')); ?>
                                        <?php if (!empty($cust['last_ticket_at'])): ?>
                                            <div class="fs-9 text-muted">Ticket: <?= e(format_datetime($cust['last_ticket_at'], 'M d')); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-3 text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= url('modules/customers/view.php?id=' . $cust['id']); ?>" class="btn btn-outline-secondary" title="View Customer Profile">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if (!$isDeleted && has_permission('customers.edit')): ?>
                                                <a href="<?= url('modules/customers/edit.php?id=' . $cust['id']); ?>" class="btn btn-outline-secondary" title="Edit Customer">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($isDeleted && has_permission('customers.edit')): ?>
                                                <!-- Restore Customer Form -->
                                                <form action="<?= url('modules/customers/restore.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Restore this customer account?');">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="id" value="<?= $cust['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-success" title="Restore Customer">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </form>
                                            <?php elseif (!$isDeleted && has_permission('customers.edit')): ?>
                                                <!-- Status Toggle Form -->
                                                <form action="<?= url('modules/customers/status.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to <?= $isActive ? 'deactivate' : 'activate'; ?> this customer?');">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="id" value="<?= $cust['id']; ?>">
                                                    <input type="hidden" name="status" value="<?= $isActive ? STATUS_INACTIVE : STATUS_ACTIVE; ?>">
                                                    <button type="submit" class="btn btn-outline-<?= $isActive ? 'warning' : 'success'; ?>" title="<?= $isActive ? 'Deactivate Account' : 'Activate Account'; ?>">
                                                        <i class="bi bi-<?= $isActive ? 'pause-circle' : 'play-circle'; ?>"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (!$isDeleted && has_permission('customers.delete')): ?>
                                                <!-- Delete Customer Form -->
                                                <form action="<?= url('modules/customers/delete.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this customer account? Historical tickets and activity records will be preserved.');">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="id" value="<?= $cust['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete Customer">
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
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="h6 fw-bold">No customers found</h5>
                                    <p class="small mb-0">
                                        <?php if (!empty($search) || !empty($statusFilter)): ?>
                                            No customers match your filter query. Try resetting filters.
                                        <?php else: ?>
                                            No customer accounts exist yet.
                                        <?php endif; ?>
                                    </p>
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
                    Showing <strong><?= ($offset + 1); ?></strong> to <strong><?= min($offset + $limit, $totalRecords); ?></strong> of <strong><?= $totalRecords; ?></strong> customers
                </span>
                <nav aria-label="Customers pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/customers/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= url('modules/customers/index.php?' . http_build_query(array_merge($_GET, ['page' => $p]))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/customers/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
