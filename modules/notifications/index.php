<?php
/**
 * Notification Center - User In-App Notifications Inbox (support-mgt Phase 05)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_login();

$user = current_user();
$db = get_db();

$filter = trim($_GET['filter'] ?? 'all');

$whereClauses = ['user_id = ?'];
$params = [$user['id']];

if ($filter === 'unread') {
    $whereClauses[] = 'is_read = 0';
}

$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

// Count matching records
$countSql = "SELECT COUNT(*) FROM notifications $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Safe Pagination
$pagination = get_pagination_params($totalRecords, 20, [20, 50, 100]);
$page = $pagination['page'];
$limit = $pagination['per_page'];
$offset = $pagination['offset'];
$totalPages = $pagination['total_pages'];

// Fetch Notifications
$notifsSql = "
    SELECT * FROM notifications 
    $whereSql 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
";
$notifsStmt = $db->prepare($notifsSql);
$notifsStmt->execute($params);
$notifications = $notifsStmt->fetchAll();

$pageTitle = 'Notifications';
$pageHeader = 'Notification Center';
$activePage = 'notifications';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header & Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-bell me-2 text-primary"></i>Notification Center
            </h1>
            <p class="text-secondary-custom small mb-0">
                Stay updated on ticket assignments, customer responses, and system updates
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= url('modules/profile/notifications.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-sliders"></i> Preferences
            </a>
            <?php if ($totalRecords > 0): ?>
                <form action="<?= url('modules/notifications/mark_all_read.php'); ?>" method="POST">
                    <?= csrf_field(); ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-all"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-2 d-flex align-items-center justify-content-between">
            <div class="btn-group" role="group">
                <a href="<?= url('modules/notifications/index.php'); ?>" 
                   class="btn btn-sm <?= ($filter === 'all') ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    All Notifications
                </a>
                <a href="<?= url('modules/notifications/index.php?filter=unread'); ?>" 
                   class="btn btn-sm <?= ($filter === 'unread') ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    Unread Only
                </a>
            </div>

            <span class="text-muted small pe-2">
                Total: <strong><?= $totalRecords; ?></strong>
            </span>
        </div>
    </div>

    <!-- Notifications List Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <?php if (!empty($notifications)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notif): 
                        $meta = format_notification_meta($notif);
                        $isUnread = ((int)$notif['is_read'] === 0);
                    ?>
                        <div class="list-group-item d-flex align-items-start justify-content-between p-3 gap-3 <?= $isUnread ? 'bg-light border-start border-primary border-3' : ''; ?>">
                            <div class="d-flex align-items-start gap-3">
                                <div class="p-2 rounded bg-white border <?= $meta['color_class']; ?> flex-shrink-0 mt-1">
                                    <i class="bi <?= $meta['icon']; ?> fs-5"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="fw-semibold text-dark"><?= e($notif['title']); ?></span>
                                        <?php if ($isUnread): ?>
                                            <span class="badge bg-primary fs-8">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-secondary small mb-2">
                                        <?= nl2br(e($notif['message'])); ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 text-muted fs-8">
                                        <span><i class="bi bi-clock me-1"></i><?= e(format_datetime($notif['created_at'])); ?></span>
                                        <?php if ($meta['url'] !== '#'): ?>
                                            <a href="<?= $meta['url']; ?>" class="text-primary text-decoration-none fw-medium">
                                                View Ticket <i class="bi bi-arrow-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <?php if ($isUnread): ?>
                                <form action="<?= url('modules/notifications/mark_read.php'); ?>" method="POST" class="flex-shrink-0">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= $notif['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Mark as Read">
                                        <i class="bi bi-check2"></i> Mark Read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash fs-1 text-secondary mb-2 d-block"></i>
                    <h5 class="h6 fw-bold">No notifications found</h5>
                    <p class="small mb-0">
                        <?= ($filter === 'unread') ? 'You have no unread notifications.' : 'You have not received any notifications yet.'; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white d-flex align-items-center justify-content-between py-3">
                <span class="small text-muted">
                    Showing <strong><?= ($offset + 1); ?></strong> to <strong><?= min($offset + $limit, $totalRecords); ?></strong> of <strong><?= $totalRecords; ?></strong>
                </span>
                <nav aria-label="Notifications pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/notifications/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= url('modules/notifications/index.php?' . http_build_query(array_merge($_GET, ['page' => $p]))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/notifications/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
