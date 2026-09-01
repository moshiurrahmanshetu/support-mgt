<?php
/**
 * Ticket Management - Update Ticket Department Handler (Admin Only)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/ticket_activity.php';

require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/tickets/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'modules/tickets/index.php');
}

$user = current_user();
$ticketId = (int)($_POST['ticket_id'] ?? 0);
$departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

if ($ticketId <= 0) {
    flash('danger', 'Invalid ticket ID.');
    redirect('modules/tickets/index.php');
}

$db = get_db();

// 1. Fetch Ticket with Current Department
$stmt = $db->prepare("
    SELECT t.id, t.department_id, d.name AS old_dept_name
    FROM tickets t
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found.');
    redirect('modules/tickets/index.php');
}

$oldDeptName = $ticket['old_dept_name'] ?: 'None';
$newDeptName = 'None';

// 2. Validate New Department
if ($departmentId !== null) {
    $deptStmt = $db->prepare("SELECT id, name FROM departments WHERE id = ? AND status = 'active' LIMIT 1");
    $deptStmt->execute([$departmentId]);
    $newDept = $deptStmt->fetch();

    if (!$newDept) {
        flash('danger', 'Selected department is invalid or inactive.');
        redirect('modules/tickets/view.php?id=' . $ticketId);
    }
    $newDeptName = $newDept['name'];
}

// 3. Update Department
if ((int)$ticket['department_id'] !== (int)$departmentId) {
    $updateStmt = $db->prepare("UPDATE tickets SET department_id = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$departmentId, $ticketId]);

    log_ticket_activity($ticketId, $user['id'], 'department_changed', $oldDeptName, $newDeptName, "Department updated to {$newDeptName}");
    flash('success', "Ticket department updated to <strong>" . e($newDeptName) . "</strong>.");
} else {
    flash('info', "Ticket is already assigned to {$newDeptName}.");
}

redirect('modules/tickets/view.php?id=' . $ticketId);
