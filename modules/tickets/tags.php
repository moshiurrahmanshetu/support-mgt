<?php
/**
 * Ticket Management - Add/Remove Tags on Ticket (Admin & Agent Only)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/ticket_activity.php';

require_role([ROLE_ADMIN, ROLE_AGENT]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/tickets/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'modules/tickets/index.php');
}

$user = current_user();
$ticketId = (int)($_POST['ticket_id'] ?? 0);
$tagId = (int)($_POST['tag_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if ($ticketId <= 0 || $tagId <= 0) {
    flash('danger', 'Invalid ticket or tag parameters.');
    redirect('modules/tickets/index.php');
}

$db = get_db();

// 1. Verify Ticket Exists and Agent Authorization
$ticketStmt = $db->prepare("SELECT id, user_id, assigned_to FROM tickets WHERE id = ? LIMIT 1");
$ticketStmt->execute([$ticketId]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found.');
    redirect('modules/tickets/index.php');
}

// 2. Verify Tag Exists
$tagStmt = $db->prepare("SELECT id, name, color FROM ticket_tags WHERE id = ? LIMIT 1");
$tagStmt->execute([$tagId]);
$tag = $tagStmt->fetch();

if (!$tag) {
    flash('danger', 'Tag does not exist.');
    redirect('modules/tickets/view.php?id=' . $ticketId);
}

if ($action === 'add') {
    // Add tag to ticket
    $insertStmt = $db->prepare("
        INSERT IGNORE INTO ticket_tag_relations (ticket_id, tag_id, created_at)
        VALUES (?, ?, NOW())
    ");
    $insertStmt->execute([$ticketId, $tagId]);

    if ($insertStmt->rowCount() > 0) {
        log_ticket_activity($ticketId, $user['id'], 'tag_added', null, $tag['name'], "Tag '{$tag['name']}' added to ticket");
        flash('success', "Tag <strong>" . e($tag['name']) . "</strong> added to ticket.");
    } else {
        flash('info', "Tag is already attached to this ticket.");
    }
} elseif ($action === 'remove') {
    // Remove tag from ticket
    $deleteStmt = $db->prepare("DELETE FROM ticket_tag_relations WHERE ticket_id = ? AND tag_id = ?");
    $deleteStmt->execute([$ticketId, $tagId]);

    if ($deleteStmt->rowCount() > 0) {
        log_ticket_activity($ticketId, $user['id'], 'tag_removed', $tag['name'], null, "Tag '{$tag['name']}' removed from ticket");
        flash('success', "Tag <strong>" . e($tag['name']) . "</strong> removed from ticket.");
    }
}

redirect('modules/tickets/view.php?id=' . $ticketId);
