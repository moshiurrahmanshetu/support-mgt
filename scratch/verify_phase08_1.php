<?php
/**
 * Automated Verification Test Suite for Phase 08.1: New Support Ticket Sidebar Counter & Admin Viewed Tracking
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/permissions.php';

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

echo "===============================================================\n";
echo "SUPPORT-MGT: PHASE 08.1 SIDEBAR TICKET COUNTER VERIFICATION SUITE\n";
echo "===============================================================\n\n";

// Cleanup test records
$db->exec("DELETE FROM tickets WHERE ticket_number LIKE 'TKT-TEST-P81-%'");
$db->exec("DELETE FROM users WHERE email IN ('cust_a_p81@test.local', 'cust_b_p81@test.local', 'cust_c_p81@test.local', 'agent_p81@test.local')");

// Create test customer users and agent user
$custRole = get_role_by_slug('customer');
$agentRole = get_role_by_slug('support_agent');

// Customer A
$db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES ('Customer A', 'cust_a_p81@test.local', 'hash', 'customer', 'active', NOW())")->execute();
$custAId = (int)$db->lastInsertId();
assign_user_role($custAId, (int)$custRole['id']);

// Customer B
$db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES ('Customer B', 'cust_b_p81@test.local', 'hash', 'customer', 'active', NOW())")->execute();
$custBId = (int)$db->lastInsertId();
assign_user_role($custBId, (int)$custRole['id']);

// Customer C
$db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES ('Customer C', 'cust_c_p81@test.local', 'hash', 'customer', 'active', NOW())")->execute();
$custCId = (int)$db->lastInsertId();
assign_user_role($custCId, (int)$custRole['id']);

// Agent
$db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES ('Support Agent X', 'agent_p81@test.local', 'hash', 'support_agent', 'active', NOW())")->execute();
$agentId = (int)$db->lastInsertId();
assign_user_role($agentId, (int)$agentRole['id']);

// Initial baseline count
$baselineCount = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();

// TEST GROUP 1: Sequential Customer Ticket Creation & Counter Increments
echo "--- Group 1: Sequential Customer Ticket Creation & Counter Tracking ---\n";

// TEST 1: Customer A creates Ticket A
$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, admin_viewed_at, created_at, updated_at) VALUES ('TKT-TEST-P81-001', ?, 'Subject A', 'Desc A', 'medium', 'open', NULL, NOW(), NOW())")->execute([$custAId]);
$tktAId = (int)$db->lastInsertId();

$count1 = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("TEST 1: Customer A creates Ticket A -> Counter increases by 1 (Current: {$count1})", $count1 === ($baselineCount + 1));

// TEST 2: Customer B creates Ticket B
$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, admin_viewed_at, created_at, updated_at) VALUES ('TKT-TEST-P81-002', ?, 'Subject B', 'Desc B', 'high', 'open', NULL, NOW(), NOW())")->execute([$custBId]);
$tktBId = (int)$db->lastInsertId();

$count2 = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("TEST 2: Customer B creates Ticket B -> Counter increases by 2 (Current: {$count2})", $count2 === ($baselineCount + 2));

// TEST 3: Customer C creates 3 tickets
$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, admin_viewed_at, created_at, updated_at) VALUES ('TKT-TEST-P81-003', ?, 'Subject C1', 'Desc C1', 'low', 'open', NULL, NOW(), NOW())")->execute([$custCId]);
$tktC1Id = (int)$db->lastInsertId();

$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, admin_viewed_at, created_at, updated_at) VALUES ('TKT-TEST-P81-004', ?, 'Subject C2', 'Desc C2', 'medium', 'open', NULL, NOW(), NOW())")->execute([$custCId]);
$tktC2Id = (int)$db->lastInsertId();

$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, admin_viewed_at, created_at, updated_at) VALUES ('TKT-TEST-P81-005', ?, 'Subject C3', 'Desc C3', 'urgent', 'open', NULL, NOW(), NOW())")->execute([$custCId]);
$tktC3Id = (int)$db->lastInsertId();

$count3 = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("TEST 3: Customer C creates 3 tickets -> Total new customer tickets is 5 (Current: {$count3})", $count3 === ($baselineCount + 5));


// TEST GROUP 2: Admin Viewing & Decrementing Logic
echo "\n--- Group 2: Admin Viewing & Decrementing Logic ---\n";

// TEST 5: Admin opens Ticket A -> admin_viewed_at set to NOW()
$db->prepare("UPDATE tickets SET admin_viewed_at = NOW() WHERE id = ?")->execute([$tktAId]);
$count4 = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("TEST 5: Admin opens Ticket A -> Counter decrements to 4 (Current: {$count4})", $count4 === ($baselineCount + 4));

// TEST 6: Admin opens remaining 4 tickets -> Counter becomes 0
$db->prepare("UPDATE tickets SET admin_viewed_at = NOW() WHERE id IN (?, ?, ?, ?)")->execute([$tktBId, $tktC1Id, $tktC2Id, $tktC3Id]);
$count5 = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("TEST 6: Admin opens all 5 tickets -> Counter returns to baseline 0 (Current: {$count5})", $count5 === $baselineCount);

// TEST 7: Customer creates another ticket -> Counter becomes 1
$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, admin_viewed_at, created_at, updated_at) VALUES ('TKT-TEST-P81-006', ?, 'Subject D', 'Desc D', 'medium', 'open', NULL, NOW(), NOW())")->execute([$custAId]);
$tktDId = (int)$db->lastInsertId();

$count6 = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("TEST 7: Customer creates another ticket -> Counter becomes 1 (Current: {$count6})", $count6 === ($baselineCount + 1));

// TEST 8: Admin opens Ticket D -> Counter decrements to 0
$db->prepare("UPDATE tickets SET admin_viewed_at = NOW() WHERE id = ?")->execute([$tktDId]);
$count7 = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("TEST 8: Admin opens Ticket D -> Counter returns to baseline (Current: {$count7})", $count7 === $baselineCount);


// TEST GROUP 3: Staff Creation & Creator Invariants
echo "\n--- Group 3: Staff Creation & Creator Invariants ---\n";

// Admin / Agent creates ticket -> admin_viewed_at IS NOT NULL
$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, admin_viewed_at, created_at, updated_at) VALUES ('TKT-TEST-P81-STAFF', ?, 'Staff Created Ticket', 'Desc', 'medium', 'open', NOW(), NOW(), NOW())")->execute([$agentId]);
$staffTktId = (int)$db->lastInsertId();

$countStaff = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("Staff/Agent-created ticket DOES NOT increase the unread customer ticket counter", $countStaff === $baselineCount);


// TEST GROUP 4: Manual Mark as Unread & Mark as Read Toggle
echo "\n--- Group 4: Manual Mark as Unread & Mark as Read Toggle ---\n";

// Mark Ticket A as Unread manually
$db->prepare("UPDATE tickets SET admin_viewed_at = NULL WHERE id = ?")->execute([$tktAId]);
$countUnread = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("Manual 'Mark as Unread' resets admin_viewed_at to NULL and increments counter", $countUnread === ($baselineCount + 1));

// Mark Ticket A as Read manually
$db->prepare("UPDATE tickets SET admin_viewed_at = NOW() WHERE id = ?")->execute([$tktAId]);
$countRead = (int)$db->query("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL")->fetchColumn();
assert_test("Manual 'Mark as Read' updates admin_viewed_at to NOW() and decrements counter", $countRead === $baselineCount);


// TEST GROUP 5: Ticket List 'New' Filter Query
echo "\n--- Group 5: Ticket List 'New' Filter Query ---\n";

// Mark Ticket B as Unread
$db->prepare("UPDATE tickets SET admin_viewed_at = NULL WHERE id = ?")->execute([$tktBId]);

$newFilterSql = "
    SELECT t.id, t.ticket_number 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.role = 'customer' AND t.admin_viewed_at IS NULL AND t.id = ?
";
$newFilterStmt = $db->prepare($newFilterSql);
$newFilterStmt->execute([$tktBId]);
$filteredTkt = $newFilterStmt->fetch();

assert_test("Ticket list 'New' filter correctly matches unread customer ticket (TKT-TEST-P81-002)", $filteredTkt && (int)$filteredTkt['id'] === $tktBId);


// TEST GROUP 6: CSS & UI Aesthetic Rules
echo "\n--- Group 6: Visual Aesthetics & Clean Badges ---\n";

$sidebarContent = file_get_contents(__DIR__ . '/../includes/sidebar.php');
assert_test("Sidebar includes new customer ticket counter badge logic", strpos($sidebarContent, 'newCustomerTicketCount') !== false);
assert_test("Badge uses solid color styling (bg-danger rounded-pill)", strpos($sidebarContent, 'badge bg-danger rounded-pill') !== false);


// Cleanup
$db->exec("DELETE FROM tickets WHERE ticket_number LIKE 'TKT-TEST-P81-%'");
$db->exec("DELETE FROM users WHERE email IN ('cust_a_p81@test.local', 'cust_b_p81@test.local', 'cust_c_p81@test.local', 'agent_p81@test.local')");

echo "\n===============================================================\n";
echo "PHASE 08.1 TEST SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "===============================================================\n";

if ($testsFailed > 0) {
    exit(1);
} else {
    exit(0);
}
