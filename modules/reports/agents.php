<?php
/**
 * Reports - Agent Performance (Admin Only - Phase 07)
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

$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';
$statusCondition = $showInactive ? "" : "WHERE u.status = 'active'";

// Fetch Agent Metrics
$agentSql = "
    SELECT 
        u.id,
        u.name AS agent_name,
        u.email AS agent_email,
        u.status AS agent_status,
        d.name AS department_name,
        COUNT(t.id) AS assigned_tickets,
        SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
        SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets,
        SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
        SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets,
        AVG(CASE WHEN t.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.first_response_at) END) AS avg_first_response_sec,
        AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.resolved_at) END) AS avg_resolution_sec
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN tickets t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
    $statusCondition
      AND (u.role = 'agent' OR u.role = 'admin')
    GROUP BY u.id
    ORDER BY assigned_tickets DESC, u.name ASC
";
$agentStmt = $db->prepare($agentSql);
$agentStmt->execute([$from, $to]);
$agents = $agentStmt->fetchAll();

$pageTitle = 'Agent Performance Report';
$pageHeader = 'Agent Performance Report';
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
                <i class="bi bi-headset me-2 text-primary"></i>Support Agent Workload & Performance
            </h1>
        </div>

        <div>
            <a href="<?= url('modules/reports/export.php?type=agents&' . http_build_query($_GET)); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i> Export Agents (CSV)
            </a>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/reports/agents.php'); ?>" method="GET" class="row g-2 align-items-end">
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

                <div class="col-6 col-md-2 custom-date-col <?= ($dateRange['preset'] !== 'custom') ? 'd-none' : ''; ?>" id="colFromDate">
                    <label for="from_date" class="form-label fs-8 fw-semibold text-secondary-custom mb-1">From Date</label>
                    <input type="date" name="from_date" id="from_date" class="form-control form-control-sm" value="<?= e($dateRange['from_date']); ?>">
                </div>

                <div class="col-6 col-md-2 custom-date-col <?= ($dateRange['preset'] !== 'custom') ? 'd-none' : ''; ?>" id="colToDate">
                    <label for="to_date" class="form-label fs-8 fw-semibold text-secondary-custom mb-1">To Date</label>
                    <input type="date" name="to_date" id="to_date" class="form-control form-control-sm" value="<?= e($dateRange['to_date']); ?>">
                </div>

                <div class="col-12 col-md-3">
                    <div class="form-check pb-1">
                        <input class="form-check-input" type="checkbox" name="show_inactive" id="show_inactive" value="1" <?= $showInactive ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <label class="form-check-label small" for="show_inactive">
                            Show Inactive Agents
                        </label>
                    </div>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-funnel"></i> Apply
                    </button>
                    <?php if ($dateRange['preset'] !== 'last_30_days' || $showInactive): ?>
                        <a href="<?= url('modules/reports/agents.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Agents Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3">Support Agent</th>
                            <th class="py-3">Department</th>
                            <th class="py-3 text-center" style="width: 90px;">Assigned</th>
                            <th class="py-3 text-center" style="width: 80px;">Open</th>
                            <th class="py-3 text-center" style="width: 80px;">Pending</th>
                            <th class="py-3 text-center" style="width: 90px;">Resolved</th>
                            <th class="py-3 text-center" style="width: 80px;">Closed</th>
                            <th class="py-3" style="width: 140px;">Avg Response</th>
                            <th class="py-3" style="width: 140px;">Avg Resolution</th>
                            <th class="pe-3 py-3 text-end" style="width: 90px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($agents)): ?>
                            <?php foreach ($agents as $agent): 
                                $assignedCount = (int)$agent['assigned_tickets'];
                            ?>
                                <tr>
                                    <!-- Agent Name & Email -->
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark"><?= e($agent['agent_name']); ?></div>
                                        <div class="text-muted small"><?= e($agent['agent_email']); ?></div>
                                        <?php if ($agent['agent_status'] === STATUS_INACTIVE): ?>
                                            <span class="badge bg-secondary fs-8">Inactive</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Department -->
                                    <td class="small text-muted">
                                        <?= e($agent['department_name'] ?: 'All Support'); ?>
                                    </td>

                                    <!-- Assigned Total -->
                                    <td class="text-center font-monospace fw-bold text-dark">
                                        <?= $assignedCount; ?>
                                    </td>

                                    <!-- Open -->
                                    <td class="text-center font-monospace text-primary fw-medium">
                                        <?= (int)$agent['open_tickets']; ?>
                                    </td>

                                    <!-- Pending -->
                                    <td class="text-center font-monospace text-warning fw-medium">
                                        <?= (int)$agent['pending_tickets']; ?>
                                    </td>

                                    <!-- Resolved -->
                                    <td class="text-center font-monospace text-success fw-medium">
                                        <?= (int)$agent['resolved_tickets']; ?>
                                    </td>

                                    <!-- Closed -->
                                    <td class="text-center font-monospace text-secondary fw-medium">
                                        <?= (int)$agent['closed_tickets']; ?>
                                    </td>

                                    <!-- Avg First Response -->
                                    <td class="small font-monospace text-dark">
                                        <?= format_duration($agent['avg_first_response_sec']); ?>
                                    </td>

                                    <!-- Avg Resolution -->
                                    <td class="small font-monospace text-dark">
                                        <?= format_duration($agent['avg_resolution_sec']); ?>
                                    </td>

                                    <!-- Action -->
                                    <td class="pe-3 text-end">
                                        <a href="<?= url('modules/tickets/index.php?agent_id=' . $agent['id']); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Assigned Tickets">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No agents found</h5>
                                    <p class="small mb-0">Create support agents in Agent Management to view performance metrics.</p>
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
