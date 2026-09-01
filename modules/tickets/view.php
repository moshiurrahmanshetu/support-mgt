<?php
/**
 * Ticket Management - View Ticket Details & Conversation
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_login();

$user = current_user();
$ticketId = (int)($_GET['id'] ?? 0);

if ($ticketId <= 0) {
    flash('danger', 'Invalid ticket ID specified.');
    redirect('modules/tickets/index.php');
}

$db = get_db();

// 1. Fetch Ticket with Customer & Assigned Agent Details
$ticketStmt = $db->prepare("
    SELECT 
        t.*,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        u.avatar AS customer_avatar,
        u.created_at AS customer_since,
        a.name AS agent_name,
        a.email AS agent_email
    FROM tickets t
    JOIN users u ON t.user_id = u.id
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

// 3. Fetch Conversation Messages (Customers cannot see internal notes)
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

// Group attachments by message_id
$attachmentsByMessage = [];
foreach ($allAttachments as $att) {
    $msgKey = $att['message_id'] ?: 0;
    $attachmentsByMessage[$msgKey][] = $att;
}

// Fetch active agents for assignment (Admin only)
$agents = [];
if ($user['role'] === ROLE_ADMIN) {
    $agentsStmt = $db->query("SELECT id, name, email FROM users WHERE role = 'agent' AND status = 'active' ORDER BY name ASC");
    $agents = $agentsStmt->fetchAll();
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
        <!-- Main Conversation Area -->
        <div class="col-12 col-lg-8">
            <div class="timeline-container">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $idx => $msg): 
                        $isNote = ($msg['message_type'] === MESSAGE_TYPE_NOTE);
                        $isStaff = in_array($msg['sender_role'], [ROLE_ADMIN, ROLE_AGENT], true);
                        $itemClass = $isNote ? 'is-note' : ($isStaff ? 'is-agent' : 'is-customer');
                    ?>
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
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Reply Box -->
            <div class="card border shadow-sm mt-3" id="replyForm">
                <div class="card-header bg-white">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-chat-left-text me-2 text-primary"></i>Post a Response
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($ticket['status'] === STATUS_CLOSED): ?>
                        <div class="alert alert-secondary d-flex align-items-center mb-3">
                            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                            <div>This ticket is marked as <strong>Closed</strong>. Submitting a new reply will automatically reopen this ticket.</div>
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
                                <td class="text-muted" style="width: 40%;">Status:</td>
                                <td><?= render_status_badge($ticket['status']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Priority:</td>
                                <td><?= render_priority_badge($ticket['priority']); ?></td>
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
                            <?php if (!empty($ticket['resolved_at'])): ?>
                                <tr>
                                    <td class="text-muted">Resolved:</td>
                                    <td class="small text-success fw-medium"><?= e(format_datetime($ticket['resolved_at'])); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($ticket['closed_at'])): ?>
                                <tr>
                                    <td class="text-muted">Closed:</td>
                                    <td class="small text-secondary fw-medium"><?= e(format_datetime($ticket['closed_at'])); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Staff Action Panels (Admin & Agent Only) -->
            <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                <!-- Quick Status & Priority Updates -->
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title h6 mb-0 fw-bold">
                            <i class="bi bi-sliders me-2 text-primary"></i>Manage Ticket Status & Priority
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="<?= url('modules/tickets/update_status.php'); ?>" method="POST" class="mb-3">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">
                            
                            <label for="status" class="form-label small fw-semibold">Update Status</label>
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

                            <label for="priority" class="form-label small fw-semibold">Update Priority</label>
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

                <!-- Admin Assignment Control -->
                <?php if ($user['role'] === ROLE_ADMIN): ?>
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
                                    <label for="assigned_to" class="form-label small">Select Agent</label>
                                    <select name="assigned_to" id="assigned_to" class="form-select form-select-sm">
                                        <option value="">-- Unassigned --</option>
                                        <?php if (!empty($agents)): ?>
                                            <?php foreach ($agents as $agent): ?>
                                                <option value="<?= $agent['id']; ?>" <?= ($ticket['assigned_to'] == $agent['id']) ? 'selected' : ''; ?>>
                                                    <?= e($agent['name']); ?> (<?= e($agent['email']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                    <i class="bi bi-check2"></i> Assign Agent
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
