<?php
/**
 * Core Helper Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

// Safe Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false, // Set to true if on HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Escape string for safe HTML output
 */
function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate absolute URL for the application without duplicate subfolder prefixing
 */
function url(string $path = ''): string {
    // If it is already a full URL (http:// or https://), return it directly
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $baseUrl = rtrim(APP_URL, '/');
    
    // Extract base URL path (e.g. '/support-mgt')
    $basePath = parse_url($baseUrl, PHP_URL_PATH);
    $basePathClean = !empty($basePath) ? trim($basePath, '/') : '';

    $cleanPath = trim($path, '/');

    // If $cleanPath starts with the project directory name, strip it to prevent duplicate path concatenation
    if (!empty($basePathClean)) {
        if ($cleanPath === $basePathClean) {
            $cleanPath = '';
        } elseif (strpos($cleanPath, $basePathClean . '/') === 0) {
            $cleanPath = substr($cleanPath, strlen($basePathClean) + 1);
            $cleanPath = trim($cleanPath, '/');
        }
    }

    return empty($cleanPath) ? $baseUrl . '/' : $baseUrl . '/' . $cleanPath;
}

/**
 * Redirect to a given URL or internal path
 */
function redirect(string $path): void {
    $targetUrl = url($path);
    header('Location: ' . $targetUrl);
    exit;
}

/**
 * Add a flash message to session
 */
function flash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash messages
 */
function get_flashes(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Set old form inputs in session
 */
function set_old_input(array $data): void {
    // Avoid storing passwords in old input
    unset($data['password'], $data['confirm_password'], $data['current_password'], $data['new_password'], $data['csrf_token']);
    $_SESSION['old_input'] = $data;
}

/**
 * Retrieve old input value
 */
function old(string $key, $default = ''): string {
    $value = $_SESSION['old_input'][$key] ?? $default;
    return (string)$value;
}

/**
 * Clear old form inputs from session
 */
function clear_old_input(): void {
    unset($_SESSION['old_input']);
}

/**
 * Generate cryptographically secure random token
 */
function generate_token(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Format datetime string into human readable format
 */
function format_datetime(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (empty($datetime)) {
        return 'Never';
    }
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$datetime;
    }
}

/**
 * Get public avatar URL for a given avatar filename
 */
function get_avatar_url(?string $avatarFilename): string {
    if (!empty($avatarFilename)) {
        $filePath = AVATAR_UPLOAD_DIR . '/' . $avatarFilename;
        if (file_exists($filePath)) {
            return AVATAR_URL_PATH . '/' . $avatarFilename;
        }
    }
    return DEFAULT_AVATAR_PATH;
}

/**
 * Sanitize basic string input
 */
function sanitize_input(string $data): string {
    return trim(strip_tags($data));
}

/**
 * Render solid badge for Ticket Status
 */
function render_status_badge(string $status): string {
    $status = strtolower($status);
    $badges = [
        STATUS_OPEN        => '<span class="badge badge-status-open"><i class="bi bi-circle-fill fs-8 me-1"></i>Open</span>',
        STATUS_IN_PROGRESS => '<span class="badge badge-status-in-progress"><i class="bi bi-arrow-repeat me-1"></i>In Progress</span>',
        STATUS_PENDING     => '<span class="badge badge-status-pending"><i class="bi bi-hourglass-split me-1"></i>Pending</span>',
        STATUS_RESOLVED    => '<span class="badge badge-status-resolved"><i class="bi bi-check2-circle me-1"></i>Resolved</span>',
        STATUS_CLOSED      => '<span class="badge badge-status-closed"><i class="bi bi-lock-fill me-1"></i>Closed</span>',
    ];

    return $badges[$status] ?? '<span class="badge bg-secondary">' . e($status) . '</span>';
}

/**
 * Render solid badge for Ticket Priority
 */
function render_priority_badge(string $priority): string {
    $priority = strtolower($priority);
    $badges = [
        PRIORITY_LOW    => '<span class="badge badge-priority-low"><i class="bi bi-arrow-down-short"></i>Low</span>',
        PRIORITY_MEDIUM => '<span class="badge badge-priority-medium"><i class="bi bi-dash"></i>Medium</span>',
        PRIORITY_HIGH   => '<span class="badge badge-priority-high"><i class="bi bi-arrow-up-short"></i>High</span>',
        PRIORITY_URGENT => '<span class="badge badge-priority-urgent"><i class="bi bi-exclamation-triangle-fill me-1"></i>Urgent</span>',
    ];

    return $badges[$priority] ?? '<span class="badge bg-secondary">' . e($priority) . '</span>';
}

/**
 * Format bytes into readable string (KB, MB, GB)
 */
function format_file_size(int $bytes): string {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Calculate safe pagination parameters (Prevents division by zero, invalid page/limit/offset)
 *
 * @param int $totalRecords
 * @param int $defaultPerPage
 * @param array $allowedPerPage
 * @return array [ 'page' => int, 'per_page' => int, 'total_pages' => int, 'offset' => int, 'total_records' => int ]
 */
function get_pagination_params(int $totalRecords, int $defaultPerPage = 20, array $allowedPerPage = [20, 50, 100]): array {
    $rawPerPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : $defaultPerPage;
    $perPage = in_array($rawPerPage, $allowedPerPage, true) ? $rawPerPage : $defaultPerPage;
    if ($perPage <= 0) {
        $perPage = $defaultPerPage;
    }

    $totalPages = max(1, (int)ceil($totalRecords / $perPage));

    $rawPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $page = max(1, $rawPage);
    if ($totalRecords > 0 && $page > $totalPages) {
        $page = $totalPages;
    }

    $offset = max(0, ($page - 1) * $perPage);

    return [
        'page'          => $page,
        'per_page'      => $perPage,
        'total_pages'   => $totalPages,
        'offset'        => $offset,
        'total_records' => $totalRecords
    ];
}

/**
 * Retrieve previously submitted form input.
 */
function get_old_input(): array
{
    return $_SESSION['old_input'] ?? [];
}

/**
 * Retrieve the count of new / unseen customer support tickets requiring Admin attention
 *
 * @return int
 */
function get_new_customer_ticket_count(): int {
    static $cachedCount = null;
    if ($cachedCount !== null) {
        return $cachedCount;
    }

    $db = get_db();
    try {
        $stmt = $db->query("
            SELECT COUNT(*) 
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            WHERE u.role = 'customer'
              AND t.admin_viewed_at IS NULL
        ");
        $cachedCount = (int)$stmt->fetchColumn();
        return $cachedCount;
    } catch (Exception $e) {
        return 0;
    }
}

