<?php
/**
 * Ticket Management - Toggle Admin Viewed State (Phase 08.1)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Only Admin or authorized Support Manager can toggle admin viewed state
if (!is_admin_user() && !has_role(['admin', 'administrator', 'support_manager'])) {
    flash('danger', 'Unauthorized access.');
    redirect('modules/tickets/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Invalid request method.');
    redirect('modules/tickets/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security token invalid or expired. Please try again.');
    redirect('modules/tickets/index.php');
}

$ticketId = (int)($_POST['id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if ($ticketId <= 0) {
    flash('danger', 'Invalid ticket identifier.');
    redirect('modules/tickets/index.php');
}

$db = get_db();

// Verify ticket exists
$stmt = $db->prepare("SELECT id, ticket_number, admin_viewed_at FROM tickets WHERE id = ? LIMIT 1");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found.');
    redirect('modules/tickets/index.php');
}

if ($action === 'mark_unread') {
    $updateStmt = $db->prepare("UPDATE tickets SET admin_viewed_at = NULL WHERE id = ?");
    $updateStmt->execute([$ticketId]);
    flash('info', "Ticket <strong>" . e($ticket['ticket_number']) . "</strong> marked as Unread / New.");
} else {
    $updateStmt = $db->prepare("UPDATE tickets SET admin_viewed_at = NOW() WHERE id = ?");
    $updateStmt->execute([$ticketId]);
    flash('success', "Ticket <strong>" . e($ticket['ticket_number']) . "</strong> marked as Read.");
}

redirect('modules/tickets/view.php?id=' . $ticketId);
