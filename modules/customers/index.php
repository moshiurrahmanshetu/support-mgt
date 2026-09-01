<?php
/**
 * Customer Management - Customer List
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

$whereClauses = ["u.role = 'customer'"];
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

// Fetch Customers Query with Ticket Counts
$customersSql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.avatar,
        u.status,
        u.created_at,
        u.last_login_at,
        COUNT(DISTINCT t.id) AS total_tickets,
        COUNT(DISTINCT CASE WHEN t.status IN ('open', 'in_progress', 'pending') THEN t.id END) AS open_tickets
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
    </div>

    <!-- Filter and Search Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/customers/index.php'); ?>" method="GET" class="row g-2 align-items-center">
                <!-- Search Box -->
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0" 
                               name="q" 
                               value="<?= e($search); ?>" 
                               placeholder="Search customer name, email, phone...">
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?= ($statusFilter === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="col-6 col-md d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                        <a href="<?= url('modules/customers/index.php'); ?>" class="btn btn-outline-secondary" title="Reset Filters">
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
                            <th class="py-3 text-center" style="width: 110px;">Total Tickets</th>
                            <th class="py-3 text-center" style="width: 110px;">Open Tickets</th>
                            <th class="py-3" style="width: 120px;">Status</th>
                            <th class="py-3" style="width: 140px;">Joined Date</th>
                            <th class="pe-3 py-3 text-end" style="width: 150px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $cust): ?>
                                <tr>
                                    <!-- Customer Profile -->
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= e(get_avatar_url($cust['avatar'])); ?>" 
                                                 alt="<?= e($cust['name']); ?>" 
                                                 class="avatar-img avatar-sm flex-shrink-0">
                                            <div>
                                                <a href="<?= url('modules/customers/view.php?id=' . $cust['id']); ?>" class="fw-bold text-dark text-decoration-none">
                                                    <?= e($cust['name']); ?>
                                                </a>
                                                <div class="text-muted fs-8"><?= e($cust['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Contact Phone -->
                                    <td class="small text-secondary">
                                        <?= !empty($cust['phone']) ? e($cust['phone']) : '<span class="text-muted fst-italic">Not provided</span>'; ?>
                                    </td>

                                    <!-- Total Tickets -->
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <?= (int)$cust['total_tickets']; ?>
                                        </span>
                                    </td>

                                    <!-- Open Tickets -->
                                    <td class="text-center">
                                        <?php if ((int)$cust['open_tickets'] > 0): ?>
                                            <span class="badge bg-primary">
                                                <?= (int)$cust['open_tickets']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">0</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status Badge -->
                                    <td>
                                        <span class="badge badge-status-<?= e($cust['status']); ?>">
                                            <?= ucfirst(e($cust['status'])); ?>
                                        </span>
                                    </td>

                                    <!-- Joined Date -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($cust['created_at'], 'M d, Y')); ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-3 text-end">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="<?= url('modules/customers/view.php?id=' . $cust['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="View Customer Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= url('modules/customers/edit.php?id=' . $cust['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Edit Customer">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <!-- Status Toggle Form -->
                                            <form action="<?= url('modules/customers/status.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to change this customer account status?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?= $cust['id']; ?>">
                                                <input type="hidden" name="status" value="<?= ($cust['status'] === STATUS_ACTIVE) ? STATUS_INACTIVE : STATUS_ACTIVE; ?>">
                                                
                                                <?php if ($cust['status'] === STATUS_ACTIVE): ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Deactivate Customer">
                                                        <i class="bi bi-slash-circle"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2" title="Activate Customer">
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
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="h6 fw-bold">No customers found</h5>
                                    <p class="small mb-0">
                                        <?php if (!empty($search) || !empty($statusFilter)): ?>
                                            No customers match your filter query. Try resetting filters.
                                        <?php else: ?>
                                            No customer accounts have registered yet.
                                        <?php endif; ?>
                                    </p>
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
