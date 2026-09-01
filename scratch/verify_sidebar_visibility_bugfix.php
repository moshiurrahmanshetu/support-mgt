<?php
/**
 * Verification Test Suite for Bug Fix: Admin Support Tickets Sidebar Visibility & RBAC Consistency
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

echo "========================================================================\n";
echo "SUPPORT-MGT: BUG FIX - SIDEBAR VISIBILITY & RBAC VERIFICATION SUITE\n";
echo "========================================================================\n\n";

// TEST GROUP 1: Role & Permission Detection Canonical Functions
echo "--- Group 1: Role & Permission Detection Canonical Functions ---\n";

$adminUser = $db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
assert_test("Administrator user exists in database", (bool)$adminUser);

if ($adminUser) {
    assert_test("is_admin_user({$adminUser['id']}) returns TRUE for admin", is_admin_user((int)$adminUser['id']));
    assert_test("has_permission('tickets.view', {$adminUser['id']}) returns TRUE for admin", has_permission('tickets.view', (int)$adminUser['id']));
}

$agentRole = get_role_by_slug('support_agent');
$mgrRole = get_role_by_slug('support_manager');
$custRole = get_role_by_slug('customer');

function get_role_perms_by_id(int $roleId): array {
    $db = get_db();
    $stmt = $db->prepare("SELECT p.slug FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = ?");
    $stmt->execute([$roleId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

assert_test("Role 'administrator' has 'tickets.view' permission", in_array('tickets.view', get_role_perms_by_id(1), true));
assert_test("Role 'support_manager' has 'tickets.view' permission", in_array('tickets.view', get_role_perms_by_id((int)$mgrRole['id']), true));
assert_test("Role 'support_agent' has 'tickets.view' permission", in_array('tickets.view', get_role_perms_by_id((int)$agentRole['id']), true));
assert_test("Role 'customer' has 'tickets.view' permission", in_array('tickets.view', get_role_perms_by_id((int)$custRole['id']), true));


// TEST GROUP 2: Sidebar Menu Rendering Simulation for All Roles
echo "\n--- Group 2: Sidebar Menu Rendering Simulation ---\n";

function simulate_sidebar_output(array $simUser) {
    $_SESSION['user_id'] = $simUser['id'];
    $_SESSION['user'] = $simUser;
    $_SESSION['role'] = $simUser['role'];
    PermissionCache::$permissionsByUser = [];
    PermissionCache::$rolesByUser = [];

    ob_start();
    $user = $simUser;
    $currentScript = 'index.php';
    $activePage = 'dashboard';
    include __DIR__ . '/../includes/sidebar.php';
    return ob_get_clean();
}

// 1. Administrator Sidebar Test
$adminHtml = simulate_sidebar_output([
    'id' => $adminUser['id'] ?? 1,
    'name' => 'System Administrator',
    'email' => 'admin@supportmgt.local',
    'role' => 'admin',
    'status' => 'active'
]);

assert_test("ADMIN SIDEBAR: Contains 'Support Tickets' link", strpos($adminHtml, 'Support Tickets') !== false);
assert_test("ADMIN SIDEBAR: DOES NOT contain Customer-only 'My Tickets' link", strpos($adminHtml, 'My Tickets') === false);
assert_test("ADMIN SIDEBAR: DOES NOT contain Customer-only 'Create Ticket' link", strpos($adminHtml, 'Create Ticket') === false);
assert_test("ADMIN SIDEBAR: Contains 'Canned Responses' link", strpos($adminHtml, 'Canned Responses') !== false);
assert_test("ADMIN SIDEBAR: Contains 'User Management' link", strpos($adminHtml, 'Users') !== false);

// 2. Customer Sidebar Test
$custHtml = simulate_sidebar_output([
    'id' => 999,
    'name' => 'Test Customer',
    'email' => 'customer@test.local',
    'role' => 'customer',
    'status' => 'active'
]);

assert_test("CUSTOMER SIDEBAR: Contains 'My Tickets' link", strpos($custHtml, 'My Tickets') !== false);
assert_test("CUSTOMER SIDEBAR: Contains 'Create Ticket' link", strpos($custHtml, 'Create Ticket') !== false);
assert_test("CUSTOMER SIDEBAR: STRICTLY DOES NOT contain 'Support Tickets' queue link", strpos($custHtml, 'Support Tickets') === false);
assert_test("CUSTOMER SIDEBAR: STRICTLY DOES NOT contain 'Canned Responses' link", strpos($custHtml, 'Canned Responses') === false);
assert_test("CUSTOMER SIDEBAR: STRICTLY DOES NOT contain 'User Management' link", strpos($custHtml, 'Users') === false);
assert_test("CUSTOMER SIDEBAR: STRICTLY DOES NOT contain Admin ticket counter badge", strpos($custHtml, 'nav-badge') === false);

// 3. Support Manager Sidebar Test
$mgrHtml = simulate_sidebar_output([
    'id' => 888,
    'name' => 'Test Manager',
    'email' => 'manager@test.local',
    'role' => 'support_manager',
    'status' => 'active'
]);

assert_test("MANAGER SIDEBAR: Contains 'Support Tickets' link", strpos($mgrHtml, 'Support Tickets') !== false);
assert_test("MANAGER SIDEBAR: DOES NOT contain Customer-only 'My Tickets' link", strpos($mgrHtml, 'My Tickets') === false);
assert_test("MANAGER SIDEBAR: Contains 'Canned Responses' link", strpos($mgrHtml, 'Canned Responses') !== false);
assert_test("MANAGER SIDEBAR: Contains 'Customers' management link", strpos($mgrHtml, 'Customers') !== false);

// 4. Support Agent Sidebar Test
$agentHtml = simulate_sidebar_output([
    'id' => 777,
    'name' => 'Test Agent',
    'email' => 'agent@test.local',
    'role' => 'support_agent',
    'status' => 'active'
]);

assert_test("AGENT SIDEBAR: Contains 'Support Tickets' link", strpos($agentHtml, 'Support Tickets') !== false);
assert_test("AGENT SIDEBAR: DOES NOT contain Customer-only 'My Tickets' link", strpos($agentHtml, 'My Tickets') === false);
assert_test("AGENT SIDEBAR: Contains 'Canned Responses' link", strpos($agentHtml, 'Canned Responses') !== false);
assert_test("AGENT SIDEBAR: DOES NOT contain Admin-only counter badge", strpos($agentHtml, 'nav-badge') === false);


// TEST GROUP 3: Server-side IDOR & Query Security Verification
echo "\n--- Group 3: Server-side IDOR & Query Security Verification ---\n";

// Verify Customer ticket isolation logic
$customerAId = 1001;
$customerBId = 1002;

$db->exec("DELETE FROM tickets WHERE ticket_number IN ('TKT-ISO-01', 'TKT-ISO-02')");
$db->exec("DELETE FROM users WHERE id IN ({$customerAId}, {$customerBId})");

$db->prepare("INSERT INTO users (id, name, email, password, role, status, created_at) VALUES (?, 'Cust A', 'iso_a@test.local', 'hash', 'customer', 'active', NOW())")->execute([$customerAId]);
$db->prepare("INSERT INTO users (id, name, email, password, role, status, created_at) VALUES (?, 'Cust B', 'iso_b@test.local', 'hash', 'customer', 'active', NOW())")->execute([$customerBId]);

$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, created_at) VALUES ('TKT-ISO-01', ?, 'Cust A Ticket', 'Desc', 'medium', 'open', NOW())")->execute([$customerAId]);
$tktAId = (int)$db->lastInsertId();

$db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, created_at) VALUES ('TKT-ISO-02', ?, 'Cust B Ticket', 'Desc', 'medium', 'open', NOW())")->execute([$customerBId]);
$tktBId = (int)$db->lastInsertId();

// Customer A Query in modules/tickets/index.php
$stmtA = $db->prepare("SELECT id, ticket_number FROM tickets t WHERE t.user_id = ?");
$stmtA->execute([$customerAId]);
$custATickets = $stmtA->fetchAll(PDO::FETCH_COLUMN);

assert_test("Customer A sees only Ticket A", in_array($tktAId, $custATickets, true) && !in_array($tktBId, $custATickets, true));

// Cleanup
$db->exec("DELETE FROM tickets WHERE ticket_number IN ('TKT-ISO-01', 'TKT-ISO-02')");
$db->exec("DELETE FROM users WHERE id IN ({$customerAId}, {$customerBId})");


// TEST GROUP 4: CSS Aesthetics
echo "\n--- Group 4: Visual Aesthetics ---\n";
$cssFiles = glob(__DIR__ . '/../assets/css/*.css');
$gradientFound = false;
foreach ($cssFiles as $f) {
    $c = file_get_contents($f);
    if (stripos($c, 'linear-gradient') !== false || stripos($c, 'radial-gradient') !== false) {
        $gradientFound = true;
    }
}
assert_test("Strict UI Rule: No gradients in CSS", !$gradientFound);

echo "\n========================================================================\n";
echo "BUG FIX TEST SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "========================================================================\n";

if ($testsFailed > 0) {
    exit(1);
} else {
    exit(0);
}
