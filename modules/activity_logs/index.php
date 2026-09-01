<?php
/**
 * System Activity Logs Viewer (Admin Only - support-mgt Phase 05)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Admin Authorization
require_role(ROLE_ADMIN);

$user = current_user();
$db = get_db();

// Filter parameters
$search = trim($_GET['q'] ?? '');
$moduleFilter = trim($_GET['module'] ?? '');
$actionFilter = trim($_GET['action_type'] ?? '');
$userIdFilter = (int)($_GET['user_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$whereClauses = [];
$params = [];

// Search Filter
if (!empty($search)) {
    $whereClauses[] = '(al.description LIKE ? OR al.ip_address LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Module Filter
if (!empty($moduleFilter)) {
    $whereClauses[] = 'al.module = ?';
    $params[] = $moduleFilter;
}

// Action Filter
if (!empty($actionFilter)) {
    $whereClauses[] = 'al.action = ?';
    $params[] = $actionFilter;
}

// User Filter
if ($userIdFilter > 0) {
    $whereClauses[] = 'al.user_id = ?';
    $params[] = $userIdFilter;
}

// Date Range Filter
if (!empty($dateFrom)) {
    $whereClauses[] = 'al.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if (!empty($dateTo)) {
    $whereClauses[] = 'al.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count Total Matching Records
$countSql = "
    SELECT COUNT(*) 
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereSql
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Safe Pagination
$pagination = get_pagination_params($totalRecords, 20, [20, 50, 100]);
$page = $pagination['page'];
$limit = $pagination['per_page'];
$offset = $pagination['offset'];
$totalPages = $pagination['total_pages'];
$perPage = $limit;

// Fetch Activity Logs
$logsSql = "
    SELECT 
        al.*,
        u.name AS user_name,
        u.email AS user_email,
        u.role AS user_role
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereSql
    ORDER BY al.created_at DESC
    LIMIT $limit OFFSET $offset
";
$logsStmt = $db->prepare($logsSql);
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

// Fetch distinct modules and users for filter dropdowns
$modulesStmt = $db->query("SELECT DISTINCT module FROM activity_logs ORDER BY module ASC");
$allModules = $modulesStmt->fetchAll(PDO::FETCH_COLUMN);

$usersListStmt = $db->query("SELECT id, name, email, role FROM users ORDER BY name ASC");
$allUsers = $usersListStmt->fetchAll();

$pageTitle = 'System Activity Logs';
$pageHeader = 'System Activity Logs';
$activePage = 'activity_logs';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-clock-history me-2 text-primary"></i>System Activity Logs
            </h1>
            <p class="text-secondary-custom small mb-0">
                Audit trail of authentication events, user updates, and administrative configurations
            </p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/activity_logs/index.php'); ?>" method="GET" class="row g-2 align-items-center">
                <!-- Search -->
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0" 
                               name="q" 
                               value="<?= e($search); ?>" 
                               placeholder="Search description, IP, user...">
                    </div>
                </div>

                <!-- Module Filter -->
                <div class="col-6 col-md-2">
                    <select name="module" class="form-select">
                        <option value="">All Modules</option>
                        <?php foreach ($allModules as $mod): ?>
                            <option value="<?= e($mod); ?>" <?= ($moduleFilter === $mod) ? 'selected' : ''; ?>>
                                <?= ucfirst(str_replace('_', ' ', $mod)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- User Filter -->
                <div class="col-6 col-md-2">
                    <select name="user_id" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id']; ?>" <?= ($userIdFilter === (int)$u['id']) ? 'selected' : ''; ?>>
                                <?= e($u['name']); ?> (<?= e($u['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date From -->
                <div class="col-6 col-md-2">
                    <input type="date" class="form-control" name="date_from" value="<?= e($dateFrom); ?>" placeholder="Date From">
                </div>

                <!-- Date To -->
                <div class="col-6 col-md-2">
                    <input type="date" class="form-control" name="date_to" value="<?= e($dateTo); ?>" placeholder="Date To">
                </div>

                <!-- Filter & Reset Buttons -->
                <div class="col-12 col-md d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($moduleFilter) || $userIdFilter > 0 || !empty($dateFrom) || !empty($dateTo)): ?>
                        <a href="<?= url('modules/activity_logs/index.php'); ?>" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Logs Table -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3" style="width: 170px;">Date & Time</th>
                            <th class="py-3" style="width: 180px;">User</th>
                            <th class="py-3" style="width: 130px;">Module</th>
                            <th class="py-3" style="width: 160px;">Action</th>
                            <th class="py-3">Description</th>
                            <th class="pe-3 py-3" style="width: 130px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <!-- Timestamp -->
                                    <td class="ps-3 text-muted fs-8 font-monospace">
                                        <?= e(format_datetime($log['created_at'])); ?>
                                    </td>

                                    <!-- User -->
                                    <td>
                                        <?php if (!empty($log['user_name'])): ?>
                                            <div class="fw-medium text-dark"><?= e($log['user_name']); ?></div>
                                            <div class="text-muted fs-8"><?= e($log['user_role']); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">System / Guest</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Module -->
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= ucfirst(str_replace('_', ' ', $log['module'])); ?>
                                        </span>
                                    </td>

                                    <!-- Action -->
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border font-monospace fs-8">
                                            <?= e($log['action']); ?>
                                        </span>
                                    </td>

                                    <!-- Description -->
                                    <td class="small text-dark">
                                        <?= e($log['description']); ?>
                                    </td>

                                    <!-- IP Address -->
                                    <td class="pe-3 text-muted fs-8 font-monospace">
                                        <?= e($log['ip_address'] ?? '127.0.0.1'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No activity logs found</h5>
                                    <p class="small mb-0">There are no activity records matching your filter criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white d-flex align-items-center justify-content-between py-3">
                <span class="small text-muted">
                    Showing <strong><?= ($offset + 1); ?></strong> to <strong><?= min($offset + $limit, $totalRecords); ?></strong> of <strong><?= $totalRecords; ?></strong> logs
                </span>
                <nav aria-label="Activity logs pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/activity_logs/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= url('modules/activity_logs/index.php?' . http_build_query(array_merge($_GET, ['page' => $p]))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/activity_logs/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
