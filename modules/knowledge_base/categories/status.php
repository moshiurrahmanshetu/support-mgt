<?php
/**
 * Knowledge Base - Toggle Category Status (Admin Only - Phase 06)
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
$status = trim($_POST['status'] ?? '');

if ($id <= 0 || !in_array($status, VALID_STATUSES, true)) {
    flash('danger', 'Invalid category status parameters.');
    redirect('modules/knowledge_base/categories/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT id, name FROM knowledge_base_categories WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    flash('danger', 'Category not found.');
    redirect('modules/knowledge_base/categories/index.php');
}

// Update status
$updateStmt = $db->prepare("UPDATE knowledge_base_categories SET status = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$status, $id]);

$label = ($status === STATUS_ACTIVE) ? 'activated' : 'deactivated';
$user = current_user();
log_activity($user['id'], 'knowledge_base', 'knowledge_base_category_status_changed', "Category '{$category['name']}' was {$label}", 'kb_category', $id);

flash('success', "Category <strong>" . e($category['name']) . "</strong> has been {$label}.");
redirect($_SERVER['HTTP_REFERER'] ?? 'modules/knowledge_base/categories/index.php');
