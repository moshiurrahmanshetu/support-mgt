<?php
/**
 * Knowledge Base Article Management - Index (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/knowledge_base.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$db = get_db();

// Filter & Search Inputs
$search = trim($_GET['q'] ?? '');
$categoryFilter = (int)($_GET['category_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$featuredFilter = trim($_GET['featured'] ?? '');

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = '(a.title LIKE ? OR a.excerpt LIKE ? OR a.content LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryFilter > 0) {
    $whereClauses[] = 'a.category_id = ?';
    $params[] = $categoryFilter;
}

if (!empty($statusFilter) && in_array($statusFilter, ['draft', 'published'], true)) {
    $whereClauses[] = 'a.status = ?';
    $params[] = $statusFilter;
}

if ($featuredFilter === '1') {
    $whereClauses[] = 'a.is_featured = 1';
} elseif ($featuredFilter === '0') {
    $whereClauses[] = 'a.is_featured = 0';
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count Total Matching Records
$countSql = "
    SELECT COUNT(*) 
    FROM knowledge_base_articles a
    JOIN knowledge_base_categories c ON a.category_id = c.id
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

// Fetch Articles
$articlesSql = "
    SELECT 
        a.*,
        c.name AS category_name,
        c.icon AS category_icon,
        c.status AS category_status,
        u.name AS author_name
    FROM knowledge_base_articles a
    JOIN knowledge_base_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    $whereSql
    ORDER BY a.created_at DESC
    LIMIT $limit OFFSET $offset
";
$articlesStmt = $db->prepare($articlesSql);
$articlesStmt->execute($params);
$articles = $articlesStmt->fetchAll();

// Fetch all categories for filter dropdown
$catListStmt = $db->query("SELECT id, name FROM knowledge_base_categories ORDER BY name ASC");
$allCategories = $catListStmt->fetchAll();

$pageTitle = 'Knowledge Base Articles';
$pageHeader = 'Knowledge Base Articles';
$activePage = 'kb_articles';

include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-file-earmark-text me-2 text-primary"></i>Knowledge Base Articles
            </h1>
            <p class="text-secondary-custom small mb-0">
                Create and manage self-service documentation and support guides
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= url('modules/knowledge_base/categories/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-folder"></i> Manage Categories
            </a>
            <a href="<?= url('modules/knowledge_base/articles/create.php'); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Create Article
            </a>
        </div>
    </div>

    <!-- Filters & Search Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= url('modules/knowledge_base/articles/index.php'); ?>" method="GET" class="row g-2 align-items-center">
                <!-- Search -->
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0" 
                               name="q" 
                               value="<?= e($search); ?>" 
                               placeholder="Search title, excerpt, content...">
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="col-6 col-md-3">
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= $cat['id']; ?>" <?= ($categoryFilter === (int)$cat['id']) ? 'selected' : ''; ?>>
                                <?= e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="published" <?= ($statusFilter === 'published') ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?= ($statusFilter === 'draft') ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>

                <!-- Featured Filter -->
                <div class="col-6 col-md-2">
                    <select name="featured" class="form-select">
                        <option value="">All Articles</option>
                        <option value="1" <?= ($featuredFilter === '1') ? 'selected' : ''; ?>>Featured Only</option>
                        <option value="0" <?= ($featuredFilter === '0') ? 'selected' : ''; ?>>Standard Only</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="col-6 col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary w-100" title="Filter Articles">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <?php if (!empty($search) || $categoryFilter > 0 || !empty($statusFilter) || $featuredFilter !== ''): ?>
                        <a href="<?= url('modules/knowledge_base/articles/index.php'); ?>" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Articles Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3">Article Title</th>
                            <th class="py-3">Category</th>
                            <th class="py-3" style="width: 110px;">Status</th>
                            <th class="py-3" style="width: 100px;">Featured</th>
                            <th class="py-3" style="width: 90px;">Views</th>
                            <th class="py-3" style="width: 140px;">Author</th>
                            <th class="py-3" style="width: 130px;">Updated</th>
                            <th class="pe-3 py-3 text-end" style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($articles)): ?>
                            <?php foreach ($articles as $art): ?>
                                <tr>
                                    <!-- Title & Excerpt -->
                                    <td class="ps-3">
                                        <div class="d-flex flex-column">
                                            <a href="<?= url('modules/knowledge_base/articles/view.php?id=' . $art['id']); ?>" class="fw-semibold text-dark text-decoration-none">
                                                <?= e($art['title']); ?>
                                            </a>
                                            <?php if (!empty($art['excerpt'])): ?>
                                                <div class="text-muted small text-truncate" style="max-width: 320px;">
                                                    <?= e($art['excerpt']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Category -->
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi <?= e($art['category_icon']); ?> me-1 text-primary"></i><?= e($art['category_name']); ?>
                                        </span>
                                        <?php if ($art['category_status'] === STATUS_INACTIVE): ?>
                                            <span class="badge bg-warning text-dark fs-8 ms-1">Category Inactive</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <?php if ($art['status'] === 'published'): ?>
                                            <span class="badge badge-status-resolved"><i class="bi bi-check-circle-fill me-1"></i>Published</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-pencil-fill me-1"></i>Draft</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Featured -->
                                    <td>
                                        <?php if ((int)$art['is_featured'] === 1): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>Featured</span>
                                        <?php else: ?>
                                            <span class="text-muted small">No</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Views -->
                                    <td class="font-monospace text-muted small">
                                        <i class="bi bi-eye me-1"></i><?= (int)$art['view_count']; ?>
                                    </td>

                                    <!-- Author -->
                                    <td class="small text-muted">
                                        <?= e($art['author_name'] ?: 'System'); ?>
                                    </td>

                                    <!-- Updated -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($art['updated_at'], 'M d, Y')); ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-3 text-end">
                                        <div class="d-flex justify-content-end align-items-center gap-1">
                                            <!-- Preview -->
                                            <a href="<?= url('modules/knowledge_base/view.php?slug=' . urlencode($art['slug'])); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Public View" target="_blank">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>

                                            <!-- Edit -->
                                            <a href="<?= url('modules/knowledge_base/articles/edit.php?id=' . $art['id']); ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Article">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <!-- Delete -->
                                            <form action="<?= url('modules/knowledge_base/articles/delete.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete article \'<?= e(addslashes($art['title'])); ?>\'?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?= $art['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Article">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-file-earmark-x fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No articles found</h5>
                                    <p class="small mb-3">
                                        <?php if (!empty($search) || $categoryFilter > 0 || !empty($statusFilter) || $featuredFilter !== ''): ?>
                                            No articles match your current filter parameters.
                                        <?php else: ?>
                                            Create your first knowledge base article to assist customers.
                                        <?php endif; ?>
                                    </p>
                                    <a href="<?= url('modules/knowledge_base/articles/create.php'); ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create Article
                                    </a>
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
                    Showing <strong><?= ($offset + 1); ?></strong> to <strong><?= min($offset + $limit, $totalRecords); ?></strong> of <strong><?= $totalRecords; ?></strong> articles
                </span>
                <nav aria-label="Articles navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/knowledge_base/articles/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= url('modules/knowledge_base/articles/index.php?' . http_build_query(array_merge($_GET, ['page' => $p]))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= url('modules/knowledge_base/articles/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
