<?php
/**
 * Knowledge Base Category Management - Index (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/knowledge_base.php';

// Strict Admin Authorization
require_role(ROLE_ADMIN);

$db = get_db();

// Fetch Categories with article counts
$stmt = $db->query("
    SELECT 
        c.*,
        u.name AS creator_name,
        COUNT(a.id) AS total_articles,
        COUNT(CASE WHEN a.status = 'published' THEN 1 END) AS published_articles
    FROM knowledge_base_categories c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN knowledge_base_articles a ON c.id = a.category_id
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.name ASC
");
$categories = $stmt->fetchAll();

$pageTitle = 'Knowledge Base Categories';
$pageHeader = 'Knowledge Base Categories';
$activePage = 'kb_categories';

include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-folder me-2 text-primary"></i>Knowledge Base Categories
            </h1>
            <p class="text-secondary-custom small mb-0">
                Organize help articles into customer-facing topic categories
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= url('modules/knowledge_base/articles/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-text"></i> Manage Articles
            </a>
            <a href="<?= url('modules/knowledge_base/categories/create.php'); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Add Category
            </a>
        </div>
    </div>

    <!-- Categories Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3" style="width: 60px;">Icon</th>
                            <th class="py-3">Category Name</th>
                            <th class="py-3">Slug</th>
                            <th class="py-3" style="width: 130px;">Articles</th>
                            <th class="py-3" style="width: 100px;">Sort Order</th>
                            <th class="py-3" style="width: 110px;">Status</th>
                            <th class="pe-3 py-3 text-end" style="width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <!-- Icon -->
                                    <td class="ps-3">
                                        <div class="p-2 rounded bg-light text-primary d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="bi <?= e($cat['icon']); ?> fs-5"></i>
                                        </div>
                                    </td>

                                    <!-- Name & Description -->
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($cat['name']); ?></div>
                                        <?php if (!empty($cat['description'])): ?>
                                            <div class="text-muted small text-truncate" style="max-width: 320px;"><?= e($cat['description']); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Slug -->
                                    <td class="font-monospace text-muted small">
                                        <?= e($cat['slug']); ?>
                                    </td>

                                    <!-- Articles Count -->
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <strong><?= (int)$cat['published_articles']; ?></strong> published / <?= (int)$cat['total_articles']; ?> total
                                        </span>
                                    </td>

                                    <!-- Sort Order -->
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= (int)$cat['sort_order']; ?></span>
                                    </td>

                                    <!-- Status Badge -->
                                    <td>
                                        <?php if ($cat['status'] === STATUS_ACTIVE): ?>
                                            <span class="badge badge-status-resolved"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-dash-circle-fill me-1"></i>Inactive</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-3 text-end">
                                        <div class="d-flex justify-content-end align-items-center gap-1">
                                            <!-- Status Toggle -->
                                            <form action="<?= url('modules/knowledge_base/categories/status.php'); ?>" method="POST" class="d-inline">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?= $cat['id']; ?>">
                                                <input type="hidden" name="status" value="<?= ($cat['status'] === STATUS_ACTIVE) ? STATUS_INACTIVE : STATUS_ACTIVE; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary py-1 px-2" title="<?= ($cat['status'] === STATUS_ACTIVE) ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="bi <?= ($cat['status'] === STATUS_ACTIVE) ? 'bi-pause-fill text-warning' : 'bi-play-fill text-success'; ?>"></i>
                                                </button>
                                            </form>

                                            <!-- Edit -->
                                            <a href="<?= url('modules/knowledge_base/categories/edit.php?id=' . $cat['id']); ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Category">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <!-- Delete -->
                                            <form action="<?= url('modules/knowledge_base/categories/delete.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete category \'<?= e(addslashes($cat['name'])); ?>\'?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?= $cat['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Category">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-folder-x fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No categories created yet</h5>
                                    <p class="small mb-3">Create your first category to start organizing knowledge base articles.</p>
                                    <a href="<?= url('modules/knowledge_base/categories/create.php'); ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add First Category
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
