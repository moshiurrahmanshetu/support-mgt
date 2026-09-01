<?php
/**
 * Ticket Management - Reply & Note Submission Handler
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

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

// 1. Fetch and Authorize Ticket Access
$ticketStmt = $db->prepare("SELECT * FROM tickets WHERE id = ? LIMIT 1");
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

    // If customer replies to a closed/resolved ticket, automatically reopen ticket
    if ($user['role'] === ROLE_CUSTOMER && in_array($ticket['status'], [STATUS_RESOLVED, STATUS_CLOSED], true)) {
        $updateTicketStmt = $db->prepare("
            UPDATE tickets 
            SET status = ?, resolved_at = NULL, closed_at = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateTicketStmt->execute([STATUS_OPEN, $ticketId]);
    } else {
        // Just touch updated_at
        $touchStmt = $db->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
        $touchStmt->execute([$ticketId]);
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
