<?php
/**
 * Ticket Management - Update Ticket Status & Priority Handler (Integrated with Activity Logging)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/ticket_activity.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/tickets/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'modules/tickets/index.php');
}

$user = current_user();

// Strict authorization: Only Admin and Agent can change ticket status/priority
if ($user['role'] === ROLE_CUSTOMER) {
    flash('danger', 'You do not have permission to modify ticket properties.');
    redirect('modules/tickets/index.php');
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$action = trim($_POST['action'] ?? 'update_status');

if ($ticketId <= 0) {
    flash('danger', 'Invalid ticket reference.');
    redirect('modules/tickets/index.php');
}

$db = get_db();
$ticketStmt = $db->prepare("SELECT * FROM tickets WHERE id = ? LIMIT 1");
$ticketStmt->execute([$ticketId]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found.');
    redirect('modules/tickets/index.php');
}

// 1. Handle Status Update
if ($action === 'update_status') {
    $newStatus = trim($_POST['status'] ?? '');

    if (!in_array($newStatus, VALID_TICKET_STATUSES, true)) {
        flash('danger', 'Invalid status value provided.');
        redirect('modules/tickets/view.php?id=' . $ticketId);
    }

    if ($ticket['status'] !== $newStatus) {
        $resolvedAt = $ticket['resolved_at'];
        $closedAt = $ticket['closed_at'];

        if ($newStatus === STATUS_RESOLVED) {
            $resolvedAt = date('Y-m-d H:i:s');
        } elseif ($newStatus === STATUS_CLOSED) {
            $closedAt = date('Y-m-d H:i:s');
            if (empty($resolvedAt)) {
                $resolvedAt = date('Y-m-d H:i:s');
            }
        } elseif (in_array($newStatus, [STATUS_OPEN, STATUS_IN_PROGRESS, STATUS_PENDING], true)) {
            // If transitioning back, clear closed/resolved timestamps
            if ($ticket['status'] === STATUS_CLOSED) {
                $closedAt = null;
            }
            if ($ticket['status'] === STATUS_RESOLVED) {
                $resolvedAt = null;
            }
        }

        $updateStmt = $db->prepare("
            UPDATE tickets 
            SET status = ?, resolved_at = ?, closed_at = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([
            $newStatus,
            $resolvedAt,
            $closedAt,
            $ticketId
        ]);

        log_ticket_activity(
            $ticketId,
            $user['id'],
            'status_changed',
            $ticket['status'],
            $newStatus,
            "Status changed from " . ucfirst(str_replace('_', ' ', $ticket['status'])) . " to " . ucfirst(str_replace('_', ' ', $newStatus))
        );

        flash('success', 'Ticket status updated to <strong>' . ucfirst(str_replace('_', ' ', $newStatus)) . '</strong>.');
    } else {
        flash('info', 'Ticket status is already ' . ucfirst(str_replace('_', ' ', $newStatus)) . '.');
    }
}

// 2. Handle Priority Update
if ($action === 'update_priority') {
    $newPriority = trim($_POST['priority'] ?? '');

    if (!in_array($newPriority, VALID_PRIORITIES, true)) {
        flash('danger', 'Invalid priority level provided.');
        redirect('modules/tickets/view.php?id=' . $ticketId);
    }

    if ($ticket['priority'] !== $newPriority) {
        $updateStmt = $db->prepare("UPDATE tickets SET priority = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newPriority, $ticketId]);

        log_ticket_activity(
            $ticketId,
            $user['id'],
            'priority_changed',
            $ticket['priority'],
            $newPriority,
            "Priority changed from " . ucfirst($ticket['priority']) . " to " . ucfirst($newPriority)
        );

        flash('success', 'Ticket priority updated to <strong>' . ucfirst($newPriority) . '</strong>.');
    } else {
        flash('info', 'Ticket priority is already ' . ucfirst($newPriority) . '.');
    }
}

redirect('modules/tickets/view.php?id=' . $ticketId);
