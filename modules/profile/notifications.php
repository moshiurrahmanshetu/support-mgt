<?php
/**
 * User Notification Preferences (support-mgt Phase 05)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/activity_log.php';

require_login();

$user = current_user();
$db = get_db();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash('danger', 'Security validation failed. Please try again.');
        redirect('modules/profile/notifications.php');
    }

    $inAppEnabled = isset($_POST['in_app_enabled']) ? 1 : 0;
    $emailCreated = isset($_POST['email_ticket_created']) ? 1 : 0;
    $emailAssigned = isset($_POST['email_ticket_assigned']) ? 1 : 0;
    $emailReply = isset($_POST['email_ticket_reply']) ? 1 : 0;
    $emailStatus = isset($_POST['email_ticket_status']) ? 1 : 0;
    $emailReopened = isset($_POST['email_ticket_reopened']) ? 1 : 0;

    $stmt = $db->prepare("
        INSERT INTO user_notification_preferences 
        (user_id, in_app_enabled, email_ticket_created, email_ticket_assigned, email_ticket_reply, email_ticket_status, email_ticket_reopened, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            in_app_enabled = VALUES(in_app_enabled),
            email_ticket_created = VALUES(email_ticket_created),
            email_ticket_assigned = VALUES(email_ticket_assigned),
            email_ticket_reply = VALUES(email_ticket_reply),
            email_ticket_status = VALUES(email_ticket_status),
            email_ticket_reopened = VALUES(email_ticket_reopened),
            updated_at = NOW()
    ");
    $stmt->execute([
        $user['id'],
        $inAppEnabled,
        $emailCreated,
        $emailAssigned,
        $emailReply,
        $emailStatus,
        $emailReopened
    ]);

    log_activity($user['id'], 'profile', 'notification_preferences_updated', 'Updated notification preferences');

    flash('success', 'Your notification preferences have been saved successfully.');
    redirect('modules/profile/notifications.php');
}

// Fetch current preferences
$prefStmt = $db->prepare("SELECT * FROM user_notification_preferences WHERE user_id = ? LIMIT 1");
$prefStmt->execute([$user['id']]);
$prefs = $prefStmt->fetch();

// Defaults
$inAppEnabled  = $prefs ? (bool)$prefs['in_app_enabled'] : true;
$emailCreated  = $prefs ? (bool)$prefs['email_ticket_created'] : true;
$emailAssigned = $prefs ? (bool)$prefs['email_ticket_assigned'] : true;
$emailReply    = $prefs ? (bool)$prefs['email_ticket_reply'] : true;
$emailStatus   = $prefs ? (bool)$prefs['email_ticket_status'] : true;
$emailReopened = $prefs ? (bool)$prefs['email_ticket_reopened'] : true;

$pageTitle = 'Notification Preferences';
$pageHeader = 'Notification Settings';
$activePage = 'profile';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Breadcrumb -->
    <div class="mb-3">
        <a href="<?= url('modules/profile/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Profile
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card border shadow-sm">
                <div class="card-header bg-white p-3">
                    <h5 class="card-title h6 mb-0 fw-bold">
                        <i class="bi bi-sliders me-2 text-primary"></i>Personal Notification Preferences
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('modules/profile/notifications.php'); ?>" method="POST">
                        <?= csrf_field(); ?>

                        <!-- In-App Notification Section -->
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                            <i class="bi bi-bell me-2 text-primary"></i>In-App Notifications
                        </h6>
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="in_app_enabled" name="in_app_enabled" value="1" <?= $inAppEnabled ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold" for="in_app_enabled">
                                Enable In-App Notifications
                            </label>
                            <div class="form-text small text-muted">
                                Receive notifications in the top bar bell and notification center.
                            </div>
                        </div>

                        <!-- Email Notification Section -->
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                            <i class="bi bi-envelope me-2 text-primary"></i>Email Notifications
                        </h6>
                        <p class="text-muted small mb-3">
                            Choose which ticket events trigger an automated email notification to <strong><?= e($user['email']); ?></strong>.
                        </p>

                        <?php if ($user['role'] !== ROLE_CUSTOMER): ?>
                            <!-- Ticket Created (Staff only) -->
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="email_ticket_created" name="email_ticket_created" value="1" <?= $emailCreated ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_ticket_created">
                                    <strong>New Ticket Created</strong>
                                    <span class="d-block small text-muted">When a customer creates a new ticket in your department</span>
                                </label>
                            </div>

                            <!-- Ticket Assigned (Staff only) -->
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="email_ticket_assigned" name="email_ticket_assigned" value="1" <?= $emailAssigned ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_ticket_assigned">
                                    <strong>Ticket Assigned to Me</strong>
                                    <span class="d-block small text-muted">When an administrator assigns a ticket to you</span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <!-- Ticket Reply -->
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="email_ticket_reply" name="email_ticket_reply" value="1" <?= $emailReply ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="email_ticket_reply">
                                <strong>New Response / Reply</strong>
                                <span class="d-block small text-muted">When a new public response is posted to your ticket</span>
                            </label>
                        </div>

                        <!-- Ticket Status Changed -->
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="email_ticket_status" name="email_ticket_status" value="1" <?= $emailStatus ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="email_ticket_status">
                                <strong>Status Updates</strong>
                                <span class="d-block small text-muted">When a ticket is marked In Progress, Pending, Resolved, or Closed</span>
                            </label>
                        </div>

                        <!-- Ticket Reopened -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="email_ticket_reopened" name="email_ticket_reopened" value="1" <?= $emailReopened ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="email_ticket_reopened">
                                <strong>Ticket Reopened</strong>
                                <span class="d-block small text-muted">When a previously resolved or closed ticket is reopened</span>
                            </label>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
