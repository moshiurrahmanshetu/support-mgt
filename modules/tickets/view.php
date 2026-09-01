<?php
/**
 * Ticket Management - View Ticket Details, Full Timeline, Tags & Canned Responses (Phase 04)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/ticket_activity.php';

require_login();

$user = current_user();
$ticketId = (int)($_GET['id'] ?? 0);

if ($ticketId <= 0) {
    flash('danger', 'Invalid ticket ID specified.');
    redirect('modules/tickets/index.php');
}

$db = get_db();

// 1. Fetch Ticket Details
$ticketStmt = $db->prepare("
    SELECT 
        t.*,
        d.name AS department_name,
        d.status AS department_status,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        u.avatar AS customer_avatar,
        u.created_at AS customer_since,
        a.name AS agent_name,
        a.email AS agent_email
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN users a ON t.assigned_to = a.id
    WHERE t.id = ?
    LIMIT 1
");
$ticketStmt->execute([$ticketId]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found or has been removed.');
    redirect('modules/tickets/index.php');
}

// 2. Server-side IDOR Access Authorization
if ($user['role'] === ROLE_CUSTOMER && (int)$ticket['user_id'] !== (int)$user['id']) {
    flash('danger', 'You do not have permission to view that support ticket.');
    redirect('modules/tickets/index.php');
}

// 3. Fetch Conversation Messages
if ($user['role'] === ROLE_CUSTOMER) {
    $msgStmt = $db->prepare("
        SELECT m.*, u.name AS sender_name, u.role AS sender_role, u.avatar AS sender_avatar
        FROM ticket_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.ticket_id = ? AND m.message_type = 'reply'
        ORDER BY m.created_at ASC
    ");
} else {
    $msgStmt = $db->prepare("
        SELECT m.*, u.name AS sender_name, u.role AS sender_role, u.avatar AS sender_avatar
        FROM ticket_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.ticket_id = ?
        ORDER BY m.created_at ASC
    ");
}
$msgStmt->execute([$ticketId]);
$messages = $msgStmt->fetchAll();

// 4. Fetch All Attachments for this Ticket
$attStmt = $db->prepare("
    SELECT a.*, u.name AS uploader_name
    FROM ticket_attachments a
    JOIN users u ON a.uploaded_by = u.id
    WHERE a.ticket_id = ?
    ORDER BY a.created_at ASC
");
$attStmt->execute([$ticketId]);
$allAttachments = $attStmt->fetchAll();

$attachmentsByMessage = [];
foreach ($allAttachments as $att) {
    $msgKey = $att['message_id'] ?: 0;
    $attachmentsByMessage[$msgKey][] = $att;
}

// 5. Fetch Assigned Tags for this Ticket
$tagStmt = $db->prepare("
    SELECT tt.id, tt.name, tt.color 
    FROM ticket_tags tt
    JOIN ticket_tag_relations ttr ON ttr.tag_id = tt.id
    WHERE ttr.ticket_id = ?
    ORDER BY tt.name ASC
");
$tagStmt->execute([$ticketId]);
$ticketTags = $tagStmt->fetchAll();
$ticketTagIds = array_column($ticketTags, 'id');

// 6. Fetch All Available Tags (for Staff Tag Assignment)
$allTags = [];
if ($user['role'] !== ROLE_CUSTOMER) {
    $allTagsStmt = $db->query("SELECT id, name, color FROM ticket_tags ORDER BY name ASC");
    $allTags = $allTagsStmt->fetchAll();
}

// 7. Fetch Activity Logs for Staff Timeline
$timelineEvents = [];
if ($user['role'] !== ROLE_CUSTOMER) {
    $logStmt = $db->prepare("
        SELECT tal.*, u.name AS user_name, u.role AS user_role
        FROM ticket_activity_logs tal
        LEFT JOIN users u ON tal.user_id = u.id
        WHERE tal.ticket_id = ?
        ORDER BY tal.created_at ASC
    ");
    $logStmt->execute([$ticketId]);
    $activityLogs = $logStmt->fetchAll();

    // Merge messages and activity logs chronologically
    foreach ($messages as $msg) {
        $timelineEvents[] = [
            'type'       => 'message',
            'created_at' => $msg['created_at'],
            'data'       => $msg
        ];
    }
    foreach ($activityLogs as $log) {
        // Exclude generic reply/note activity to avoid duplication with message bubbles
        if (!in_array($log['action'], ['reply_added', 'internal_note_added'], true)) {
            $timelineEvents[] = [
                'type'       => 'activity',
                'created_at' => $log['created_at'],
                'data'       => $log
            ];
        }
    }

    usort($timelineEvents, function ($a, $b) {
        return strcmp($a['created_at'], $b['created_at']);
    });
} else {
    // Customer timeline: messages only
    foreach ($messages as $msg) {
        $timelineEvents[] = [
            'type'       => 'message',
            'created_at' => $msg['created_at'],
            'data'       => $msg
        ];
    }
}

// 8. Fetch Canned Responses for Staff
$cannedResponses = [];
if ($user['role'] !== ROLE_CUSTOMER) {
    $cannedStmt = $db->query("SELECT id, title, content FROM canned_responses ORDER BY title ASC");
    $cannedResponses = $cannedStmt->fetchAll();
}

// 9. Fetch Active Departments & Agents (for Admin controls)
$activeDepartments = [];
$agents = [];
if ($user['role'] === ROLE_ADMIN) {
    $deptStmt = $db->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC");
    $activeDepartments = $deptStmt->fetchAll();

    if (!empty($ticket['department_id'])) {
        $agentsStmt = $db->prepare("
            SELECT u.id, u.name, u.email, d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.role = 'agent' AND u.status = 'active' AND (u.department_id = ? OR u.id = ?)
            ORDER BY u.name ASC
        ");
        $agentsStmt->execute([$ticket['department_id'], (int)($ticket['assigned_to'] ?? 0)]);
        $agents = $agentsStmt->fetchAll();
    }
    
    if (empty($agents)) {
        $agentsStmt = $db->query("
            SELECT u.id, u.name, u.email, d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.role = 'agent' AND u.status = 'active'
            ORDER BY u.name ASC
        ");
        $agents = $agentsStmt->fetchAll();
    }
}

$pageTitle = 'Ticket #' . $ticket['ticket_number'];
$pageHeader = 'Ticket Details';
$activePage = 'tickets';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Breadcrumb / Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/tickets/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Tickets
        </a>
    </div>

    <!-- Ticket Top Header Banner -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="fs-5 fw-bold text-primary font-monospace"><?= e($ticket['ticket_number']); ?></span>
                        <?= render_status_badge($ticket['status']); ?>
                        <?= render_priority_badge($ticket['priority']); ?>
                        <?php if (!empty($ticket['department_name'])): ?>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-building me-1 text-secondary"></i><?= e($ticket['department_name']); ?>
                            </span>
                        <?php endif; ?>
                        <!-- Ticket Tags -->
                        <?php foreach ($ticketTags as $tTag): ?>
                            <span class="badge" style="background-color: <?= e($tTag['color']); ?>; color: #ffffff;">
                                <i class="bi bi-tag-fill me-1"></i><?= e($tTag['name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <h1 class="h4 fw-bold mb-2"><?= e($ticket['subject']); ?></h1>
                    <div class="text-secondary-custom small d-flex flex-wrap align-items-center gap-3">
                        <span><i class="bi bi-calendar-event me-1"></i> Created <?= e(format_datetime($ticket['created_at'])); ?></span>
                        <span><i class="bi bi-clock-history me-1"></i> Last updated <?= e(format_datetime($ticket['updated_at'])); ?></span>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="#replyForm" class="btn btn-primary btn-sm">
                        <i class="bi bi-reply-fill"></i> Post Reply
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Conversation & Timeline Area -->
        <div class="col-12 col-lg-8">
            <div class="timeline-container">
                <?php if (!empty($timelineEvents)): ?>
                    <?php foreach ($timelineEvents as $item): ?>
                        <?php if ($item['type'] === 'message'): 
                            $msg = $item['data'];
                            $isNote = ($msg['message_type'] === MESSAGE_TYPE_NOTE);
                            $isStaff = in_array($msg['sender_role'], [ROLE_ADMIN, ROLE_AGENT], true);
                            $itemClass = $isNote ? 'is-note' : ($isStaff ? 'is-agent' : 'is-customer');
                        ?>
                            <!-- Message Timeline Entry -->
                            <div class="timeline-item <?= $itemClass; ?>" id="message-<?= $msg['id']; ?>">
                                <div class="timeline-dot"></div>
                                <div class="timeline-card <?= $itemClass; ?>">
                                    <div class="timeline-header">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= e(get_avatar_url($msg['sender_avatar'])); ?>" 
                                                 alt="<?= e($msg['sender_name']); ?>" 
                                                 class="avatar-img avatar-sm">
                                            <div>
                                                <span class="fw-semibold text-dark fs-7"><?= e($msg['sender_name']); ?></span>
                                                <span class="badge badge-role-<?= e($msg['sender_role']); ?> ms-1"><?= e($msg['sender_role']); ?></span>
                                                <?php if ($isNote): ?>
                                                    <span class="badge bg-warning text-dark ms-1">
                                                        <i class="bi bi-lock-fill me-1"></i>Internal Note
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-muted fs-8">
                                            <?= e(format_datetime($msg['created_at'])); ?>
                                        </div>
                                    </div>

                                    <div class="timeline-body">
                                        <?= nl2br(e($msg['message'])); ?>
                                    </div>

                                    <!-- Attachments for this message -->
                                    <?php if (!empty($attachmentsByMessage[$msg['id']])): ?>
                                        <div class="timeline-attachments">
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="small text-muted me-1"><i class="bi bi-paperclip"></i> Attachments:</span>
                                                <?php foreach ($attachmentsByMessage[$msg['id']] as $att): ?>
                                                    <a href="<?= url('modules/tickets/download_attachment.php?id=' . $att['id']); ?>" 
                                                       class="btn btn-sm btn-outline-secondary py-1 px-2 d-inline-flex align-items-center gap-1"
                                                       target="_blank">
                                                        <i class="bi bi-file-earmark"></i>
                                                        <span class="text-truncate" style="max-width: 200px;"><?= e($att['original_name']); ?></span>
                                                        <span class="badge bg-light text-secondary border fs-8"><?= format_file_size($att['file_size']); ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($item['type'] === 'activity' && $user['role'] !== ROLE_CUSTOMER): 
                            $log = $item['data'];
                            $ev = format_activity_event($log);
                        ?>
                            <!-- System Activity Event Entry (Staff Only) -->
                            <div class="d-flex align-items-center gap-2 my-2 py-2 px-3 bg-light rounded border fs-8 text-secondary">
                                <i class="bi <?= $ev['icon']; ?> <?= $ev['class']; ?> fs-6"></i>
                                <div class="flex-grow-1">
                                    <?= $ev['text']; ?>
                                </div>
                                <div class="text-muted fs-8">
                                    <?= e(format_datetime($log['created_at'], 'M d, H:i')); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Reply Box -->
            <div class="card border shadow-sm mt-3" id="replyForm">
                <div class="card-header bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-chat-left-text me-2 text-primary"></i>Post a Response
                    </h5>

                    <!-- Canned Responses Selector (Staff Only) -->
                    <?php if ($user['role'] !== ROLE_CUSTOMER && !empty($cannedResponses)): ?>
                        <div class="d-flex align-items-center gap-2">
                            <label for="cannedSelect" class="small text-muted mb-0 fw-medium flex-shrink-0">
                                <i class="bi bi-chat-square-quote"></i> Template:
                            </label>
                            <select id="cannedSelect" class="form-select form-select-sm" style="max-width: 220px;">
                                <option value="">-- Insert Canned Response --</option>
                                <?php foreach ($cannedResponses as $cr): ?>
                                    <option value="<?= $cr['id']; ?>" data-content="<?= htmlspecialchars($cr['content'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= e($cr['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <?php if ($ticket['status'] === STATUS_CLOSED || $ticket['status'] === STATUS_RESOLVED): ?>
                        <div class="alert alert-secondary d-flex align-items-center mb-3">
                            <i class="bi bi-info-circle-fill me-2 fs-5 text-primary"></i>
                            <div>
                                This ticket is currently marked as <strong><?= ucfirst($ticket['status']); ?></strong>. 
                                Submitting a new reply will automatically <strong>reopen</strong> the ticket.
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="<?= url('modules/tickets/reply.php'); ?>" method="POST" enctype="multipart/form-data" novalidate>
                        <?= csrf_field(); ?>
                        <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">

                        <!-- Staff Message Type Selector (Admin & Agent only) -->
                        <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                            <div class="mb-3 p-3 bg-light rounded border">
                                <label class="form-label d-block fw-semibold mb-2">Message Type</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="message_type" id="typeReply" value="reply" checked>
                                        <label class="form-check-label fw-medium" for="typeReply">
                                            <i class="bi bi-reply-fill text-primary"></i> Public Reply (Customer will see this)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="message_type" id="typeNote" value="internal_note">
                                        <label class="form-check-label fw-medium text-warning-emphasis" for="typeNote">
                                            <i class="bi bi-lock-fill text-warning"></i> Internal Note (Staff only)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="message_type" value="reply">
                        <?php endif; ?>

                        <!-- Reply Message -->
                        <div class="mb-3">
                            <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" 
                                      id="message" 
                                      name="message" 
                                      rows="5" 
                                      placeholder="Type your response here..." 
                                      required></textarea>
                        </div>

                        <!-- Attachment -->
                        <div class="mb-4">
                            <label for="attachment" class="form-label">Attach File <span class="text-muted small">(Optional, max 10MB)</span></label>
                            <input type="file" class="form-control" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.txt,.zip,.log,.csv,.xlsx">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send-fill"></i> Submit Response
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Ticket Details & Action Controls -->
        <div class="col-12 col-lg-4">
            <!-- Ticket Info Card -->
            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-info-circle me-2 text-primary"></i>Ticket Information
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Customer Profile Snippet -->
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded mb-3 border">
                        <img src="<?= e(get_avatar_url($ticket['customer_avatar'])); ?>" 
                             alt="<?= e($ticket['customer_name']); ?>" 
                             class="avatar-img avatar-md">
                        <div class="overflow-hidden">
                            <div class="fw-bold text-dark text-truncate"><?= e($ticket['customer_name']); ?></div>
                            <div class="small text-muted text-truncate"><?= e($ticket['customer_email']); ?></div>
                            <?php if (!empty($ticket['customer_phone'])): ?>
                                <div class="small text-muted"><i class="bi bi-telephone"></i> <?= e($ticket['customer_phone']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 42%;">Status:</td>
                                <td><?= render_status_badge($ticket['status']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Priority:</td>
                                <td><?= render_priority_badge($ticket['priority']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Department:</td>
                                <td>
                                    <?php if (!empty($ticket['department_name'])): ?>
                                        <span class="badge bg-light text-dark border"><i class="bi bi-building me-1 text-secondary"></i><?= e($ticket['department_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">General Support</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Assigned To:</td>
                                <td class="fw-semibold">
                                    <?php if (!empty($ticket['agent_name'])): ?>
                                        <i class="bi bi-person-check text-primary me-1"></i><?= e($ticket['agent_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Created:</td>
                                <td class="small"><?= e(format_datetime($ticket['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Updated:</td>
                                <td class="small"><?= e(format_datetime($ticket['updated_at'])); ?></td>
                            </tr>

                            <!-- Response & Resolution Metrics (Staff Only) -->
                            <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                                <tr class="border-top">
                                    <td class="text-muted pt-2">First Response:</td>
                                    <td class="small pt-2">
                                        <?php if (!empty($ticket['first_response_at'])): ?>
                                            <span class="text-success fw-medium">
                                                <i class="bi bi-stopwatch me-1"></i><?= format_duration($ticket['created_at'], $ticket['first_response_at']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Awaiting response</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Resolution Time:</td>
                                    <td class="small">
                                        <?php if (!empty($ticket['resolved_at'])): ?>
                                            <span class="text-primary fw-medium">
                                                <i class="bi bi-check2-circle me-1"></i><?= format_duration($ticket['created_at'], $ticket['resolved_at']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Not resolved yet</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tags Management Panel (Staff Only) -->
            <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between">
                        <h5 class="card-title h6 mb-0 fw-bold">
                            <i class="bi bi-tags me-2 text-primary"></i>Ticket Tags
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Currently Assigned Tags with Remove Action -->
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            <?php if (!empty($ticketTags)): ?>
                                <?php foreach ($ticketTags as $tTag): ?>
                                    <form action="<?= url('modules/tickets/tags.php'); ?>" method="POST" class="d-inline">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">
                                        <input type="hidden" name="tag_id" value="<?= $tTag['id']; ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <button type="submit" class="badge border-0 py-1 px-2 d-inline-flex align-items-center gap-1" style="background-color: <?= e($tTag['color']); ?>; color: #ffffff;" title="Click to remove tag">
                                            <?= e($tTag['name']); ?> <i class="bi bi-x"></i>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="small text-muted fst-italic">No tags attached to this ticket.</span>
                            <?php endif; ?>
                        </div>

                        <!-- Attach New Tag Form -->
                        <?php if (!empty($allTags)): ?>
                            <form action="<?= url('modules/tickets/tags.php'); ?>" method="POST" class="d-flex gap-2">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">
                                <input type="hidden" name="action" value="add">
                                <select name="tag_id" class="form-select form-select-sm" required>
                                    <option value="">-- Attach Tag --</option>
                                    <?php foreach ($allTags as $aTag): ?>
                                        <?php if (!in_array($aTag['id'], $ticketTagIds)): ?>
                                            <option value="<?= $aTag['id']; ?>"><?= e($aTag['name']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary flex-shrink-0">
                                    <i class="bi bi-plus"></i> Attach
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Staff Action Panels (Admin & Agent Only) -->
            <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                <!-- Quick Status & Priority Updates -->
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title h6 mb-0 fw-bold">
                            <i class="bi bi-sliders me-2 text-primary"></i>Status & Priority
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="<?= url('modules/tickets/update_status.php'); ?>" method="POST" class="mb-3">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">
                            
                            <label for="status" class="form-label small fw-semibold">Status</label>
                            <div class="input-group">
                                <select name="status" id="status" class="form-select form-select-sm">
                                    <?php foreach (VALID_TICKET_STATUSES as $st): ?>
                                        <option value="<?= e($st); ?>" <?= ($ticket['status'] === $st) ? 'selected' : ''; ?>>
                                            <?= ucfirst(str_replace('_', ' ', $st)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="action" value="update_status" class="btn btn-sm btn-primary">
                                    Save
                                </button>
                            </div>
                        </form>

                        <form action="<?= url('modules/tickets/update_status.php'); ?>" method="POST">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">

                            <label for="priority" class="form-label small fw-semibold">Priority</label>
                            <div class="input-group">
                                <select name="priority" id="priority" class="form-select form-select-sm">
                                    <?php foreach (VALID_PRIORITIES as $pr): ?>
                                        <option value="<?= e($pr); ?>" <?= ($ticket['priority'] === $pr) ? 'selected' : ''; ?>>
                                            <?= ucfirst($pr); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="action" value="update_priority" class="btn btn-sm btn-primary">
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Admin Department & Assignment Controls -->
                <?php if ($user['role'] === ROLE_ADMIN): ?>
                    <!-- Department Update Control -->
                    <div class="card border shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title h6 mb-0 fw-bold">
                                <i class="bi bi-building me-2 text-primary"></i>Support Department
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="<?= url('modules/tickets/update_department.php'); ?>" method="POST">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">

                                <div class="mb-2">
                                    <select name="department_id" class="form-select form-select-sm">
                                        <option value="">-- General / None --</option>
                                        <?php foreach ($activeDepartments as $dept): ?>
                                            <option value="<?= $dept['id']; ?>" <?= ((int)$ticket['department_id'] === (int)$dept['id']) ? 'selected' : ''; ?>>
                                                <?= e($dept['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                    <i class="bi bi-check2"></i> Move Department
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Agent Assignment Control -->
                    <div class="card border shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title h6 mb-0 fw-bold">
                                <i class="bi bi-person-plus me-2 text-primary"></i>Assign Support Agent
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="<?= url('modules/tickets/assign.php'); ?>" method="POST">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">

                                <div class="mb-3">
                                    <select name="assigned_to" id="assigned_to" class="form-select form-select-sm">
                                        <option value="">-- Unassigned --</option>
                                        <?php if (!empty($agents)): ?>
                                            <?php foreach ($agents as $agent): ?>
                                                <option value="<?= $agent['id']; ?>" <?= ($ticket['assigned_to'] == $agent['id']) ? 'selected' : ''; ?>>
                                                    <?= e($agent['name']); ?> 
                                                    <?= !empty($agent['department_name']) ? '[' . e($agent['department_name']) . ']' : ''; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <?php if (!empty($ticket['department_name'])): ?>
                                        <div class="form-text fs-8 text-muted">
                                            Showing active agents associated with <?= e($ticket['department_name']); ?>.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                    <i class="bi bi-check2"></i> Save Assignment
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Vanilla JS for Canned Responses Insertion -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var cannedSelect = document.getElementById('cannedSelect');
    var messageTextarea = document.getElementById('message');

    if (cannedSelect && messageTextarea) {
        cannedSelect.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var templateContent = selectedOption.getAttribute('data-content');
            if (templateContent) {
                if (messageTextarea.value.trim() !== '') {
                    if (confirm('Replace existing message with this canned response template?')) {
                        messageTextarea.value = templateContent;
                    }
                } else {
                    messageTextarea.value = templateContent;
                }
                messageTextarea.focus();
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
