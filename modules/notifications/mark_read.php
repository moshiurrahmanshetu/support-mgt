<?php
/**
 * Mark Single Notification as Read Handler (support-mgt Phase 05)
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
$notifId = (int)($_POST['id'] ?? 0);

if ($notifId > 0) {
    $db = get_db();
    // Strict IDOR protection: only mark read if notification belongs to logged-in user
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $user['id']]);
}

redirect($_SERVER['HTTP_REFERER'] ?? 'modules/notifications/index.php');
