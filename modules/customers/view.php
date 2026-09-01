<?php
/**
 * Customer Management - Customer Details & Activity (Phase 08 Completion)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';

// Strict Authorization Guard
require_permission('customers.view');

$customerId = (int)($_GET['id'] ?? 0);

if ($customerId <= 0) {
    flash('danger', 'Invalid customer reference.');
    redirect('modules/customers/index.php');
}

$db = get_db();
$currentUser = current_user();

// 1. Fetch Customer Record
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer account not found.');
    redirect('modules/customers/index.php');
}

$isDeleted = !empty($customer['deleted_at']);
$isActive = ($customer['status'] === STATUS_ACTIVE && !$isDeleted);

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
$stats = $statStmt->fetch() ?: [
    'total' => 0, 'open_count' => 0, 'in_progress_count' => 0,
    'pending_count' => 0, 'resolved_count' => 0, 'closed_count' => 0
];

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

// 4. Fetch Customer System Activity
$actStmt = $db->prepare("
    SELECT * FROM activity_logs 
    WHERE (user_id = ? OR (reference_type = 'user' AND reference_id = ?))
    ORDER BY created_at DESC 
    LIMIT 8
");
$actStmt->execute([$customerId, $customerId]);
$recentActivities = $actStmt->fetchAll();

$pageTitle = 'Customer: ' . $customer['name'];
$pageHeader = 'Customer Account Profile';
$activePage = 'customers';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Back Link & Action Buttons -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= url('modules/customers/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Customers Directory
                </a>
            </div>
            <h1 class="h4 fw-bold mb-0">Customer: <?= e($customer['name']); ?></h1>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if (!$isDeleted && has_permission('customers.edit')): ?>
                <a href="<?= url('modules/customers/edit.php?id=' . $customer['id']); ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Customer
                </a>

                <!-- Status Toggle Form -->
                <form action="<?= url('modules/customers/status.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to <?= $isActive ? 'deactivate' : 'activate'; ?> this customer?');">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="id" value="<?= $customer['id']; ?>">
                    <input type="hidden" name="status" value="<?= $isActive ? STATUS_INACTIVE : STATUS_ACTIVE; ?>">
                    <button type="submit" class="btn btn-outline-<?= $isActive ? 'warning' : 'success'; ?> btn-sm">
                        <i class="bi bi-<?= $isActive ? 'pause-circle' : 'play-circle'; ?> me-1"></i> <?= $isActive ? 'Deactivate' : 'Activate'; ?>
                    </button>
                </form>
            <?php elseif ($isDeleted && has_permission('customers.edit')): ?>
                <!-- Restore Form -->
                <form action="<?= url('modules/customers/restore.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Restore this deleted customer account?');">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="id" value="<?= $customer['id']; ?>">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restore Customer
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!$isDeleted && has_permission('customers.delete')): ?>
                <!-- Delete Form -->
                <form action="<?= url('modules/customers/delete.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this customer? All historical ticket records will be preserved.');">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="id" value="<?= $customer['id']; ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Delete Customer
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isDeleted): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            This customer account was deleted on <?= e(format_datetime($customer['deleted_at'])); ?>. All historical tickets and conversations remain preserved.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Profile Info Card -->
        <div class="col-12 col-lg-4">
            <div class="card border shadow-sm h-100">
                <div class="card-body p-4 text-center">
                    <img src="<?= e(get_avatar_url($customer['avatar'])); ?>" 
                         alt="<?= e($customer['name']); ?>" 
                         class="avatar-img avatar-xl shadow-sm mb-3">

                    <h2 class="h5 fw-bold text-dark mb-1"><?= e($customer['name']); ?></h2>
                    <p class="text-muted small mb-2"><?= e($customer['email']); ?></p>

                    <div class="mb-3">
                        <span class="badge badge-role-customer me-1">Customer</span>
                        <?php if ($isDeleted): ?>
                            <span class="badge bg-danger">Deleted</span>
                        <?php elseif ($isActive): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>

                    <div class="border-top pt-3 text-start small">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Customer ID:</span>
                            <span class="fw-bold font-monospace">#<?= $customer['id']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Phone Number:</span>
                            <span class="fw-semibold font-monospace"><?= e($customer['phone'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Account Registered:</span>
                            <span class="fw-semibold"><?= e(format_datetime($customer['created_at'], 'M d, Y')); ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span class="text-muted">Last Signed In:</span>
                            <span class="fw-semibold"><?= !empty($customer['last_login_at']) ? e(format_datetime($customer['last_login_at'])) : 'Never'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Ticket Statistics & Recent History -->
        <div class="col-12 col-lg-8">
            <!-- Ticket Metrics Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4">
                    <div class="card border shadow-sm p-3 text-center">
                        <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Total Inquiries</div>
                        <div class="h3 fw-bold text-dark mb-0"><?= (int)$stats['total']; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card border shadow-sm p-3 text-center">
                        <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Open & In Progress</div>
                        <div class="h3 fw-bold text-primary mb-0"><?= (int)$stats['open_count'] + (int)$stats['in_progress_count'] + (int)$stats['pending_count']; ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border shadow-sm p-3 text-center">
                        <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Resolved & Closed</div>
                        <div class="h3 fw-bold text-success mb-0"><?= (int)$stats['resolved_count'] + (int)$stats['closed_count']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Recent Customer Tickets -->
            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h3 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-ticket-perforated me-1 text-primary"></i>Customer Ticket History
                    </h3>
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
                                    <?php foreach ($recentTickets as $t): ?>
                                        <tr>
                                            <td class="ps-3 font-monospace fw-bold">
                                                <a href="<?= url('modules/tickets/view.php?id=' . $t['id']); ?>" class="text-decoration-none">
                                                    <?= e($t['ticket_number']); ?>
                                                </a>
                                            </td>
                                            <td class="text-truncate fw-semibold text-dark" style="max-width: 220px;">
                                                <?= e($t['subject']); ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= e($t['department_name'] ?: 'General Support'); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-priority-<?= e($t['priority']); ?>">
                                                    <?= ucfirst(e($t['priority'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-status-<?= e($t['status']); ?>">
                                                    <?= ucfirst(e(str_replace('_', ' ', $t['status']))); ?>
                                                </span>
                                            </td>
                                            <td class="pe-3 text-end">
                                                <a href="<?= url('modules/tickets/view.php?id=' . $t['id']); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                                    <i class="bi bi-arrow-right"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted small">No tickets submitted by this customer yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Audit Logs -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-clock-history me-1 text-primary"></i>Customer Activity History
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $act): ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-light text-dark border font-monospace fs-8"><?= e($act['action']); ?></span>
                                        <span class="text-muted fs-8"><?= e(format_datetime($act['created_at'])); ?></span>
                                    </div>
                                    <p class="mb-0 small text-dark"><?= e($act['description']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted small">No recorded activity logs for this customer account.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
