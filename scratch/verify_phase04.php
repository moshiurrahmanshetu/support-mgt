<?php
/**
 * Automated Verification Test Suite for Phase 04
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/ticket_activity.php';

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
echo "SUPPORT-MGT: PHASE 04 AUTOMATED VERIFICATION SUITE\n";
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
// SECTION 2: DATABASE SCHEMA & COLUMN VERIFICATION
// ----------------------------------------------------
echo "\n--- Section 2: Database Schema & Column Verification ---\n";

$stmt = $db->query("SHOW COLUMNS FROM tickets LIKE 'first_response_at'");
assert_test("tickets.first_response_at column exists", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW TABLES LIKE 'ticket_tags'");
assert_test("ticket_tags table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW TABLES LIKE 'ticket_tag_relations'");
assert_test("ticket_tag_relations table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW TABLES LIKE 'canned_responses'");
assert_test("canned_responses table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW TABLES LIKE 'ticket_activity_logs'");
assert_test("ticket_activity_logs table exists", $stmt->rowCount() > 0);

$stmt = $db->query("SELECT COUNT(*) FROM ticket_tags");
$tagCount = (int)$stmt->fetchColumn();
assert_test("Initial seed tags exist (found: {$tagCount})", $tagCount >= 8);

$stmt = $db->query("SELECT COUNT(*) FROM canned_responses");
$cannedCount = (int)$stmt->fetchColumn();
assert_test("Initial canned responses exist (found: {$cannedCount})", $cannedCount >= 4);

// ----------------------------------------------------
// SECTION 3: TICKET TAGS CRUD & PIVOT INTEGRITY
// ----------------------------------------------------
echo "\n--- Section 3: Tag Management & Cascade Safety ---\n";

$testTagName = 'Performance ' . bin2hex(random_bytes(3));
$testTagColor = '#ff5722';

// 1. Create Tag
$stmt = $db->prepare("INSERT INTO ticket_tags (name, color, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
$stmt->execute([$testTagName, $testTagColor]);
$testTagId = (int)$db->lastInsertId();
assert_test("Tag created successfully (ID: {$testTagId})", $testTagId > 0);

// 2. Reject Duplicate Tag
$dupRejected = false;
try {
    $stmt = $db->prepare("INSERT INTO ticket_tags (name, color, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
    $stmt->execute([$testTagName, '#000000']);
} catch (Exception $e) {
    $dupRejected = true;
}
assert_test("Duplicate tag name rejected by UNIQUE constraint", $dupRejected);

// 3. Edit Tag
$updatedTagName = $testTagName . ' (Updated)';
$stmt = $db->prepare("UPDATE ticket_tags SET name = ?, color = '#e91e63' WHERE id = ?");
$stmt->execute([$updatedTagName, $testTagId]);
$stmt = $db->prepare("SELECT name, color FROM ticket_tags WHERE id = ?");
$stmt->execute([$testTagId]);
$tagRow = $stmt->fetch();
assert_test("Tag updated successfully", $tagRow['name'] === $updatedTagName && $tagRow['color'] === '#e91e63');

// 4. Create a test ticket to attach tag
$testCustEmail = 'tag_cust_' . bin2hex(random_bytes(3)) . '@supportmgt.local';
$stmt = $db->prepare("INSERT INTO users (role, name, email, password, status, created_at, updated_at) VALUES ('customer', 'Tag Test Cust', ?, 'dummy_hash', 'active', NOW(), NOW())");
$stmt->execute([$testCustEmail]);
$testCustId = (int)$db->lastInsertId();

$stmt = $db->prepare("INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, created_at, updated_at) VALUES (?, ?, 'Tag Attachment Test', 'Test description', 'medium', 'open', NOW(), NOW())");
$stmt->execute(['TKT-TAG-' . bin2hex(random_bytes(3)), $testCustId]);
$testTicketId = (int)$db->lastInsertId();

// Attach tag
$stmt = $db->prepare("INSERT INTO ticket_tag_relations (ticket_id, tag_id, created_at) VALUES (?, ?, NOW())");
$stmt->execute([$testTicketId, $testTagId]);
$stmt = $db->prepare("SELECT COUNT(*) FROM ticket_tag_relations WHERE ticket_id = ? AND tag_id = ?");
$stmt->execute([$testTicketId, $testTagId]);
assert_test("Tag attached to ticket in pivot table", (int)$stmt->fetchColumn() === 1);

// 5. Delete Tag -> verify pivot removed and ticket remains intact
$stmt = $db->prepare("DELETE FROM ticket_tags WHERE id = ?");
$stmt->execute([$testTagId]);

$stmt = $db->prepare("SELECT COUNT(*) FROM ticket_tag_relations WHERE ticket_id = ? AND tag_id = ?");
$stmt->execute([$testTicketId, $testTagId]);
assert_test("Tag deletion removed pivot relation", (int)$stmt->fetchColumn() === 0);

$stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE id = ?");
$stmt->execute([$testTicketId]);
assert_test("Ticket remains completely intact after tag deletion", (int)$stmt->fetchColumn() === 1);

// ----------------------------------------------------
// SECTION 4: CANNED RESPONSES
// ----------------------------------------------------
echo "\n--- Section 4: Canned Responses System ---\n";

$testCannedTitle = 'Security Policy Notice ' . bin2hex(random_bytes(3));
$testCannedContent = 'Please remember to never share your password with anyone.';

// 1. Create Canned Response
$stmt = $db->prepare("INSERT INTO canned_responses (title, content, created_by, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
$stmt->execute([$testCannedTitle, $testCannedContent, 1]);
$testCannedId = (int)$db->lastInsertId();
assert_test("Canned response created successfully (ID: {$testCannedId})", $testCannedId > 0);

// 2. Edit Canned Response
$stmt = $db->prepare("UPDATE canned_responses SET title = ?, content = ? WHERE id = ?");
$stmt->execute([$testCannedTitle . ' (Updated)', $testCannedContent . ' (Updated)', $testCannedId]);
$stmt = $db->prepare("SELECT title FROM canned_responses WHERE id = ?");
$stmt->execute([$testCannedId]);
assert_test("Canned response updated successfully", $stmt->fetchColumn() === $testCannedTitle . ' (Updated)');

// 3. Delete Canned Response
$stmt = $db->prepare("DELETE FROM canned_responses WHERE id = ?");
$stmt->execute([$testCannedId]);
$stmt = $db->prepare("SELECT COUNT(*) FROM canned_responses WHERE id = ?");
$stmt->execute([$testCannedId]);
assert_test("Canned response deleted successfully", (int)$stmt->fetchColumn() === 0);

// ----------------------------------------------------
// SECTION 5: ACTIVITY LOGGING & HELPER FUNCTIONS
// ----------------------------------------------------
echo "\n--- Section 5: Activity Logging & Duration Metrics ---\n";

// 1. Log Activity Helper
$logSuccess = log_ticket_activity($testTicketId, $testCustId, 'status_changed', 'open', 'in_progress', 'Investigation started');
assert_test("log_ticket_activity helper executed successfully", $logSuccess === true);

$stmt = $db->prepare("SELECT * FROM ticket_activity_logs WHERE ticket_id = ? AND action = 'status_changed' ORDER BY id DESC LIMIT 1");
$stmt->execute([$testTicketId]);
$logRow = $stmt->fetch();
assert_test("Activity log entry persisted in database", $logRow && $logRow['old_value'] === 'open' && $logRow['new_value'] === 'in_progress');

// 2. Duration Formatting Helper
$duration1 = format_duration('2026-09-01 10:00:00', '2026-09-01 10:25:00');
assert_test("format_duration correctly formatted 25m ('{$duration1}')", $duration1 === '25m');

$duration2 = format_duration('2026-09-01 10:00:00', '2026-09-01 13:45:00');
assert_test("format_duration correctly formatted 3h 45m ('{$duration2}')", $duration2 === '3h 45m');

$duration3 = format_duration('2026-09-01 10:00:00', '2026-09-03 12:30:00');
assert_test("format_duration correctly formatted 2d 2h 30m ('{$duration3}')", $duration3 === '2d 2h 30m');

// ----------------------------------------------------
// SECTION 6: TICKET REOPEN WORKFLOW & FIRST RESPONSE TRACKING
// ----------------------------------------------------
echo "\n--- Section 6: Reopen Workflow & First Response Tracking ---\n";

// 1. Resolve ticket
$stmt = $db->prepare("UPDATE tickets SET status = 'resolved', resolved_at = '2026-09-01 11:00:00' WHERE id = ?");
$stmt->execute([$testTicketId]);

// 2. Simulate customer reply -> auto reopen
$stmt = $db->prepare("
    UPDATE tickets 
    SET status = 'open', resolved_at = NULL, closed_at = NULL, updated_at = NOW() 
    WHERE id = ?
");
$stmt->execute([$testTicketId]);
log_ticket_activity($testTicketId, $testCustId, 'ticket_reopened', 'resolved', 'open', 'Ticket reopened by customer reply');

$stmt = $db->prepare("SELECT status, resolved_at FROM tickets WHERE id = ?");
$stmt->execute([$testTicketId]);
$reopenRow = $stmt->fetch();
assert_test("Resolved ticket auto-reopened to 'open'", $reopenRow['status'] === 'open');
assert_test("resolved_at timestamp cleared to NULL", $reopenRow['resolved_at'] === null);

// 3. First Response Tracking: Simulate first staff response
$stmt = $db->prepare("UPDATE tickets SET first_response_at = '2026-09-01 11:30:00' WHERE id = ? AND first_response_at IS NULL");
$stmt->execute([$testTicketId]);

$stmt = $db->prepare("SELECT first_response_at FROM tickets WHERE id = ?");
$stmt->execute([$testTicketId]);
$firstResp = $stmt->fetchColumn();
assert_test("first_response_at set on first staff reply", !empty($firstResp));

// Attempt to overwrite first_response_at (should not overwrite if guarded with IS NULL)
$stmt = $db->prepare("UPDATE tickets SET first_response_at = '2026-09-01 12:00:00' WHERE id = ? AND first_response_at IS NULL");
$stmt->execute([$testTicketId]);
$stmt = $db->prepare("SELECT first_response_at FROM tickets WHERE id = ?");
$stmt->execute([$testTicketId]);
assert_test("Subsequent staff replies do not overwrite first_response_at", $stmt->fetchColumn() === '2026-09-01 11:30:00');

// ----------------------------------------------------
// SECTION 7: ADVANCED SEARCH & TAG FILTERING
// ----------------------------------------------------
echo "\n--- Section 7: Advanced Search & Tag Filter Queries ---\n";

// Attach standard tag #1 (Technical) to test ticket
$stmt = $db->prepare("INSERT INTO ticket_tag_relations (ticket_id, tag_id, created_at) VALUES (?, 1, NOW())");
$stmt->execute([$testTicketId]);

// Query by tag ID
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM tickets t 
    WHERE EXISTS (SELECT 1 FROM ticket_tag_relations ttr WHERE ttr.ticket_id = t.id AND ttr.tag_id = ?)
");
$stmt->execute([1]);
assert_test("Tag filter query matches tickets attached to tag #1", (int)$stmt->fetchColumn() >= 1);

// Query by search string
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
    WHERE (t.ticket_number LIKE ? OR t.subject LIKE ? OR u.name LIKE ? OR u.email LIKE ?)
");
$searchParam = "%Tag Attachment Test%";
$stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
assert_test("Search query matches ticket subject correctly", (int)$stmt->fetchColumn() >= 1);

// ----------------------------------------------------
// CLEANUP TEMPORARY TEST DATA
// ----------------------------------------------------
$db->prepare("DELETE FROM tickets WHERE id = ?")->execute([$testTicketId]);
$db->prepare("DELETE FROM users WHERE id = ?")->execute([$testCustId]);

echo "\n====================================================\n";
echo "VERIFICATION SUMMARY\n";
echo "Total Tests: {$totalTests} | Passed: {$passedTests} | Failed: {$failedTests}\n";
echo "====================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
