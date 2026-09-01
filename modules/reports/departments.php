<?php
/**
 * Reports - Department Performance (Admin Only - Phase 07)
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

// Total Tickets in period for percentage baseline
$totalStmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_at BETWEEN ? AND ?");
$totalStmt->execute([$from, $to]);
$globalTotalTickets = (int)$totalStmt->fetchColumn();

// Fetch department metrics
$deptStmt = $db->prepare("
    SELECT 
        d.id,
        d.name AS department_name,
        d.status AS department_status,
        COUNT(t.id) AS total_tickets,
        SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
        SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets,
        SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
        SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets,
        AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.resolved_at) END) AS avg_resolution_sec
    FROM departments d
    LEFT JOIN tickets t ON d.id = t.department_id AND t.created_at BETWEEN ? AND ?
    GROUP BY d.id
    ORDER BY total_tickets DESC, d.name ASC
");
$deptStmt->execute([$from, $to]);
$departments = $deptStmt->fetchAll();

// Uncategorized / None tickets
$uncatStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets,
        AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) END) AS avg_resolution_sec
    FROM tickets
    WHERE department_id IS NULL AND created_at BETWEEN ? AND ?
");
$uncatStmt->execute([$from, $to]);
$uncategorized = $uncatStmt->fetch();

$pageTitle = 'Department Performance Report';
$pageHeader = 'Department Performance Report';
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
                <i class="bi bi-building me-2 text-primary"></i>Department Volume & Performance
            </h1>
        </div>

        <div>
            <a href="<?= url('modules/reports/export.php?type=departments&' . http_build_query($_GET)); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i> Export Departments (CSV)
            </a>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/reports/departments.php'); ?>" method="GET" class="row g-2 align-items-end">
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
                        <a href="<?= url('modules/reports/departments.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset to Last 30 Days">
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
                            <th class="ps-3 py-3">Department</th>
                            <th class="py-3 text-center" style="width: 100px;">Total</th>
                            <th class="py-3 text-center" style="width: 80px;">Open</th>
                            <th class="py-3 text-center" style="width: 90px;">Pending</th>
                            <th class="py-3 text-center" style="width: 90px;">Resolved</th>
                            <th class="py-3 text-center" style="width: 80px;">Closed</th>
                            <th class="py-3" style="width: 150px;">Avg Resolution</th>
                            <th class="py-3" style="width: 150px;">Share (%)</th>
                            <th class="pe-3 py-3 text-end" style="width: 90px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $dept): 
                                $deptTotal = (int)$dept['total_tickets'];
                                $deptShare = safe_percentage($deptTotal, $globalTotalTickets);
                            ?>
                                <tr>
                                    <!-- Department Name & Status -->
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark">
                                            <i class="bi bi-building me-1 text-primary"></i><?= e($dept['department_name']); ?>
                                        </div>
                                        <?php if ($dept['department_status'] === STATUS_INACTIVE): ?>
                                            <span class="badge bg-secondary fs-8">Inactive Team</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Total -->
                                    <td class="text-center font-monospace fw-bold text-dark">
                                        <?= $deptTotal; ?>
                                    </td>

                                    <!-- Open -->
                                    <td class="text-center font-monospace text-primary fw-medium">
                                        <?= (int)$dept['open_tickets']; ?>
                                    </td>

                                    <!-- Pending -->
                                    <td class="text-center font-monospace text-warning fw-medium">
                                        <?= (int)$dept['pending_tickets']; ?>
                                    </td>

                                    <!-- Resolved -->
                                    <td class="text-center font-monospace text-success fw-medium">
                                        <?= (int)$dept['resolved_tickets']; ?>
                                    </td>

                                    <!-- Closed -->
                                    <td class="text-center font-monospace text-secondary fw-medium">
                                        <?= (int)$dept['closed_tickets']; ?>
                                    </td>

                                    <!-- Avg Resolution -->
                                    <td class="small font-monospace text-dark">
                                        <?= format_duration($dept['avg_resolution_sec']); ?>
                                    </td>

                                    <!-- Share -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $deptShare; ?>%;"></div>
                                            </div>
                                            <span class="small font-monospace text-muted" style="width: 40px;"><?= $deptShare; ?>%</span>
                                        </div>
                                    </td>

                                    <!-- Action -->
                                    <td class="pe-3 text-end">
                                        <a href="<?= url('modules/tickets/index.php?department_id=' . $dept['id']); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Department Tickets">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Uncategorized Row (if any) -->
                            <?php if (!empty($uncategorized) && (int)$uncategorized['total_tickets'] > 0): 
                                $uncatTotal = (int)$uncategorized['total_tickets'];
                                $uncatShare = safe_percentage($uncatTotal, $globalTotalTickets);
                            ?>
                                <tr class="table-light">
                                    <td class="ps-3 text-muted fst-italic">
                                        <i class="bi bi-dash-circle me-1"></i>Unassigned Department
                                    </td>
                                    <td class="text-center font-monospace fw-bold text-dark"><?= $uncatTotal; ?></td>
                                    <td class="text-center font-monospace text-primary fw-medium"><?= (int)$uncategorized['open_tickets']; ?></td>
                                    <td class="text-center font-monospace text-warning fw-medium"><?= (int)$uncategorized['pending_tickets']; ?></td>
                                    <td class="text-center font-monospace text-success fw-medium"><?= (int)$uncategorized['resolved_tickets']; ?></td>
                                    <td class="text-center font-monospace text-secondary fw-medium"><?= (int)$uncategorized['closed_tickets']; ?></td>
                                    <td class="small font-monospace text-dark"><?= format_duration($uncategorized['avg_resolution_sec']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-secondary" role="progressbar" style="width: <?= $uncatShare; ?>%;"></div>
                                            </div>
                                            <span class="small font-monospace text-muted" style="width: 40px;"><?= $uncatShare; ?>%</span>
                                        </div>
                                    </td>
                                    <td class="pe-3 text-end">
                                        <a href="<?= url('modules/tickets/index.php?department_id=0'); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Uncategorized Tickets">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-building-x fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No department data found</h5>
                                    <p class="small mb-0">Create departments in Department Management to categorize inquiries.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
