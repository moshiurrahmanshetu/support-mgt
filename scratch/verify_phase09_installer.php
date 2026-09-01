<?php
/**
 * Comprehensive Automated Verification Suite for Phase 09 - Professional Installer
 */

// Set up CLI execution
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../install/functions.php';

$totalPassed = 0;
$totalFailed = 0;

function report_pass(string $msg): void {
    global $totalPassed;
    $totalPassed++;
    echo "  [PASS] {$msg}\n";
}

function report_fail(string $msg, string $detail = ''): void {
    global $totalFailed;
    $totalFailed++;
    echo "  [FAIL] {$msg}" . ($detail ? " -- Detail: {$detail}" : "") . "\n";
}

echo "=================================================================\n";
echo "SUPPORT-MGT: PHASE 09 INSTALLATION WIZARD VERIFICATION SUITE\n";
echo "=================================================================\n\n";

// ---------------------------------------------------------------
// Group 1: System Requirements & Directory Permissions
// ---------------------------------------------------------------
echo "--- Group 1: Server Requirements & Permission Validation ---\n";
$reqCheck = check_system_requirements();

if ($reqCheck['all_passed']) {
    report_pass("check_system_requirements() returned all_passed = TRUE");
} else {
    report_fail("check_system_requirements() failed on local environment");
}

if ($reqCheck['requirements']['php_version']['passed']) {
    report_pass("PHP Version requirement passed (" . PHP_VERSION . " >= 8.1.0)");
} else {
    report_fail("PHP Version requirement failed (" . PHP_VERSION . ")");
}

if ($reqCheck['requirements']['pdo']['passed'] && $reqCheck['requirements']['pdo_mysql']['passed']) {
    report_pass("PDO and PDO MySQL extensions are enabled");
} else {
    report_fail("PDO or PDO MySQL extension missing");
}

if ($reqCheck['directories']['config_dir']['passed'] && $reqCheck['directories']['uploads_dir']['passed']) {
    report_pass("config/ and uploads/ directories are writable");
} else {
    report_fail("Required directories are not writable");
}

// ---------------------------------------------------------------
// Group 2: Database Connection Test & Friendly Error Handling
// ---------------------------------------------------------------
echo "\n--- Group 2: Database Connection & Error Protection ---\n";

// Test 2.1: Invalid host / port
$badHostRes = test_db_connection([
    'host' => '127.0.0.99',
    'port' => 3399,
    'name' => 'non_existent_db',
    'user' => 'invalid_user',
    'pass' => 'bad_pass_secret'
]);
if (!$badHostRes['success'] && strpos($badHostRes['message'], 'bad_pass_secret') === false) {
    report_pass("Invalid DB host safely rejected with friendly message without password exposure");
} else {
    report_fail("Invalid DB host test failed", json_encode($badHostRes));
}

// Test 2.2: Valid DB credentials
$validDbRes = test_db_connection([
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'support_mgt_db',
    'user' => 'root',
    'pass' => ''
]);
if ($validDbRes['success']) {
    report_pass("Valid database credentials connect successfully via PDO");
} else {
    report_fail("Valid database connection failed", $validDbRes['message']);
}

// ---------------------------------------------------------------
// Group 3: Fresh Database Schema Import from database/install.sql
// ---------------------------------------------------------------
echo "\n--- Group 3: Master Schema Import to Fresh Database ---\n";

$testDbName = 'support_mgt_test_p09';
$rawPdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Prepare clean test database
$rawPdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");
$rawPdo->exec("CREATE DATABASE `{$testDbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$testPdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname={$testDbName};charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$installSqlPath = __DIR__ . '/../database/install.sql';
if (file_exists($installSqlPath)) {
    report_pass("Found database/install.sql file (Size: " . round(filesize($installSqlPath)/1024, 1) . " KB)");
} else {
    report_fail("database/install.sql file is missing!");
}

$importRes = execute_sql_file($testPdo, $installSqlPath);
if ($importRes['success']) {
    report_pass("database/install.sql imported cleanly without syntax or FK errors (" . $importRes['statements_count'] . " statements)");
} else {
    report_fail("database/install.sql import failed", $importRes['message']);
}

// ---------------------------------------------------------------
// Group 4: Verification of All 21 Tables & Foreign Keys
// ---------------------------------------------------------------
echo "\n--- Group 4: Table Schema & Structure Verification ---\n";

$expectedTables = [
    'departments',
    'users',
    'password_resets',
    'roles',
    'user_roles',
    'permissions',
    'role_permissions',
    'tickets',
    'ticket_messages',
    'ticket_attachments',
    'ticket_tags',
    'ticket_tag_relations',
    'canned_responses',
    'ticket_activity_logs',
    'notifications',
    'user_notification_preferences',
    'activity_logs',
    'settings',
    'knowledge_base_categories',
    'knowledge_base_articles',
    'faqs'
];

$stmt = $testPdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '{$testDbName}'");
$createdTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$missingTables = array_diff($expectedTables, $createdTables);
if (empty($missingTables)) {
    report_pass("All 21 system tables exist in database (Count: " . count($createdTables) . ")");
} else {
    report_fail("Missing tables in fresh database", implode(', ', $missingTables));
}

// Check zero user accounts in fresh install.sql
$userCount = (int)$testPdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount === 0) {
    report_pass("Fresh install.sql contains ZERO test user accounts (Clean slate)");
} else {
    report_fail("install.sql contains pre-existing users (Count: {$userCount})");
}

// Check zero demo tickets in fresh install.sql
$ticketCount = (int)$testPdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
if ($ticketCount === 0) {
    report_pass("Fresh install.sql contains ZERO demo tickets (Clean slate)");
} else {
    report_fail("install.sql contains pre-existing tickets (Count: {$ticketCount})");
}

// Check default system roles
$rolesCount = (int)$testPdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
if ($rolesCount === 4) {
    report_pass("System roles seeded correctly (Administrator, Support Manager, Support Agent, Customer)");
} else {
    report_fail("Unexpected system roles count: {$rolesCount}");
}

// Check permissions count
$permsCount = (int)$testPdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
if ($permsCount === 52) {
    report_pass("System permissions seeded correctly (Count: 52)");
} else {
    report_fail("Unexpected permissions count: {$permsCount}");
}

// Check role permissions count
$rolePermsCount = (int)$testPdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
if ($rolePermsCount > 50) {
    report_pass("Role-permission mappings populated correctly (Count: {$rolePermsCount})");
} else {
    report_fail("Unexpected role permissions count: {$rolePermsCount}");
}

// Check default settings count
$settingsCount = (int)$testPdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
if ($settingsCount >= 20) {
    report_pass("Default system settings seeded correctly (Count: {$settingsCount})");
} else {
    report_fail("Unexpected settings count: {$settingsCount}");
}

// Check default KB categories and FAQs
$kbCatCount = (int)$testPdo->query("SELECT COUNT(*) FROM knowledge_base_categories")->fetchColumn();
$faqCount = (int)$testPdo->query("SELECT COUNT(*) FROM faqs")->fetchColumn();
if ($kbCatCount === 4 && $faqCount === 4) {
    report_pass("Knowledge base categories (4) and FAQs (4) seeded correctly");
} else {
    report_fail("Unexpected KB/FAQ count: Categories={$kbCatCount}, FAQs={$faqCount}");
}

// ---------------------------------------------------------------
// Group 5: Administrator Provisioning Workflow
// ---------------------------------------------------------------
echo "\n--- Group 5: Administrator Provisioning ---\n";

$adminName = 'Super Admin';
$adminEmail = 'owner@supportdesk.local';
$adminPass = 'SecureAdmin#2026!';
$hashedPass = password_hash($adminPass, PASSWORD_DEFAULT);

// Insert administrator in fresh test DB
$uStmt = $testPdo->prepare("INSERT INTO users (role, name, email, password, status, created_at, updated_at) VALUES ('admin', ?, ?, ?, 'active', NOW(), NOW())");
$uStmt->execute([$adminName, $adminEmail, $hashedPass]);
$newAdminId = (int)$testPdo->lastInsertId();

$adminRoleId = (int)$testPdo->query("SELECT id FROM roles WHERE slug = 'administrator'")->fetchColumn();
$testPdo->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())")->execute([$newAdminId, $adminRoleId]);

if ($newAdminId > 0 && $adminRoleId > 0) {
    report_pass("Administrator user provisioned with ID {$newAdminId} and linked to 'administrator' role (ID: {$adminRoleId})");
} else {
    report_fail("Administrator provisioning failed");
}

// Verify password verification works
$dbHash = $testPdo->query("SELECT password FROM users WHERE id = {$newAdminId}")->fetchColumn();
if (password_verify($adminPass, $dbHash)) {
    report_pass("Administrator password hash verified successfully");
} else {
    report_fail("Administrator password hash verification failed");
}

// ---------------------------------------------------------------
// Group 6: Installation Lock & Security Verification
// ---------------------------------------------------------------
echo "\n--- Group 6: Installation Lock & Direct Access Protection ---\n";

$testLockFile = __DIR__ . '/../config/installed.lock';

// Create lock file
$lockCreated = create_installation_lock($adminEmail);
if ($lockCreated && file_exists($testLockFile)) {
    report_pass("config/installed.lock created successfully");
} else {
    report_fail("Failed to create config/installed.lock");
}

if (is_system_installed()) {
    report_pass("is_system_installed() returns TRUE when lock file exists");
} else {
    report_fail("is_system_installed() returned false unexpectedly");
}

// Verify lock file JSON contents do NOT contain passwords
$lockContent = file_get_contents($testLockFile);
$lockData = json_decode($lockContent, true);
if (isset($lockData['installed']) && isset($lockData['version']) && !isset($lockData['password']) && !isset($lockData['db_pass'])) {
    report_pass("installed.lock contains safe metadata without sensitive passwords");
} else {
    report_fail("installed.lock contains insecure data or is malformed", $lockContent);
}

// ---------------------------------------------------------------
// Group 7: Post-Install Operational Lifecycle (Customer + Tickets)
// ---------------------------------------------------------------
echo "\n--- Group 7: Post-Install Ticket & Customer Lifecycle on Fresh DB ---\n";

// 7.1 Customer Registration
$custName = 'Sarah Jenkins';
$custEmail = 'sarah@example.com';
$custPass = password_hash('Customer123!', PASSWORD_DEFAULT);
$testPdo->prepare("INSERT INTO users (role, name, email, password, status, created_at, updated_at) VALUES ('customer', ?, ?, ?, 'active', NOW(), NOW())")
    ->execute([$custName, $custEmail, $custPass]);
$newCustId = (int)$testPdo->lastInsertId();

$custRoleId = (int)$testPdo->query("SELECT id FROM roles WHERE slug = 'customer'")->fetchColumn();
$testPdo->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())")->execute([$newCustId, $custRoleId]);

if ($newCustId > 0 && $custRoleId > 0) {
    report_pass("Customer registration on fresh DB creates user in users and user_roles table");
} else {
    report_fail("Customer registration failed");
}

// 7.2 Customer creates Ticket
$tktNumber = 'TKT-2026-0001';
$tktSubject = 'Billing question regarding invoice #1004';
$tktDesc = 'I have a question about our latest monthly invoice.';
$testPdo->prepare("INSERT INTO tickets (ticket_number, user_id, department_id, subject, description, priority, status, created_at, updated_at) VALUES (?, ?, 2, ?, ?, 'medium', 'open', NOW(), NOW())")
    ->execute([$tktNumber, $newCustId, $tktSubject, $tktDesc]);
$newTktId = (int)$testPdo->lastInsertId();

if ($newTktId > 0) {
    report_pass("Customer successfully creates ticket (#{$tktNumber}) on fresh DB");
} else {
    report_fail("Ticket creation on fresh DB failed");
}

// 7.3 Verify Unseen Ticket Counter Query
$unseenCount = (int)$testPdo->query("
    SELECT COUNT(*) 
    FROM tickets t
    JOIN users u ON u.id = t.user_id
    WHERE t.admin_viewed_at IS NULL
      AND (u.role = 'customer' OR u.role IS NULL)
")->fetchColumn();

if ($unseenCount === 1) {
    report_pass("Admin sidebar unseen ticket counter detects new customer ticket (Count: 1)");
} else {
    report_fail("Unseen ticket counter calculation mismatch: {$unseenCount}");
}

// 7.4 Admin views ticket -> admin_viewed_at updated -> counter becomes 0
$testPdo->prepare("UPDATE tickets SET admin_viewed_at = NOW() WHERE id = ?")->execute([$newTktId]);
$unseenCountAfter = (int)$testPdo->query("
    SELECT COUNT(*) 
    FROM tickets t
    JOIN users u ON u.id = t.user_id
    WHERE t.admin_viewed_at IS NULL
      AND (u.role = 'customer' OR u.role IS NULL)
")->fetchColumn();

if ($unseenCountAfter === 0) {
    report_pass("Admin viewing ticket marks admin_viewed_at and updates unread counter to 0");
} else {
    report_fail("Unseen ticket counter failed to decrement: {$unseenCountAfter}");
}

// ---------------------------------------------------------------
// Group 8: Developer Reinstallation Recovery Procedure
// ---------------------------------------------------------------
echo "\n--- Group 8: Developer Reinstallation Recovery Procedure ---\n";

// Remove lock file temporarily
unlink($testLockFile);

if (!is_system_installed()) {
    report_pass("Deleting config/installed.lock restores installer access (Reinstallation test passed)");
} else {
    report_fail("is_system_installed() still returned true after lock deletion");
}

// Restore lock file for production safety
create_installation_lock($adminEmail);

// ---------------------------------------------------------------
// Group 9: Strict Aesthetic & CSS Compliance
// ---------------------------------------------------------------
echo "\n--- Group 9: Aesthetic & UI Style Rule Compliance ---\n";

$installerFiles = [
    __DIR__ . '/../install/index.php',
    __DIR__ . '/../install/steps/welcome.php',
    __DIR__ . '/../install/steps/requirements.php',
    __DIR__ . '/../install/steps/database.php',
    __DIR__ . '/../install/steps/sql_import.php',
    __DIR__ . '/../install/steps/admin.php',
    __DIR__ . '/../install/steps/complete.php',
];

$hasGradients = false;
foreach ($installerFiles as $file) {
    $content = file_get_contents($file);
    if (stripos($content, 'linear-gradient') !== false || stripos($content, 'radial-gradient') !== false) {
        $hasGradients = true;
        report_fail("Gradient found in " . basename($file));
    }
}
if (!$hasGradients) {
    report_pass("STRICT UI RULE: Zero CSS gradients (linear-gradient/radial-gradient) in installer");
}

// Cleanup temporary test DB
$rawPdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");
report_pass("Temporary test database cleaned up safely");

echo "\n=================================================================\n";
echo "PHASE 09 TEST SUMMARY: {$totalPassed} PASSED, {$totalFailed} FAILED\n";
echo "=================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
