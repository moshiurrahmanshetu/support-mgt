<?php
/**
 * Ticket Management - Assign Agent Handler (Admin Only, Integrated with Notifications & Logs - Phase 05)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/ticket_activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/activity_log.php';

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
    SELECT t.id, t.ticket_number, t.subject, t.priority, t.status, t.assigned_to, u.name AS old_agent_name
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
$agentEmail = '';
if ($assignedTo !== null) {
    $agentStmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.department_id, d.status AS dept_status
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
    $agentEmail = $agent['email'];
}

// Update Ticket Assignment
if ((int)($ticket['assigned_to'] ?? 0) !== (int)($assignedTo ?? 0)) {
    $updateStmt = $db->prepare("UPDATE tickets SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$assignedTo, $ticketId]);

    if ($assignedTo !== null) {
        log_ticket_activity($ticketId, $user['id'], 'ticket_assigned', $oldAgentName, $newAgentName, "Assigned to agent {$newAgentName}");
        log_activity($user['id'], 'ticket', 'ticket_assigned', "Assigned ticket #{$ticket['ticket_number']} to {$newAgentName}", 'ticket', $ticketId);

        // Notify Assigned Agent
        if ((int)$assignedTo !== (int)$user['id']) {
            create_notification(
                $assignedTo,
                "Ticket Assigned: #{$ticket['ticket_number']}",
                "You have been assigned to ticket #{$ticket['ticket_number']}: {$ticket['subject']}",
                NOTIF_TICKET_ASSIGNED,
                'ticket',
                $ticketId
            );

            send_email_notification(
                $agentEmail,
                $newAgentName,
                'ticket_assigned',
                [
                    'ticket_number'   => $ticket['ticket_number'],
                    'ticket_subject'  => $ticket['subject'],
                    'ticket_priority' => ucfirst($ticket['priority']),
                    'ticket_status'   => ucfirst($ticket['status']),
                    'ticket_url'      => url('modules/tickets/view.php?id=' . $ticketId)
                ],
                $assignedTo
            );
        }

        flash('success', "Ticket assigned to agent <strong>" . e($newAgentName) . "</strong>.");
    } else {
        log_ticket_activity($ticketId, $user['id'], 'ticket_unassigned', $oldAgentName, null, "Ticket assignment removed");
        log_activity($user['id'], 'ticket', 'ticket_unassigned', "Removed agent assignment from ticket #{$ticket['ticket_number']}", 'ticket', $ticketId);
        flash('info', "Ticket assignment removed (marked as unassigned).");
    }
} else {
    flash('info', "Ticket assignment remains unchanged.");
}

redirect('modules/tickets/view.php?id=' . $ticketId);
