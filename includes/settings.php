<?php
/**
 * System Settings Helper (support-mgt Phase 05)
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Static runtime cache for system settings
 */
class SystemSettingsCache {
    public static array $settings = [];
    public static bool $loaded = false;
}

/**
 * Load all settings into memory cache
 */
function load_system_settings(): array {
    if (SystemSettingsCache::$loaded) {
        return SystemSettingsCache::$settings;
    }

    try {
        $db = get_db();
        $stmt = $db->query("SELECT setting_key, setting_value, setting_type FROM settings");
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $val = $row['setting_value'];
            if ($row['setting_type'] === 'boolean') {
                $val = ($val === '1' || $val === 1 || $val === 'true');
            } elseif ($row['setting_type'] === 'integer') {
                $val = (int)$val;
            }
            SystemSettingsCache::$settings[$row['setting_key']] = $val;
        }

        SystemSettingsCache::$loaded = true;
    } catch (Exception $e) {
        // Fail-open default fallback
        error_log("Failed to load system settings: " . $e->getMessage());
    }

    return SystemSettingsCache::$settings;
}

/**
 * Get setting value by key with optional fallback
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get_setting(string $key, $default = null) {
    $settings = load_system_settings();
    return $settings[$key] ?? $default;
}

/**
 * Set/update a setting in the database
 *
 * @param string $key
 * @param mixed $value
 * @param string $type
 * @return bool
 */
function set_setting(string $key, $value, string $type = 'string'): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = NOW()
        ");

        $dbVal = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
        $result = $stmt->execute([$key, $dbVal, $type]);

        // Update in-memory cache
        SystemSettingsCache::$settings[$key] = $value;

        return $result;
    } catch (Exception $e) {
        error_log("Failed to set system setting {$key}: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all settings as key => value array
 *
 * @return array
 */
function get_all_settings(): array {
    return load_system_settings();
}
