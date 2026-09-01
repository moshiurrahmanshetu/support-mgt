<?php
/**
 * Main Application Dashboard - Phase 03 Multi-Module Analytics
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/reports.php';

// Protect Dashboard - requires authentication
require_login();

$user = current_user();
$db = get_db();

// Parse Dashboard Date Range (Default: Last 7 Days for quick dashboard view)
$dashDateRange = get_report_date_range($_GET, 'last_7_days');
$dashFrom = $dashDateRange['from'];
$dashTo = $dashDateRange['to'];

// ----------------------------------------------------
// Real Dashboard Analytics Queries
// ----------------------------------------------------
$adminStats = [
    'total_customers'   => 0,
    'active_customers'  => 0,
    'total_agents'      => 0,
    'active_agents'     => 0,
    'total_departments' => 0,
    'active_departments'=> 0,
    'total_tickets'     => 0,
    'open_tickets'      => 0,
    'avg_first_response'=> null,
    'avg_resolution'    => null
];

$ticketStats = [
    'total'       => 0,
    'open'        => 0,
    'in_progress' => 0,
    'pending'     => 0,
    'resolved'    => 0,
    'closed'      => 0
];

$agentPerformance = [
    'avg_first_response' => null,
    'avg_resolution'     => null
];

if ($user['role'] === ROLE_ADMIN) {
    // Admin Module Overview
    $adminOverviewStmt = $db->query("
        SELECT
            (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS total_users,
            (SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL) AS active_users,
            (SELECT COUNT(*) FROM users WHERE role = 'customer' AND deleted_at IS NULL) AS total_customers,
            (SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'active' AND deleted_at IS NULL) AS active_customers,
            (SELECT COUNT(*) FROM users WHERE role IN ('agent', 'support_agent') AND deleted_at IS NULL) AS total_agents,
            (SELECT COUNT(*) FROM users WHERE role IN ('agent', 'support_agent') AND status = 'active' AND deleted_at IS NULL) AS active_agents,
            (SELECT COUNT(*) FROM users WHERE role IN ('manager', 'support_manager') AND deleted_at IS NULL) AS total_managers,
            (SELECT COUNT(*) FROM departments) AS total_departments,
            (SELECT COUNT(*) FROM departments WHERE status = 'active') AS active_departments,
            (SELECT COUNT(*) FROM tickets) AS total_tickets,
            (SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'in_progress', 'pending')) AS open_tickets,
            (SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL) AS unassigned_tickets,
            (SELECT COUNT(*) FROM knowledge_base_articles) AS total_articles,
            (SELECT COUNT(*) FROM knowledge_base_articles WHERE status = 'published') AS published_articles,
            (SELECT COUNT(*) FROM knowledge_base_articles WHERE status = 'draft') AS draft_articles,
            (SELECT COUNT(*) FROM knowledge_base_categories) AS total_categories,
            (SELECT COUNT(*) FROM faqs WHERE status = 'active') AS active_faqs
    ");
    $adminStats = $adminOverviewStmt->fetch() ?: $adminStats;

    // Period Speed Performance (Admin)
    $speedStmt = $db->prepare("
        SELECT 
            AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) END) AS avg_first_response,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) END) AS avg_resolution
        FROM tickets
        WHERE created_at BETWEEN ? AND ?
    ");
    $speedStmt->execute([$dashFrom, $dashTo]);
    $speedRow = $speedStmt->fetch();
    if ($speedRow) {
        $adminStats['avg_first_response'] = $speedRow['avg_first_response'];
        $adminStats['avg_resolution'] = $speedRow['avg_resolution'];
    }

    // Detailed Ticket Breakdown (Selected Period)
    $statStmt = $db->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_count
        FROM tickets
        WHERE created_at BETWEEN ? AND ?
    ");
    $statStmt->execute([$dashFrom, $dashTo]);
    $row = $statStmt->fetch();
    if ($row) {
        $ticketStats['total']       = (int)($row['total'] ?? 0);
        $ticketStats['open']        = (int)($row['open_count'] ?? 0);
        $ticketStats['in_progress'] = (int)($row['in_progress_count'] ?? 0);
        $ticketStats['pending']     = (int)($row['pending_count'] ?? 0);
        $ticketStats['resolved']    = (int)($row['resolved_count'] ?? 0);
        $ticketStats['closed']      = (int)($row['closed_count'] ?? 0);
    }

    // Recent Tickets (Global)
    $recentStmt = $db->query("
        SELECT t.*, u.name AS customer_name, d.name AS department_name, a.name AS agent_name
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users a ON t.assigned_to = a.id
        ORDER BY t.created_at DESC
        LIMIT 6
    ");
    $recentTickets = $recentStmt->fetchAll();

} elseif ($user['role'] === ROLE_AGENT) {
    // Agent: Assigned stats
    $statStmt = $db->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_count,
            AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) END) AS avg_first_resp,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) END) AS avg_res
        FROM tickets
        WHERE assigned_to = ?
    ");
    $statStmt->execute([$user['id']]);
    $row = $statStmt->fetch();
    if ($row) {
        $ticketStats['total']       = (int)($row['total'] ?? 0);
        $ticketStats['open']        = (int)($row['open_count'] ?? 0);
        $ticketStats['in_progress'] = (int)($row['in_progress_count'] ?? 0);
        $ticketStats['pending']     = (int)($row['pending_count'] ?? 0);
        $ticketStats['resolved']    = (int)($row['resolved_count'] ?? 0);
        $ticketStats['closed']      = (int)($row['closed_count'] ?? 0);
        $agentPerformance['avg_first_response'] = $row['avg_first_resp'];
        $agentPerformance['avg_resolution'] = $row['avg_res'];
    }

    // Recent Assigned Tickets
    $recentStmt = $db->prepare("
        SELECT t.*, u.name AS customer_name, d.name AS department_name, a.name AS agent_name
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users a ON t.assigned_to = a.id
        WHERE t.assigned_to = ?
        ORDER BY t.created_at DESC
        LIMIT 6
    ");
    $recentStmt->execute([$user['id']]);
    $recentTickets = $recentStmt->fetchAll();

} else {
    // Customer: Own ticket stats
    $statStmt = $db->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_count
        FROM tickets
        WHERE user_id = ?
    ");
    $statStmt->execute([$user['id']]);
    $row = $statStmt->fetch();
    if ($row) {
        $ticketStats['total']       = (int)($row['total'] ?? 0);
        $ticketStats['open']        = (int)($row['open_count'] ?? 0);
        $ticketStats['in_progress'] = (int)($row['in_progress_count'] ?? 0);
        $ticketStats['pending']     = (int)($row['pending_count'] ?? 0);
        $ticketStats['resolved']    = (int)($row['resolved_count'] ?? 0);
        $ticketStats['closed']      = (int)($row['closed_count'] ?? 0);
    }

    // Recent Customer Tickets
    $recentStmt = $db->prepare("
        SELECT t.*, u.name AS customer_name, d.name AS department_name, a.name AS agent_name
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users a ON t.assigned_to = a.id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT 6
    ");
    $recentStmt->execute([$user['id']]);
    $recentTickets = $recentStmt->fetchAll();
}

$pageTitle = 'Dashboard';
$pageHeader = 'Dashboard Overview';
$activePage = 'index.php';

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Welcome Header Banner -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: #ffffff;">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= e(get_avatar_url($user['avatar'] ?? null)); ?>" 
                         alt="<?= e($user['name']); ?>" 
                         class="avatar-img avatar-lg flex-shrink-0">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h4 mb-0 fw-bold"><?= e($user['name']); ?></h2>
                            <span class="badge badge-role-<?= e($user['role']); ?>"><?= e($user['role']); ?></span>
                        </div>
                        <p class="text-secondary-custom mb-0 small">
                            <i class="bi bi-envelope me-1"></i> <?= e($user['email']); ?>
                            <?php if (!empty($user['phone'])): ?>
                                <span class="mx-2">&bull;</span>
                                <i class="bi bi-telephone me-1"></i> <?= e($user['phone']); ?>
                            <?php endif; ?>
                            <span class="mx-2">&bull;</span>
                            <i class="bi bi-clock me-1"></i> Last login: <?= e(format_datetime($user['last_login_at'])); ?>
                        </p>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <?php 
                        $dashUnreadCount = get_unread_notifications_count($user['id']);
                        if ($dashUnreadCount > 0):
                    ?>
                        <a href="<?= url('modules/notifications/index.php?filter=unread'); ?>" class="btn btn-warning btn-sm text-dark fw-medium">
                            <i class="bi bi-bell-fill"></i> <?= $dashUnreadCount; ?> Unread <?= ($dashUnreadCount === 1) ? 'Notification' : 'Notifications'; ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($user['role'] === ROLE_ADMIN): ?>
                        <a href="<?= url('modules/reports/index.php'); ?>" class="btn btn-success btn-sm">
                            <i class="bi bi-graph-up"></i> Reports & Analytics
                        </a>
                    <?php endif; ?>
                    <a href="<?= url('modules/tickets/create.php'); ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> Create Ticket
                    </a>
                    <a href="<?= url('modules/tickets/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-ticket-perforated"></i> View All Tickets
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Master Metrics (Admin Only) -->
    <?php if ($user['role'] === ROLE_ADMIN): ?>
        <!-- Quick Period Switcher for Dashboard -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="fs-7 fw-bold text-dark text-uppercase">
                <i class="bi bi-speedometer me-1 text-primary"></i>Executive Overview
            </span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Dashboard Date Filter">
                <a href="<?= url('index.php?date_range=today'); ?>" class="btn <?= ($dashDateRange['preset'] === 'today') ? 'btn-primary' : 'btn-outline-secondary'; ?>">Today</a>
                <a href="<?= url('index.php?date_range=last_7_days'); ?>" class="btn <?= ($dashDateRange['preset'] === 'last_7_days') ? 'btn-primary' : 'btn-outline-secondary'; ?>">Last 7 Days</a>
                <a href="<?= url('index.php?date_range=last_30_days'); ?>" class="btn <?= ($dashDateRange['preset'] === 'last_30_days') ? 'btn-primary' : 'btn-outline-secondary'; ?>">Last 30 Days</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <!-- Customers -->
            <div class="col-6 col-md-3">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Customers</span>
                            <div class="p-2 rounded bg-light text-primary">
                                <i class="bi bi-people fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= (int)$adminStats['total_customers']; ?></div>
                        <span class="text-muted fs-8">
                            <span class="text-success fw-medium"><?= (int)$adminStats['active_customers']; ?> Active</span> accounts
                        </span>
                    </div>
                </div>
            </div>

            <!-- Agents -->
            <div class="col-6 col-md-3">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Support Agents</span>
                            <div class="p-2 rounded bg-light text-info">
                                <i class="bi bi-headset fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= (int)$adminStats['total_agents']; ?></div>
                        <span class="text-muted fs-8">
                            <span class="text-success fw-medium"><?= (int)$adminStats['active_agents']; ?> Active</span> staff
                        </span>
                    </div>
                </div>
            </div>

            <!-- Avg First Response -->
            <div class="col-6 col-md-3">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Avg First Response</span>
                            <div class="p-2 rounded bg-light text-info">
                                <i class="bi bi-stopwatch fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= format_duration($adminStats['avg_first_response']); ?></div>
                        <span class="text-muted fs-8">
                            <a href="<?= url('modules/reports/response_time.php'); ?>" class="text-primary text-decoration-none fw-medium">View Speed Report</a>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Avg Resolution -->
            <div class="col-6 col-md-3">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Avg Resolution</span>
                            <div class="p-2 rounded bg-light text-success">
                                <i class="bi bi-check2-circle fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= format_duration($adminStats['avg_resolution']); ?></div>
                        <span class="text-muted fs-8">
                            <a href="<?= url('modules/reports/resolution_time.php'); ?>" class="text-success text-decoration-none fw-medium">View Resolution Report</a>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System & Support Overview Row -->
        <div class="row g-3 mb-4">
            <!-- Departments -->
            <div class="col-6 col-md-3">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Departments</span>
                            <div class="p-2 rounded bg-light text-warning">
                                <i class="bi bi-building fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= (int)$adminStats['total_departments']; ?></div>
                        <span class="text-muted fs-8">
                            <span class="text-success fw-medium"><?= (int)$adminStats['active_departments']; ?> Active</span> teams
                        </span>
                    </div>
                </div>
            </div>

            <!-- Unassigned Tickets -->
            <div class="col-6 col-md-3">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Unassigned Tickets</span>
                            <div class="p-2 rounded bg-light text-danger">
                                <i class="bi bi-person-x fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= (int)($adminStats['unassigned_tickets'] ?? 0); ?></div>
                        <span class="text-muted fs-8">
                            <a href="<?= url('modules/tickets/index.php?agent_id=-1'); ?>" class="text-danger fw-medium text-decoration-none">Assign Agent &rarr;</a>
                        </span>
                    </div>
                </div>
            </div>

            <!-- KB Articles -->
            <div class="col-6 col-md-3">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Help Articles</span>
                            <div class="p-2 rounded bg-light text-primary">
                                <i class="bi bi-file-earmark-text fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= (int)$adminStats['total_articles']; ?></div>
                        <span class="text-muted fs-8">
                            <span class="text-success fw-medium"><?= (int)$adminStats['published_articles']; ?> Published</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Active FAQs -->
            <div class="col-6 col-md-3">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Active FAQs</span>
                            <div class="p-2 rounded bg-light text-info">
                                <i class="bi bi-question-circle fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= (int)$adminStats['active_faqs']; ?></div>
                        <span class="text-muted fs-8">Public help items</span>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($user['role'] === ROLE_AGENT): ?>
        <!-- Agent Performance Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">My Avg First Response</span>
                            <div class="p-2 rounded bg-light text-primary">
                                <i class="bi bi-stopwatch fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= format_duration($agentPerformance['avg_first_response']); ?></div>
                        <span class="text-muted fs-8">Average speed for first staff reply</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">My Avg Resolution Speed</span>
                            <div class="p-2 rounded bg-light text-success">
                                <i class="bi bi-check2-circle fs-5"></i>
                            </div>
                        </div>
                        <div class="h3 fw-bold mb-0 text-dark"><?= format_duration($agentPerformance['avg_resolution']); ?></div>
                        <span class="text-muted fs-8">Average time to solve assigned tickets</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Live Ticket Breakdown Status Cards -->
    <div class="row g-3 mb-4">
        <!-- Total -->
        <div class="col-6 col-lg-2 col-md-4">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Total</span>
                        <i class="bi bi-ticket-perforated text-primary fs-5"></i>
                    </div>
                    <div class="h3 fw-bold mb-0 text-dark"><?= $ticketStats['total']; ?></div>
                    <span class="text-muted fs-8">All inquiries</span>
                </div>
            </div>
        </div>

        <!-- Open -->
        <div class="col-6 col-lg-2 col-md-4">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Open</span>
                        <i class="bi bi-record-circle text-primary fs-5"></i>
                    </div>
                    <div class="h3 fw-bold mb-0 text-primary"><?= $ticketStats['open']; ?></div>
                    <span class="text-muted fs-8">Awaiting review</span>
                </div>
            </div>
        </div>

        <!-- In Progress -->
        <div class="col-6 col-lg-2 col-md-4">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">In Progress</span>
                        <i class="bi bi-arrow-repeat text-info fs-5"></i>
                    </div>
                    <div class="h3 fw-bold mb-0" style="color: #0891b2;"><?= $ticketStats['in_progress']; ?></div>
                    <span class="text-muted fs-8">Active investigation</span>
                </div>
            </div>
        </div>

        <!-- Pending -->
        <div class="col-6 col-lg-2 col-md-4">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Pending</span>
                        <i class="bi bi-hourglass-split text-warning fs-5"></i>
                    </div>
                    <div class="h3 fw-bold mb-0" style="color: #d97706;"><?= $ticketStats['pending']; ?></div>
                    <span class="text-muted fs-8">Waiting on user</span>
                </div>
            </div>
        </div>

        <!-- Resolved -->
        <div class="col-6 col-lg-2 col-md-4">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Resolved</span>
                        <i class="bi bi-check2-circle text-success fs-5"></i>
                    </div>
                    <div class="h3 fw-bold mb-0 text-success"><?= $ticketStats['resolved']; ?></div>
                    <span class="text-muted fs-8">Issue solved</span>
                </div>
            </div>
        </div>

        <!-- Closed -->
        <div class="col-6 col-lg-2 col-md-4">
            <div class="card h-100 border shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Closed</span>
                        <i class="bi bi-lock text-secondary fs-5"></i>
                    </div>
                    <div class="h3 fw-bold mb-0 text-secondary"><?= $ticketStats['closed']; ?></div>
                    <span class="text-muted fs-8">Archived</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tickets Table & Account Shortcuts -->
    <div class="row g-4">
        <!-- Recent Tickets Table -->
        <div class="col-12 col-xl-8">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-clock-history me-2 text-primary"></i>Recent Support Inquiries
                    </h5>
                    <a href="<?= url('modules/tickets/index.php'); ?>" class="btn btn-sm btn-outline-secondary">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="text-secondary-custom fs-7 border-bottom">
                                    <th class="ps-3 py-2">Ticket #</th>
                                    <th class="py-2">Subject</th>
                                    <th class="py-2">Department</th>
                                    <th class="py-2">Priority</th>
                                    <th class="py-2">Status</th>
                                    <th class="pe-3 py-2 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentTickets)): ?>
                                    <?php foreach ($recentTickets as $rTicket): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold">
                                                <a href="<?= url('modules/tickets/view.php?id=' . $rTicket['id']); ?>" class="text-decoration-none">
                                                    <?= e($rTicket['ticket_number']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 260px;">
                                                    <?= e($rTicket['subject']); ?>
                                                </div>
                                                <div class="text-muted fs-8">
                                                    <?= e(format_datetime($rTicket['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?= !empty($rTicket['department_name']) ? '<span class="badge bg-light text-dark border">' . e($rTicket['department_name']) . '</span>' : '<span class="text-muted small fst-italic">General</span>'; ?>
                                            </td>
                                            <td><?= render_priority_badge($rTicket['priority']); ?></td>
                                            <td><?= render_status_badge($rTicket['status']); ?></td>
                                            <td class="pe-3 text-end">
                                                <a href="<?= url('modules/tickets/view.php?id=' . $rTicket['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2">
                                                    <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                                            <span class="small">No recent support tickets found.</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Shortcuts -->
        <div class="col-12 col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-lightning-charge me-2 text-primary"></i>Management Shortcuts
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if ($user['role'] === ROLE_ADMIN): ?>
                            <a href="<?= url('modules/customers/index.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-2 rounded bg-light text-primary">
                                        <i class="bi bi-people fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">Customer Accounts</div>
                                        <div class="small text-muted">Manage registered customers and view tickets</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>

                            <a href="<?= url('modules/agents/index.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-2 rounded bg-light text-info">
                                        <i class="bi bi-headset fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">Support Agents</div>
                                        <div class="small text-muted">Manage agents and department assignments</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>

                            <a href="<?= url('modules/departments/index.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-2 rounded bg-light text-warning">
                                        <i class="bi bi-building fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">Support Departments</div>
                                        <div class="small text-muted">Configure teams and inquiry categories</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>

                            <a href="<?= url('modules/tags/index.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-2 rounded bg-light text-success">
                                        <i class="bi bi-tags fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">Ticket Tags</div>
                                        <div class="small text-muted">Manage tags and color badges</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>

                            <a href="<?= url('modules/activity_logs/index.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-2 rounded bg-light text-secondary">
                                        <i class="bi bi-clock-history fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">System Activity Logs</div>
                                        <div class="small text-muted">Audit system actions and auth events</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>

                            <a href="<?= url('modules/settings/index.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-2 rounded bg-light text-dark">
                                        <i class="bi bi-gear fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">System Settings</div>
                                        <div class="small text-muted">Configure SMTP, timezones, and rules</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                        <?php endif; ?>

                        <a href="<?= url('modules/knowledge_base/index.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded bg-light text-primary">
                                    <i class="bi bi-book fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">Support & Help Center</div>
                                    <div class="small text-muted">Browse knowledge base articles and FAQs</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>

                        <a href="<?= url('modules/notifications/index.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded bg-light text-warning">
                                    <i class="bi bi-bell fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">Notifications</div>
                                    <div class="small text-muted">View all in-app updates and alerts</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>

                        <a href="<?= url('modules/tickets/create.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded bg-light text-success">
                                    <i class="bi bi-plus-circle fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">Submit New Ticket</div>
                                    <div class="small text-muted">Create a support inquiry with attachments</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>

                        <a href="<?= url('modules/profile/index.php'); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded bg-light text-secondary">
                                    <i class="bi bi-person-circle fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">My Profile</div>
                                    <div class="small text-muted">Update details, photo, and password</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
