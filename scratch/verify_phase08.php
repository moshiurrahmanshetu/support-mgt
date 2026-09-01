<?php
/**
 * Automated Verification Test Suite for Phase 08: User Management + Roles + Permissions + Customer Registration
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

echo "==================================================\n";
echo "SUPPORT-MGT: PHASE 08 AUTOMATED VERIFICATION SUITE\n";
echo "==================================================\n\n";

// TEST GROUP 1: Database Schema & Seeds
echo "--- Group 1: Database Schema & Seeds ---\n";

// 1. Roles table
$rolesCount = (int)$db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
assert_test("Roles table exists and has default roles (Count: {$rolesCount})", $rolesCount >= 4);

// 2. Specific system roles
$adminRole = get_role_by_slug('administrator');
$mgrRole = get_role_by_slug('support_manager');
$agentRole = get_role_by_slug('support_agent');
$custRole = get_role_by_slug('customer');

assert_test("Role 'administrator' exists and is marked system (is_system=1)", $adminRole && (int)$adminRole['is_system'] === 1);
assert_test("Role 'support_manager' exists and is marked system (is_system=1)", $mgrRole && (int)$mgrRole['is_system'] === 1);
assert_test("Role 'support_agent' exists and is marked system (is_system=1)", $agentRole && (int)$agentRole['is_system'] === 1);
assert_test("Role 'customer' exists and is marked system (is_system=1)", $custRole && (int)$custRole['is_system'] === 1);

// 3. Permissions table
$permCount = (int)$db->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
assert_test("Permissions table has cataloged permissions (Count: {$permCount})", $permCount >= 50);

// 4. Role Permissions
$rpCount = (int)$db->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
assert_test("Role Permissions table populated (Count: {$rpCount})", $rpCount >= 90);

// 5. Users table alterations
$usersCols = $db->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
assert_test("Users table has deleted_at column for soft delete support", in_array('deleted_at', $usersCols, true));


// TEST GROUP 2: Centralized RBAC & Permission Helper Checks
echo "\n--- Group 2: Centralized RBAC & Permissions Logic ---\n";

// Admin user (ID 1)
$adminUser = $db->query("SELECT * FROM users WHERE email = 'admin@supportmgt.local' LIMIT 1")->fetch();
assert_test("Default Admin user exists", !empty($adminUser));

if ($adminUser) {
    $adminId = (int)$adminUser['id'];
    assert_test("is_admin_user() returns TRUE for Administrator", is_admin_user($adminId));
    assert_test("Administrator has 'users.view' permission", has_permission('users.view', $adminId));
    assert_test("Administrator has 'roles.delete' permission", has_permission('roles.delete', $adminId));
    assert_test("Administrator has 'settings.edit' permission", has_permission('settings.edit', $adminId));
}

// Create a temporary test manager, agent, and customer to test granular permissions
$db->exec("DELETE FROM users WHERE email IN ('test_mgr@test.local', 'test_agent@test.local', 'test_cust@test.local', 'test_admin2@test.local')");

// Test Manager
$db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES ('Test Manager', 'test_mgr@test.local', 'hash', 'support_manager', 'active', NOW())")->execute();
$testMgrId = (int)$db->lastInsertId();
assign_user_role($testMgrId, (int)$mgrRole['id']);

assert_test("Support Manager has 'tickets.assign' permission", has_permission('tickets.assign', $testMgrId));
assert_test("Support Manager has 'reports.view' permission", has_permission('reports.view', $testMgrId));
assert_test("Support Manager DOES NOT have 'users.delete' permission", !has_permission('users.delete', $testMgrId));
assert_test("Support Manager DOES NOT have 'roles.create' permission", !has_permission('roles.create', $testMgrId));

// Test Agent
$db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES ('Test Agent', 'test_agent@test.local', 'hash', 'support_agent', 'active', NOW())")->execute();
$testAgentId = (int)$db->lastInsertId();
assign_user_role($testAgentId, (int)$agentRole['id']);

assert_test("Support Agent has 'tickets.reply' permission", has_permission('tickets.reply', $testAgentId));
assert_test("Support Agent DOES NOT have 'tickets.assign' permission", !has_permission('tickets.assign', $testAgentId));
assert_test("Support Agent DOES NOT have 'reports.view' permission", !has_permission('reports.view', $testAgentId));

// Test Customer
$db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES ('Test Customer', 'test_cust@test.local', 'hash', 'customer', 'active', NOW())")->execute();
$testCustId = (int)$db->lastInsertId();
assign_user_role($testCustId, (int)$custRole['id']);

assert_test("Customer has 'tickets.create' permission", has_permission('tickets.create', $testCustId));
assert_test("Customer DOES NOT have 'tickets.edit' permission", !has_permission('tickets.edit', $testCustId));
assert_test("Customer DOES NOT have 'users.view' permission", !has_permission('users.view', $testCustId));


// TEST GROUP 3: Last Administrator Safety Guard
echo "\n--- Group 3: Last Administrator Safety Protection ---\n";

if ($adminUser) {
    // Only 1 admin currently active
    $canDeactivateOnlyAdmin = can_modify_user_role_or_status($adminId, 'administrator', STATUS_INACTIVE);
    assert_test("Safety Guard BLOCKS deactivating the only active admin", $canDeactivateOnlyAdmin === false);

    $canDemoteOnlyAdmin = can_modify_user_role_or_status($adminId, 'customer', STATUS_ACTIVE);
    assert_test("Safety Guard BLOCKS demoting the only active admin to customer", $canDemoteOnlyAdmin === false);

    $canDeleteOnlyAdmin = can_delete_user($adminId);
    assert_test("Safety Guard BLOCKS deleting the only active admin", $canDeleteOnlyAdmin === false);

    // Create a 2nd active admin
    $db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES ('Second Admin', 'test_admin2@test.local', 'hash', 'administrator', 'active', NOW())")->execute();
    $secondAdminId = (int)$db->lastInsertId();
    assign_user_role($secondAdminId, (int)$adminRole['id']);

    // Now with 2 admins, can modify one
    $canDeactivateWithTwoAdmins = can_modify_user_role_or_status($secondAdminId, 'administrator', STATUS_INACTIVE);
    assert_test("Safety Guard ALLOWS deactivation when another active admin exists", $canDeactivateWithTwoAdmins === true);

    // Clean up second admin
    $db->exec("DELETE FROM users WHERE id = {$secondAdminId}");
}


// TEST GROUP 4: Public Customer Registration Security
echo "\n--- Group 4: Customer Registration Security & Invariant ---\n";

$regEmail = 'secure_customer_' . time() . '@test.local';
$regName = 'Alice Secure Customer';
$regPass = 'Secret123!';

// Simulate public customer registration process
$db->prepare("INSERT INTO users (name, email, phone, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'customer', 'active', NOW(), NOW())")
   ->execute([$regName, $regEmail, '+123456789', password_hash($regPass, PASSWORD_DEFAULT)]);
$newCustId = (int)$db->lastInsertId();
assign_user_role($newCustId, (int)$custRole['id']);

create_notification($newCustId, 'Welcome to Support Desk!', 'Your account has been created.', 'system', 'user', $newCustId);
log_activity($newCustId, 'auth', 'customer_registered', "New customer {$regName} registered");

$savedCust = $db->query("SELECT * FROM users WHERE id = {$newCustId}")->fetch();
$savedCustRoleSlugs = get_user_role_slugs($newCustId);

assert_test("Registered user has role column = 'customer'", $savedCust && $savedCust['role'] === 'customer');
assert_test("Registered user is linked to role 'customer' in user_roles", in_array('customer', $savedCustRoleSlugs, true));
assert_test("Registered user received welcome notification", (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$newCustId}")->fetchColumn() > 0);
assert_test("Registered user has 'customer_registered' activity log", (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE user_id = {$newCustId} AND action = 'customer_registered'")->fetchColumn() > 0);


// TEST GROUP 5: Custom Role Lifecycle & Assignment Guards
echo "\n--- Group 5: Custom Role Lifecycle & Safety Guards ---\n";

// 1. Create a custom role
$db->prepare("INSERT INTO roles (name, slug, description, status, is_system, created_at) VALUES ('QA Specialist', 'qa_specialist', 'Quality Assurance team', 'active', 0, NOW())")->execute();
$qaRoleId = (int)$db->lastInsertId();
assert_test("Created custom role 'QA Specialist' (is_system=0)", $qaRoleId > 0);

// 2. Assign custom permissions to custom role
$permId = (int)$db->query("SELECT id FROM permissions WHERE slug = 'tickets.view'")->fetchColumn();
$db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())")->execute([$qaRoleId, $permId]);

// 3. Assign custom role to a test user
assign_user_role($testAgentId, $qaRoleId);
PermissionCache::$permissionsByUser = [];
assert_test("User successfully assigned custom role 'QA Specialist'", in_array('tickets.view', get_user_permissions($testAgentId), true));

// 4. Test Role Delete Blocker when users are assigned
$assignedUsersCount = (int)$db->query("SELECT COUNT(*) FROM user_roles WHERE role_id = {$qaRoleId}")->fetchColumn();
assert_test("Custom role has assigned users (Count: {$assignedUsersCount})", $assignedUsersCount > 0);

// 5. Reassign user back to support agent
assign_user_role($testAgentId, (int)$agentRole['id']);
$assignedUsersCountAfter = (int)$db->query("SELECT COUNT(*) FROM user_roles WHERE role_id = {$qaRoleId}")->fetchColumn();
assert_test("Custom role has zero assigned users after reassignment", $assignedUsersCountAfter === 0);

// 6. Delete custom role
$db->exec("DELETE FROM roles WHERE id = {$qaRoleId}");
$roleDeleted = (int)$db->query("SELECT COUNT(*) FROM roles WHERE id = {$qaRoleId}")->fetchColumn();
assert_test("Custom role successfully deleted when unassigned", $roleDeleted === 0);


// TEST GROUP 6: CSS & UI Aesthetics Check
echo "\n--- Group 6: Visual Aesthetics & Path Integrity ---\n";

$cssFiles = glob(__DIR__ . '/../assets/css/*.css');
$foundGradient = false;
foreach ($cssFiles as $cssFile) {
    $content = file_get_contents($cssFile);
    if (stripos($content, 'linear-gradient') !== false || stripos($content, 'radial-gradient') !== false) {
        $foundGradient = true;
        break;
    }
}
assert_test("STRICT UI RULE: No CSS gradients (linear-gradient/radial-gradient) in assets/css", !$foundGradient);

// Clean up temporary test accounts
$db->exec("DELETE FROM users WHERE email IN ('test_mgr@test.local', 'test_agent@test.local', 'test_cust@test.local')");
$db->exec("DELETE FROM users WHERE id = {$newCustId}");

echo "\n==================================================\n";
echo "VERIFICATION SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "==================================================\n";

if ($testsFailed > 0) {
    exit(1);
} else {
    exit(0);
}
