<?php
/**
 * Knowledge Base - Delete Article Handler (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/knowledge_base/articles/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect('modules/knowledge_base/articles/index.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid article reference.');
    redirect('modules/knowledge_base/articles/index.php');
}

$db = get_db();

// Verify Article exists
$stmt = $db->prepare("SELECT id, title, featured_image FROM knowledge_base_articles WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    flash('danger', 'Article not found.');
    redirect('modules/knowledge_base/articles/index.php');
}

// Remove featured image if exists
if (!empty($article['featured_image'])) {
    $filePath = __DIR__ . '/../../../uploads/kb/' . $article['featured_image'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// Delete article
$delStmt = $db->prepare("DELETE FROM knowledge_base_articles WHERE id = ?");
$delStmt->execute([$id]);

$user = current_user();
log_activity($user['id'], 'knowledge_base', 'knowledge_base_article_deleted', "Deleted article '{$article['title']}' (ID: {$id})", 'kb_article', $id);

flash('success', "Article <strong>" . e($article['title']) . "</strong> has been deleted.");
redirect('modules/knowledge_base/articles/index.php');
