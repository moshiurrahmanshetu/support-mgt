<?php
/**
 * Reports - Secure CSV Export Engine (Admin Only - Phase 07)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/reports.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$user = current_user();
$db = get_db();

// Parse Export Type & Date Range
$exportType = trim($_GET['type'] ?? 'tickets');
$dateRange = get_report_date_range($_GET, 'last_30_days');
$from = $dateRange['from'];
$to = $dateRange['to'];

$timestamp = date('Y-m-d_His');
$filename = "support_mgt_{$exportType}_{$timestamp}.csv";

// Activity log entry
log_activity($user['id'], 'reports', 'report_exported', "Exported {$exportType} CSV report ({$dateRange['label']})");

// Send HTTP headers for direct file download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM for perfect Microsoft Excel / LibreOffice compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

switch ($exportType) {
    case 'agents':
        // Header row
        fputcsv($output, [
            'Agent ID',
            'Name',
            'Email',
            'Department',
            'Status',
            'Assigned Tickets in Period',
            'Open Tickets',
            'In Progress Tickets',
            'Pending Tickets',
            'Resolved Tickets',
            'Closed Tickets',
            'Avg First Response (sec)',
            'Avg Resolution Time (sec)'
        ]);

        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.name AS agent_name,
                u.email AS agent_email,
                d.name AS department_name,
                u.status AS agent_status,
                COUNT(t.id) AS assigned_tickets,
                SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
                SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
                SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets,
                SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
                SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets,
                ROUND(AVG(CASE WHEN t.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.first_response_at) END)) AS avg_first_response_sec,
                ROUND(AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.resolved_at) END)) AS avg_resolution_sec
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN tickets t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
            WHERE u.role IN ('agent', 'admin')
            GROUP BY u.id
            ORDER BY assigned_tickets DESC, u.name ASC
        ");
        $stmt->execute([$from, $to]);

        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                sanitize_csv_cell($row['agent_name']),
                sanitize_csv_cell($row['agent_email']),
                sanitize_csv_cell($row['department_name'] ?: 'All Support'),
                sanitize_csv_cell($row['agent_status']),
                $row['assigned_tickets'],
                $row['open_tickets'],
                $row['in_progress_tickets'],
                $row['pending_tickets'],
                $row['resolved_tickets'],
                $row['closed_tickets'],
                $row['avg_first_response_sec'] ?? 'N/A',
                $row['avg_resolution_sec'] ?? 'N/A'
            ]);
        }
        break;

    case 'departments':
        // Header row
        fputcsv($output, [
            'Department ID',
            'Department Name',
            'Status',
            'Total Tickets in Period',
            'Open Tickets',
            'In Progress Tickets',
            'Pending Tickets',
            'Resolved Tickets',
            'Closed Tickets',
            'Avg Resolution Time (sec)'
        ]);

        $stmt = $db->prepare("
            SELECT 
                d.id,
                d.name AS department_name,
                d.status AS department_status,
                COUNT(t.id) AS total_tickets,
                SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
                SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
                SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets,
                SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
                SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets,
                ROUND(AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.resolved_at) END)) AS avg_resolution_sec
            FROM departments d
            LEFT JOIN tickets t ON d.id = t.department_id AND t.created_at BETWEEN ? AND ?
            GROUP BY d.id
            ORDER BY total_tickets DESC, d.name ASC
        ");
        $stmt->execute([$from, $to]);

        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                sanitize_csv_cell($row['department_name']),
                sanitize_csv_cell($row['department_status']),
                $row['total_tickets'],
                $row['open_tickets'],
                $row['in_progress_tickets'],
                $row['pending_tickets'],
                $row['resolved_tickets'],
                $row['closed_tickets'],
                $row['avg_resolution_sec'] ?? 'N/A'
            ]);
        }
        break;

    case 'customers':
        // Header row
        fputcsv($output, [
            'Customer ID',
            'Name',
            'Email',
            'Status',
            'Registered Date',
            'Tickets in Period',
            'Open Tickets',
            'Resolved Tickets',
            'Closed Tickets',
            'Last Ticket Date'
        ]);

        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.name AS customer_name,
                u.email AS customer_email,
                u.status AS customer_status,
                u.created_at AS registered_at,
                COUNT(t.id) AS total_tickets,
                SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
                SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
                SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets,
                MAX(t.created_at) AS last_ticket_date
            FROM users u
            LEFT JOIN tickets t ON u.id = t.user_id AND t.created_at BETWEEN ? AND ?
            WHERE u.role = 'customer'
            GROUP BY u.id
            ORDER BY total_tickets DESC, u.name ASC
        ");
        $stmt->execute([$from, $to]);

        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                sanitize_csv_cell($row['customer_name']),
                sanitize_csv_cell($row['customer_email']),
                sanitize_csv_cell($row['customer_status']),
                $row['registered_at'],
                $row['total_tickets'],
                $row['open_tickets'],
                $row['resolved_tickets'],
                $row['closed_tickets'],
                $row['last_ticket_date'] ?: 'None in period'
            ]);
        }
        break;

    case 'tickets':
    default:
        // Header row
        fputcsv($output, [
            'Ticket ID',
            'Ticket Number',
            'Subject',
            'Customer Name',
            'Customer Email',
            'Department',
            'Assigned Agent',
            'Priority',
            'Status',
            'Created At',
            'First Response At',
            'Resolved At',
            'Closed At'
        ]);

        $stmt = $db->prepare("
            SELECT 
                t.id,
                t.ticket_number,
                t.subject,
                u.name AS customer_name,
                u.email AS customer_email,
                d.name AS department_name,
                a.name AS agent_name,
                t.priority,
                t.status,
                t.created_at,
                t.first_response_at,
                t.resolved_at,
                t.closed_at
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN departments d ON t.department_id = d.id
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE t.created_at BETWEEN ? AND ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$from, $to]);

        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                sanitize_csv_cell($row['ticket_number']),
                sanitize_csv_cell($row['subject']),
                sanitize_csv_cell($row['customer_name']),
                sanitize_csv_cell($row['customer_email']),
                sanitize_csv_cell($row['department_name'] ?: 'None'),
                sanitize_csv_cell($row['agent_name'] ?: 'Unassigned'),
                sanitize_csv_cell($row['priority']),
                sanitize_csv_cell($row['status']),
                $row['created_at'],
                $row['first_response_at'] ?: '',
                $row['resolved_at'] ?: '',
                $row['closed_at'] ?: ''
            ]);
        }
        break;
}

fclose($output);
exit;
