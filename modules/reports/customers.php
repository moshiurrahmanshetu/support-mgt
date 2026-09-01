<?php
/**
 * Reports - Customer Ticket Analytics (Admin Only - Phase 07)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/reports.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$db = get_db();

// Parse Date Range Filter
$dateRange = get_report_date_range($_GET, 'last_30_days');
$from = $dateRange['from'];
$to = $dateRange['to'];

// Total Customers count for pagination
$totalStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$totalRecords = (int)$totalStmt->fetchColumn();

// Safe Pagination
$pagination = get_pagination_params($totalRecords, 20, [20, 50, 100]);
$page = $pagination['page'];
$limit = $pagination['per_page'];
$offset = $pagination['offset'];
$totalPages = $pagination['total_pages'];

// Fetch Customers and ticket metrics in period
$custSql = "
    SELECT 
        u.id,
        u.name AS customer_name,
        u.email AS customer_email,
        u.status AS customer_status,
        COUNT(t.id) AS total_tickets,
        SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets,
        SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
        SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets,
        MAX(t.created_at) AS last_ticket_date
    FROM users u
    LEFT JOIN tickets t ON u.id = t.user_id AND t.created_at BETWEEN ? AND ?
    WHERE u.role = 'customer'
    GROUP BY u.id
    ORDER BY total_tickets DESC, last_ticket_date DESC, u.name ASC
    LIMIT $limit OFFSET $offset
";
$custStmt = $db->prepare($custSql);
$custStmt->execute([$from, $to]);
$customers = $custStmt->fetchAll();

$pageTitle = 'Customer Ticket Analytics';
$pageHeader = 'Customer Ticket Analytics';
$activePage = 'reports';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header & Back Link -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= url('modules/reports/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Analytics Overview
                </a>
            </div>
            <h1 class="h4 fw-bold mb-0">
                <i class="bi bi-people me-2 text-primary"></i>Customer Ticket Volume & Activity
            </h1>
        </div>

        <div>
            <a href="<?= url('modules/reports/export.php?type=customers&' . http_build_query($_GET)); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i> Export Customers (CSV)
            </a>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/reports/customers.php'); ?>" method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="date_range" class="form-label fs-8 fw-semibold text-secondary-custom mb-1">Date Range</label>
                    <select name="date_range" id="date_range" class="form-select form-select-sm" onchange="toggleCustomDates(this.value)">
                        <option value="today" <?= ($dateRange['preset'] === 'today') ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?= ($dateRange['preset'] === 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="last_7_days" <?= ($dateRange['preset'] === 'last_7_days') ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="last_30_days" <?= ($dateRange['preset'] === 'last_30_days') ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="this_month" <?= ($dateRange['preset'] === 'this_month') ? 'selected' : ''; ?>>This Month</option>
                        <option value="last_month" <?= ($dateRange['preset'] === 'last_month') ? 'selected' : ''; ?>>Last Month</option>
                        <option value="custom" <?= ($dateRange['preset'] === 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>

                <div class="col-6 col-md-3 custom-date-col <?= ($dateRange['preset'] !== 'custom') ? 'd-none' : ''; ?>" id="colFromDate">
                    <label for="from_date" class="form-label fs-8 fw-semibold text-secondary-custom mb-1">From Date</label>
                    <input type="date" name="from_date" id="from_date" class="form-control form-control-sm" value="<?= e($dateRange['from_date']); ?>">
                </div>

                <div class="col-6 col-md-3 custom-date-col <?= ($dateRange['preset'] !== 'custom') ? 'd-none' : ''; ?>" id="colToDate">
                    <label for="to_date" class="form-label fs-8 fw-semibold text-secondary-custom mb-1">To Date</label>
                    <input type="date" name="to_date" id="to_date" class="form-control form-control-sm" value="<?= e($dateRange['to_date']); ?>">
                </div>

                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-funnel"></i> Apply Filter
                    </button>
                    <?php if ($dateRange['preset'] !== 'last_30_days'): ?>
                        <a href="<?= url('modules/reports/customers.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset to Last 30 Days">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3">Customer</th>
                            <th class="py-3 text-center" style="width: 110px;">Tickets in Period</th>
                            <th class="py-3 text-center" style="width: 80px;">Open</th>
                            <th class="py-3 text-center" style="width: 90px;">Pending</th>
                            <th class="py-3 text-center" style="width: 90px;">Resolved</th>
                            <th class="py-3 text-center" style="width: 80px;">Closed</th>
                            <th class="py-3" style="width: 150px;">Last Ticket</th>
                            <th class="pe-3 py-3 text-end" style="width: 90px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $cust): ?>
                                <tr>
                                    <!-- Customer Name -->
                                    <td class="ps-3">
                                        <a href="<?= url('modules/customers/view.php?id=' . $cust['id']); ?>" class="fw-semibold text-dark text-decoration-none">
                                            <?= e($cust['customer_name']); ?>
                                        </a>
                                        <div class="text-muted small"><?= e($cust['customer_email']); ?></div>
                                    </td>

                                    <!-- Total -->
                                    <td class="text-center font-monospace fw-bold text-dark">
                                        <?= (int)$cust['total_tickets']; ?>
                                    </td>

                                    <!-- Open -->
                                    <td class="text-center font-monospace text-primary fw-medium">
                                        <?= (int)$cust['open_tickets']; ?>
                                    </td>

                                    <!-- Pending -->
                                    <td class="text-center font-monospace text-warning fw-medium">
                                        <?= (int)$cust['pending_tickets']; ?>
                                    </td>

                                    <!-- Resolved -->
                                    <td class="text-center font-monospace text-success fw-medium">
                                        <?= (int)$cust['resolved_tickets']; ?>
                                    </td>

                                    <!-- Closed -->
                                    <td class="text-center font-monospace text-secondary fw-medium">
                                        <?= (int)$cust['closed_tickets']; ?>
                                    </td>

                                    <!-- Last Ticket Date -->
                                    <td class="small text-muted">
                                        <?= !empty($cust['last_ticket_date']) ? e(format_datetime($cust['last_ticket_date'], 'M d, Y')) : '<span class="text-muted">None in period</span>'; ?>
                                    </td>

                                    <!-- Action -->
                                    <td class="pe-3 text-end">
                                        <a href="<?= url('modules/customers/view.php?id=' . $cust['id']); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Customer Profile">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No customer records found</h5>
                                    <p class="small mb-0">Customers will appear here as they register and create support tickets.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Safe Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white d-flex align-items-center justify-content-between py-3">
                <span class="small text-muted">
                    Showing <strong><?= ($offset + 1); ?></strong> to <strong><?= min($offset + $limit, $totalRecords); ?></strong> of <strong><?= $totalRecords; ?></strong> customers
                </span>
                <nav aria-label="Customers navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/reports/customers.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= url('modules/reports/customers.php?' . http_build_query(array_merge($_GET, ['page' => $p]))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/reports/customers.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleCustomDates(preset) {
    const fromCol = document.getElementById('colFromDate');
    const toCol = document.getElementById('colToDate');
    if (preset === 'custom') {
        fromCol.classList.remove('d-none');
        toCol.classList.remove('d-none');
    } else {
        fromCol.classList.add('d-none');
        toCol.classList.add('d-none');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
