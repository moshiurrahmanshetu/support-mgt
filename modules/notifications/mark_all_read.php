<?php
/**
 * Mark All Notifications as Read Handler (support-mgt Phase 05)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/notifications/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'modules/notifications/index.php');
}

$user = current_user();
$db = get_db();

// Strict user scoping: only updates current user's notifications
$stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user['id']]);

flash('success', 'All notifications marked as read.');
redirect($_SERVER['HTTP_REFERER'] ?? 'modules/notifications/index.php');
