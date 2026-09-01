<?php
/**
 * Email Notification Infrastructure (support-mgt Phase 05)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/settings.php';

/**
 * Standard Email Templates
 */
function get_email_template(string $eventType): array {
    $templates = [
        'ticket_created' => [
            'subject' => '[{{app_name}}] New Ticket Created: #{{ticket_number}} - {{ticket_subject}}',
            'body'    => "Hello {{user_name}},\n\nA new support ticket has been created.\n\nTicket Number: {{ticket_number}}\nSubject: {{ticket_subject}}\nPriority: {{ticket_priority}}\nStatus: {{ticket_status}}\n\nYou can view and respond to this ticket here:\n{{ticket_url}}\n\nRegards,\n{{app_name}} Support Team"
        ],
        'ticket_assigned' => [
            'subject' => '[{{app_name}}] Ticket Assigned to You: #{{ticket_number}}',
            'body'    => "Hello {{user_name}},\n\nYou have been assigned to support ticket #{{ticket_number}}.\n\nSubject: {{ticket_subject}}\nPriority: {{ticket_priority}}\nStatus: {{ticket_status}}\n\nPlease review and attend to this inquiry:\n{{ticket_url}}\n\nRegards,\n{{app_name}} Support Team"
        ],
        'ticket_reply' => [
            'subject' => '[{{app_name}}] New Reply on Ticket #{{ticket_number}}',
            'body'    => "Hello {{user_name}},\n\nA new response has been posted to your support ticket #{{ticket_number}}.\n\nSubject: {{ticket_subject}}\n\nYou can read the full conversation and reply here:\n{{ticket_url}}\n\nRegards,\n{{app_name}} Support Team"
        ],
        'ticket_status' => [
            'subject' => '[{{app_name}}] Ticket Status Updated: #{{ticket_number}} is now {{ticket_status}}',
            'body'    => "Hello {{user_name}},\n\nThe status of your support ticket #{{ticket_number}} has been updated to: {{ticket_status}}.\n\nSubject: {{ticket_subject}}\n\nView details here:\n{{ticket_url}}\n\nRegards,\n{{app_name}} Support Team"
        ],
        'ticket_reopened' => [
            'subject' => '[{{app_name}}] Ticket Reopened: #{{ticket_number}}',
            'body'    => "Hello {{user_name}},\n\nSupport ticket #{{ticket_number}} has been reopened.\n\nSubject: {{ticket_subject}}\n\nView the active conversation here:\n{{ticket_url}}\n\nRegards,\n{{app_name}} Support Team"
        ]
    ];

    return $templates[$eventType] ?? [
        'subject' => '[{{app_name}}] Notification regarding ticket #{{ticket_number}}',
        'body'    => "Hello {{user_name}},\n\nThere is an update on your support ticket #{{ticket_number}}.\n\n{{ticket_url}}\n\nRegards,\n{{app_name}}"
    ];
}

/**
 * Check if a specific email notification type is enabled for a user
 *
 * @param int $userId
 * @param string $eventType
 * @return bool
 */
function is_user_email_notification_enabled(int $userId, string $eventType): bool {
    // Global master toggle
    if (!get_setting('mail_enabled', false) || !get_setting('enable_email_notifications', false)) {
        return false;
    }

    $fieldMap = [
        'ticket_created'  => 'email_ticket_created',
        'ticket_assigned' => 'email_ticket_assigned',
        'ticket_reply'    => 'email_ticket_reply',
        'ticket_status'   => 'email_ticket_status',
        'ticket_reopened' => 'email_ticket_reopened'
    ];

    $col = $fieldMap[$eventType] ?? null;
    if (!$col) {
        return true;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT {$col} FROM user_notification_preferences WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $pref = $stmt->fetch();

        if ($pref !== false && isset($pref[$col])) {
            return (bool)$pref[$col];
        }
    } catch (Exception $e) {
        error_log("Failed to query user email preferences: " . $e->getMessage());
    }

    return true; // Default enabled if no preference record exists yet
}

/**
 * Send an email notification (Safe, non-blocking fail-open)
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $eventType
 * @param array $placeholders
 * @param int|null $userId
 * @return bool
 */
function send_email_notification(string $toEmail, string $toName, string $eventType, array $placeholders = [], ?int $userId = null): bool {
    // Check global & user preference
    if ($userId !== null && !is_user_email_notification_enabled($userId, $eventType)) {
        return false;
    }

    if (!get_setting('mail_enabled', false)) {
        return false; // Email globally disabled
    }

    $template = get_email_template($eventType);

    // Merge default placeholders
    $defaults = [
        'app_name'        => get_setting('app_name', APP_NAME),
        'user_name'       => $toName,
        'ticket_number'   => '',
        'ticket_subject'  => '',
        'ticket_status'   => '',
        'ticket_priority' => '',
        'ticket_url'      => get_setting('app_url', APP_URL)
    ];

    $allPlaceholders = array_merge($defaults, $placeholders);

    // Replace template tokens
    $subject = $template['subject'];
    $body = $template['body'];

    foreach ($allPlaceholders as $k => $v) {
        $token = '{{' . $k . '}}';
        $subject = str_replace($token, (string)$v, $subject);
        $body = str_replace($token, (string)$v, $body);
    }

    $fromName = get_setting('mail_from_name', APP_NAME);
    $fromEmail = get_setting('mail_from_email', 'no-reply@supportmgt.local');

    $headers = [
        'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromEmail),
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8'
    ];

    try {
        // Native mail wrapper (Safe fail-open)
        $sent = @mail($toEmail, $subject, $body, implode("\r\n", $headers));
        if (!$sent) {
            error_log("Notice: Mail delivery to {$toEmail} could not be completed via native PHP mail handler.");
        }
        return $sent;
    } catch (Throwable $t) {
        error_log("Mail exception sending to {$toEmail}: " . $t->getMessage());
        return false;
    }
}
