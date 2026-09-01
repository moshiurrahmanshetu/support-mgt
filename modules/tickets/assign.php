<?php
/**
 * Ticket Management - Assign Agent Handler (Admin Only, Integrated with Activity Logging)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/ticket_activity.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/tickets/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'modules/tickets/index.php');
}

$user = current_user();
$ticketId = (int)($_POST['ticket_id'] ?? 0);
$assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

if ($ticketId <= 0) {
    flash('danger', 'Invalid ticket ID.');
    redirect('modules/tickets/index.php');
}

$db = get_db();

// Verify Ticket exists with current agent
$ticketStmt = $db->prepare("
    SELECT t.id, t.assigned_to, u.name AS old_agent_name
    FROM tickets t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.id = ?
    LIMIT 1
");
$ticketStmt->execute([$ticketId]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found.');
    redirect('modules/tickets/index.php');
}

$oldAgentName = $ticket['old_agent_name'] ?: 'Unassigned';

// Validate Agent if specified
$newAgentName = 'Unassigned';
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

    $newAgentName = $agent['name'];
}

// Update Ticket Assignment
if ((int)($ticket['assigned_to'] ?? 0) !== (int)($assignedTo ?? 0)) {
    $updateStmt = $db->prepare("UPDATE tickets SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$assignedTo, $ticketId]);

    if ($assignedTo !== null) {
        log_ticket_activity($ticketId, $user['id'], 'ticket_assigned', $oldAgentName, $newAgentName, "Assigned to agent {$newAgentName}");
        flash('success', "Ticket assigned to agent <strong>" . e($newAgentName) . "</strong>.");
    } else {
        log_ticket_activity($ticketId, $user['id'], 'ticket_unassigned', $oldAgentName, null, "Ticket assignment removed");
        flash('info', "Ticket assignment removed (marked as unassigned).");
    }
} else {
    flash('info', "Ticket assignment remains unchanged.");
}

redirect('modules/tickets/view.php?id=' . $ticketId);
