<?php
/**
 * Knowledge Base - Delete Category Handler (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/knowledge_base/categories/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect('modules/knowledge_base/categories/index.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid category reference.');
    redirect('modules/knowledge_base/categories/index.php');
}

$db = get_db();

// Verify Category exists
$stmt = $db->prepare("SELECT id, name FROM knowledge_base_categories WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    flash('danger', 'Category not found.');
    redirect('modules/knowledge_base/categories/index.php');
}

// Check if category has articles
$checkStmt = $db->prepare("SELECT COUNT(*) FROM knowledge_base_articles WHERE category_id = ?");
$checkStmt->execute([$id]);
$articleCount = (int)$checkStmt->fetchColumn();

if ($articleCount > 0) {
    flash('danger', "Cannot delete category <strong>" . e($category['name']) . "</strong> because it contains {$articleCount} article(s). Please reassign or delete the articles first.");
    redirect('modules/knowledge_base/categories/index.php');
}

// Delete category
$delStmt = $db->prepare("DELETE FROM knowledge_base_categories WHERE id = ?");
$delStmt->execute([$id]);

$user = current_user();
log_activity($user['id'], 'knowledge_base', 'knowledge_base_category_deleted', "Deleted category '{$category['name']}' (ID: {$id})", 'kb_category', $id);

flash('success', "Category <strong>" . e($category['name']) . "</strong> has been deleted.");
redirect('modules/knowledge_base/categories/index.php');
