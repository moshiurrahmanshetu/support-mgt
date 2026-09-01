<?php
/**
 * Reports & Analytics - Overview Dashboard (Admin Only - Phase 07)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/reports.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$user = current_user();
$db = get_db();

// Parse Date Range Filter
$dateRange = get_report_date_range($_GET, 'last_30_days');
$from = $dateRange['from'];
$to = $dateRange['to'];

// 1. Ticket Volume Metrics for Selected Period
$tktStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets,
        SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) AS unassigned_tickets,
        AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) END) AS avg_first_response_sec,
        AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) END) AS avg_resolution_sec
    FROM tickets
    WHERE created_at BETWEEN ? AND ?
");
$tktStmt->execute([$from, $to]);
$tktMetrics = $tktStmt->fetch() ?: [];

$totalTickets = (int)($tktMetrics['total_tickets'] ?? 0);
$openTickets = (int)($tktMetrics['open_tickets'] ?? 0);
$inProgressTickets = (int)($tktMetrics['in_progress_tickets'] ?? 0);
$pendingTickets = (int)($tktMetrics['pending_tickets'] ?? 0);
$resolvedTickets = (int)($tktMetrics['resolved_tickets'] ?? 0);
$closedTickets = (int)($tktMetrics['closed_tickets'] ?? 0);
$unassignedTickets = (int)($tktMetrics['unassigned_tickets'] ?? 0);
$avgFirstResponseSec = $tktMetrics['avg_first_response_sec'] ?? null;
$avgResolutionSec = $tktMetrics['avg_resolution_sec'] ?? null;

// 2. Global System Totals
$userCountsStmt = $db->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'customer') AS total_customers,
        (SELECT COUNT(*) FROM users WHERE role = 'agent') AS total_agents,
        (SELECT COUNT(*) FROM users WHERE role = 'agent' AND status = 'active') AS active_agents,
        (SELECT COUNT(*) FROM tickets WHERE created_at >= CURDATE()) AS tickets_today,
        (SELECT COUNT(*) FROM tickets WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL (DAYOFWEEK(CURDATE()) - 1) DAY)) AS tickets_this_week,
        (SELECT COUNT(*) FROM tickets WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS tickets_this_month
");
$systemCounts = $userCountsStmt->fetch() ?: [];

// 3. Ticket Status Distribution for Progress Bars
$openPct = safe_percentage($openTickets, $totalTickets);
$inProgressPct = safe_percentage($inProgressTickets, $totalTickets);
$pendingPct = safe_percentage($pendingTickets, $totalTickets);
$resolvedPct = safe_percentage($resolvedTickets, $totalTickets);
$closedPct = safe_percentage($closedTickets, $totalTickets);

// Log Report View
log_activity($user['id'], 'reports', 'report_viewed', "Viewed Reports Overview Dashboard ({$dateRange['label']})");

$pageTitle = 'Reports & Analytics Dashboard';
$pageHeader = 'Reports & Analytics';
$activePage = 'reports';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header & Date Filter Bar -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-graph-up me-2 text-primary"></i>Executive Support Analytics
            </h1>
            <p class="text-secondary-custom small mb-0">
                Period: <strong class="text-dark"><?= e($dateRange['label']); ?></strong>
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="<?= url('modules/reports/export.php?' . http_build_query($_GET)); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/reports/index.php'); ?>" method="GET" class="row g-2 align-items-end">
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
                        <a href="<?= url('modules/reports/index.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset to Last 30 Days">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Master KPI Cards Row -->
    <div class="row g-3 mb-4">
        <!-- Total Tickets in Period -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Tickets Created</span>
                        <div class="p-2 rounded bg-light text-primary">
                            <i class="bi bi-ticket-perforated fs-5"></i>
                        </div>
                    </div>
                    <div class="h3 fw-bold mb-0 text-dark"><?= $totalTickets; ?></div>
                    <span class="text-muted fs-8">In selected period</span>
                </div>
            </div>
        </div>

        <!-- Average First Response -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Avg First Response</span>
                        <div class="p-2 rounded bg-light text-info">
                            <i class="bi bi-stopwatch fs-5"></i>
                        </div>
                    </div>
                    <div class="h3 fw-bold mb-0 text-dark"><?= format_duration($avgFirstResponseSec); ?></div>
                    <span class="text-muted fs-8">First reply speed</span>
                </div>
            </div>
        </div>

        <!-- Average Resolution Time -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Avg Resolution Time</span>
                        <div class="p-2 rounded bg-light text-success">
                            <i class="bi bi-check2-circle fs-5"></i>
                        </div>
                    </div>
                    <div class="h3 fw-bold mb-0 text-dark"><?= format_duration($avgResolutionSec); ?></div>
                    <span class="text-muted fs-8">Duration to resolution</span>
                </div>
            </div>
        </div>

        <!-- Unassigned Tickets -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Unassigned Tickets</span>
                        <div class="p-2 rounded bg-light text-danger">
                            <i class="bi bi-person-x fs-5"></i>
                        </div>
                    </div>
                    <div class="h3 fw-bold mb-0 text-dark"><?= $unassignedTickets; ?></div>
                    <span class="text-muted fs-8">
                        <a href="<?= url('modules/tickets/index.php?agent_id=-1'); ?>" class="text-danger text-decoration-none fw-medium">View Unassigned</a>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Breakdown Cards -->
    <div class="row g-3 mb-4">
        <!-- Open -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border shadow-sm text-center p-3">
                <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Open</div>
                <div class="h4 fw-bold text-primary mb-1"><?= $openTickets; ?></div>
                <div class="small text-muted"><?= $openPct; ?>%</div>
            </div>
        </div>

        <!-- In Progress -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border shadow-sm text-center p-3">
                <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">In Progress</div>
                <div class="h4 fw-bold text-info mb-1"><?= $inProgressTickets; ?></div>
                <div class="small text-muted"><?= $inProgressPct; ?>%</div>
            </div>
        </div>

        <!-- Pending -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border shadow-sm text-center p-3">
                <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Pending</div>
                <div class="h4 fw-bold text-warning mb-1"><?= $pendingTickets; ?></div>
                <div class="small text-muted"><?= $pendingPct; ?>%</div>
            </div>
        </div>

        <!-- Resolved -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border shadow-sm text-center p-3">
                <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Resolved</div>
                <div class="h4 fw-bold text-success mb-1"><?= $resolvedTickets; ?></div>
                <div class="small text-muted"><?= $resolvedPct; ?>%</div>
            </div>
        </div>

        <!-- Closed -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border shadow-sm text-center p-3">
                <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Closed</div>
                <div class="h4 fw-bold text-secondary mb-1"><?= $closedTickets; ?></div>
                <div class="small text-muted"><?= $closedPct; ?>%</div>
            </div>
        </div>

        <!-- Resolution Ratio -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border shadow-sm text-center p-3">
                <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Resolution Rate</div>
                <div class="h4 fw-bold text-dark mb-1"><?= safe_percentage($resolvedTickets + $closedTickets, $totalTickets); ?>%</div>
                <div class="small text-muted"><?= ($resolvedTickets + $closedTickets); ?> resolved</div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation to Dedicated Reports Grid -->
    <h2 class="h5 fw-bold text-dark mb-3">
        <i class="bi bi-pie-chart me-2 text-primary"></i>Detailed Analytical Reports
    </h2>

    <div class="row g-3 mb-4">
        <!-- 1. Tickets Report -->
        <div class="col-12 col-md-6 col-lg-4">
            <a href="<?= url('modules/reports/tickets.php?' . http_build_query($_GET)); ?>" class="card h-100 border shadow-sm text-decoration-none hover-shadow transition">
                <div class="card-body p-4">
                    <div class="p-3 rounded bg-light text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-ticket-perforated fs-4"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-1">Ticket Status & Priority</h3>
                    <p class="text-secondary-custom small mb-0">Distribution of inquiries across lifecycle stages and urgency levels.</p>
                </div>
            </a>
        </div>

        <!-- 2. Departments Report -->
        <div class="col-12 col-md-6 col-lg-4">
            <a href="<?= url('modules/reports/departments.php?' . http_build_query($_GET)); ?>" class="card h-100 border shadow-sm text-decoration-none hover-shadow transition">
                <div class="card-body p-4">
                    <div class="p-3 rounded bg-light text-warning d-inline-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-building fs-4"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-1">Department Performance</h3>
                    <p class="text-secondary-custom small mb-0">Ticket volume, resolution rates, and speed comparisons across support teams.</p>
                </div>
            </a>
        </div>

        <!-- 3. Agents Report -->
        <div class="col-12 col-md-6 col-lg-4">
            <a href="<?= url('modules/reports/agents.php?' . http_build_query($_GET)); ?>" class="card h-100 border shadow-sm text-decoration-none hover-shadow transition">
                <div class="card-body p-4">
                    <div class="p-3 rounded bg-light text-info d-inline-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-headset fs-4"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-1">Agent Workload & Speed</h3>
                    <p class="text-secondary-custom small mb-0">Assigned workload, response metrics, and resolution turnaround per agent.</p>
                </div>
            </a>
        </div>

        <!-- 4. Customers Report -->
        <div class="col-12 col-md-6 col-lg-4">
            <a href="<?= url('modules/reports/customers.php?' . http_build_query($_GET)); ?>" class="card h-100 border shadow-sm text-decoration-none hover-shadow transition">
                <div class="card-body p-4">
                    <div class="p-3 rounded bg-light text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-1">Customer Ticket Volume</h3>
                    <p class="text-secondary-custom small mb-0">Ticket frequencies, active customer inquiries, and inquiry history.</p>
                </div>
            </a>
        </div>

        <!-- 5. First Response Time -->
        <div class="col-12 col-md-6 col-lg-4">
            <a href="<?= url('modules/reports/response_time.php?' . http_build_query($_GET)); ?>" class="card h-100 border shadow-sm text-decoration-none hover-shadow transition">
                <div class="card-body p-4">
                    <div class="p-3 rounded bg-light text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-stopwatch fs-4"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-1">First Response Analytics</h3>
                    <p class="text-secondary-custom small mb-0">Detailed breakdown of first reply latency, fastest answers, and backlogs.</p>
                </div>
            </a>
        </div>

        <!-- 6. Resolution Time -->
        <div class="col-12 col-md-6 col-lg-4">
            <a href="<?= url('modules/reports/resolution_time.php?' . http_build_query($_GET)); ?>" class="card h-100 border shadow-sm text-decoration-none hover-shadow transition">
                <div class="card-body p-4">
                    <div class="p-3 rounded bg-light text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-1">Resolution Duration</h3>
                    <p class="text-secondary-custom small mb-0">Turnaround times from ticket creation to final resolution and closure.</p>
                </div>
            </a>
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
