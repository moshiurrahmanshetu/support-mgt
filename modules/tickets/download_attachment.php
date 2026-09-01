<?php
/**
 * Ticket Management - Secure Attachment Download Handler
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_login();

$user = current_user();
$attachmentId = (int)($_GET['id'] ?? 0);

if ($attachmentId <= 0) {
    http_response_code(404);
    die('Attachment not found.');
}

$db = get_db();
$stmt = $db->prepare("
    SELECT 
        a.*,
        t.user_id AS ticket_owner_id,
        m.message_type
    FROM ticket_attachments a
    JOIN tickets t ON a.ticket_id = t.id
    LEFT JOIN ticket_messages m ON a.message_id = m.id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch();

if (!$attachment) {
    http_response_code(404);
    die('Attachment not found.');
}

// Authorization Checks
if ($user['role'] === ROLE_CUSTOMER) {
    // 1. Must own the ticket
    if ((int)$attachment['ticket_owner_id'] !== (int)$user['id']) {
        http_response_code(403);
        die('Access denied.');
    }
    // 2. Customer must never access internal note attachments
    if ($attachment['message_type'] === MESSAGE_TYPE_NOTE) {
        http_response_code(403);
        die('Access denied.');
    }
}

$filePath = TICKET_UPLOAD_DIR . '/' . $attachment['stored_name'];

// Verify file exists on disk
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    die('Attachment file is missing from storage.');
}

// Clean output buffer before sending binary stream
if (ob_get_level()) {
    ob_end_clean();
}

$mimeType = !empty($attachment['mime_type']) ? $attachment['mime_type'] : 'application/octet-stream';
$filename = basename($attachment['original_name']);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;
