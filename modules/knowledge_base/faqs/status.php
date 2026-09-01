<?php
/**
 * FAQ Management - Toggle Status (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/knowledge_base/faqs/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect('modules/knowledge_base/faqs/index.php');
}

$id = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($id <= 0 || !in_array($status, VALID_STATUSES, true)) {
    flash('danger', 'Invalid FAQ status parameters.');
    redirect('modules/knowledge_base/faqs/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT id, question FROM faqs WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$faq = $stmt->fetch();

if (!$faq) {
    flash('danger', 'FAQ not found.');
    redirect('modules/knowledge_base/faqs/index.php');
}

// Update status
$updateStmt = $db->prepare("UPDATE faqs SET status = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$status, $id]);

$label = ($status === STATUS_ACTIVE) ? 'activated' : 'deactivated';
$user = current_user();
log_activity($user['id'], 'knowledge_base', 'knowledge_base_faq_status_changed', "FAQ '{$faq['question']}' was {$label}", 'faq', $id);

flash('success', "FAQ has been {$label}.");
redirect($_SERVER['HTTP_REFERER'] ?? 'modules/knowledge_base/faqs/index.php');
