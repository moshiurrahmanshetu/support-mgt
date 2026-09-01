<?php
/**
 * Ticket Activity Logger & Workflow Metrics Helper
 */

require_once __DIR__ . '/functions.php';

/**
 * Log an internal operational ticket activity event
 *
 * @param int         $ticketId
 * @param int|null    $userId
 * @param string      $action       e.g. ticket_created, ticket_assigned, status_changed, etc.
 * @param string|null $oldValue
 * @param string|null $newValue
 * @param string|null $description
 * @return bool
 */
function log_ticket_activity(
    int $ticketId,
    ?int $userId,
    string $action,
    ?string $oldValue = null,
    ?string $newValue = null,
    ?string $description = null
): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO ticket_activity_logs (ticket_id, user_id, action, old_value, new_value, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $ticketId,
            $userId,
            $action,
            $oldValue,
            $newValue,
            $description
        ]);
    } catch (Exception $e) {
        // Safe fail-open for activity logging: Log error if debugging is active
        error_log("Failed to log ticket activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Format time interval between two datetimes into human-readable duration
 *
 * @param string|null $fromTime
 * @param string|null $toTime
 * @return string
 */
function format_duration(?string $fromTime, ?string $toTime): string {
    if (empty($fromTime) || empty($toTime)) {
        return 'Not recorded';
    }

    $from = new DateTime($fromTime);
    $to = new DateTime($toTime);
    $interval = $from->diff($to);

    $parts = [];
    if ($interval->d > 0) {
        $parts[] = $interval->d . 'd';
    }
    if ($interval->h > 0) {
        $parts[] = $interval->h . 'h';
    }
    if ($interval->i > 0 || empty($parts)) {
        $parts[] = $interval->i . 'm';
    }

    return implode(' ', $parts);
}

/**
 * Format activity log action into user-friendly badge/text
 *
 * @param array $log
 * @return array [ 'icon' => string, 'class' => string, 'text' => string ]
 */
function format_activity_event(array $log): array {
    $action = $log['action'];
    $userName = !empty($log['user_name']) ? e($log['user_name']) : 'System';

    switch ($action) {
        case 'ticket_created':
            return [
                'icon'  => 'bi-plus-circle-fill',
                'class' => 'text-primary',
                'text'  => "Ticket was created by <strong>{$userName}</strong>"
            ];
        case 'ticket_reopened':
            return [
                'icon'  => 'bi-arrow-repeat',
                'class' => 'text-warning',
                'text'  => "Ticket was <strong>reopened</strong> by <strong>{$userName}</strong>"
            ];
        case 'status_changed':
            $old = ucfirst(str_replace('_', ' ', $log['old_value'] ?? ''));
            $new = ucfirst(str_replace('_', ' ', $log['new_value'] ?? ''));
            return [
                'icon'  => 'bi-toggle-on',
                'class' => 'text-info',
                'text'  => "Status changed from <span class='badge bg-light text-dark border'>{$old}</span> to <span class='badge bg-light text-dark border'>{$new}</span> by <strong>{$userName}</strong>"
            ];
        case 'priority_changed':
            $old = ucfirst($log['old_value'] ?? '');
            $new = ucfirst($log['new_value'] ?? '');
            return [
                'icon'  => 'bi-exclamation-triangle-fill',
                'class' => 'text-warning',
                'text'  => "Priority changed from <span class='badge bg-light text-dark border'>{$old}</span> to <span class='badge bg-light text-dark border'>{$new}</span> by <strong>{$userName}</strong>"
            ];
        case 'ticket_assigned':
            $new = e($log['new_value'] ?? 'Agent');
            return [
                'icon'  => 'bi-person-check-fill',
                'class' => 'text-primary',
                'text'  => "Assigned to <strong>{$new}</strong> by <strong>{$userName}</strong>"
            ];
        case 'ticket_unassigned':
            return [
                'icon'  => 'bi-person-x-fill',
                'class' => 'text-secondary',
                'text'  => "Assignment removed by <strong>{$userName}</strong>"
            ];
        case 'department_changed':
            $old = e($log['old_value'] ?? 'General');
            $new = e($log['new_value'] ?? 'General');
            return [
                'icon'  => 'bi-building',
                'class' => 'text-secondary',
                'text'  => "Department moved from <strong>{$old}</strong> to <strong>{$new}</strong> by <strong>{$userName}</strong>"
            ];
        case 'tag_added':
            $tag = e($log['new_value'] ?? 'Tag');
            return [
                'icon'  => 'bi-tag-fill',
                'class' => 'text-success',
                'text'  => "Added tag <span class='badge bg-light text-dark border'>{$tag}</span> by <strong>{$userName}</strong>"
            ];
        case 'tag_removed':
            $tag = e($log['old_value'] ?? 'Tag');
            return [
                'icon'  => 'bi-tag',
                'class' => 'text-danger',
                'text'  => "Removed tag <span class='badge bg-light text-dark border'>{$tag}</span> by <strong>{$userName}</strong>"
            ];
        case 'reply_added':
            return [
                'icon'  => 'bi-chat-dots-fill',
                'class' => 'text-primary',
                'text'  => "Public reply posted by <strong>{$userName}</strong>"
            ];
        case 'internal_note_added':
            return [
                'icon'  => 'bi-lock-fill',
                'class' => 'text-warning',
                'text'  => "Internal note added by <strong>{$userName}</strong>"
            ];
        default:
            $desc = !empty($log['description']) ? e($log['description']) : str_replace('_', ' ', $action);
            return [
                'icon'  => 'bi-info-circle-fill',
                'class' => 'text-secondary',
                'text'  => "{$desc} by <strong>{$userName}</strong>"
            ];
    }
}
