<?php
/**
 * Reports - First Response Time Analytics (Admin Only - Phase 07)
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

// Aggregate Response Time Metrics
$metricsStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_tickets,
        COUNT(first_response_at) AS responded_tickets,
        SUM(CASE WHEN first_response_at IS NULL THEN 1 ELSE 0 END) AS unresponded_tickets,
        AVG(TIMESTAMPDIFF(SECOND, created_at, first_response_at)) AS avg_response_sec,
        MIN(TIMESTAMPDIFF(SECOND, created_at, first_response_at)) AS fastest_response_sec,
        MAX(TIMESTAMPDIFF(SECOND, created_at, first_response_at)) AS slowest_response_sec
    FROM tickets
    WHERE created_at BETWEEN ? AND ?
");
$metricsStmt->execute([$from, $to]);
$metrics = $metricsStmt->fetch() ?: [];

$totalTickets = (int)($metrics['total_tickets'] ?? 0);
$respondedTickets = (int)($metrics['responded_tickets'] ?? 0);
$unrespondedTickets = (int)($metrics['unresponded_tickets'] ?? 0);
$avgResponseSec = $metrics['avg_response_sec'] ?? null;
$fastestResponseSec = $metrics['fastest_response_sec'] ?? null;
$slowestResponseSec = $metrics['slowest_response_sec'] ?? null;

// Response Speed Distribution Brackets: <15m, 15m-1h, 1h-4h, 4h-24h, >24h
$bracketsStmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) < 900 THEN 1 ELSE 0 END) AS under_15m,
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) BETWEEN 900 AND 3599 THEN 1 ELSE 0 END) AS under_1h,
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) BETWEEN 3600 AND 14399 THEN 1 ELSE 0 END) AS under_4h,
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) BETWEEN 14400 AND 86399 THEN 1 ELSE 0 END) AS under_24h,
        SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) >= 86400 THEN 1 ELSE 0 END) AS over_24h
    FROM tickets
    WHERE first_response_at IS NOT NULL AND created_at BETWEEN ? AND ?
");
$bracketsStmt->execute([$from, $to]);
$brackets = $bracketsStmt->fetch() ?: [];

// Fetch sampled tickets with response speed
$sampleStmt = $db->prepare("
    SELECT 
        t.id,
        t.ticket_number,
        t.subject,
        t.created_at,
        t.first_response_at,
        TIMESTAMPDIFF(SECOND, t.created_at, t.first_response_at) AS response_sec,
        u.name AS customer_name,
        a.name AS agent_name
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    WHERE t.first_response_at IS NOT NULL AND t.created_at BETWEEN ? AND ?
    ORDER BY t.first_response_at DESC
    LIMIT 20
");
$sampleStmt->execute([$from, $to]);
$sampleTickets = $sampleStmt->fetchAll();

$pageTitle = 'First Response Time Analytics';
$pageHeader = 'First Response Time Analytics';
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
                <i class="bi bi-stopwatch me-2 text-primary"></i>First Response Time Analytics
            </h1>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/reports/response_time.php'); ?>" method="GET" class="row g-2 align-items-end">
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
                        <a href="<?= url('modules/reports/response_time.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset to Last 30 Days">
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
                    <div class="text-secondary-custom fs-8 fw-semibold text-uppercase mb-1">Average Response</div>
                    <div class="h3 fw-bold text-primary mb-0"><?= format_duration($avgResponseSec); ?></div>
                    <span class="text-muted fs-8">Across <?= $respondedTickets; ?> replied tickets</span>
                </div>
            </div>
        </div>

        <!-- Fastest -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="text-secondary-custom fs-8 fw-semibold text-uppercase mb-1">Fastest Response</div>
                    <div class="h3 fw-bold text-success mb-0"><?= format_duration($fastestResponseSec); ?></div>
                    <span class="text-muted fs-8">Best turnaround in period</span>
                </div>
            </div>
        </div>

        <!-- Slowest -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="text-secondary-custom fs-8 fw-semibold text-uppercase mb-1">Slowest Response</div>
                    <div class="h3 fw-bold text-danger mb-0"><?= format_duration($slowestResponseSec); ?></div>
                    <span class="text-muted fs-8">Longest wait time</span>
                </div>
            </div>
        </div>

        <!-- Responded Share -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="text-secondary-custom fs-8 fw-semibold text-uppercase mb-1">Response Coverage</div>
                    <div class="h3 fw-bold text-dark mb-0"><?= safe_percentage($respondedTickets, $totalTickets); ?>%</div>
                    <span class="text-muted fs-8"><?= $unrespondedTickets; ?> waiting for first reply</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Speed Distribution Brackets Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h2 class="h6 mb-0 fw-bold text-dark">
                <i class="bi bi-bar-chart-steps me-2 text-primary"></i>Response Speed Distribution
            </h2>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 text-center">
                <div class="col-6 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">&lt; 15 Minutes</div>
                        <div class="h4 fw-bold text-success mb-0"><?= (int)($brackets['under_15m'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">15m – 1 Hour</div>
                        <div class="h4 fw-bold text-info mb-0"><?= (int)($brackets['under_1h'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">1h – 4 Hours</div>
                        <div class="h4 fw-bold text-primary mb-0"><?= (int)($brackets['under_4h'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">4h – 24 Hours</div>
                        <div class="h4 fw-bold text-warning mb-0"><?= (int)($brackets['under_24h'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-12 col-md">
                    <div class="p-3 bg-light rounded border">
                        <div class="small text-muted fw-semibold">&gt; 24 Hours</div>
                        <div class="h4 fw-bold text-danger mb-0"><?= (int)($brackets['over_24h'] ?? 0); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Responded Tickets Sample -->
    <div class="card border shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
            <h2 class="h6 mb-0 fw-bold text-dark">
                <i class="bi bi-list-check me-2 text-primary"></i>Recent First Responses
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
                            <th class="py-3">Assigned Agent</th>
                            <th class="py-3" style="width: 150px;">First Response Time</th>
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
                                    <td class="fw-semibold text-dark text-truncate" style="max-width: 300px;">
                                        <?= e($st['subject']); ?>
                                    </td>
                                    <td class="small text-muted"><?= e($st['customer_name']); ?></td>
                                    <td class="small text-muted"><?= e($st['agent_name'] ?: 'Staff Reply'); ?></td>
                                    <td class="font-monospace fw-bold text-primary">
                                        <?= format_duration($st['response_sec']); ?>
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
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-stopwatch fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No response data available for the selected period</h5>
                                    <p class="small mb-0">First response times are recorded when support staff post their initial reply to a customer ticket.</p>
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
