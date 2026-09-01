<?php
/**
 * Public Knowledge Base Search (support-mgt Phase 06)
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

$query = trim($_GET['q'] ?? '');

// Limit query string to 100 characters max
if (mb_strlen($query) > 100) {
    $query = mb_substr($query, 0, 100);
}

$articles = [];
$totalResults = 0;

if (!empty($query)) {
    $db = get_db();
    $searchStmt = $db->prepare("
        SELECT 
            a.id,
            a.title,
            a.slug,
            a.excerpt,
            a.content,
            a.view_count,
            a.published_at,
            a.created_at,
            c.name AS category_name,
            c.slug AS category_slug,
            c.icon AS category_icon
        FROM knowledge_base_articles a
        JOIN knowledge_base_categories c ON a.category_id = c.id
        WHERE a.status = 'published'
          AND c.status = 'active'
          AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.content LIKE ?)
        ORDER BY 
            (CASE WHEN a.title LIKE ? THEN 1 ELSE 2 END),
            a.view_count DESC,
            a.created_at DESC
        LIMIT 30
    ");
    $param = "%$query%";
    $searchStmt->execute([$param, $param, $param, $param]);
    $articles = $searchStmt->fetchAll();
    $totalResults = count($articles);
}

$pageTitle = !empty($query) ? 'Search: ' . $query : 'Search Knowledge Base';
$pageHeader = 'Search Results';
$activePage = 'knowledge_base';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 960px;">
    <!-- Breadcrumb & Search Header -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('modules/knowledge_base/index.php'); ?>">Support Center</a></li>
            <li class="breadcrumb-item active" aria-current="page">Search</li>
        </ol>
    </nav>

    <!-- Search Box Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-4">
            <form action="<?= url('modules/knowledge_base/search.php'); ?>" method="GET">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white text-muted ps-3">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" 
                           name="q" 
                           class="form-control" 
                           value="<?= e($query); ?>" 
                           placeholder="Search knowledge base articles..." 
                           required 
                           maxlength="100">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h5 fw-bold text-dark mb-0">
            <?php if (!empty($query)): ?>
                Search results for "<span class="text-primary"><?= e($query); ?></span>"
            <?php else: ?>
                Search the Knowledge Base
            <?php endif; ?>
        </h1>
        <span class="badge bg-light text-dark border">
            <?= $totalResults; ?> <?= ($totalResults === 1) ? 'Result' : 'Results'; ?> Found
        </span>
    </div>

    <!-- Results List -->
    <?php if (!empty($articles)): ?>
        <div class="list-group shadow-sm border mb-4">
            <?php foreach ($articles as $art): ?>
                <a href="<?= url('modules/knowledge_base/view.php?slug=' . urlencode($art['slug'])); ?>" class="list-group-item list-group-item-action p-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-light text-primary border">
                            <i class="bi <?= e($art['category_icon']); ?> me-1"></i><?= e($art['category_name']); ?>
                        </span>
                        <span class="text-muted fs-8">
                            <i class="bi bi-eye me-1"></i><?= (int)$art['view_count']; ?> views
                        </span>
                    </div>

                    <h2 class="h5 fw-bold text-dark mb-2"><?= e($art['title']); ?></h2>

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
        <div class="card border shadow-sm p-5 text-center my-4">
            <i class="bi bi-search text-secondary fs-1 mb-3"></i>
            <h3 class="h6 fw-bold text-dark mb-1">
                <?= !empty($query) ? 'No matching articles found' : 'Enter a search term above'; ?>
            </h3>
            <p class="text-muted small mb-4">
                <?= !empty($query) ? 'Try using different keywords or browse our categories directly.' : 'Search for keywords like "password", "invoice", or "attachment".'; ?>
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?= url('modules/knowledge_base/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-grid"></i> Browse All Topics
                </a>
                <?php if (is_logged_in()): ?>
                    <a href="<?= url('modules/tickets/create.php'); ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-ticket-perforated"></i> Submit a Ticket
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
