<?php
/**
 * Ticket Management - Create New Ticket
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_login();

$user = current_user();
$errors = [];
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $priority = trim($_POST['priority'] ?? PRIORITY_MEDIUM);
        $description = trim($_POST['description'] ?? '');

        // Customer ID: strictly bound to logged-in user for customers
        $ticketUserId = (int)$user['id'];
        if ($user['role'] === ROLE_ADMIN && !empty($_POST['customer_id'])) {
            $selectedCustId = (int)$_POST['customer_id'];
            $custCheckStmt = $db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
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
                    INSERT INTO tickets (ticket_number, user_id, subject, description, priority, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insertTicketStmt->execute([
                    $tempNumber,
                    $ticketUserId,
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
                'subject'     => $subject,
                'priority'    => $priority,
                'description' => $description
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
                           autofocus>
                </div>

                <!-- Priority -->
                <div class="mb-3">
                    <label for="priority" class="form-label">Priority Level <span class="text-danger">*</span></label>
                    <select name="priority" id="priority" class="form-select" style="max-width: 250px;">
                        <option value="low" <?= (old('priority') === 'low') ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?= (old('priority', 'medium') === 'medium') ? 'selected' : ''; ?>>Medium (Standard)</option>
                        <option value="high" <?= (old('priority') === 'high') ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?= (old('priority') === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                    </select>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
