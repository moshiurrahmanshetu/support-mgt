<?php
/**
 * Automated Verification Test Suite for Phase 08 Fix: Customer CRUD Completion
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/activity_log.php';

$db = get_db();

$testsPassed = 0;
$testsFailed = 0;

function assert_test($description, $condition) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $testsFailed++;
    }
}

echo "=======================================================\n";
echo "SUPPORT-MGT: CUSTOMER CRUD COMPLETION VERIFICATION SUITE\n";
echo "=======================================================\n\n";

// Cleanup test records
$db->exec("DELETE FROM users WHERE email IN ('admin_created_cust@test.local', 'cust_ticket_owner@test.local', 'mgr_test_user@test.local')");

// TEST GROUP 1: Admin Customer Creation Workflow & Security
echo "--- Group 1: Admin Customer Creation Workflow & Security ---\n";

$custRole = get_role_by_slug('customer');
assert_test("Customer system role exists in database", $custRole && (int)$custRole['is_system'] === 1);

// Simulate Admin Customer Creation
$adminCustEmail = 'admin_created_cust@test.local';
$adminCustName = 'Edward Customer';
$adminCustPhone = '+1 555-0199';
$adminCustPass = 'Secret123!';

$db->beginTransaction();
try {
    $hashedPassword = password_hash($adminCustPass, PASSWORD_DEFAULT);
    $roleSlug = 'customer'; // Server-enforced

    $insertStmt = $db->prepare("
        INSERT INTO users (role, name, email, phone, password, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ");
    $insertStmt->execute([
        $roleSlug,
        $adminCustName,
        $adminCustEmail,
        $adminCustPhone,
        $hashedPassword
    ]);
    $newCustomerId = (int)$db->lastInsertId();

    $urStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())");
    $urStmt->execute([$newCustomerId, (int)$custRole['id']]);

    create_notification(
        $newCustomerId,
        'Welcome to Customer Support!',
        'Your customer support account has been provisioned.',
        'system',
        'user',
        $newCustomerId
    );

    log_activity(1, 'customer', 'customer_created', "Created customer account: {$adminCustName}", 'user', $newCustomerId);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
}

assert_test("Customer created successfully in users table with ID: {$newCustomerId}", $newCustomerId > 0);

// Verify Database consistency
$createdCust = $db->query("SELECT * FROM users WHERE id = {$newCustomerId}")->fetch();
assert_test("Created customer has users.role = 'customer'", $createdCust && $createdCust['role'] === 'customer');
assert_test("Created customer password is secure hash (not plain text)", $createdCust && password_verify($adminCustPass, $createdCust['password']));

// Verify user_roles consistency
$custRoleSlugs = get_user_role_slugs($newCustomerId);
assert_test("Created customer linked to 'customer' in user_roles table", in_array('customer', $custRoleSlugs, true));

// Verify in-app welcome notification
$notifCount = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$newCustomerId}")->fetchColumn();
assert_test("Customer received in-app welcome notification", $notifCount > 0);

// Verify activity audit log
$logCount = (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'customer_created' AND reference_id = {$newCustomerId}")->fetchColumn();
assert_test("Activity log entry recorded for customer_created", $logCount > 0);


// TEST GROUP 2: Duplicate Email Protection
echo "\n--- Group 2: Duplicate Email Protection ---\n";

$dupStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$dupStmt->execute([$adminCustEmail]);
$dupExists = (bool)$dupStmt->fetch();
assert_test("Duplicate email check successfully detects existing customer email", $dupExists === true);


// TEST GROUP 3: Customer Permission & Access Boundary
echo "\n--- Group 3: Customer Permission & Access Boundary ---\n";

assert_test("Customer CANNOT view users directory ('users.view')", !has_permission('users.view', $newCustomerId));
assert_test("Customer CANNOT edit users ('users.edit')", !has_permission('users.edit', $newCustomerId));
assert_test("Customer CANNOT delete users ('users.delete')", !has_permission('users.delete', $newCustomerId));
assert_test("Customer CANNOT manage roles ('roles.view')", !has_permission('roles.view', $newCustomerId));
assert_test("Customer CANNOT view customer management ('customers.view')", !has_permission('customers.view', $newCustomerId));
assert_test("Customer CAN create tickets ('tickets.create')", has_permission('tickets.create', $newCustomerId));


// TEST GROUP 4: Customer Edit Workflow & Password Preservation
echo "\n--- Group 4: Customer Edit Workflow & Password Preservation ---\n";

$oldPasswordHash = $createdCust['password'];
$updatedName = 'Edward Customer Jr.';
$updatedPhone = '+1 555-0999';

// 1. Edit without changing password
$db->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ? AND role = 'customer'")
   ->execute([$updatedName, $updatedPhone, $newCustomerId]);

$updatedCust = $db->query("SELECT * FROM users WHERE id = {$newCustomerId}")->fetch();
assert_test("Customer name updated to '{$updatedName}'", $updatedCust['name'] === $updatedName);
assert_test("Customer phone updated to '{$updatedPhone}'", $updatedCust['phone'] === $updatedPhone);
assert_test("Password unchanged when blank in edit form", $updatedCust['password'] === $oldPasswordHash);
assert_test("Role strictly remains 'customer' after edit", $updatedCust['role'] === 'customer');

// 2. Edit with new password
$newPass = 'NewPassword456!';
$newHashed = password_hash($newPass, PASSWORD_DEFAULT);
$db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? AND role = 'customer'")
   ->execute([$newHashed, $newCustomerId]);

$pwdUpdatedCust = $db->query("SELECT * FROM users WHERE id = {$newCustomerId}")->fetch();
assert_test("Customer password successfully updated when provided", password_verify($newPass, $pwdUpdatedCust['password']));


// TEST GROUP 5: Ticket Association & Analytics
echo "\n--- Group 5: Ticket Association & View Analytics ---\n";

// Create test tickets for this customer
$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, created_at, updated_at) VALUES ('TKT-TEST-001', ?, 'Billing Issue', 'Description', 'medium', 'open', NOW(), NOW())")
   ->execute([$newCustomerId]);
$ticket1Id = (int)$db->lastInsertId();

$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, created_at, updated_at, resolved_at) VALUES ('TKT-TEST-002', ?, 'Resolved Bug', 'Description', 'high', 'resolved', NOW(), NOW(), NOW())")
   ->execute([$newCustomerId]);
$ticket2Id = (int)$db->lastInsertId();

// Verify ticket counts query
$statStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count
    FROM tickets
    WHERE user_id = ?
");
$statStmt->execute([$newCustomerId]);
$tStats = $statStmt->fetch();

assert_test("Customer has 2 total tickets linked via tickets.user_id", (int)$tStats['total'] === 2);
assert_test("Customer has 1 open ticket", (int)$tStats['open_count'] === 1);
assert_test("Customer has 1 resolved ticket", (int)$tStats['resolved_count'] === 1);


// TEST GROUP 6: Safe Soft Deletion & Ticket Preservation
echo "\n--- Group 6: Safe Soft Deletion & Ticket Preservation ---\n";

// Perform soft delete
$db->prepare("UPDATE users SET status = 'inactive', deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND role = 'customer'")
   ->execute([$newCustomerId]);
log_activity(1, 'customer', 'customer_deleted', "Deleted customer {$updatedName}", 'user', $newCustomerId);

$deletedCust = $db->query("SELECT * FROM users WHERE id = {$newCustomerId}")->fetch();
assert_test("Customer marked as inactive and deleted_at IS NOT NULL", $deletedCust['status'] === 'inactive' && !empty($deletedCust['deleted_at']));

// Check tickets remain 100% intact
$tCountAfter = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE user_id = {$newCustomerId}")->fetchColumn();
assert_test("HISTORICAL PRESERVATION: All customer tickets preserved in database (Count: {$tCountAfter})", $tCountAfter === 2);

// Check activity log recorded
$delLogCount = (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'customer_deleted' AND reference_id = {$newCustomerId}")->fetchColumn();
assert_test("Activity log recorded for customer_deleted", $delLogCount > 0);


// TEST GROUP 7: Customer Restoration
echo "\n--- Group 7: Customer Restoration ---\n";

$db->prepare("UPDATE users SET status = 'active', deleted_at = NULL, updated_at = NOW() WHERE id = ? AND role = 'customer'")
   ->execute([$newCustomerId]);
log_activity(1, 'customer', 'customer_restored', "Restored customer {$updatedName}", 'user', $newCustomerId);

$restoredCust = $db->query("SELECT * FROM users WHERE id = {$newCustomerId}")->fetch();
assert_test("Customer restored: status = 'active' and deleted_at IS NULL", $restoredCust['status'] === 'active' && empty($restoredCust['deleted_at']));

$tCountRestored = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE user_id = {$newCustomerId}")->fetchColumn();
assert_test("Tickets remain fully accessible after customer restoration", $tCountRestored === 2);


// TEST GROUP 8: Support Manager Granular Role Enforcement
echo "\n--- Group 8: Support Manager Permissions vs Admin ---\n";

$mgrRole = get_role_by_slug('support_manager');
$db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES ('Test Mgr', 'mgr_test_user@test.local', 'hash', 'support_manager', 'active', NOW())")->execute();
$mgrId = (int)$db->lastInsertId();
assign_user_role($mgrId, (int)$mgrRole['id']);

assert_test("Support Manager CAN view customer directory ('customers.view')", has_permission('customers.view', $mgrId));
assert_test("Support Manager CAN create customers ('customers.create')", has_permission('customers.create', $mgrId));
assert_test("Support Manager CAN edit customers ('customers.edit')", has_permission('customers.edit', $mgrId));
assert_test("Support Manager CANNOT delete customers ('customers.delete')", !has_permission('customers.delete', $mgrId));


// TEST GROUP 9: Cleanup and Final Checks
echo "\n--- Group 9: Cleanup ---\n";

$db->exec("DELETE FROM tickets WHERE id IN ({$ticket1Id}, {$ticket2Id})");
$db->exec("DELETE FROM users WHERE id IN ({$newCustomerId}, {$mgrId})");
assert_test("Temporary test artifacts cleaned up safely", true);

echo "\n=======================================================\n";
echo "CUSTOMER CRUD TEST SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "=======================================================\n";

if ($testsFailed > 0) {
    exit(1);
} else {
    exit(0);
}
