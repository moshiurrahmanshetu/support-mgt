<?php
/**
 * Public Knowledge Base - Single Article View (support-mgt Phase 06)
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
$id = (int)($_GET['id'] ?? 0);

if (empty($slug) && $id <= 0) {
    redirect('modules/knowledge_base/index.php');
}

$db = get_db();

// Fetch published article under active category
if (!empty($slug)) {
    $stmt = $db->prepare("
        SELECT 
            a.*,
            c.name AS category_name,
            c.slug AS category_slug,
            c.icon AS category_icon,
            u.name AS author_name
        FROM knowledge_base_articles a
        JOIN knowledge_base_categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.created_by = u.id
        WHERE a.slug = ? AND a.status = 'published' AND c.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
} else {
    $stmt = $db->prepare("
        SELECT 
            a.*,
            c.name AS category_name,
            c.slug AS category_slug,
            c.icon AS category_icon,
            u.name AS author_name
        FROM knowledge_base_articles a
        JOIN knowledge_base_categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.created_by = u.id
        WHERE a.id = ? AND a.status = 'published' AND c.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$id]);
}

$article = $stmt->fetch();

if (!$article) {
    // Check if user is admin viewing a draft
    $user = current_user();
    if ($user && $user['role'] === ROLE_ADMIN) {
        $adminStmt = $db->prepare("SELECT id FROM knowledge_base_articles WHERE slug = ? OR id = ? LIMIT 1");
        $adminStmt->execute([$slug, $id]);
        $draftArt = $adminStmt->fetch();
        if ($draftArt) {
            flash('info', 'Viewing article preview (Admin mode).');
            redirect('modules/knowledge_base/articles/view.php?id=' . $draftArt['id']);
        }
    }

    flash('danger', 'The requested article was not found or is currently not published.');
    redirect('modules/knowledge_base/index.php');
}

// Increment view count safely
increment_article_view_count((int)$article['id']);

// Fetch related articles from same category
$relatedArticles = get_related_articles((int)$article['category_id'], (int)$article['id'], 5);

$pageTitle = $article['title'] . ' - Knowledge Base';
$pageHeader = $article['title'];
$activePage = 'knowledge_base';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('modules/knowledge_base/index.php'); ?>">Support Center</a></li>
            <li class="breadcrumb-item"><a href="<?= url('modules/knowledge_base/category.php?slug=' . urlencode($article['category_slug'])); ?>"><?= e($article['category_name']); ?></a></li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 320px;" aria-current="page"><?= e($article['title']); ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Main Content Area -->
        <div class="col-12 col-lg-8">
            <div class="card border shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <!-- Category Badge -->
                    <div class="mb-3">
                        <a href="<?= url('modules/knowledge_base/category.php?slug=' . urlencode($article['category_slug'])); ?>" class="badge bg-light text-primary border text-decoration-none py-2 px-3">
                            <i class="bi <?= e($article['category_icon']); ?> me-1"></i><?= e($article['category_name']); ?>
                        </a>
                    </div>

                    <!-- Article Title -->
                    <h1 class="h2 fw-bold text-dark mb-3"><?= e($article['title']); ?></h1>

                    <!-- Meta Information -->
                    <div class="d-flex flex-wrap align-items-center gap-3 text-secondary-custom fs-8 pb-3 mb-4 border-bottom">
                        <div>
                            <i class="bi bi-calendar-check me-1"></i>
                            Updated: <?= e(format_datetime($article['updated_at'] ?: $article['created_at'], 'M d, Y')); ?>
                        </div>
                        <div><span class="mx-1">&bull;</span></div>
                        <div>
                            <i class="bi bi-eye me-1"></i>
                            <?= (int)$article['view_count']; ?> Views
                        </div>
                        <?php if (is_admin()): ?>
                            <div class="ms-auto">
                                <a href="<?= url('modules/knowledge_base/articles/edit.php?id=' . $article['id']); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Edit Article">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Featured Image if present -->
                    <?php if (!empty($article['featured_image']) && file_exists(__DIR__ . '/../../uploads/kb/' . $article['featured_image'])): ?>
                        <div class="mb-4 text-center">
                            <img src="<?= url('uploads/kb/' . $article['featured_image']); ?>" alt="<?= e($article['title']); ?>" class="img-fluid rounded border shadow-sm" style="max-height: 400px; width: 100%; object-fit: cover;">
                        </div>
                    <?php endif; ?>

                    <!-- Excerpt Banner -->
                    <?php if (!empty($article['excerpt'])): ?>
                        <div class="p-3 bg-light border-start border-4 border-primary rounded-end mb-4 text-dark fs-6">
                            <?= e($article['excerpt']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Body Content -->
                    <div class="article-content lh-lg text-dark fs-6">
                        <?= render_article_content($article['content']); ?>
                    </div>

                    <!-- Was this helpful feedback area -->
                    <div class="mt-5 pt-4 border-top text-center">
                        <p class="text-secondary-custom fw-medium small mb-3">Did this article answer your question?</p>
                        <div class="d-inline-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="this.className='btn btn-success btn-sm px-3'; this.innerHTML='<i class=\'bi bi-hand-thumbs-up-fill me-1\'></i> Thanks!';">
                                <i class="bi bi-hand-thumbs-up me-1"></i> Yes, it helped
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="this.className='btn btn-secondary btn-sm px-3'; this.innerHTML='<i class=\'bi bi-hand-thumbs-down-fill me-1\'></i> Feedback recorded';">
                                <i class="bi bi-hand-thumbs-down me-1"></i> No, I need more help
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar: Related Articles & Contact Support -->
        <div class="col-12 col-lg-4">
            <!-- Related Articles Card -->
            <?php if (!empty($relatedArticles)): ?>
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h3 class="h6 mb-0 fw-bold text-dark">
                            <i class="bi bi-link-45deg me-1 text-primary"></i>Related Articles
                        </h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($relatedArticles as $rel): ?>
                            <a href="<?= url('modules/knowledge_base/view.php?slug=' . urlencode($rel['slug'])); ?>" class="list-group-item list-group-item-action py-3 px-3">
                                <div class="fw-semibold text-dark small mb-1"><?= e($rel['title']); ?></div>
                                <div class="text-muted fs-8">
                                    <i class="bi bi-eye me-1"></i><?= (int)$rel['view_count']; ?> views
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Need Assistance Card (Solid Background) -->
            <div class="card border-0 shadow-sm text-center p-4" style="background-color: #0f172a; color: #ffffff; border-radius: 8px;">
                <i class="bi bi-headset fs-2 text-primary mb-2"></i>
                <h4 class="h6 fw-bold text-white mb-2">Still need assistance?</h4>
                <p class="text-light opacity-75 small mb-3">
                    If this guide doesn't resolve your issue, open a support ticket for personalized assistance.
                </p>
                <?php if (is_logged_in()): ?>
                    <a href="<?= url('modules/tickets/create.php'); ?>" class="btn btn-primary btn-sm fw-semibold w-100">
                        <i class="bi bi-plus-circle"></i> Submit a Ticket
                    </a>
                <?php else: ?>
                    <a href="<?= url('auth/login.php'); ?>" class="btn btn-primary btn-sm fw-semibold w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In to Submit Ticket
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
