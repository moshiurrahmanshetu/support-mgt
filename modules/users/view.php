<?php
/**
 * User Management - View User Details (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';

// Strict Authorization Guard
require_permission('users.view');

$db = get_db();
$userId = (int)($_GET['id'] ?? 0);

// Fetch user record
$stmt = $db->prepare("
    SELECT u.*, ur.role_id, r.name AS role_name, r.slug AS role_slug, r.description AS role_description, d.name AS department_name
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ? AND u.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'User account not found or has been deleted.');
    redirect('modules/users/index.php');
}

// User Ticket Statistics
$tktStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets
    FROM tickets
    WHERE user_id = ? OR assigned_to = ?
");
$tktStmt->execute([$userId, $userId]);
$ticketStats = $tktStmt->fetch() ?: [];

// Recent Tickets
$recentTktStmt = $db->prepare("
    SELECT t.*, d.name AS department_name
    FROM tickets t
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.user_id = ? OR t.assigned_to = ?
    ORDER BY t.created_at DESC
    LIMIT 5
");
$recentTktStmt->execute([$userId, $userId]);
$recentTickets = $recentTktStmt->fetchAll();

// Recent Activity Logs
$actStmt = $db->prepare("
    SELECT * FROM activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$actStmt->execute([$userId]);
$recentActivities = $actStmt->fetchAll();

$displayRole = $user['role_name'] ?: ucfirst(str_replace('_', ' ', $user['role']));
$roleSlug = strtolower($user['role_slug'] ?: $user['role']);
$isActive = ($user['status'] === STATUS_ACTIVE);

$pageTitle = 'User Profile: ' . $user['name'];
$pageHeader = 'User Account Overview';
$activePage = 'users';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= url('modules/users/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Users Directory
                </a>
            </div>
            <h1 class="h4 fw-bold mb-0">User Account: <?= e($user['name']); ?></h1>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if (has_permission('users.edit')): ?>
                <a href="<?= url('modules/users/edit.php?id=' . $user['id']); ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit User
                </a>
                <a href="<?= url('modules/users/roles.php?id=' . $user['id']); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-shield-check me-1"></i> Assign Role
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Profile Card -->
        <div class="col-12 col-lg-4">
            <div class="card border shadow-sm h-100">
                <div class="card-body p-4 text-center">
                    <img src="<?= e(get_avatar_url($user['avatar'] ?? null)); ?>" alt="<?= e($user['name']); ?>" class="avatar-img avatar-xl mb-3 shadow-sm">
                    <h2 class="h5 fw-bold mb-1 text-dark"><?= e($user['name']); ?></h2>
                    <p class="text-muted small mb-2"><?= e($user['email']); ?></p>
                    <div class="mb-3">
                        <span class="badge badge-role-<?= e($roleSlug); ?> me-1"><?= e($displayRole); ?></span>
                        <span class="badge bg-<?= $isActive ? 'success' : 'secondary'; ?>"><?= e(ucfirst($user['status'])); ?></span>
                    </div>

                    <div class="border-top pt-3 text-start small">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">User ID:</span>
                            <span class="fw-semibold font-monospace">#<?= $user['id']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Phone:</span>
                            <span class="fw-semibold font-monospace"><?= e($user['phone'] ?: 'None'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Department:</span>
                            <span class="fw-semibold"><?= e($user['department_name'] ?: 'None'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Registered:</span>
                            <span class="fw-semibold"><?= e(format_datetime($user['created_at'], 'M d, Y')); ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Last Login:</span>
                            <span class="fw-semibold"><?= !empty($user['last_login_at']) ? e(format_datetime($user['last_login_at'])) : 'Never'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Ticket Metrics & Recent Activity -->
        <div class="col-12 col-lg-8">
            <!-- Ticket Metrics Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4">
                    <div class="card border shadow-sm p-3 text-center">
                        <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Total Inquiries</div>
                        <div class="h3 fw-bold text-dark mb-0"><?= (int)$ticketStats['total_tickets']; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card border shadow-sm p-3 text-center">
                        <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Open / Pending</div>
                        <div class="h3 fw-bold text-primary mb-0"><?= (int)$ticketStats['open_tickets'] + (int)$ticketStats['pending_tickets']; ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border shadow-sm p-3 text-center">
                        <div class="fs-8 fw-semibold text-secondary-custom text-uppercase">Resolved / Closed</div>
                        <div class="h3 fw-bold text-success mb-0"><?= (int)$ticketStats['resolved_tickets'] + (int)$ticketStats['closed_tickets']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Recent Tickets -->
            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h3 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-ticket-perforated me-1 text-primary"></i>Related Tickets
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="text-secondary-custom fs-7 border-bottom">
                                    <th class="ps-3 py-2">Ticket #</th>
                                    <th class="py-2">Subject</th>
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
                                            <td class="text-truncate fw-semibold text-dark" style="max-width: 280px;">
                                                <?= e($t['subject']); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-status-<?= e($t['status']); ?>">
                                                    <?= e(ucfirst(str_replace('_', ' ', $t['status']))); ?>
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
                                        <td colspan="4" class="text-center py-4 text-muted small">No tickets associated with this user.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-clock-history me-1 text-primary"></i>Recent System Activity
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
                            <div class="p-4 text-center text-muted small">No recorded activity for this user.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
