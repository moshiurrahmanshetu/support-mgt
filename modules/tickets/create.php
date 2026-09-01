<?php
/**
 * Ticket Management - Create New Ticket (Integrated with Departments)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/ticket_activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/activity_log.php';

require_login();

$user = current_user();
$errors = [];
$db = get_db();

// Fetch Active Departments
$deptStmt = $db->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC");
$activeDepartments = $deptStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $priority = trim($_POST['priority'] ?? PRIORITY_MEDIUM);
        $description = trim($_POST['description'] ?? '');

        // Customer ID: strictly bound to logged-in user for customers
        $ticketUserId = (int)$user['id'];
        if ($user['role'] === ROLE_ADMIN && !empty($_POST['customer_id'])) {
            $selectedCustId = (int)$_POST['customer_id'];
            $custCheckStmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
            $custCheckStmt->execute([$selectedCustId]);
            if ($custCheckStmt->fetch()) {
                $ticketUserId = $selectedCustId;
            }
        }

        // 1. Validation
        if (empty($subject)) {
            $errors[] = 'Please provide a subject for your ticket.';
        } elseif (mb_strlen($subject) < 3 || mb_strlen($subject) > 255) {
            $errors[] = 'Ticket subject must be between 3 and 255 characters.';
        }

        // Department validation
        if ($departmentId !== null) {
            $deptCheck = $db->prepare("SELECT id FROM departments WHERE id = ? AND status = 'active' LIMIT 1");
            $deptCheck->execute([$departmentId]);
            if (!$deptCheck->fetch()) {
                $errors[] = 'The selected department is invalid or currently inactive.';
            }
        }

        if (!in_array($priority, VALID_PRIORITIES, true)) {
            $priority = PRIORITY_MEDIUM;
        }

        if (empty($description)) {
            $errors[] = 'Please provide a description of the issue.';
        } elseif (mb_strlen($description) < 10) {
            $errors[] = 'Ticket description must be at least 10 characters long.';
        }

        // 2. Attachment Validation (if uploaded)
        $hasAttachment = false;
        $attachmentFile = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload error (Error Code: ' . $_FILES['attachment']['error'] . ').';
            } else {
                $attachmentFile = $_FILES['attachment'];

                if ($attachmentFile['size'] > MAX_TICKET_ATTACHMENT_SIZE) {
                    $errors[] = 'Attachment file size exceeds the 10MB limit.';
                }

                $ext = strtolower(pathinfo($attachmentFile['name'], PATHINFO_EXTENSION));
                if (in_array($ext, BLOCKED_ATTACHMENT_EXTENSIONS, true)) {
                    $errors[] = 'Executable or script files (.'.$ext.') are strictly prohibited.';
                } elseif (!in_array($ext, ALLOWED_ATTACHMENT_EXTENSIONS, true)) {
                    $errors[] = 'Unsupported file format (.'.$ext.'). Allowed formats: ' . implode(', ', ALLOWED_ATTACHMENT_EXTENSIONS);
                } else {
                    $hasAttachment = true;
                }
            }
        }

        // 3. Process Ticket Creation
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Insert placeholder record
                $tempNumber = 'TMP-' . bin2hex(random_bytes(6));
                $insertTicketStmt = $db->prepare("
                    INSERT INTO tickets (ticket_number, user_id, department_id, subject, description, priority, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insertTicketStmt->execute([
                    $tempNumber,
                    $ticketUserId,
                    $departmentId,
                    $subject,
                    $description,
                    $priority,
                    STATUS_OPEN
                ]);

                $ticketId = (int)$db->lastInsertId();
                $ticketNumber = 'TKT-' . (100000 + $ticketId);

                // Update with final unique ticket number
                $updateNumberStmt = $db->prepare("UPDATE tickets SET ticket_number = ? WHERE id = ?");
                $updateNumberStmt->execute([$ticketNumber, $ticketId]);

                // Insert opening message into ticket_messages
                $insertMsgStmt = $db->prepare("
                    INSERT INTO ticket_messages (ticket_id, user_id, message, message_type, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $insertMsgStmt->execute([
                    $ticketId,
                    $user['id'],
                    $description,
                    MESSAGE_TYPE_REPLY
                ]);
                $messageId = (int)$db->lastInsertId();

                // Save Attachment if present
                if ($hasAttachment && $attachmentFile) {
                    if (!is_dir(TICKET_UPLOAD_DIR)) {
                        mkdir(TICKET_UPLOAD_DIR, 0755, true);
                    }

                    $ext = strtolower(pathinfo($attachmentFile['name'], PATHINFO_EXTENSION));
                    $storedName = 'att_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    $destination = TICKET_UPLOAD_DIR . '/' . $storedName;

                    if (move_uploaded_file($attachmentFile['tmp_name'], $destination)) {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $finfo->file($destination) ?: 'application/octet-stream';

                        $insertAttStmt = $db->prepare("
                            INSERT INTO ticket_attachments (ticket_id, message_id, uploaded_by, original_name, stored_name, file_path, mime_type, file_size, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $insertAttStmt->execute([
                            $ticketId,
                            $messageId,
                            $user['id'],
                            basename($attachmentFile['name']),
                            $storedName,
                            $storedName,
                            $mimeType,
                            $attachmentFile['size']
                        ]);
                    }
                }

                // Log Ticket Specific Activity
                log_ticket_activity($ticketId, $user['id'], 'ticket_created', null, $ticketNumber, "Ticket created with subject '{$subject}'");

                // System Activity Log
                log_activity($user['id'], 'ticket', 'ticket_created', "Created ticket #{$ticketNumber} - {$subject}", 'ticket', $ticketId);

                // In-App Notifications for Staff
                $staffQuery = "SELECT id, email, name FROM users WHERE (role = 'admin' OR (role = 'agent' AND department_id = ?)) AND status = 'active' AND id != ?";
                $staffStmt = $db->prepare($staffQuery);
                $staffStmt->execute([(int)$departmentId, $user['id']]);
                $staffList = $staffStmt->fetchAll();

                foreach ($staffList as $staff) {
                    create_notification(
                        (int)$staff['id'],
                        "New Ticket: #{$ticketNumber}",
                        "New ticket created by {$user['name']}: {$subject}",
                        NOTIF_TICKET_CREATED,
                        'ticket',
                        $ticketId
                    );

                    send_email_notification(
                        $staff['email'],
                        $staff['name'],
                        'ticket_created',
                        [
                            'ticket_number'   => $ticketNumber,
                            'ticket_subject'  => $subject,
                            'ticket_priority' => ucfirst($priority),
                            'ticket_status'   => 'Open',
                            'ticket_url'      => url('modules/tickets/view.php?id=' . $ticketId)
                        ],
                        (int)$staff['id']
                    );
                }

                // Email confirmation to customer
                if ($user['role'] === ROLE_CUSTOMER) {
                    send_email_notification(
                        $user['email'],
                        $user['name'],
                        'ticket_created',
                        [
                            'ticket_number'   => $ticketNumber,
                            'ticket_subject'  => $subject,
                            'ticket_priority' => ucfirst($priority),
                            'ticket_status'   => 'Open',
                            'ticket_url'      => url('modules/tickets/view.php?id=' . $ticketId)
                        ],
                        (int)$user['id']
                    );
                }

                $db->commit();

                clear_old_input();
                flash('success', "Ticket <strong>#{$ticketNumber}</strong> has been created successfully!");
                redirect('modules/tickets/view.php?id=' . $ticketId);
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = 'Failed to create ticket: ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            set_old_input([
                'subject'       => $subject,
                'department_id' => $departmentId,
                'priority'      => $priority,
                'description'   => $description
            ]);
        }
    }
}

// If admin, fetch customer list for selection
$customers = [];
if ($user['role'] === ROLE_ADMIN) {
    $custStmt = $db->query("SELECT id, name, email FROM users WHERE role = 'customer' AND status = 'active' ORDER BY name ASC");
    $customers = $custStmt->fetchAll();
}

$pageTitle = 'Create New Ticket';
$pageHeader = 'Create Support Ticket';
$activePage = 'tickets';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 860px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/tickets/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Tickets
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-plus-circle me-2 text-primary"></i>Create New Support Ticket
            </h5>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="<?= url('modules/tickets/create.php'); ?>" method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field(); ?>

                <!-- Customer Selection (Admin Only) -->
                <?php if ($user['role'] === ROLE_ADMIN && !empty($customers)): ?>
                    <div class="mb-3">
                        <label for="customer_id" class="form-label">On Behalf of Customer</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <option value="">-- Myself (Administrator) --</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?= $cust['id']; ?>" <?= (old('customer_id') == $cust['id']) ? 'selected' : ''; ?>>
                                    <?= e($cust['name']); ?> (<?= e($cust['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Subject -->
                <div class="mb-3">
                    <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="subject" 
                           name="subject" 
                           value="<?= e(old('subject')); ?>" 
                           placeholder="Brief summary of the issue..." 
                           required 
                           autocomplete="off"
                           autofocus>

                    <!-- Knowledge Base Live Suggestions Container -->
                    <div id="kbSuggestionsBox" class="mt-2 d-none">
                        <div class="card border-primary-subtle bg-light shadow-sm">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="small fw-bold text-primary">
                                        <i class="bi bi-lightbulb me-1"></i> Relevant Help Center Articles
                                    </span>
                                    <button type="button" class="btn-close btn-close-sm" id="btnDismissSuggestions" aria-label="Close" style="font-size: 0.65rem;"></button>
                                </div>
                                <p class="small text-muted mb-2">These articles might resolve your issue immediately without waiting for a support reply:</p>
                                <div id="kbSuggestionsList" class="list-group list-group-flush bg-transparent">
                                    <!-- Suggestions dynamically inserted here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Department -->
                    <div class="col-md-6 mb-3">
                        <label for="department_id" class="form-label">Support Department</label>
                        <select name="department_id" id="department_id" class="form-select">
                            <option value="">-- General Support / None --</option>
                            <?php foreach ($activeDepartments as $dept): ?>
                                <option value="<?= $dept['id']; ?>" <?= (old('department_id') == $dept['id']) ? 'selected' : ''; ?>>
                                    <?= e($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div class="col-md-6 mb-3">
                        <label for="priority" class="form-label">Priority Level <span class="text-danger">*</span></label>
                        <select name="priority" id="priority" class="form-select">
                            <option value="low" <?= (old('priority') === 'low') ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?= (old('priority', 'medium') === 'medium') ? 'selected' : ''; ?>>Medium (Standard)</option>
                            <option value="high" <?= (old('priority') === 'high') ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?= (old('priority') === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label for="description" class="form-label">Detailed Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" 
                              id="description" 
                              name="description" 
                              rows="6" 
                              placeholder="Please describe the issue in detail. Include any error messages or steps to reproduce..." 
                              required><?= e(old('description')); ?></textarea>
                </div>

                <!-- Attachment -->
                <div class="mb-4">
                    <label for="attachment" class="form-label">Attachment <span class="text-muted small">(Optional)</span></label>
                    <input type="file" 
                           class="form-control" 
                           id="attachment" 
                           name="attachment" 
                           accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.txt,.zip,.log,.csv,.xlsx">
                    <div class="form-text text-muted">
                        Max file size: 10MB. Allowed formats: Images, PDF, Word documents, Text, ZIP, Logs.
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Submit Ticket
                    </button>
                    <a href="<?= url('modules/tickets/index.php'); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const subjectInput = document.getElementById('subject');
    const suggestionsBox = document.getElementById('kbSuggestionsBox');
    const suggestionsList = document.getElementById('kbSuggestionsList');
    const btnDismiss = document.getElementById('btnDismissSuggestions');

    if (!subjectInput || !suggestionsBox || !suggestionsList) return;

    let debounceTimer = null;
    let isDismissed = false;

    btnDismiss.addEventListener('click', function() {
        suggestionsBox.classList.add('d-none');
        isDismissed = true;
    });

    subjectInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(debounceTimer);

        if (query.length < 3) {
            suggestionsBox.classList.add('d-none');
            return;
        }

        debounceTimer = setTimeout(function() {
            fetch('<?= url('modules/knowledge_base/suggestions.php'); ?>?q=' + encodeURIComponent(query))
                .then(function(response) {
                    if (!response.ok) throw new Error('Network error');
                    return response.json();
                })
                .then(function(data) {
                    if (data && data.suggestions && data.suggestions.length > 0) {
                        let html = '';
                        data.suggestions.forEach(function(item) {
                            html += '<a href="' + item.url + '" target="_blank" class="list-group-item list-group-item-action bg-transparent px-2 py-1 d-flex align-items-center justify-content-between text-decoration-none border-0">' +
                                        '<div class="d-flex align-items-center gap-2">' +
                                            '<i class="bi ' + (item.category_icon || 'bi-file-text') + ' text-primary small"></i>' +
                                            '<span class="text-dark small fw-medium">' + item.title + '</span>' +
                                            '<span class="badge bg-white text-muted border fs-8">' + item.category_name + '</span>' +
                                        '</div>' +
                                        '<i class="bi bi-box-arrow-up-right text-muted fs-8"></i>' +
                                    '</a>';
                        });
                        suggestionsList.innerHTML = html;
                        suggestionsBox.classList.remove('d-none');
                    } else {
                        suggestionsBox.classList.add('d-none');
                    }
                })
                .catch(function(err) {
                    // Non-blocking: fail silently
                    suggestionsBox.classList.add('d-none');
                });
        }, 350);
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
