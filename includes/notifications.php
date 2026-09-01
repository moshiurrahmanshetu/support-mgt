<?php
/**
 * In-App Notification Helper (support-mgt Phase 05)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/settings.php';

// Valid Notification Types
const NOTIF_TICKET_CREATED        = 'ticket_created';
const NOTIF_TICKET_ASSIGNED       = 'ticket_assigned';
const NOTIF_TICKET_REPLY          = 'ticket_reply';
const NOTIF_TICKET_INTERNAL_NOTE  = 'ticket_internal_note';
const NOTIF_TICKET_STATUS_CHANGED = 'ticket_status_changed';
const NOTIF_TICKET_REOPENED       = 'ticket_reopened';
const NOTIF_SYSTEM                = 'system';

/**
 * Check if in-app notifications are enabled for a user
 *
 * @param int $userId
 * @return bool
 */
function is_in_app_notification_enabled(int $userId): bool {
    // Global setting check
    if (!get_setting('enable_in_app_notifications', true)) {
        return false;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT in_app_enabled FROM user_notification_preferences WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $pref = $stmt->fetch();

        if ($pref !== false) {
            return (bool)$pref['in_app_enabled'];
        }
    } catch (Exception $e) {
        error_log("Failed to check user notification preferences: " . $e->getMessage());
    }

    return true; // Default enabled
}

/**
 * Create an in-app notification record for a user
 *
 * @param int $userId
 * @param string $title
 * @param string $message
 * @param string $type
 * @param string|null $refType
 * @param int|null $refId
 * @return bool
 */
function create_notification(int $userId, string $title, string $message, string $type, ?string $refType = null, ?int $refId = null): bool {
    if (!is_in_app_notification_enabled($userId)) {
        return false;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([
            $userId,
            $title,
            $message,
            $type,
            $refType,
            $refId
        ]);
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get count of unread notifications for a user
 *
 * @param int $userId
 * @return int
 */
function get_unread_notifications_count(int $userId): int {
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Failed to fetch unread notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Fetch recent notifications for topbar dropdown
 *
 * @param int $userId
 * @param int $limit
 * @return array
 */
function get_recent_notifications(int $userId, int $limit = 5): array {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to fetch recent notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Format notification metadata for display (icon, color, URL)
 *
 * @param array $notification
 * @return array
 */
function format_notification_meta(array $notification): array {
    $type = $notification['type'] ?? 'system';
    $refType = $notification['reference_type'] ?? '';
    $refId = $notification['reference_id'] ?? 0;

    $url = '#';
    if ($refType === 'ticket' && $refId > 0) {
        $url = url('modules/tickets/view.php?id=' . $refId);
    }

    $icon = 'bi-bell';
    $colorClass = 'text-primary';

    switch ($type) {
        case NOTIF_TICKET_CREATED:
            $icon = 'bi-ticket-perforated-fill';
            $colorClass = 'text-primary';
            break;
        case NOTIF_TICKET_ASSIGNED:
            $icon = 'bi-person-check-fill';
            $colorClass = 'text-info';
            break;
        case NOTIF_TICKET_REPLY:
            $icon = 'bi-chat-left-text-fill';
            $colorClass = 'text-success';
            break;
        case NOTIF_TICKET_INTERNAL_NOTE:
            $icon = 'bi-lock-fill';
            $colorClass = 'text-warning';
            break;
        case NOTIF_TICKET_STATUS_CHANGED:
            $icon = 'bi-arrow-repeat';
            $colorClass = 'text-secondary';
            break;
        case NOTIF_TICKET_REOPENED:
            $icon = 'bi-arrow-counterclockwise';
            $colorClass = 'text-danger';
            break;
        case NOTIF_SYSTEM:
        default:
            $icon = 'bi-info-circle-fill';
            $colorClass = 'text-secondary';
            break;
    }

    return [
        'icon'        => $icon,
        'color_class' => $colorClass,
        'url'         => $url
    ];
}
