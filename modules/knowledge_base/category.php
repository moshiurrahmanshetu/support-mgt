<?php
/**
 * Public Knowledge Base - Category View (support-mgt Phase 06)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/knowledge_base.php';

$kbEnabled = (get_setting('knowledge_base_enabled', '1') === '1');
if (!$kbEnabled) {
    flash('warning', 'Knowledge Base is currently unavailable.');
    redirect('index.php');
}

$slug = trim($_GET['slug'] ?? '');

if (empty($slug)) {
    redirect('modules/knowledge_base/index.php');
}

$db = get_db();

// Fetch Category (Must be active for public view)
$stmt = $db->prepare("SELECT * FROM knowledge_base_categories WHERE slug = ? AND status = 'active' LIMIT 1");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    flash('danger', 'The requested category was not found or is inactive.');
    redirect('modules/knowledge_base/index.php');
}

// Fetch published articles in this category
$artStmt = $db->prepare("
    SELECT id, title, slug, excerpt, content, view_count, published_at, created_at, is_featured
    FROM knowledge_base_articles
    WHERE category_id = ? AND status = 'published'
    ORDER BY is_featured DESC, published_at DESC, created_at DESC
");
$artStmt->execute([$category['id']]);
$articles = $artStmt->fetchAll();

// Other active categories for sidebar
$otherCatsStmt = $db->prepare("
    SELECT c.*, COUNT(a.id) AS article_count
    FROM knowledge_base_categories c
    LEFT JOIN knowledge_base_articles a ON c.id = a.category_id AND a.status = 'published'
    WHERE c.status = 'active' AND c.id != ?
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.name ASC
");
$otherCatsStmt->execute([$category['id']]);
$otherCategories = $otherCatsStmt->fetchAll();

$pageTitle = $category['name'] . ' - Knowledge Base';
$pageHeader = $category['name'];
$activePage = 'knowledge_base';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('modules/knowledge_base/index.php'); ?>">Support Center</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($category['name']); ?></li>
        </ol>
    </nav>

    <!-- Category Header Card (Solid Color) -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: #1e293b; color: #ffffff; border-radius: 8px;">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="bi <?= e($category['icon']); ?> fs-3"></i>
                </div>
                <div>
                    <h1 class="h4 fw-bold text-white mb-1"><?= e($category['name']); ?></h1>
                    <?php if (!empty($category['description'])): ?>
                        <p class="text-light opacity-75 small mb-0"><?= e($category['description']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Column: Article List -->
        <div class="col-12 col-lg-8">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h6 fw-bold text-dark mb-0">Articles in this Topic</h2>
                <span class="badge bg-light text-dark border">
                    <?= count($articles); ?> <?= (count($articles) === 1) ? 'Article' : 'Articles'; ?>
                </span>
            </div>

            <?php if (!empty($articles)): ?>
                <div class="list-group shadow-sm border mb-4">
                    <?php foreach ($articles as $art): ?>
                        <a href="<?= url('modules/knowledge_base/view.php?slug=' . urlencode($art['slug'])); ?>" class="list-group-item list-group-item-action p-4">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if ((int)$art['is_featured'] === 1): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>Featured</span>
                                <?php endif; ?>
                                <span class="text-muted fs-8">
                                    <i class="bi bi-eye me-1"></i><?= (int)$art['view_count']; ?> views
                                </span>
                                <span class="text-muted fs-8">&bull;</span>
                                <span class="text-muted fs-8">
                                    <i class="bi bi-calendar me-1"></i><?= e(format_datetime($art['published_at'] ?: $art['created_at'], 'M d, Y')); ?>
                                </span>
                            </div>

                            <h3 class="h5 fw-bold text-dark mb-2"><?= e($art['title']); ?></h3>

                            <p class="text-secondary-custom small mb-0">
                                <?php if (!empty($art['excerpt'])): ?>
                                    <?= e($art['excerpt']); ?>
                                <?php else: ?>
                                    <?= e(mb_strimwidth(strip_tags($art['content']), 0, 180, '...')); ?>
                                <?php endif; ?>
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card border shadow-sm p-5 text-center text-muted">
                    <i class="bi bi-file-earmark-x fs-1 text-secondary mb-2"></i>
                    <h4 class="h6 fw-bold text-dark mb-1">No articles published in this category yet</h4>
                    <p class="small mb-0">Check back soon or search our full knowledge base for related topics.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar: Other Topics & Need Help CTA -->
        <div class="col-12 col-lg-4">
            <!-- Other Topics -->
            <?php if (!empty($otherCategories)): ?>
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h3 class="h6 mb-0 fw-bold text-dark">Other Topics</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($otherCategories as $oc): ?>
                            <a href="<?= url('modules/knowledge_base/category.php?slug=' . urlencode($oc['slug'])); ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi <?= e($oc['icon']); ?> text-primary"></i>
                                    <span class="small fw-semibold text-dark"><?= e($oc['name']); ?></span>
                                </div>
                                <span class="badge bg-light text-muted border"><?= (int)$oc['article_count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Need Assistance Card (Solid Background) -->
            <div class="card border-0 shadow-sm text-center p-4" style="background-color: #0f172a; color: #ffffff; border-radius: 8px;">
                <i class="bi bi-headset fs-2 text-primary mb-2"></i>
                <h4 class="h6 fw-bold text-white mb-2">Need direct help?</h4>
                <p class="text-light opacity-75 small mb-3">
                    If this guide doesn't answer your question, our support team is standing by.
                </p>
                <?php if (is_logged_in()): ?>
                    <a href="<?= url('modules/tickets/create.php'); ?>" class="btn btn-primary btn-sm fw-semibold w-100">
                        <i class="bi bi-plus-circle"></i> Submit a Ticket
                    </a>
                <?php else: ?>
                    <a href="<?= url('auth/login.php'); ?>" class="btn btn-primary btn-sm fw-semibold w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In to Contact Support
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
