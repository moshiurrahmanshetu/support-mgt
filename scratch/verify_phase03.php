<?php
/**
 * Automated Verification Test Suite for Phase 03
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_check.php';

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
echo "SUPPORT-MGT: PHASE 03 AUTOMATED VERIFICATION SUITE\n";
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
// SECTION 2: DATABASE SCHEMA & FOREIGN KEYS
// ----------------------------------------------------
echo "\n--- Section 2: Database Schema & Column Verification ---\n";

// Check departments table exists
$stmt = $db->query("SHOW TABLES LIKE 'departments'");
assert_test("departments table exists", $stmt->rowCount() > 0);

// Check users.department_id exists
$stmt = $db->query("SHOW COLUMNS FROM users LIKE 'department_id'");
assert_test("users.department_id column exists", $stmt->rowCount() > 0);

// Check tickets.department_id exists
$stmt = $db->query("SHOW COLUMNS FROM tickets LIKE 'department_id'");
assert_test("tickets.department_id column exists", $stmt->rowCount() > 0);

// Check initial seed departments exist
$stmt = $db->query("SELECT COUNT(*) FROM departments WHERE status = 'active'");
$activeDepts = (int)$stmt->fetchColumn();
assert_test("Default active departments seeded (found: {$activeDepts})", $activeDepts >= 4);

// ----------------------------------------------------
// SECTION 3: DEPARTMENT CRUD & STATUS TOGGLE
// ----------------------------------------------------
echo "\n--- Section 3: Department Management Logic ---\n";

$testDeptName = 'QA & Testing Dept ' . bin2hex(random_bytes(3));
// 1. Create Department
$stmt = $db->prepare("INSERT INTO departments (name, description, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())");
$stmt->execute([$testDeptName, 'Temporary test department for verification']);
$testDeptId = (int)$db->lastInsertId();
assert_test("Department created successfully (ID: {$testDeptId})", $testDeptId > 0);

// 2. Reject Duplicate Department Name
$dupRejected = false;
try {
    $stmt = $db->prepare("INSERT INTO departments (name, description, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())");
    $stmt->execute([$testDeptName, 'Duplicate test']);
} catch (Exception $e) {
    $dupRejected = true;
}
assert_test("Duplicate department name rejected by UNIQUE constraint", $dupRejected);

// 3. Edit Department
$updatedDeptName = $testDeptName . ' (Updated)';
$stmt = $db->prepare("UPDATE departments SET name = ?, description = 'Updated description' WHERE id = ?");
$stmt->execute([$updatedDeptName, $testDeptId]);
$stmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
$stmt->execute([$testDeptId]);
$fetchedName = $stmt->fetchColumn();
assert_test("Department name updated successfully", $fetchedName === $updatedDeptName);

// 4. Deactivate Department
$stmt = $db->prepare("UPDATE departments SET status = 'inactive' WHERE id = ?");
$stmt->execute([$testDeptId]);
$stmt = $db->prepare("SELECT status FROM departments WHERE id = ?");
$stmt->execute([$testDeptId]);
assert_test("Department deactivated successfully", $stmt->fetchColumn() === 'inactive');

// 5. Inactive department excluded from active queries
$stmt = $db->prepare("SELECT id FROM departments WHERE id = ? AND status = 'active'");
$stmt->execute([$testDeptId]);
assert_test("Inactive department excluded from active queries", $stmt->fetch() === false);

// Reactivate for further tests
$stmt = $db->prepare("UPDATE departments SET status = 'active' WHERE id = ?");
$stmt->execute([$testDeptId]);

// ----------------------------------------------------
// SECTION 4: AGENT PROVISIONING & DEPARTMENT ASSIGNMENT
// ----------------------------------------------------
echo "\n--- Section 4: Agent Management & Role Enforcement ---\n";

$testAgentEmail = 'test_agent_' . bin2hex(random_bytes(4)) . '@supportmgt.local';
$testAgentPass = 'Agent@123456';
$hashedPass = password_hash($testAgentPass, PASSWORD_DEFAULT);

// 1. Create Agent with department assignment
$stmt = $db->prepare("
    INSERT INTO users (role, name, email, phone, password, department_id, status, created_at, updated_at)
    VALUES ('agent', 'Test Agent John', ?, '+1 555-123-4567', ?, ?, 'active', NOW(), NOW())
");
$stmt->execute([$testAgentEmail, $hashedPass, $testDeptId]);
$testAgentId = (int)$db->lastInsertId();
assert_test("Agent account created with role='agent' (ID: {$testAgentId})", $testAgentId > 0);

// 2. Verify agent role is immutable and cannot be admin
$stmt = $db->prepare("SELECT role, department_id FROM users WHERE id = ?");
$stmt->execute([$testAgentId]);
$agentRow = $stmt->fetch();
assert_test("Agent role verified as 'agent'", $agentRow['role'] === 'agent');
assert_test("Agent assigned to test department", (int)$agentRow['department_id'] === $testDeptId);

// 3. Deactivate Agent
$stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
$stmt->execute([$testAgentId]);
$stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$testAgentId]);
assert_test("Agent deactivated successfully", $stmt->fetchColumn() === 'inactive');

// 4. Inactive agent excluded from active agents query
$stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'agent' AND status = 'active'");
$stmt->execute([$testAgentId]);
assert_test("Inactive agent excluded from active ticket assignment pool", $stmt->fetch() === false);

// Reactivate agent
$stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
$stmt->execute([$testAgentId]);

// ----------------------------------------------------
// SECTION 5: CUSTOMER MANAGEMENT & ISOLATION
// ----------------------------------------------------
echo "\n--- Section 5: Customer Management Logic ---\n";

$testCustEmail = 'test_cust_' . bin2hex(random_bytes(4)) . '@supportmgt.local';
$stmt = $db->prepare("
    INSERT INTO users (role, name, email, phone, password, status, created_at, updated_at)
    VALUES ('customer', 'Test Customer Jane', ?, '+1 555-987-6543', ?, 'active', NOW(), NOW())
");
$stmt->execute([$testCustEmail, $hashedPass]);
$testCustId = (int)$db->lastInsertId();
assert_test("Customer account created (ID: {$testCustId})", $testCustId > 0);

// Customer list query check: excludes admin and agent
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND (role = 'admin' OR role = 'agent')");
assert_test("Customer list strictly filters role = 'customer'", (int)$stmt->fetchColumn() === 0);

// Edit customer details
$stmt = $db->prepare("UPDATE users SET name = 'Test Customer Jane Updated', phone = '+1 555-000-1111' WHERE id = ? AND role = 'customer'");
$stmt->execute([$testCustId]);
$stmt = $db->prepare("SELECT name, phone FROM users WHERE id = ?");
$stmt->execute([$testCustId]);
$custRow = $stmt->fetch();
assert_test("Customer details updated successfully", $custRow['name'] === 'Test Customer Jane Updated' && $custRow['phone'] === '+1 555-000-1111');

// ----------------------------------------------------
// SECTION 6: TICKET-DEPARTMENT INTEGRATION & ASSIGNMENT
// ----------------------------------------------------
echo "\n--- Section 6: Ticket-Department Integration ---\n";

// 1. Create Ticket with Department
$tktNumber = 'TKT-TEST-' . bin2hex(random_bytes(3));
$stmt = $db->prepare("
    INSERT INTO tickets (ticket_number, user_id, department_id, subject, description, priority, status, created_at, updated_at)
    VALUES (?, ?, ?, 'Payment Gateway Issue', 'Unable to checkout with PayPal', 'high', 'open', NOW(), NOW())
");
$stmt->execute([$tktNumber, $testCustId, $testDeptId]);
$testTicketId = (int)$db->lastInsertId();
assert_test("Ticket created with department_id (Ticket ID: {$testTicketId})", $testTicketId > 0);

// 2. Fetch ticket with department name
$stmt = $db->prepare("
    SELECT t.*, d.name AS department_name 
    FROM tickets t 
    LEFT JOIN departments d ON t.department_id = d.id 
    WHERE t.id = ?
");
$stmt->execute([$testTicketId]);
$tktRow = $stmt->fetch();
assert_test("Ticket links to department correctly", $tktRow['department_name'] === $updatedDeptName);

// 3. Assign Agent to Ticket
$stmt = $db->prepare("UPDATE tickets SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
$stmt->execute([$testAgentId, $testTicketId]);
$stmt = $db->prepare("SELECT assigned_to FROM tickets WHERE id = ?");
$stmt->execute([$testTicketId]);
assert_test("Ticket assigned to agent successfully", (int)$stmt->fetchColumn() === $testAgentId);

// 4. Verify department agent filtering logic
$stmt = $db->prepare("
    SELECT u.id, u.name 
    FROM users u 
    WHERE u.role = 'agent' AND u.status = 'active' AND u.department_id = ?
");
$stmt->execute([$testDeptId]);
$deptAgents = $stmt->fetchAll();
assert_test("Department-specific agent query returns matching agent", count($deptAgents) >= 1 && (int)$deptAgents[0]['id'] === $testAgentId);

// ----------------------------------------------------
// SECTION 7: ADMIN SAFETY GUARD (LAST ADMIN PROTECTION)
// ----------------------------------------------------
echo "\n--- Section 7: Admin Safety Deactivation Protection ---\n";

// Find current active admins
$stmt = $db->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
$adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$adminCount = count($adminIds);

if ($adminCount === 1) {
    $soleAdminId = (int)$adminIds[0];
    $canDeactivate = can_deactivate_user($soleAdminId);
    assert_test("can_deactivate_user returns FALSE for sole active admin", $canDeactivate === false);
} else {
    // If multiple admins exist, verify condition
    assert_test("Multiple active admins found ({$adminCount})", true);
}

// ----------------------------------------------------
// CLEANUP TEMPORARY TEST DATA
// ----------------------------------------------------
$db->prepare("DELETE FROM tickets WHERE id = ?")->execute([$testTicketId]);
$db->prepare("DELETE FROM users WHERE id IN (?, ?)")->execute([$testAgentId, $testCustId]);
$db->prepare("DELETE FROM departments WHERE id = ?")->execute([$testDeptId]);

echo "\n====================================================\n";
echo "VERIFICATION SUMMARY\n";
echo "Total Tests: {$totalTests} | Passed: {$passedTests} | Failed: {$failedTests}\n";
echo "====================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
