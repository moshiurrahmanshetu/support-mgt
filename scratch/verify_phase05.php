<?php
/**
 * Automated Verification Test Suite for Phase 05
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/activity_log.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assert_test($description, $condition, $details = '') {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$description}" . ($details ? " - {$details}" : "") . "\n";
    }
}

echo "====================================================\n";
echo "SUPPORT-MGT: PHASE 05 AUTOMATED VERIFICATION SUITE\n";
echo "====================================================\n\n";

$db = get_db();

// ----------------------------------------------------
// SECTION 1: LINT ALL PHP FILES
// ----------------------------------------------------
echo "--- Section 1: PHP Syntax Validation ---\n";
$phpFiles = [];
$dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__)));
foreach ($dirIterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}

$syntaxFailures = 0;
foreach ($phpFiles as $file) {
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnVar);
    $isOk = ($returnVar === 0 && strpos(implode("\n", $output), 'No syntax errors detected') !== false);
    if (!$isOk) {
        $syntaxFailures++;
        echo "  [FAIL] Syntax error in: {$file}\n";
    }
}
assert_test("All " . count($phpFiles) . " PHP files pass syntax check (php -l)", $syntaxFailures === 0);

// ----------------------------------------------------
// SECTION 2: PAGINATION SAFETY & EDGE CASES
// ----------------------------------------------------
echo "\n--- Section 2: Pagination Engine Hardening ---\n";

// Case 1: Standard input
$_GET = [];
$p1 = get_pagination_params(100, 20);
assert_test("Default pagination params (page: 1, per_page: 20, total_pages: 5, offset: 0)", 
    $p1['page'] === 1 && $p1['per_page'] === 20 && $p1['total_pages'] === 5 && $p1['offset'] === 0);

// Case 2: per_page = 0 (Must not divide by zero!)
$_GET['per_page'] = 0;
$p2 = get_pagination_params(100, 20);
assert_test("per_page = 0 falls back safely to 20 without division by zero", $p2['per_page'] === 20 && $p2['total_pages'] === 5);

// Case 3: per_page = -10
$_GET['per_page'] = -10;
$p3 = get_pagination_params(100, 20);
assert_test("per_page = -10 falls back safely to 20", $p3['per_page'] === 20);

// Case 4: per_page = 'abc'
$_GET['per_page'] = 'abc';
$p4 = get_pagination_params(100, 20);
assert_test("per_page = 'abc' falls back safely to 20", $p4['per_page'] === 20);

// Case 5: Valid per_page = 50 and 100
$_GET['per_page'] = 50;
$p5 = get_pagination_params(100, 20);
assert_test("per_page = 50 accepted (total_pages: 2)", $p5['per_page'] === 50 && $p5['total_pages'] === 2);

$_GET['per_page'] = 100;
$p6 = get_pagination_params(100, 20);
assert_test("per_page = 100 accepted (total_pages: 1)", $p6['per_page'] === 100 && $p6['total_pages'] === 1);

// Case 6: page = 0 and page = -5
$_GET['page'] = 0;
$_GET['per_page'] = 20;
$p7 = get_pagination_params(100, 20);
assert_test("page = 0 clamped to 1 (offset: 0)", $p7['page'] === 1 && $p7['offset'] === 0);

$_GET['page'] = -5;
$p8 = get_pagination_params(100, 20);
assert_test("page = -5 clamped to 1 (offset: 0)", $p8['page'] === 1 && $p8['offset'] === 0);

// Case 7: page > total_pages
$_GET['page'] = 9999;
$p9 = get_pagination_params(50, 20); // total_pages = 3
assert_test("page = 9999 clamped to max page 3 (offset: 40)", $p9['page'] === 3 && $p9['offset'] === 40);

// Case 8: totalRecords = 0
$_GET = [];
$p10 = get_pagination_params(0, 20);
assert_test("totalRecords = 0 produces safe total_pages: 1 and offset: 0", $p10['total_pages'] === 1 && $p10['offset'] === 0);

// ----------------------------------------------------
// SECTION 3: DATABASE SCHEMA VERIFICATION
// ----------------------------------------------------
echo "\n--- Section 3: Phase 05 Database Schema ---\n";

$stmt = $db->query("SHOW TABLES LIKE 'notifications'");
assert_test("notifications table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW TABLES LIKE 'user_notification_preferences'");
assert_test("user_notification_preferences table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW TABLES LIKE 'activity_logs'");
assert_test("activity_logs table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW TABLES LIKE 'settings'");
assert_test("settings table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SELECT COUNT(*) FROM settings");
$settingsCount = (int)$stmt->fetchColumn();
assert_test("Initial settings seeded (found: {$settingsCount})", $settingsCount >= 15);

// ----------------------------------------------------
// SECTION 4: IN-APP NOTIFICATIONS SYSTEM
// ----------------------------------------------------
echo "\n--- Section 4: In-App Notification System ---\n";

// Create test user for notification tests
$testEmail = 'notif_user_' . bin2hex(random_bytes(3)) . '@supportmgt.local';
$stmt = $db->prepare("INSERT INTO users (role, name, email, password, status, created_at, updated_at) VALUES ('customer', 'Notif Tester', ?, 'dummy_hash', 'active', NOW(), NOW())");
$stmt->execute([$testEmail]);
$testUserId = (int)$db->lastInsertId();

// 1. Create Notification
$notifCreated = create_notification($testUserId, "Test Ticket Notification", "Your ticket has been updated", NOTIF_TICKET_REPLY, 'ticket', 101);
assert_test("create_notification() executed successfully", $notifCreated === true);

// 2. Unread Count
$unreadCount = get_unread_notifications_count($testUserId);
assert_test("get_unread_notifications_count() returns 1", $unreadCount === 1);

// 3. Recent Notifications
$recent = get_recent_notifications($testUserId, 5);
assert_test("get_recent_notifications() returned test notification", count($recent) === 1 && $recent[0]['title'] === "Test Ticket Notification");
$notifId = (int)$recent[0]['id'];

// 4. Mark Single as Read
$stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$stmt->execute([$notifId, $testUserId]);
$unreadCountAfter = get_unread_notifications_count($testUserId);
assert_test("Notification marked as read (unread count is now 0)", $unreadCountAfter === 0);

// 5. Mark All as Read (Create 2 more, mark all)
create_notification($testUserId, "Alert 1", "Msg 1", NOTIF_SYSTEM);
create_notification($testUserId, "Alert 2", "Msg 2", NOTIF_SYSTEM);
$unreadCount2 = get_unread_notifications_count($testUserId);
assert_test("Created 2 new notifications (unread count: 2)", $unreadCount2 === 2);

$stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->execute([$testUserId]);
assert_test("Mark all read reset unread count to 0", get_unread_notifications_count($testUserId) === 0);

// ----------------------------------------------------
// SECTION 5: NOTIFICATION PREFERENCES
// ----------------------------------------------------
echo "\n--- Section 5: User Notification Preferences ---\n";

$stmt = $db->prepare("
    INSERT INTO user_notification_preferences (user_id, in_app_enabled, email_ticket_created, email_ticket_reply, updated_at)
    VALUES (?, 0, 1, 0, NOW())
    ON DUPLICATE KEY UPDATE in_app_enabled = 0, email_ticket_reply = 0
");
$stmt->execute([$testUserId]);

assert_test("is_in_app_notification_enabled() respects user preference (false)", is_in_app_notification_enabled($testUserId) === false);

// ----------------------------------------------------
// SECTION 6: EMAIL INFRASTRUCTURE & TEMPLATES
// ----------------------------------------------------
echo "\n--- Section 6: Email Notification Infrastructure ---\n";

$tpl = get_email_template('ticket_created');
assert_test("get_email_template('ticket_created') returns subject and body", !empty($tpl['subject']) && !empty($tpl['body']));

// Test fail-safe email execution
$emailResult = send_email_notification('test@supportmgt.local', 'Test User', 'ticket_created', [
    'ticket_number'  => 'TKT-100001',
    'ticket_subject' => 'Email Test',
    'ticket_status'  => 'Open'
]);
// Since mail_enabled is 0 by default, it should return false gracefully without exception
assert_test("send_email_notification() handles mail_enabled=0 gracefully without throwing exceptions", $emailResult === false);

// ----------------------------------------------------
// SECTION 7: SYSTEM ACTIVITY LOGGING
// ----------------------------------------------------
echo "\n--- Section 7: System Activity Logs ---\n";

$logResult = log_activity($testUserId, 'auth', 'test_event', 'Automated system verification log entry', 'user', $testUserId);
assert_test("log_activity() persisted event", $logResult === true);

$stmt = $db->prepare("SELECT * FROM activity_logs WHERE user_id = ? AND action = 'test_event' LIMIT 1");
$stmt->execute([$testUserId]);
$logRow = $stmt->fetch();
assert_test("Activity log captured IP address and description", $logRow && !empty($logRow['ip_address']) && $logRow['description'] === 'Automated system verification log entry');

// ----------------------------------------------------
// SECTION 8: SYSTEM SETTINGS
// ----------------------------------------------------
echo "\n--- Section 8: System Settings Engine ---\n";

$initialAppName = get_setting('app_name', 'Default');
assert_test("get_setting('app_name') retrieved valid string", !empty($initialAppName));

// Update setting
$setSuccess = set_setting('test_temp_key', 'Hello Phase 05', 'string');
assert_test("set_setting() created temporary setting", $setSuccess === true);

$readBack = get_setting('test_temp_key');
assert_test("get_setting() read back updated value from cache", $readBack === 'Hello Phase 05');

// Clean up temp setting
$db->prepare("DELETE FROM settings WHERE setting_key = 'test_temp_key'")->execute();

// ----------------------------------------------------
// CLEANUP TEST DATA
// ----------------------------------------------------
$db->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$testUserId]);
$db->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$testUserId]);
$db->prepare("DELETE FROM user_notification_preferences WHERE user_id = ?")->execute([$testUserId]);
$db->prepare("DELETE FROM users WHERE id = ?")->execute([$testUserId]);

echo "\n====================================================\n";
echo "VERIFICATION SUMMARY\n";
echo "Total Tests: {$totalTests} | Passed: {$passedTests} | Failed: {$failedTests}\n";
echo "====================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
