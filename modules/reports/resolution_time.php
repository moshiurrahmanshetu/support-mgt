<?php
/**
 * Reports - Resolution Time Analytics (Admin Only - Phase 07)
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

// Aggregate Resolution Time Metrics
$metricsStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_tickets,
        COUNT(resolved_at) AS resolved_tickets,
        SUM(CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END) AS unresolved_tickets,
        AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) AS avg_resolution_sec,
        MIN(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) AS fastest_resolution_sec,
        MAX(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) AS slowest_resolution_sec
    FROM tickets
    WHERE created_at BETWEEN ? AND ?
");
$metricsStmt->execute([$from, $to]);
$metrics = $metricsStmt->fetch() ?: [];

$totalTickets = (int)($metrics['total_tickets'] ?? 0);
$resolvedTickets = (int)($metrics['resolved_tickets'] ?? 0);
$unresolvedTickets = (int)($metrics['unresolved_tickets'] ?? 0);
$avgResolutionSec = $metrics['avg_resolution_sec'] ?? null;
$fastestResolutionSec = $metrics['fastest_resolution_sec'] ?? null;
$slowestResolutionSec = $metrics['slowest_resolution_sec'] ?? null;

// Resolution Speed Distribution Brackets: <1h, 1h-8h, 8h-24h, 1d-3d, >3d
$bracketsStmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) < 3600 THEN 1 ELSE 0 END) AS under_1h,
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) BETWEEN 3600 AND 28799 THEN 1 ELSE 0 END) AS under_8h,
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) BETWEEN 28800 AND 86399 THEN 1 ELSE 0 END) AS under_24h,
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) BETWEEN 86400 AND 259199 THEN 1 ELSE 0 END) AS under_3d,
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) >= 259200 THEN 1 ELSE 0 END) AS over_3d
    FROM tickets
    WHERE resolved_at IS NOT NULL AND created_at BETWEEN ? AND ?
");
$bracketsStmt->execute([$from, $to]);
$brackets = $bracketsStmt->fetch() ?: [];

// Fetch sampled resolved tickets
$sampleStmt = $db->prepare("
    SELECT 
        t.id,
        t.ticket_number,
        t.subject,
        t.created_at,
        t.resolved_at,
        TIMESTAMPDIFF(SECOND, t.created_at, t.resolved_at) AS resolution_sec,
        u.name AS customer_name,
        a.name AS agent_name,
        d.name AS department_name
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.resolved_at IS NOT NULL AND t.created_at BETWEEN ? AND ?
    ORDER BY t.resolved_at DESC
    LIMIT 20
");
$sampleStmt->execute([$from, $to]);
$sampleTickets = $sampleStmt->fetchAll();

$pageTitle = 'Resolution Time Analytics';
$pageHeader = 'Resolution Time Analytics';
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
                <i class="bi bi-clock-history me-2 text-primary"></i>Resolution Turnaround & Speed
            </h1>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/reports/resolution_time.php'); ?>" method="GET" class="row g-2 align-items-end">
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
                        <a href="<?= url('modules/reports/resolution_time.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset to Last 30 Days">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <!-- Average -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="text-secondary-custom fs-8 fw-semibold text-uppercase mb-1">Average Resolution</div>
                    <div class="h3 fw-bold text-success mb-0"><?= format_duration($avgResolutionSec); ?></div>
                    <span class="text-muted fs-8">Across <?= $resolvedTickets; ?> resolved tickets</span>
                </div>
            </div>
        </div>

        <!-- Fastest -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="text-secondary-custom fs-8 fw-semibold text-uppercase mb-1">Fastest Resolution</div>
                    <div class="h3 fw-bold text-primary mb-0"><?= format_duration($fastestResolutionSec); ?></div>
                    <span class="text-muted fs-8">Shortest resolution time</span>
                </div>
            </div>
        </div>

        <!-- Slowest -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="text-secondary-custom fs-8 fw-semibold text-uppercase mb-1">Slowest Resolution</div>
                    <div class="h3 fw-bold text-danger mb-0"><?= format_duration($slowestResolutionSec); ?></div>
                    <span class="text-muted fs-8">Longest resolution duration</span>
                </div>
            </div>
        </div>

        <!-- Resolution Ratio -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="text-secondary-custom fs-8 fw-semibold text-uppercase mb-1">Resolution Rate</div>
                    <div class="h3 fw-bold text-dark mb-0"><?= safe_percentage($resolvedTickets, $totalTickets); ?>%</div>
                    <span class="text-muted fs-8"><?= $unresolvedTickets; ?> inquiries currently unresolved</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Resolution Speed Brackets -->
    <div class="card border shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h2 class="h6 mb-0 fw-bold text-dark">
                <i class="bi bi-bar-chart-steps me-2 text-primary"></i>Resolution Duration Brackets
            </h2>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 text-center">
                <div class="col-6 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">&lt; 1 Hour</div>
                        <div class="h4 fw-bold text-success mb-0"><?= (int)($brackets['under_1h'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">1h – 8 Hours</div>
                        <div class="h4 fw-bold text-info mb-0"><?= (int)($brackets['under_8h'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">8h – 24 Hours</div>
                        <div class="h4 fw-bold text-primary mb-0"><?= (int)($brackets['under_24h'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">1 – 3 Days</div>
                        <div class="h4 fw-bold text-warning mb-0"><?= (int)($brackets['under_3d'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-12 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">&gt; 3 Days</div>
                        <div class="h4 fw-bold text-danger mb-0"><?= (int)($brackets['over_3d'] ?? 0); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sample Resolved Tickets -->
    <div class="card border shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
            <h2 class="h6 mb-0 fw-bold text-dark">
                <i class="bi bi-check-circle me-2 text-success"></i>Recently Resolved Tickets
            </h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3" style="width: 140px;">Ticket #</th>
                            <th class="py-3">Subject</th>
                            <th class="py-3">Customer</th>
                            <th class="py-3">Department</th>
                            <th class="py-3">Resolved By</th>
                            <th class="py-3" style="width: 160px;">Resolution Time</th>
                            <th class="pe-3 py-3 text-end" style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sampleTickets)): ?>
                            <?php foreach ($sampleTickets as $st): ?>
                                <tr>
                                    <td class="ps-3 font-monospace fw-bold">
                                        <a href="<?= url('modules/tickets/view.php?id=' . $st['id']); ?>" class="text-decoration-none">
                                            <?= e($st['ticket_number']); ?>
                                        </a>
                                    </td>
                                    <td class="fw-semibold text-dark text-truncate" style="max-width: 260px;">
                                        <?= e($st['subject']); ?>
                                    </td>
                                    <td class="small text-muted"><?= e($st['customer_name']); ?></td>
                                    <td class="small text-muted"><?= e($st['department_name'] ?: 'General'); ?></td>
                                    <td class="small text-muted"><?= e($st['agent_name'] ?: 'Staff'); ?></td>
                                    <td class="font-monospace fw-bold text-success">
                                        <?= format_duration($st['resolution_sec']); ?>
                                    </td>
                                    <td class="pe-3 text-end">
                                        <a href="<?= url('modules/tickets/view.php?id=' . $st['id']); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Ticket">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-clock-history fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No resolved ticket data available for the selected period</h5>
                                    <p class="small mb-0">Tickets transition to resolved when marked as solved by support staff or customers.</p>
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
