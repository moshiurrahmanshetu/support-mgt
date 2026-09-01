<?php
/**
 * Ticket Management - Reply & Note Submission Handler (Integrated with Notifications, Email & Logs - Phase 05)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/ticket_activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/activity_log.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/tickets/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try submitting again.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'modules/tickets/index.php');
}

$user = current_user();
$ticketId = (int)($_POST['ticket_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$messageType = trim($_POST['message_type'] ?? MESSAGE_TYPE_REPLY);

if ($ticketId <= 0) {
    flash('danger', 'Invalid ticket reference.');
    redirect('modules/tickets/index.php');
}

$db = get_db();

// 1. Fetch and Authorize Ticket Access (joined with customer & agent data)
$ticketStmt = $db->prepare("
    SELECT t.*, u.name AS customer_name, u.email AS customer_email, a.name AS agent_name, a.email AS agent_email
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    WHERE t.id = ? 
    LIMIT 1
");
$ticketStmt->execute([$ticketId]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found.');
    redirect('modules/tickets/index.php');
}

// Customer Authorization (IDOR protection)
if ($user['role'] === ROLE_CUSTOMER) {
    if ((int)$ticket['user_id'] !== (int)$user['id']) {
        flash('danger', 'You do not have permission to post to this ticket.');
        redirect('modules/tickets/index.php');
    }
    // Strict enforcement: Customer can NEVER post internal notes
    $messageType = MESSAGE_TYPE_REPLY;
} else {
    // Validate staff message type
    if (!in_array($messageType, [MESSAGE_TYPE_REPLY, MESSAGE_TYPE_NOTE], true)) {
        $messageType = MESSAGE_TYPE_REPLY;
    }
}

// 2. Validate Message Content
if (empty($message)) {
    flash('danger', 'Please enter a message before submitting.');
    redirect('modules/tickets/view.php?id=' . $ticketId . '#replyForm');
}

// 3. Process Attachment (if uploaded)
$hasAttachment = false;
$attachmentFile = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'Attachment upload failed (Error Code: ' . $_FILES['attachment']['error'] . ').');
        redirect('modules/tickets/view.php?id=' . $ticketId . '#replyForm');
    }

    $attachmentFile = $_FILES['attachment'];

    if ($attachmentFile['size'] > MAX_TICKET_ATTACHMENT_SIZE) {
        flash('danger', 'Attachment exceeds maximum file size (10MB).');
        redirect('modules/tickets/view.php?id=' . $ticketId . '#replyForm');
    }

    $ext = strtolower(pathinfo($attachmentFile['name'], PATHINFO_EXTENSION));
    if (in_array($ext, BLOCKED_ATTACHMENT_EXTENSIONS, true)) {
        flash('danger', 'Prohibited executable/script file type (.'.$ext.').');
        redirect('modules/tickets/view.php?id=' . $ticketId . '#replyForm');
    } elseif (!in_array($ext, ALLOWED_ATTACHMENT_EXTENSIONS, true)) {
        flash('danger', 'Unsupported file extension (.'.$ext.'). Allowed: ' . implode(', ', ALLOWED_ATTACHMENT_EXTENSIONS));
        redirect('modules/tickets/view.php?id=' . $ticketId . '#replyForm');
    } else {
        $hasAttachment = true;
    }
}

// 4. Save Message & Attachment Transaction
try {
    $db->beginTransaction();

    // Insert Message
    $insertMsgStmt = $db->prepare("
        INSERT INTO ticket_messages (ticket_id, user_id, message, message_type, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $insertMsgStmt->execute([
        $ticketId,
        $user['id'],
        $message,
        $messageType
    ]);
    $messageId = (int)$db->lastInsertId();

    // Save Attachment if present
    if ($hasAttachment && $attachmentFile) {
        if (!is_dir(TICKET_UPLOAD_DIR)) {
            mkdir(TICKET_UPLOAD_DIR, 0755, true);
        }

        $ext = strtolower(pathinfo($attachmentFile['name'], PATHINFO_EXTENSION));
        $storedName = 'att_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = TICKET_UPLOAD_DIR . '/' . $storedName;

        if (move_uploaded_file($attachmentFile['tmp_name'], $destination)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($destination) ?: 'application/octet-stream';

            $insertAttStmt = $db->prepare("
                INSERT INTO ticket_attachments (ticket_id, message_id, uploaded_by, original_name, stored_name, file_path, mime_type, file_size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insertAttStmt->execute([
                $ticketId,
                $messageId,
                $user['id'],
                basename($attachmentFile['name']),
                $storedName,
                $storedName,
                $mimeType,
                $attachmentFile['size']
            ]);
        }
    }

    $isReopened = false;
    // A. Reopen Workflow: If customer replies to a closed/resolved ticket, automatically reopen ticket
    if ($user['role'] === ROLE_CUSTOMER && in_array($ticket['status'], [STATUS_RESOLVED, STATUS_CLOSED], true)) {
        $isReopened = true;
        $updateTicketStmt = $db->prepare("
            UPDATE tickets 
            SET status = ?, resolved_at = NULL, closed_at = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateTicketStmt->execute([STATUS_OPEN, $ticketId]);

        log_ticket_activity($ticketId, $user['id'], 'ticket_reopened', $ticket['status'], STATUS_OPEN, "Ticket reopened by customer reply");
    } else {
        // B. First Response Tracking: If staff (Admin/Agent) posts first public reply
        if (in_array($user['role'], [ROLE_ADMIN, ROLE_AGENT], true) && $messageType === MESSAGE_TYPE_REPLY && empty($ticket['first_response_at'])) {
            $updateFirstRespStmt = $db->prepare("
                UPDATE tickets 
                SET first_response_at = NOW(), updated_at = NOW() 
                WHERE id = ?
            ");
            $updateFirstRespStmt->execute([$ticketId]);
        } else {
            $touchStmt = $db->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
            $touchStmt->execute([$ticketId]);
        }
    }

    // Log Ticket Specific Activity (Reply or Note)
    if ($messageType === MESSAGE_TYPE_NOTE) {
        log_ticket_activity($ticketId, $user['id'], 'internal_note_added', null, null, "Internal staff note added");
        log_activity($user['id'], 'ticket', 'internal_note_added', "Added internal note on ticket #{$ticket['ticket_number']}", 'ticket', $ticketId);
    } else {
        log_ticket_activity($ticketId, $user['id'], 'reply_added', null, null, "Public reply posted");
        log_activity($user['id'], 'ticket', 'reply_added', "Posted reply on ticket #{$ticket['ticket_number']}", 'ticket', $ticketId);
    }

    // C. Dispatch Notifications & Emails
    $ticketUrl = url('modules/tickets/view.php?id=' . $ticketId);

    if ($user['role'] === ROLE_CUSTOMER) {
        // Customer posted reply -> notify assigned agent and admin
        $staffToNotify = [];

        if (!empty($ticket['assigned_to'])) {
            $staffToNotify[] = [
                'id'    => (int)$ticket['assigned_to'],
                'name'  => $ticket['agent_name'],
                'email' => $ticket['agent_email']
            ];
        }

        // Also fetch active Admins (except current user if any)
        $adminStmt = $db->prepare("SELECT id, name, email FROM users WHERE role = 'admin' AND status = 'active' AND id != ?");
        $adminStmt->execute([$user['id']]);
        $admins = $adminStmt->fetchAll();

        foreach ($admins as $adm) {
            // Avoid duplicate if admin is already the assigned agent
            if (empty($ticket['assigned_to']) || (int)$ticket['assigned_to'] !== (int)$adm['id']) {
                $staffToNotify[] = [
                    'id'    => (int)$adm['id'],
                    'name'  => $adm['name'],
                    'email' => $adm['email']
                ];
            }
        }

        foreach ($staffToNotify as $staff) {
            if ($isReopened) {
                create_notification(
                    $staff['id'],
                    "Ticket Reopened: #{$ticket['ticket_number']}",
                    "Customer {$user['name']} replied and reopened ticket: {$ticket['subject']}",
                    NOTIF_TICKET_REOPENED,
                    'ticket',
                    $ticketId
                );

                send_email_notification(
                    $staff['email'],
                    $staff['name'],
                    'ticket_reopened',
                    [
                        'ticket_number'  => $ticket['ticket_number'],
                        'ticket_subject' => $ticket['subject'],
                        'ticket_url'     => $ticketUrl
                    ],
                    $staff['id']
                );
            } else {
                create_notification(
                    $staff['id'],
                    "Customer Reply: #{$ticket['ticket_number']}",
                    "{$user['name']} replied on ticket: {$ticket['subject']}",
                    NOTIF_TICKET_REPLY,
                    'ticket',
                    $ticketId
                );

                send_email_notification(
                    $staff['email'],
                    $staff['name'],
                    'ticket_reply',
                    [
                        'ticket_number'  => $ticket['ticket_number'],
                        'ticket_subject' => $ticket['subject'],
                        'ticket_url'     => $ticketUrl
                    ],
                    $staff['id']
                );
            }
        }
    } else {
        // Staff posted -> if public reply, notify Customer (Never notify customer for internal notes!)
        if ($messageType === MESSAGE_TYPE_REPLY && (int)$ticket['user_id'] !== (int)$user['id']) {
            create_notification(
                (int)$ticket['user_id'],
                "New Support Reply: #{$ticket['ticket_number']}",
                "A support agent replied to your ticket: {$ticket['subject']}",
                NOTIF_TICKET_REPLY,
                'ticket',
                $ticketId
            );

            send_email_notification(
                $ticket['customer_email'],
                $ticket['customer_name'],
                'ticket_reply',
                [
                    'ticket_number'  => $ticket['ticket_number'],
                    'ticket_subject' => $ticket['subject'],
                    'ticket_url'     => $ticketUrl
                ],
                (int)$ticket['user_id']
            );
        }
    }

    $db->commit();

    $notice = ($messageType === MESSAGE_TYPE_NOTE) ? 'Internal staff note added.' : 'Your reply has been posted successfully.';
    flash('success', $notice);
    redirect('modules/tickets/view.php?id=' . $ticketId . '#message-' . $messageId);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    flash('danger', 'Failed to save response: ' . $e->getMessage());
    redirect('modules/tickets/view.php?id=' . $ticketId);
}
