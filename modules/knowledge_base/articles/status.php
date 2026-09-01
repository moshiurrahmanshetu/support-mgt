<?php
/**
 * Knowledge Base - Toggle Article Status (Admin Only - Phase 06)
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
$status = trim($_POST['status'] ?? '');

if ($id <= 0 || !in_array($status, ['draft', 'published'], true)) {
    flash('danger', 'Invalid article status parameters.');
    redirect('modules/knowledge_base/articles/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT id, title, published_at FROM knowledge_base_articles WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    flash('danger', 'Article not found.');
    redirect('modules/knowledge_base/articles/index.php');
}

$publishedAt = $article['published_at'];
if ($status === 'published' && empty($publishedAt)) {
    $publishedAt = date('Y-m-d H:i:s');
}

// Update status
$updateStmt = $db->prepare("UPDATE knowledge_base_articles SET status = ?, published_at = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$status, $publishedAt, $id]);

$actionKey = ($status === 'published') ? 'knowledge_base_article_published' : 'knowledge_base_article_unpublished';
$label = ($status === 'published') ? 'published' : 'moved to draft';
$user = current_user();
log_activity($user['id'], 'knowledge_base', $actionKey, "Article '{$article['title']}' was {$label}", 'kb_article', $id);

flash('success', "Article <strong>" . e($article['title']) . "</strong> has been {$label}.");
redirect($_SERVER['HTTP_REFERER'] ?? 'modules/knowledge_base/articles/index.php');
