<?php
/**
 * Permissions & Role-Based Access Control (RBAC) Helper (support-mgt Phase 08)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_check.php';

/**
 * Runtime memory cache for user permissions
 */
class PermissionCache {
    public static array $permissionsByUser = [];
    public static array $rolesByUser = [];
}

/**
 * Check if a user has Administrator role
 *
 * @param int|null $userId
 * @return bool
 */
function is_admin_user(?int $userId = null): bool {
    if ($userId === null) {
        $user = current_user();
        if (!$user) {
            return false;
        }
        $role = strtolower($user['role'] ?? '');
        return ($role === 'admin' || $role === 'administrator');
    }

    $db = get_db();
    $stmt = $db->prepare("
        SELECT u.role AS primary_role, r.slug AS assigned_slug 
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $pr = strtolower($r['primary_role'] ?? '');
        $as = strtolower($r['assigned_slug'] ?? '');
        if ($pr === 'admin' || $pr === 'administrator' || $as === 'admin' || $as === 'administrator') {
            return true;
        }
    }

    return false;
}

/**
 * Get all role slugs for a user
 *
 * @param int $userId
 * @return array
 */
function get_user_role_slugs(int $userId): array {
    if (isset(PermissionCache::$rolesByUser[$userId])) {
        return PermissionCache::$rolesByUser[$userId];
    }

    $db = get_db();
    $stmt = $db->prepare("
        SELECT r.slug 
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? AND r.status = 'active'
    ");
    $stmt->execute([$userId]);
    $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // Fallback to users.role if user_roles has no record
    if (empty($slugs)) {
        $uStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $pRole = $uStmt->fetchColumn();
        if ($pRole) {
            $slugs[] = ($pRole === 'admin') ? 'administrator' : (($pRole === 'agent') ? 'support_agent' : $pRole);
        }
    }

    PermissionCache::$rolesByUser[$userId] = $slugs;
    return $slugs;
}

/**
 * Get all permission slugs for a user
 *
 * @param int $userId
 * @return array
 */
function get_user_permissions(int $userId): array {
    if (isset(PermissionCache::$permissionsByUser[$userId])) {
        return PermissionCache::$permissionsByUser[$userId];
    }

    $db = get_db();

    // Administrator has all permissions automatically
    if (is_admin_user($userId)) {
        $pStmt = $db->query("SELECT slug FROM permissions");
        $allPerms = $pStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        PermissionCache::$permissionsByUser[$userId] = $allPerms;
        return $allPerms;
    }

    // Query permissions linked via roles and user_roles
    $stmt = $db->prepare("
        SELECT DISTINCT p.slug
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        JOIN user_roles ur ON rp.role_id = ur.role_id
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? AND r.status = 'active'
    ");
    $stmt->execute([$userId]);
    $perms = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // Fallback: If user_roles was empty, map using users.role column
    if (empty($perms)) {
        $uStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $pRole = $uStmt->fetchColumn();
        if ($pRole) {
            $normalizedSlug = ($pRole === 'admin') ? 'administrator' : (($pRole === 'agent') ? 'support_agent' : $pRole);
            $fbStmt = $db->prepare("
                SELECT DISTINCT p.slug
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN roles r ON rp.role_id = r.id
                WHERE r.slug = ? AND r.status = 'active'
            ");
            $fbStmt->execute([$normalizedSlug]);
            $perms = $fbStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
    }

    PermissionCache::$permissionsByUser[$userId] = $perms;
    return $perms;
}

/**
 * Check if a user has a specific permission
 *
 * @param string $permissionSlug
 * @param int|null $userId If null, checks current logged in user
 * @return bool
 */
function has_permission(string $permissionSlug, ?int $userId = null): bool {
    if ($userId === null) {
        $user = current_user();
        if (!$user) {
            return false;
        }
        $userId = (int)$user['id'];
    }

    // Super Administrator bypass
    if (is_admin_user($userId)) {
        return true;
    }

    $permissions = get_user_permissions($userId);
    return in_array($permissionSlug, $permissions, true);
}

/**
 * Guard page: Require user to have specific permission
 *
 * @param string $permissionSlug
 */
function require_permission(string $permissionSlug): void {
    require_login();

    if (!has_permission($permissionSlug)) {
        flash('danger', 'You do not have permission to access that resource.');
        redirect('index.php');
    }
}

/**
 * Check if the last active Administrator can be modified or deactivated
 *
 * @param int $targetUserId
 * @param string $newRole
 * @param string $newStatus
 * @return bool
 */
function can_modify_user_role_or_status(int $targetUserId, string $newRole, string $newStatus): bool {
    $db = get_db();
    
    // Check if target user is currently an active administrator
    $stmt = $db->prepare("SELECT role, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    $isCurrentlyAdmin = in_array(strtolower($user['role']), ['admin', 'administrator'], true) && $user['status'] === STATUS_ACTIVE;
    
    if ($isCurrentlyAdmin) {
        $willRemainAdmin = in_array(strtolower($newRole), ['admin', 'administrator'], true) && $newStatus === STATUS_ACTIVE;

        if (!$willRemainAdmin) {
            // Count total active admins in system
            $countStmt = $db->query("
                SELECT COUNT(DISTINCT u.id) 
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                WHERE (u.role IN ('admin', 'administrator') OR r.slug IN ('admin', 'administrator'))
                  AND u.status = 'active'
                  AND u.deleted_at IS NULL
            ");
            $activeAdminCount = (int)$countStmt->fetchColumn();

            if ($activeAdminCount <= 1) {
                return false; // Cannot remove or deactivate the last remaining active admin
            }
        }
    }

    return true;
}

/**
 * Check if a user can be deleted safely
 *
 * @param int $targetUserId
 * @return bool
 */
function can_delete_user(int $targetUserId): bool {
    return can_modify_user_role_or_status($targetUserId, 'customer', STATUS_INACTIVE);
}

/**
 * Fetch role record by slug
 *
 * @param string $slug
 * @return array|null
 */
function get_role_by_slug(string $slug): ?array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM roles WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/**
 * Assign a role to a user and sync primary role column
 *
 * @param int $userId
 * @param int $roleId
 * @return bool
 */
function assign_user_role(int $userId, int $roleId): bool {
    $db = get_db();
    
    // Fetch role slug
    $rStmt = $db->prepare("SELECT slug FROM roles WHERE id = ?");
    $rStmt->execute([$roleId]);
    $roleSlug = $rStmt->fetchColumn();

    if (!$roleSlug) {
        return false;
    }

    $db->beginTransaction();
    try {
        // 1. Clear previous primary roles in user_roles
        $delStmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $delStmt->execute([$userId]);

        // 2. Insert new user_role
        $insStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())");
        $insStmt->execute([$userId, $roleId]);

        // 3. Update users.role column
        $uStmt = $db->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
        $uStmt->execute([$roleSlug, $userId]);

        $db->commit();

        // Clear cache
        unset(PermissionCache::$permissionsByUser[$userId], PermissionCache::$rolesByUser[$userId]);
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Failed to assign role: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve all permissions grouped by module
 *
 * @return array
 */
function get_all_permissions_grouped(): array {
    $db = get_db();
    $stmt = $db->query("SELECT * FROM permissions ORDER BY module ASC, name ASC");
    $all = $stmt->fetchAll();

    $grouped = [];
    foreach ($all as $perm) {
        $grouped[$perm['module']][] = $perm;
    }
    return $grouped;
}
