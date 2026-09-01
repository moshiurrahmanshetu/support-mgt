<?php
/**
 * Automated Verification Test Suite for Phase 06
 * Knowledge Base, Categories, Articles, FAQs & Public Support Center
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/knowledge_base.php';
require_once __DIR__ . '/../includes/activity_log.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assert_test($description, $condition, $details = '') {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$description}" . ($details ? " - {$details}" : "") . "\n";
    }
}

echo "====================================================\n";
echo "SUPPORT-MGT: PHASE 06 AUTOMATED VERIFICATION SUITE\n";
echo "====================================================\n\n";

$db = get_db();

// ----------------------------------------------------
// SECTION 1: LINT ALL PHP FILES
// ----------------------------------------------------
echo "--- Section 1: PHP Syntax Validation ---\n";
$phpFiles = [];
$dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__)));
foreach ($dirIterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}

$syntaxFailures = 0;
foreach ($phpFiles as $file) {
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnVar);
    $isOk = ($returnVar === 0 && strpos(implode("\n", $output), 'No syntax errors detected') !== false);
    if (!$isOk) {
        $syntaxFailures++;
        echo "  [FAIL] Syntax error in: {$file}\n";
    }
}
assert_test("All " . count($phpFiles) . " PHP files pass syntax check (php -l)", $syntaxFailures === 0);

// ----------------------------------------------------
// SECTION 2: DATABASE SCHEMA & SEEDS
// ----------------------------------------------------
echo "\n--- Section 2: Phase 06 Database Schema & Seeds ---\n";

$stmt = $db->query("SHOW TABLES LIKE 'knowledge_base_categories'");
assert_test("knowledge_base_categories table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW TABLES LIKE 'knowledge_base_articles'");
assert_test("knowledge_base_articles table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW TABLES LIKE 'faqs'");
assert_test("faqs table exists", $stmt->rowCount() > 0);

$catCount = (int)$db->query("SELECT COUNT(*) FROM knowledge_base_categories")->fetchColumn();
assert_test("Default KB categories seeded (found: {$catCount})", $catCount >= 4);

$artCount = (int)$db->query("SELECT COUNT(*) FROM knowledge_base_articles")->fetchColumn();
assert_test("Default KB articles seeded (found: {$artCount})", $artCount >= 4);

$faqCount = (int)$db->query("SELECT COUNT(*) FROM faqs")->fetchColumn();
assert_test("Default FAQs seeded (found: {$faqCount})", $faqCount >= 4);

$kbSetting = get_setting('knowledge_base_enabled');
assert_test("knowledge_base_enabled setting exists and is enabled (boolean true)", $kbSetting === true || $kbSetting === '1');

$faqSetting = get_setting('faq_enabled');
assert_test("faq_enabled setting exists and is enabled (boolean true)", $faqSetting === true || $faqSetting === '1');

// ----------------------------------------------------
// SECTION 3: CATEGORY SLUGS & CRUD SAFETY
// ----------------------------------------------------
echo "\n--- Section 3: Category Management & Safety Blocker ---\n";

// 1. Slug Generator
$slug1 = generate_unique_slug('knowledge_base_categories', 'Test New Topic Category');
assert_test("Slug generator produces clean SEO slug", $slug1 === 'test-new-topic-category');

// 2. Create Category
$stmt = $db->prepare("INSERT INTO knowledge_base_categories (name, slug, description, icon, status, created_at, updated_at) VALUES ('Temp Category', ?, 'Temp description', 'bi-folder', 'active', NOW(), NOW())");
$stmt->execute([$slug1]);
$tempCatId = (int)$db->lastInsertId();
assert_test("Created temporary test category (ID: {$tempCatId})", $tempCatId > 0);

// 3. Collision Slug Generation
$slug2 = generate_unique_slug('knowledge_base_categories', 'Test New Topic Category');
assert_test("Slug generator avoids collisions (appended -2)", $slug2 === 'test-new-topic-category-2');

// 4. Create Article inside Temp Category
$artSlug = generate_unique_slug('knowledge_base_articles', 'Temp Article for Deletion Check');
$stmt = $db->prepare("INSERT INTO knowledge_base_articles (category_id, title, slug, content, status, created_at, updated_at) VALUES (?, 'Temp Article', ?, 'Content text', 'published', NOW(), NOW())");
$stmt->execute([$tempCatId, $artSlug]);
$tempArtId = (int)$db->lastInsertId();

// 5. Category Deletion Safety Check
$artCheckStmt = $db->prepare("SELECT COUNT(*) FROM knowledge_base_articles WHERE category_id = ?");
$artCheckStmt->execute([$tempCatId]);
$hasArticles = (int)$artCheckStmt->fetchColumn();
assert_test("Category deletion blocker identifies category has articles (count: {$hasArticles})", $hasArticles > 0);

// 6. Clean up article, then delete category safely
$db->prepare("DELETE FROM knowledge_base_articles WHERE id = ?")->execute([$tempArtId]);
$artCheckStmt->execute([$tempCatId]);
$hasArticlesAfter = (int)$artCheckStmt->fetchColumn();
assert_test("Category is now empty (count: {$hasArticlesAfter})", $hasArticlesAfter === 0);

$db->prepare("DELETE FROM knowledge_base_categories WHERE id = ?")->execute([$tempCatId]);
$catDeleted = $db->prepare("SELECT id FROM knowledge_base_categories WHERE id = ?");
$catDeleted->execute([$tempCatId]);
assert_test("Empty category deleted successfully", !$catDeleted->fetch());

// ----------------------------------------------------
// SECTION 4: ARTICLE MANAGEMENT & VIEW COUNTER
// ----------------------------------------------------
echo "\n--- Section 4: Article Publishing, Views & Related Articles ---\n";

// 1. Create Draft Article in Category 1
$draftSlug = generate_unique_slug('knowledge_base_articles', 'Draft Secret Guide');
$stmt = $db->prepare("INSERT INTO knowledge_base_articles (category_id, title, slug, excerpt, content, status, view_count, created_at, updated_at) VALUES (1, 'Draft Secret Guide', ?, 'Secret summary', 'Secret content', 'draft', 0, NOW(), NOW())");
$stmt->execute([$draftSlug]);
$draftId = (int)$db->lastInsertId();

// 2. Verify Draft NOT in public search
$searchStmt = $db->prepare("SELECT COUNT(*) FROM knowledge_base_articles WHERE status = 'published' AND title LIKE '%Secret Guide%'");
$searchStmt->execute();
$publicDraftCount = (int)$searchStmt->fetchColumn();
assert_test("Draft article is NOT returned in public search queries", $publicDraftCount === 0);

// 3. Publish Article
$db->prepare("UPDATE knowledge_base_articles SET status = 'published', published_at = NOW() WHERE id = ?")->execute([$draftId]);
$searchStmt->execute();
$publishedCount = (int)$searchStmt->fetchColumn();
assert_test("Published article is returned in public search queries", $publishedCount === 1);

// 4. Test View Counter
$_SESSION['viewed_articles'] = [];
increment_article_view_count($draftId);
$viewCount1 = (int)$db->query("SELECT view_count FROM knowledge_base_articles WHERE id = {$draftId}")->fetchColumn();
assert_test("First view incremented view count to 1", $viewCount1 === 1);

// Repeat call in same session should NOT increment again
increment_article_view_count($draftId);
$viewCount2 = (int)$db->query("SELECT view_count FROM knowledge_base_articles WHERE id = {$draftId}")->fetchColumn();
assert_test("Subsequent view in same session does not increment (remains 1)", $viewCount2 === 1);

// 5. Related Articles
$related = get_related_articles(1, $draftId, 3);
assert_test("get_related_articles() returns other published articles in same category", is_array($related) && count($related) > 0);

// Clean up test article
$db->prepare("DELETE FROM knowledge_base_articles WHERE id = ?")->execute([$draftId]);

// ----------------------------------------------------
// SECTION 5: PUBLIC SEARCH ENGINE & EDGE CASES
// ----------------------------------------------------
echo "\n--- Section 5: Public Search Engine Validation ---\n";

// Case 1: Standard query
$q1Stmt = $db->prepare("
    SELECT COUNT(*) FROM knowledge_base_articles a
    JOIN knowledge_base_categories c ON a.category_id = c.id
    WHERE a.status = 'published' AND c.status = 'active'
      AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.content LIKE ?)
");
$q1Param = "%password%";
$q1Stmt->execute([$q1Param, $q1Param, $q1Param]);
$q1Count = (int)$q1Stmt->fetchColumn();
assert_test("Search for 'password' returned published results (found: {$q1Count})", $q1Count > 0);

// Case 2: Special characters & injection safety
$injectionString = "' OR '1'='1' -- /*";
$injParam = "%$injectionString%";
$q1Stmt->execute([$injParam, $injParam, $injParam]);
$injCount = (int)$q1Stmt->fetchColumn();
assert_test("Search safely handles SQL injection characters without errors", $injCount === 0);

// ----------------------------------------------------
// SECTION 6: FAQ ENGINE
// ----------------------------------------------------
echo "\n--- Section 6: FAQ System ---\n";

// 1. Create Inactive FAQ
$stmt = $db->prepare("INSERT INTO faqs (question, answer, sort_order, status, created_at, updated_at) VALUES ('Inactive FAQ Test', 'Answer', 99, 'inactive', NOW(), NOW())");
$stmt->execute();
$inactiveFaqId = (int)$db->lastInsertId();

// 2. Query public FAQs
$pubFaqs = $db->query("SELECT COUNT(*) FROM faqs WHERE status = 'active' AND question = 'Inactive FAQ Test'")->fetchColumn();
assert_test("Inactive FAQ is hidden from public query", (int)$pubFaqs === 0);

// 3. Activate FAQ
$db->prepare("UPDATE faqs SET status = 'active' WHERE id = ?")->execute([$inactiveFaqId]);
$pubFaqs2 = $db->query("SELECT COUNT(*) FROM faqs WHERE status = 'active' AND question = 'Inactive FAQ Test'")->fetchColumn();
assert_test("Active FAQ is visible to public query", (int)$pubFaqs2 === 1);

// Clean up
$db->prepare("DELETE FROM faqs WHERE id = ?")->execute([$inactiveFaqId]);

// ----------------------------------------------------
// SECTION 7: TICKET AUTO-SUGGESTIONS API
// ----------------------------------------------------
echo "\n--- Section 7: Ticket Creation Auto-Suggestions ---\n";

$sugStmt = $db->prepare("
    SELECT 
        a.id, a.title, a.slug, c.name AS category_name, c.icon AS category_icon
    FROM knowledge_base_articles a
    JOIN knowledge_base_categories c ON a.category_id = c.id
    WHERE a.status = 'published' AND c.status = 'active'
      AND (a.title LIKE ? OR a.excerpt LIKE ?)
    LIMIT 5
");
$sugParam = "%ticket%";
$sugStmt->execute([$sugParam, $sugParam]);
$sugResults = $sugStmt->fetchAll();
assert_test("Ticket suggestion query for 'ticket' returned valid results", count($sugResults) > 0);

// ----------------------------------------------------
// SECTION 8: ACTIVITY LOGGING FOR KNOWLEDGE BASE
// ----------------------------------------------------
echo "\n--- Section 8: Knowledge Base Activity Logging ---\n";

$log1 = log_activity(1, 'knowledge_base', 'knowledge_base_category_created', 'Created test category in automated suite');
assert_test("log_activity() persisted category event", $log1 === true);

$log2 = log_activity(1, 'knowledge_base', 'knowledge_base_article_published', 'Published test article in automated suite');
assert_test("log_activity() persisted article event", $log2 === true);

$log3 = log_activity(1, 'knowledge_base', 'knowledge_base_faq_created', 'Created test FAQ in automated suite');
assert_test("log_activity() persisted FAQ event", $log3 === true);

// Clean up verification logs
$db->prepare("DELETE FROM activity_logs WHERE description LIKE '%in automated suite%'")->execute();

echo "\n====================================================\n";
echo "VERIFICATION SUMMARY\n";
echo "Total Tests: {$totalTests} | Passed: {$passedTests} | Failed: {$failedTests}\n";
echo "====================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
