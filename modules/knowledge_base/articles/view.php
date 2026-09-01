<?php
/**
 * Knowledge Base Article - Admin Preview (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/knowledge_base.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid article reference.');
    redirect('modules/knowledge_base/articles/index.php');
}

$db = get_db();
$stmt = $db->prepare("
    SELECT 
        a.*,
        c.name AS category_name,
        c.icon AS category_icon,
        c.status AS category_status,
        u.name AS author_name
    FROM knowledge_base_articles a
    JOIN knowledge_base_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    flash('danger', 'Article not found.');
    redirect('modules/knowledge_base/articles/index.php');
}

$pageTitle = 'Article Preview: ' . $article['title'];
$pageHeader = 'Article Preview';
$activePage = 'kb_articles';

include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 900px;">
    <!-- Back Link & Action Bar -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
        <a href="<?= url('modules/knowledge_base/articles/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Articles Directory
        </a>

        <div class="d-flex gap-2">
            <a href="<?= url('modules/knowledge_base/view.php?slug=' . urlencode($article['slug'])); ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> Public View
            </a>
            <a href="<?= url('modules/knowledge_base/articles/edit.php?id=' . $article['id']); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil"></i> Edit Article
            </a>
        </div>
    </div>

    <!-- Article Preview Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-4 p-md-5">
            <!-- Badges Header -->
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="badge bg-light text-dark border">
                    <i class="bi <?= e($article['category_icon']); ?> text-primary me-1"></i><?= e($article['category_name']); ?>
                </span>

                <?php if ($article['status'] === 'published'): ?>
                    <span class="badge badge-status-resolved"><i class="bi bi-check-circle-fill me-1"></i>Published</span>
                <?php else: ?>
                    <span class="badge bg-secondary"><i class="bi bi-pencil-fill me-1"></i>Draft (Admin Preview Only)</span>
                <?php endif; ?>

                <?php if ((int)$article['is_featured'] === 1): ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>Featured</span>
                <?php endif; ?>

                <span class="badge bg-light text-muted border font-monospace ms-auto">
                    <i class="bi bi-eye me-1"></i><?= (int)$article['view_count']; ?> Views
                </span>
            </div>

            <!-- Title -->
            <h1 class="h3 fw-bold text-dark mb-3"><?= e($article['title']); ?></h1>

            <!-- Meta info -->
            <div class="d-flex flex-wrap align-items-center gap-3 text-secondary-custom fs-8 pb-3 mb-4 border-bottom">
                <div><i class="bi bi-person me-1"></i> Author: <strong><?= e($article['author_name'] ?: 'System'); ?></strong></div>
                <div><i class="bi bi-calendar-event me-1"></i> Created: <?= e(format_datetime($article['created_at'], 'M d, Y H:i')); ?></div>
                <?php if (!empty($article['published_at'])): ?>
                    <div><i class="bi bi-check2-circle me-1"></i> Published: <?= e(format_datetime($article['published_at'], 'M d, Y')); ?></div>
                <?php endif; ?>
            </div>

            <!-- Featured Image -->
            <?php if (!empty($article['featured_image']) && file_exists(__DIR__ . '/../../../uploads/kb/' . $article['featured_image'])): ?>
                <div class="mb-4 text-center">
                    <img src="<?= url('uploads/kb/' . $article['featured_image']); ?>" alt="<?= e($article['title']); ?>" class="img-fluid rounded border shadow-sm" style="max-height: 380px; width: 100%; object-fit: cover;">
                </div>
            <?php endif; ?>

            <!-- Excerpt if available -->
            <?php if (!empty($article['excerpt'])): ?>
                <div class="p-3 bg-light border-start border-4 border-primary rounded-end mb-4 text-dark fs-6 fw-medium">
                    <?= e($article['excerpt']); ?>
                </div>
            <?php endif; ?>

            <!-- Article Body Content -->
            <div class="article-content lh-lg text-dark">
                <?= render_article_content($article['content']); ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
