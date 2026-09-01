<?php
/**
 * System Activity Log Helper (support-mgt Phase 05)
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Log a system-level activity event
 *
 * @param int|null $userId
 * @param string $module
 * @param string $action
 * @param string $description
 * @param string|null $refType
 * @param int|null $refId
 * @return bool
 */
function log_activity(?int $userId, string $module, string $action, string $description, ?string $refType = null, ?int $refId = null): bool {
    try {
        $db = get_db();

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System', 0, 255);

        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, module, action, description, ip_address, user_agent, reference_type, reference_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $userId,
            $module,
            $action,
            $description,
            $ipAddress,
            $userAgent,
            $refType,
            $refId
        ]);
    } catch (Exception $e) {
        error_log("Failed to write system activity log: " . $e->getMessage());
        return false;
    }
}
