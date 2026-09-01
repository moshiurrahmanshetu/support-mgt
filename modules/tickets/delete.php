<?php
/**
 * Ticket Management - Delete / Close Protection Handler
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Invalid deletion request method.');
    redirect('modules/tickets/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'modules/tickets/index.php');
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);

if ($ticketId <= 0) {
    flash('danger', 'Invalid ticket reference.');
    redirect('modules/tickets/index.php');
}

// In compliance with support audit rules, historical tickets are archived/closed rather than permanently destroyed
$db = get_db();
$stmt = $db->prepare("UPDATE tickets SET status = 'closed', closed_at = NOW(), updated_at = NOW() WHERE id = ?");
$stmt->execute([$ticketId]);

flash('info', 'Ticket has been safely closed and archived to preserve support audit history.');
redirect('modules/tickets/index.php');
