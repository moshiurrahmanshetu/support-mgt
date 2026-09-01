<?php
/**
 * Public Knowledge Base & Support Center (support-mgt Phase 06)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/knowledge_base.php';

// Check if Knowledge Base is globally enabled
$kbEnabled = (get_setting('knowledge_base_enabled', '1') === '1');
$faqEnabled = (get_setting('faq_enabled', '1') === '1');

$currentUser = current_user();
$categories = get_active_categories_with_counts();
$featuredArticles = get_featured_articles(6);
$popularArticles = get_popular_articles(6);

$db = get_db();
$activeFaqs = [];
if ($faqEnabled) {
    $faqStmt = $db->query("SELECT * FROM faqs WHERE status = 'active' ORDER BY sort_order ASC, created_at ASC");
    $activeFaqs = $faqStmt->fetchAll();
}

$pageTitle = 'Support Center & Knowledge Base';
$pageHeader = 'Help Center';
$activePage = 'knowledge_base';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <?php if (!$kbEnabled): ?>
        <div class="alert alert-warning py-4 text-center my-4">
            <i class="bi bi-exclamation-triangle-fill fs-2 mb-2 d-block text-warning"></i>
            <h4 class="h5 fw-bold">Knowledge Base is Currently Unavailable</h4>
            <p class="small text-muted mb-3">Self-service articles and documentation are currently offline for maintenance.</p>
            <?php if (is_logged_in()): ?>
                <a href="<?= url('modules/tickets/create.php'); ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-ticket-perforated"></i> Submit a Support Ticket
                </a>
            <?php else: ?>
                <a href="<?= url('auth/login.php'); ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In to Submit a Ticket
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Hero Search Section (Solid Color Background) -->
        <div class="card border-0 shadow-sm mb-4" style="background-color: #1e293b; color: #ffffff; border-radius: 8px;">
            <div class="card-body p-4 p-md-5 text-center">
                <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-semibold mb-3">
                    <i class="bi bi-stars me-1"></i> Customer Help Center
                </span>
                <h1 class="h2 fw-bold text-white mb-2">How can we help you today?</h1>
                <p class="text-light opacity-75 mb-4 mx-auto" style="max-width: 600px;">
                    Search our knowledge base for setup tutorials, billing answers, and troubleshooting guides.
                </p>

                <!-- Search Bar -->
                <form action="<?= url('modules/knowledge_base/search.php'); ?>" method="GET" class="mx-auto" style="max-width: 680px;">
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text bg-white border-0 text-muted ps-3">
                            <i class="bi bi-search fs-5"></i>
                        </span>
                        <input type="text" 
                               name="q" 
                               class="form-control border-0 py-3 text-dark" 
                               placeholder="Type a question or keyword (e.g. password, invoices, tickets)..." 
                               required 
                               maxlength="100">
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Browse by Category Section -->
        <div class="mb-5">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-0 text-dark">
                        <i class="bi bi-grid me-2 text-primary"></i>Browse by Topic
                    </h2>
                    <p class="text-secondary-custom small mb-0">Explore guides grouped by product area</p>
                </div>
            </div>

            <div class="row g-3">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="col-12 col-md-6 col-lg-3">
                            <a href="<?= url('modules/knowledge_base/category.php?slug=' . urlencode($category['slug'])); ?>" class="card h-100 border shadow-sm text-decoration-none hover-shadow transition" style="border-radius: 8px;">
                                <div class="card-body p-4 d-flex flex-column">
                                    <div class="p-3 rounded bg-light text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                                        <i class="bi <?= e($category['icon']); ?> fs-4"></i>
                                    </div>
                                    <h3 class="h6 fw-bold text-dark mb-1"><?= e($category['name']); ?></h3>
                                    <?php if (!empty($category['description'])): ?>
                                        <p class="text-muted small mb-3 flex-grow-1"><?= e($category['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="mt-auto d-flex align-items-center justify-content-between text-secondary-custom fs-8">
                                        <span><strong><?= (int)$category['article_count']; ?></strong> <?= ($category['article_count'] === 1) ? 'Article' : 'Articles'; ?></span>
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card border p-4 text-center text-muted">
                            <i class="bi bi-folder2-open fs-2 mb-2"></i>
                            <p class="mb-0">No categories available at the moment.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Featured & Popular Articles Grid -->
        <div class="row g-4 mb-5">
            <!-- Featured Articles -->
            <div class="col-12 col-lg-6">
                <div class="card h-100 border shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h3 class="h6 mb-0 fw-bold text-dark">
                            <i class="bi bi-star-fill text-warning me-2"></i>Featured Guides
                        </h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (!empty($featuredArticles)): ?>
                            <?php foreach ($featuredArticles as $fArt): ?>
                                <a href="<?= url('modules/knowledge_base/view.php?slug=' . urlencode($fArt['slug'])); ?>" class="list-group-item list-group-item-action py-3 px-4 d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <span class="badge bg-light text-primary border mb-1">
                                            <i class="bi <?= e($fArt['category_icon']); ?> me-1"></i><?= e($fArt['category_name']); ?>
                                        </span>
                                        <h4 class="h6 mb-1 fw-bold text-dark"><?= e($fArt['title']); ?></h4>
                                        <?php if (!empty($fArt['excerpt'])): ?>
                                            <p class="text-muted small mb-0 text-truncate" style="max-width: 420px;"><?= e($fArt['excerpt']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted mt-2"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted small">No featured articles highlighted yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Popular Articles -->
            <div class="col-12 col-lg-6">
                <div class="card h-100 border shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h3 class="h6 mb-0 fw-bold text-dark">
                            <i class="bi bi-fire text-danger me-2"></i>Popular Articles
                        </h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (!empty($popularArticles)): ?>
                            <?php foreach ($popularArticles as $pArt): ?>
                                <a href="<?= url('modules/knowledge_base/view.php?slug=' . urlencode($pArt['slug'])); ?>" class="list-group-item list-group-item-action py-3 px-4 d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <span class="badge bg-light text-secondary border mb-1">
                                            <i class="bi <?= e($pArt['category_icon']); ?> me-1"></i><?= e($pArt['category_name']); ?>
                                        </span>
                                        <h4 class="h6 mb-1 fw-bold text-dark"><?= e($pArt['title']); ?></h4>
                                        <div class="text-muted fs-8">
                                            <i class="bi bi-eye me-1"></i><?= (int)$pArt['view_count']; ?> views
                                        </div>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted mt-2"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted small">No published articles yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Frequently Asked Questions (Accordion) -->
        <?php if ($faqEnabled && !empty($activeFaqs)): ?>
            <div class="card border shadow-sm mb-5">
                <div class="card-header bg-white py-3 border-bottom">
                    <h3 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-question-circle me-2 text-primary"></i>Frequently Asked Questions
                    </h3>
                </div>
                <div class="card-body p-4">
                    <div class="accordion" id="supportFaqAccordion">
                        <?php foreach ($activeFaqs as $idx => $faq): ?>
                            <div class="accordion-item border mb-2 rounded overflow-hidden">
                                <h2 class="accordion-header" id="faqHeading<?= $faq['id']; ?>">
                                    <button class="accordion-button <?= ($idx !== 0) ? 'collapsed' : ''; ?> fw-semibold text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse<?= $faq['id']; ?>" aria-expanded="<?= ($idx === 0) ? 'true' : 'false'; ?>" aria-controls="faqCollapse<?= $faq['id']; ?>">
                                        <?= e($faq['question']); ?>
                                    </button>
                                </h2>
                                <div id="faqCollapse<?= $faq['id']; ?>" class="accordion-collapse collapse <?= ($idx === 0) ? 'show' : ''; ?>" aria-labelledby="faqHeading<?= $faq['id']; ?>" data-bs-parent="#supportFaqAccordion">
                                    <div class="accordion-body text-secondary-custom lh-base">
                                        <?= nl2br(e($faq['answer'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Still Need Help CTA Banner (Solid Background) -->
        <div class="card border-0 shadow-sm text-center p-4 p-md-5" style="background-color: #0f172a; color: #ffffff; border-radius: 8px;">
            <div class="card-body py-2">
                <div class="p-3 rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                    <i class="bi bi-headset fs-3"></i>
                </div>
                <h3 class="h4 fw-bold text-white mb-2">Still can't find what you are looking for?</h3>
                <p class="text-light opacity-75 mb-4 mx-auto" style="max-width: 540px;">
                    Our dedicated support team is available to assist with technical troubleshooting, billing queries, and account management.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <?php if (is_logged_in()): ?>
                        <a href="<?= url('modules/tickets/create.php'); ?>" class="btn btn-primary px-4 py-2 fw-semibold">
                            <i class="bi bi-plus-circle me-1"></i> Submit a Support Ticket
                        </a>
                        <a href="<?= url('modules/tickets/index.php'); ?>" class="btn btn-outline-light px-4 py-2">
                            <i class="bi bi-ticket-perforated me-1"></i> View My Tickets
                        </a>
                    <?php else: ?>
                        <a href="<?= url('auth/login.php'); ?>" class="btn btn-primary px-4 py-2 fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Submit Ticket
                        </a>
                        <a href="<?= url('auth/register.php'); ?>" class="btn btn-outline-light px-4 py-2">
                            <i class="bi bi-person-plus me-1"></i> Create Account
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
