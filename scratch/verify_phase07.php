<?php
/**
 * Automated Verification Test Suite for Phase 07
 * Reports & Analytics + Dashboard Enhancement
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/reports.php';
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
echo "SUPPORT-MGT: PHASE 07 AUTOMATED VERIFICATION SUITE\n";
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
// SECTION 2: DATE RANGE HELPER & PRESETS
// ----------------------------------------------------
echo "\n--- Section 2: Date Range Helper Validation ---\n";

$rToday = get_report_date_range(['date_range' => 'today']);
assert_test("Preset 'today' sets today's date", $rToday['from_date'] === date('Y-m-d') && $rToday['to_date'] === date('Y-m-d'));

$rYesterday = get_report_date_range(['date_range' => 'yesterday']);
$yDate = date('Y-m-d', strtotime('-1 day'));
assert_test("Preset 'yesterday' sets yesterday's date", $rYesterday['from_date'] === $yDate && $rYesterday['to_date'] === $yDate);

$r7 = get_report_date_range(['date_range' => 'last_7_days']);
$d7Ago = date('Y-m-d', strtotime('-6 days'));
assert_test("Preset 'last_7_days' spans 7 days", $r7['from_date'] === $d7Ago && $r7['to_date'] === date('Y-m-d'));

$r30 = get_report_date_range(['date_range' => 'last_30_days']);
$d30Ago = date('Y-m-d', strtotime('-29 days'));
assert_test("Preset 'last_30_days' spans 30 days", $r30['from_date'] === $d30Ago && $r30['to_date'] === date('Y-m-d'));

$rThisMonth = get_report_date_range(['date_range' => 'this_month']);
assert_test("Preset 'this_month' starts on 1st of current month", $rThisMonth['from_date'] === date('Y-m-01'));

$rCustomValid = get_report_date_range(['date_range' => 'custom', 'from_date' => '2026-01-01', 'to_date' => '2026-01-15']);
assert_test("Preset 'custom' accepts valid dates", $rCustomValid['from_date'] === '2026-01-01' && $rCustomValid['to_date'] === '2026-01-15');

$rCustomReversed = get_report_date_range(['date_range' => 'custom', 'from_date' => '2026-02-15', 'to_date' => '2026-02-01']);
assert_test("Preset 'custom' auto-swaps reversed from/to dates", $rCustomReversed['from_date'] === '2026-02-01' && $rCustomReversed['to_date'] === '2026-02-15');

$rCustomInvalid = get_report_date_range(['date_range' => 'custom', 'from_date' => 'invalid-date', 'to_date' => 'not-a-date']);
assert_test("Preset 'custom' with invalid dates safely falls back to default last_30_days", $rCustomInvalid['preset'] === 'last_30_days');

// ----------------------------------------------------
// SECTION 3: CALCULATION & DIVISION BY ZERO SAFETY
// ----------------------------------------------------
echo "\n--- Section 3: Safe Calculations & Duration Formatter ---\n";

assert_test("safe_percentage(0, 0) returns 0.0 with no error", safe_percentage(0, 0) === 0.0);
assert_test("safe_percentage(10, 0) returns 0.0 with no error", safe_percentage(10, 0) === 0.0);
assert_test("safe_percentage(25, 100) returns 25.0", safe_percentage(25, 100) === 25.0);
assert_test("safe_percentage(1, 3) returns 33.3", safe_percentage(1, 3) === 33.3);

assert_test("format_duration(null) returns 'N/A'", format_duration(null) === 'N/A');
assert_test("format_duration(0) returns '0m'", format_duration(0) === '0m');
assert_test("format_duration(-10) returns '0m'", format_duration(-10) === '0m');
assert_test("format_duration(45) returns '< 1m'", format_duration(45) === '< 1m');
assert_test("format_duration(1500) returns '25m'", format_duration(1500) === '25m');
assert_test("format_duration(5400) returns '1h 30m'", format_duration(5400) === '1h 30m');
assert_test("format_duration(7200) returns '2h'", format_duration(7200) === '2h');
assert_test("format_duration(90000) returns '1d 1h'", format_duration(90000) === '1d 1h');
assert_test("format_duration(86400) returns '1d'", format_duration(86400) === '1d');

// ----------------------------------------------------
// SECTION 4: CSV SECURITY & FORMULA INJECTION
// ----------------------------------------------------
echo "\n--- Section 4: CSV Formula Injection Protection ---\n";

assert_test("sanitize_csv_cell('=1+1') prepends single quote", sanitize_csv_cell('=1+1') === "'=1+1");
assert_test("sanitize_csv_cell('+cmd|/c') prepends single quote", sanitize_csv_cell('+cmd|/c') === "'+cmd|/c");
assert_test("sanitize_csv_cell('-500') prepends single quote", sanitize_csv_cell('-500') === "'-500");
assert_test("sanitize_csv_cell('@SUM(A1)') prepends single quote", sanitize_csv_cell('@SUM(A1)') === "'@SUM(A1)");
assert_test("sanitize_csv_cell('Safe Customer') remains unchanged", sanitize_csv_cell('Safe Customer') === 'Safe Customer');
assert_test("sanitize_csv_cell(null) returns empty string", sanitize_csv_cell(null) === '');

// ----------------------------------------------------
// SECTION 5: DATABASE REPORT QUERIES
// ----------------------------------------------------
echo "\n--- Section 5: Report SQL Queries Execution ---\n";

// 1. Overview KPIs Query
$overviewStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
        AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) END) AS avg_resp,
        AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) END) AS avg_res
    FROM tickets
    WHERE created_at BETWEEN ? AND ?
");
$overviewStmt->execute([$r30['from'], $r30['to']]);
$ov = $overviewStmt->fetch();
assert_test("Overview KPIs query executes cleanly without SQL errors", is_array($ov));

// 2. Department Report Query
$deptStmt = $db->prepare("
    SELECT 
        d.name,
        COUNT(t.id) AS total_tickets,
        AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.resolved_at) END) AS avg_resolution
    FROM departments d
    LEFT JOIN tickets t ON d.id = t.department_id AND t.created_at BETWEEN ? AND ?
    GROUP BY d.id
");
$deptStmt->execute([$r30['from'], $r30['to']]);
$depts = $deptStmt->fetchAll();
assert_test("Department performance query executes cleanly", is_array($depts));

// 3. Agent Performance Query
$agentStmt = $db->prepare("
    SELECT 
        u.name,
        COUNT(t.id) AS assigned_tickets,
        AVG(CASE WHEN t.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.first_response_at) END) AS avg_resp
    FROM users u
    LEFT JOIN tickets t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
    WHERE u.role IN ('agent', 'admin')
    GROUP BY u.id
");
$agentStmt->execute([$r30['from'], $r30['to']]);
$agents = $agentStmt->fetchAll();
assert_test("Agent performance query executes cleanly", is_array($agents));

// 4. Customer Report Query
$custStmt = $db->prepare("
    SELECT 
        u.name,
        COUNT(t.id) AS total_tickets,
        MAX(t.created_at) AS last_ticket
    FROM users u
    LEFT JOIN tickets t ON u.id = t.user_id AND t.created_at BETWEEN ? AND ?
    WHERE u.role = 'customer'
    GROUP BY u.id
    LIMIT 20 OFFSET 0
");
$custStmt->execute([$r30['from'], $r30['to']]);
$custs = $custStmt->fetchAll();
assert_test("Customer analytics query executes cleanly", is_array($custs));

// ----------------------------------------------------
// SECTION 6: ACTIVITY LOGGING
// ----------------------------------------------------
echo "\n--- Section 6: Report Activity Logging ---\n";

$log1 = log_activity(1, 'reports', 'report_viewed', 'Viewed test report in verification suite');
assert_test("log_activity() persisted report_viewed event", $log1 === true);

$log2 = log_activity(1, 'reports', 'report_exported', 'Exported test CSV report in verification suite');
assert_test("log_activity() persisted report_exported event", $log2 === true);

// Clean up test logs
$db->prepare("DELETE FROM activity_logs WHERE description LIKE '%in verification suite%'")->execute();

echo "\n====================================================\n";
echo "VERIFICATION SUMMARY\n";
echo "Total Tests: {$totalTests} | Passed: {$passedTests} | Failed: {$failedTests}\n";
echo "====================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
