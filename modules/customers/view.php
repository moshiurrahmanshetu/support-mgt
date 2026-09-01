<?php
/**
 * Customer Management - Customer Details & Activity
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$customerId = (int)($_GET['id'] ?? 0);

if ($customerId <= 0) {
    flash('danger', 'Invalid customer reference.');
    redirect('modules/customers/index.php');
}

$db = get_db();

// 1. Fetch Customer Info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer account not found.');
    redirect('modules/customers/index.php');
}

// 2. Fetch Customer Ticket Statistics
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
$statStmt->execute([$customerId]);
$stats = $statStmt->fetch();

// 3. Fetch Recent Customer Tickets
$ticketStmt = $db->prepare("
    SELECT 
        t.*,
        d.name AS department_name,
        a.name AS agent_name
    FROM tickets t
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN users a ON t.assigned_to = a.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$ticketStmt->execute([$customerId]);
$recentTickets = $ticketStmt->fetchAll();

$pageTitle = 'Customer: ' . $customer['name'];
$pageHeader = 'Customer Details';
$activePage = 'customers';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/customers/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Customers
        </a>
    </div>

    <!-- Customer Header Profile Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= e(get_avatar_url($customer['avatar'])); ?>" 
                         alt="<?= e($customer['name']); ?>" 
                         class="avatar-img avatar-xl shadow-sm flex-shrink-0">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h4 mb-0 fw-bold"><?= e($customer['name']); ?></h2>
                            <span class="badge badge-status-<?= e($customer['status']); ?>"><?= ucfirst(e($customer['status'])); ?></span>
                        </div>
                        <p class="text-secondary-custom mb-1 small">
                            <i class="bi bi-envelope me-1"></i> <?= e($customer['email']); ?>
                            <?php if (!empty($customer['phone'])): ?>
                                <span class="mx-2">&bull;</span>
                                <i class="bi bi-telephone me-1"></i> <?= e($customer['phone']); ?>
                            <?php endif; ?>
                        </p>
                        <div class="text-muted fs-8">
                            <span><i class="bi bi-calendar-check me-1"></i> Joined <?= e(format_datetime($customer['created_at'], 'M d, Y')); ?></span>
                            <span class="mx-2">&bull;</span>
                            <span><i class="bi bi-clock me-1"></i> Last Login: <?= e(format_datetime($customer['last_login_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('modules/customers/edit.php?id=' . $customer['id']); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil"></i> Edit Details
                    </a>

                    <!-- Status Toggle Form -->
                    <form action="<?= url('modules/customers/status.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to change this customer status?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= $customer['id']; ?>">
                        <input type="hidden" name="status" value="<?= ($customer['status'] === STATUS_ACTIVE) ? STATUS_INACTIVE : STATUS_ACTIVE; ?>">
                        
                        <?php if ($customer['status'] === STATUS_ACTIVE): ?>
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
                <span class="text-secondary-custom fs-8 fw-semibold text-uppercase">Total Tickets</span>
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

    <!-- Recent Tickets by Customer -->
    <div class="card border shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-ticket-perforated me-2 text-primary"></i>Ticket History
            </h5>
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
                            <th class="py-2">Assigned Agent</th>
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
                                    <td>
                                        <?= !empty($ticket['department_name']) ? '<span class="badge bg-light text-dark border">' . e($ticket['department_name']) . '</span>' : '<span class="text-muted small fst-italic">None</span>'; ?>
                                    </td>
                                    <td><?= render_priority_badge($ticket['priority']); ?></td>
                                    <td><?= render_status_badge($ticket['status']); ?></td>
                                    <td class="small">
                                        <?= !empty($ticket['agent_name']) ? e($ticket['agent_name']) : '<span class="text-muted fst-italic">Unassigned</span>'; ?>
                                    </td>
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
                                    <span class="small">This customer has not created any support tickets yet.</span>
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
