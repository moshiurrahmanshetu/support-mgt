<?php
/**
 * Ticket Management - Assign Agent Handler (Admin Only)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/tickets/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'modules/tickets/index.php');
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

if ($ticketId <= 0) {
    flash('danger', 'Invalid ticket ID.');
    redirect('modules/tickets/index.php');
}

$db = get_db();

// Verify Ticket exists
$ticketStmt = $db->prepare("SELECT id, department_id FROM tickets WHERE id = ? LIMIT 1");
$ticketStmt->execute([$ticketId]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found.');
    redirect('modules/tickets/index.php');
}

// Validate Agent if specified
$agentName = 'Unassigned';
if ($assignedTo !== null) {
    $agentStmt = $db->prepare("
        SELECT u.id, u.name, u.department_id, d.status AS dept_status
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.id = ? AND u.role = 'agent' AND u.status = 'active' 
        LIMIT 1
    ");
    $agentStmt->execute([$assignedTo]);
    $agent = $agentStmt->fetch();

    if (!$agent) {
        flash('danger', 'Selected user is not an active support agent.');
        redirect('modules/tickets/view.php?id=' . $ticketId);
    }

    // Ensure agent is not assigned to an inactive department if they belong to one
    if (!empty($agent['department_id']) && $agent['dept_status'] === STATUS_INACTIVE) {
        flash('danger', 'Cannot assign an agent belonging to an inactive department.');
        redirect('modules/tickets/view.php?id=' . $ticketId);
    }

    $agentName = $agent['name'];
}

// Update Ticket Assignment
$updateStmt = $db->prepare("UPDATE tickets SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$assignedTo, $ticketId]);

if ($assignedTo !== null) {
    flash('success', "Ticket assigned to agent <strong>" . e($agentName) . "</strong>.");
} else {
    flash('info', "Ticket assignment removed (marked as unassigned).");
}

redirect('modules/tickets/view.php?id=' . $ticketId);
