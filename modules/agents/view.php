<?php
/**
 * Agent Management - Agent Profile & Workload Details
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$agentId = (int)($_GET['id'] ?? 0);

if ($agentId <= 0) {
    flash('danger', 'Invalid agent reference.');
    redirect('modules/agents/index.php');
}

$db = get_db();

// 1. Fetch Agent Info with Department
$stmt = $db->prepare("
    SELECT u.*, d.name AS department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ? AND u.role = 'agent'
    LIMIT 1
");
$stmt->execute([$agentId]);
$agent = $stmt->fetch();

if (!$agent) {
    flash('danger', 'Support agent account not found.');
    redirect('modules/agents/index.php');
}

// 2. Fetch Agent Assigned Ticket Statistics
$statStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_count
    FROM tickets
    WHERE assigned_to = ?
");
$statStmt->execute([$agentId]);
$stats = $statStmt->fetch();

// 3. Fetch Recent Assigned Tickets
$ticketStmt = $db->prepare("
    SELECT 
        t.*,
        u.name AS customer_name,
        d.name AS department_name
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.assigned_to = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$ticketStmt->execute([$agentId]);
$recentTickets = $ticketStmt->fetchAll();

$pageTitle = 'Agent: ' . $agent['name'];
$pageHeader = 'Agent Profile';
$activePage = 'agents';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/agents/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Agents
        </a>
    </div>

    <!-- Agent Header Profile Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= e(get_avatar_url($agent['avatar'])); ?>" 
                         alt="<?= e($agent['name']); ?>" 
                         class="avatar-img avatar-xl shadow-sm flex-shrink-0">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h4 mb-0 fw-bold"><?= e($agent['name']); ?></h2>
                            <span class="badge badge-status-<?= e($agent['status']); ?>"><?= ucfirst(e($agent['status'])); ?></span>
                            <?php if (!empty($agent['department_name'])): ?>
                                <span class="badge bg-light text-dark border"><i class="bi bi-building me-1 text-secondary"></i><?= e($agent['department_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-secondary-custom mb-1 small">
                            <i class="bi bi-envelope me-1"></i> <?= e($agent['email']); ?>
                            <?php if (!empty($agent['phone'])): ?>
                                <span class="mx-2">&bull;</span>
                                <i class="bi bi-telephone me-1"></i> <?= e($agent['phone']); ?>
                            <?php endif; ?>
                        </p>
                        <div class="text-muted fs-8">
                            <span><i class="bi bi-calendar-check me-1"></i> Joined <?= e(format_datetime($agent['created_at'], 'M d, Y')); ?></span>
                            <span class="mx-2">&bull;</span>
                            <span><i class="bi bi-clock me-1"></i> Last Login: <?= e(format_datetime($agent['last_login_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('modules/agents/edit.php?id=' . $agent['id']); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil"></i> Edit Details
                    </a>

                    <!-- Status Toggle Form -->
                    <form action="<?= url('modules/agents/status.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to change this agent status?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= $agent['id']; ?>">
                        <input type="hidden" name="status" value="<?= ($agent['status'] === STATUS_ACTIVE) ? STATUS_INACTIVE : STATUS_ACTIVE; ?>">
                        
                        <?php if ($agent['status'] === STATUS_ACTIVE): ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-slash-circle"></i> Deactivate
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-check-circle"></i> Activate
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Ticket Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100 p-3">
                <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Assigned Total</span>
                <div class="h3 fw-bold mb-0 text-dark"><?= (int)$stats['total']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100 p-3">
                <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Open</span>
                <div class="h3 fw-bold mb-0 text-primary"><?= (int)$stats['open_count']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100 p-3">
                <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">In Progress</span>
                <div class="h3 fw-bold mb-0 text-info"><?= (int)$stats['in_progress_count']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100 p-3">
                <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Pending</span>
                <div class="h3 fw-bold mb-0 text-warning"><?= (int)$stats['pending_count']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100 p-3">
                <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Resolved</span>
                <div class="h3 fw-bold mb-0 text-success"><?= (int)$stats['resolved_count']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100 p-3">
                <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Closed</span>
                <div class="h3 fw-bold mb-0 text-secondary"><?= (int)$stats['closed_count']; ?></div>
            </div>
        </div>
    </div>

    <!-- Recent Assigned Tickets -->
    <div class="card border shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-ticket-perforated me-2 text-primary"></i>Assigned Ticket History
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-2">Ticket #</th>
                            <th class="py-2">Subject</th>
                            <th class="py-2">Customer</th>
                            <th class="py-2">Department</th>
                            <th class="py-2">Priority</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Created Date</th>
                            <th class="pe-3 py-2 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentTickets)): ?>
                            <?php foreach ($recentTickets as $ticket): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">
                                        <a href="<?= url('modules/tickets/view.php?id=' . $ticket['id']); ?>" class="text-decoration-none">
                                            <?= e($ticket['ticket_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?= url('modules/tickets/view.php?id=' . $ticket['id']); ?>" class="text-dark fw-semibold text-decoration-none">
                                            <?= e($ticket['subject']); ?>
                                        </a>
                                    </td>
                                    <td class="small fw-medium"><?= e($ticket['customer_name']); ?></td>
                                    <td>
                                        <?= !empty($ticket['department_name']) ? '<span class="badge bg-light text-dark border">' . e($ticket['department_name']) . '</span>' : '<span class="text-muted small fst-italic">None</span>'; ?>
                                    </td>
                                    <td><?= render_priority_badge($ticket['priority']); ?></td>
                                    <td><?= render_status_badge($ticket['status']); ?></td>
                                    <td class="text-muted fs-8"><?= e(format_datetime($ticket['created_at'])); ?></td>
                                    <td class="pe-3 text-end">
                                        <a href="<?= url('modules/tickets/view.php?id=' . $ticket['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <span class="small">No tickets have been assigned to this agent yet.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
