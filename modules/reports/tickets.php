<?php
/**
 * Reports - Ticket Status & Priority Distribution (Admin Only - Phase 07)
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

// 1. Total Tickets in Period
$totalStmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_at BETWEEN ? AND ?");
$totalStmt->execute([$from, $to]);
$totalTickets = (int)$totalStmt->fetchColumn();

// 2. Status Breakdown
$statusStmt = $db->prepare("
    SELECT 
        status, 
        COUNT(*) AS count 
    FROM tickets 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
");
$statusStmt->execute([$from, $to]);
$rawStatuses = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$statusRows = [];
foreach (VALID_TICKET_STATUSES as $st) {
    $count = (int)($rawStatuses[$st] ?? 0);
    $statusRows[] = [
        'status'     => $st,
        'label'      => ucfirst(str_replace('_', ' ', $st)),
        'count'      => $count,
        'percentage' => safe_percentage($count, $totalTickets)
    ];
}

// 3. Priority Breakdown
$priorityStmt = $db->prepare("
    SELECT 
        priority, 
        COUNT(*) AS count 
    FROM tickets 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY priority
");
$priorityStmt->execute([$from, $to]);
$rawPriorities = $priorityStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$priorityRows = [];
foreach (VALID_PRIORITIES as $pr) {
    $count = (int)($rawPriorities[$pr] ?? 0);
    $priorityRows[] = [
        'priority'   => $pr,
        'label'      => ucfirst($pr),
        'count'      => $count,
        'percentage' => safe_percentage($count, $totalTickets)
    ];
}

$pageTitle = 'Ticket Status & Priority Report';
$pageHeader = 'Ticket Distribution Report';
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
                <i class="bi bi-ticket-perforated me-2 text-primary"></i>Ticket Status & Priority Distribution
            </h1>
        </div>

        <div>
            <a href="<?= url('modules/reports/export.php?type=tickets&' . http_build_query($_GET)); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i> Export Ticket Data (CSV)
            </a>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/reports/tickets.php'); ?>" method="GET" class="row g-2 align-items-end">
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
                        <a href="<?= url('modules/reports/tickets.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset to Last 30 Days">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Period Summary Header -->
    <div class="alert alert-light border shadow-sm d-flex align-items-center justify-content-between py-2 px-3 mb-4">
        <span class="small text-secondary-custom">
            Analyzing <strong><?= $totalTickets; ?></strong> tickets created during <strong><?= e($dateRange['label']); ?></strong>
        </span>
        <span class="badge bg-primary"><?= $totalTickets; ?> Total</span>
    </div>

    <div class="row g-4">
        <!-- Status Breakdown Table -->
        <div class="col-12 col-lg-6">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-activity me-2 text-primary"></i>Tickets by Status
                    </h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="text-secondary-custom fs-7">
                                    <th class="ps-3 py-3">Status</th>
                                    <th class="py-3 text-center" style="width: 100px;">Count</th>
                                    <th class="py-3" style="width: 180px;">Share (%)</th>
                                    <th class="pe-3 py-3 text-end" style="width: 90px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statusRows as $row): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="badge badge-status-<?= e($row['status']); ?>">
                                                <?= e($row['label']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center font-monospace fw-bold text-dark">
                                            <?= $row['count']; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $row['percentage']; ?>%;" aria-valuenow="<?= $row['percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <span class="small font-monospace text-muted" style="width: 45px;"><?= $row['percentage']; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="pe-3 text-end">
                                            <a href="<?= url('modules/tickets/index.php?status=' . urlencode($row['status'])); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Tickets">
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Priority Breakdown Table -->
        <div class="col-12 col-lg-6">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-exclamation-diamond me-2 text-primary"></i>Tickets by Priority
                    </h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="text-secondary-custom fs-7">
                                    <th class="ps-3 py-3">Priority Level</th>
                                    <th class="py-3 text-center" style="width: 100px;">Count</th>
                                    <th class="py-3" style="width: 180px;">Share (%)</th>
                                    <th class="pe-3 py-3 text-end" style="width: 90px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($priorityRows as $row): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="badge badge-priority-<?= e($row['priority']); ?>">
                                                <?= e($row['label']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center font-monospace fw-bold text-dark">
                                            <?= $row['count']; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $row['percentage']; ?>%;" aria-valuenow="<?= $row['percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <span class="small font-monospace text-muted" style="width: 45px;"><?= $row['percentage']; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="pe-3 text-end">
                                            <a href="<?= url('modules/tickets/index.php?priority=' . urlencode($row['priority'])); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Tickets">
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
