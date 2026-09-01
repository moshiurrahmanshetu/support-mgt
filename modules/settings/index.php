<?php
/**
 * System Settings Management (Admin Only - support-mgt Phase 05)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Admin Authorization
require_role(ROLE_ADMIN);

$user = current_user();
$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash('danger', 'Security validation failed. Please try again.');
        redirect('modules/settings/index.php');
    }

    $tab = trim($_POST['tab'] ?? 'general');

    // 1. General Settings
    if ($tab === 'general') {
        $appName = trim($_POST['app_name'] ?? '');
        $appUrl = trim($_POST['app_url'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $companyEmail = trim($_POST['company_email'] ?? '');
        $companyPhone = trim($_POST['company_phone'] ?? '');
        $timezone = trim($_POST['timezone'] ?? 'UTC');
        $dateFormat = trim($_POST['date_format'] ?? 'M d, Y h:i A');

        if (empty($appName)) {
            $errors[] = 'Application Name cannot be blank.';
        }

        // Validate Timezone
        $validTimezones = DateTimeZone::listIdentifiers();
        if (!in_array($timezone, $validTimezones, true)) {
            $errors[] = 'Invalid timezone selected.';
        }

        // Normalize App URL
        if (!empty($appUrl)) {
            $appUrl = rtrim($appUrl, '/');
        }

        if (empty($errors)) {
            set_setting('app_name', $appName, 'string');
            set_setting('app_url', $appUrl, 'string');
            set_setting('company_name', $companyName, 'string');
            set_setting('company_email', $companyEmail, 'string');
            set_setting('company_phone', $companyPhone, 'string');
            set_setting('timezone', $timezone, 'string');
            set_setting('date_format', $dateFormat, 'string');

            log_activity($user['id'], 'settings', 'general_settings_updated', 'Updated general system settings');
            flash('success', 'General settings saved successfully.');
            redirect('modules/settings/index.php?tab=general');
        }
    }

    // 2. Support Desk Settings
    if ($tab === 'support') {
        $defaultPriority = trim($_POST['default_priority'] ?? 'medium');
        $defaultStatus = trim($_POST['default_status'] ?? 'open');
        $allowAttachments = isset($_POST['allow_customer_attachments']) ? '1' : '0';
        $maxSizeMb = (int)($_POST['max_attachment_size_mb'] ?? 10);
        $kbEnabled = isset($_POST['knowledge_base_enabled']) ? '1' : '0';
        $faqEnabled = isset($_POST['faq_enabled']) ? '1' : '0';

        if (!in_array($defaultPriority, VALID_PRIORITIES, true)) {
            $defaultPriority = 'medium';
        }
        if (!in_array($defaultStatus, VALID_TICKET_STATUSES, true)) {
            $defaultStatus = 'open';
        }
        if ($maxSizeMb < 1 || $maxSizeMb > 100) {
            $maxSizeMb = 10;
        }

        set_setting('default_priority', $defaultPriority, 'string');
        set_setting('default_status', $defaultStatus, 'string');
        set_setting('allow_customer_attachments', $allowAttachments, 'boolean');
        set_setting('max_attachment_size_mb', (string)$maxSizeMb, 'integer');
        set_setting('knowledge_base_enabled', $kbEnabled, 'boolean');
        set_setting('faq_enabled', $faqEnabled, 'boolean');

        log_activity($user['id'], 'settings', 'support_settings_updated', 'Updated support desk rules and Knowledge Base toggles');
        flash('success', 'Support desk and Knowledge Base settings saved successfully.');
        redirect('modules/settings/index.php?tab=support');
    }

    // 3. Email / SMTP Settings
    if ($tab === 'email') {
        $mailEnabled = isset($_POST['mail_enabled']) ? '1' : '0';
        $smtpHost = trim($_POST['smtp_host'] ?? 'localhost');
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $smtpUser = trim($_POST['smtp_user'] ?? '');
        $smtpPass = trim($_POST['smtp_pass'] ?? '');
        $smtpEncryption = trim($_POST['smtp_encryption'] ?? 'tls');
        $mailFromName = trim($_POST['mail_from_name'] ?? APP_NAME);
        $mailFromEmail = trim($_POST['mail_from_email'] ?? 'no-reply@supportmgt.local');

        set_setting('mail_enabled', $mailEnabled, 'boolean');
        set_setting('smtp_host', $smtpHost, 'string');
        set_setting('smtp_port', (string)$smtpPort, 'integer');
        set_setting('smtp_user', $smtpUser, 'string');
        if (!empty($smtpPass)) {
            set_setting('smtp_pass', $smtpPass, 'string');
        }
        set_setting('smtp_encryption', $smtpEncryption, 'string');
        set_setting('mail_from_name', $mailFromName, 'string');
        set_setting('mail_from_email', $mailFromEmail, 'string');

        log_activity($user['id'], 'settings', 'email_settings_updated', 'Updated email and SMTP configuration');
        flash('success', 'Email & SMTP settings saved successfully.');
        redirect('modules/settings/index.php?tab=email');
    }

    // 4. Notifications Settings
    if ($tab === 'notifications') {
        $inAppEnabled = isset($_POST['enable_in_app_notifications']) ? '1' : '0';
        $emailEnabled = isset($_POST['enable_email_notifications']) ? '1' : '0';

        set_setting('enable_in_app_notifications', $inAppEnabled, 'boolean');
        set_setting('enable_email_notifications', $emailEnabled, 'boolean');

        log_activity($user['id'], 'settings', 'notification_settings_updated', 'Updated global notification settings');
        flash('success', 'Notification settings saved successfully.');
        redirect('modules/settings/index.php?tab=notifications');
    }
}

// Load current settings
$activeTab = trim($_GET['tab'] ?? 'general');
$settings = get_all_settings();
$timezones = DateTimeZone::listIdentifiers();

$pageTitle = 'System Settings';
$pageHeader = 'System Settings';
$activePage = 'settings';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-gear-fill me-2 text-primary"></i>System Settings
            </h1>
            <p class="text-secondary-custom small mb-0">
                Configure application environment, support desk rules, SMTP mail, and notification channels
            </p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Settings Nav Tabs -->
        <div class="col-12 col-md-3">
            <div class="list-group list-group-custom border shadow-sm">
                <a href="<?= url('modules/settings/index.php?tab=general'); ?>" 
                   class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= ($activeTab === 'general') ? 'active' : ''; ?>">
                    <i class="bi bi-sliders"></i> General Settings
                </a>
                <a href="<?= url('modules/settings/index.php?tab=support'); ?>" 
                   class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= ($activeTab === 'support') ? 'active' : ''; ?>">
                    <i class="bi bi-ticket-detailed"></i> Support Desk Rules
                </a>
                <a href="<?= url('modules/settings/index.php?tab=email'); ?>" 
                   class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= ($activeTab === 'email') ? 'active' : ''; ?>">
                    <i class="bi bi-envelope"></i> Email / SMTP Server
                </a>
                <a href="<?= url('modules/settings/index.php?tab=notifications'); ?>" 
                   class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= ($activeTab === 'notifications') ? 'active' : ''; ?>">
                    <i class="bi bi-bell"></i> Global Notifications
                </a>
            </div>
        </div>

        <!-- Settings Content Card -->
        <div class="col-12 col-md-9">
            <div class="card border shadow-sm">
                <div class="card-body p-4">
                    <!-- Tab 1: General Settings -->
                    <?php if ($activeTab === 'general'): ?>
                        <h5 class="card-title h6 fw-bold mb-3 pb-2 border-bottom">
                            <i class="bi bi-sliders me-2 text-primary"></i>General Application Settings
                        </h5>
                        <form action="<?= url('modules/settings/index.php'); ?>" method="POST">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="tab" value="general">

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Application Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="app_name" value="<?= e($settings['app_name'] ?? APP_NAME); ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Application URL</label>
                                    <input type="url" class="form-control" name="app_url" value="<?= e($settings['app_url'] ?? APP_URL); ?>" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Company Name</label>
                                    <input type="text" class="form-control" name="company_name" value="<?= e($settings['company_name'] ?? ''); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Company Support Email</label>
                                    <input type="email" class="form-control" name="company_email" value="<?= e($settings['company_email'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Company Phone</label>
                                    <input type="text" class="form-control" name="company_phone" value="<?= e($settings['company_phone'] ?? ''); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Timezone</label>
                                    <select name="timezone" class="form-select">
                                        <?php foreach ($timezones as $tz): ?>
                                            <option value="<?= e($tz); ?>" <?= (($settings['timezone'] ?? 'UTC') === $tz) ? 'selected' : ''; ?>>
                                                <?= e($tz); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Date & Time Display Format</label>
                                <input type="text" class="form-control" name="date_format" value="<?= e($settings['date_format'] ?? 'M d, Y h:i A'); ?>">
                                <div class="form-text small text-muted">e.g. <code>M d, Y h:i A</code> or <code>Y-m-d H:i:s</code></div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2"></i> Save General Settings
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Tab 2: Support Settings -->
                    <?php if ($activeTab === 'support'): ?>
                        <h5 class="card-title h6 fw-bold mb-3 pb-2 border-bottom">
                            <i class="bi bi-ticket-detailed me-2 text-primary"></i>Support Desk & Ticket Rules
                        </h5>
                        <form action="<?= url('modules/settings/index.php'); ?>" method="POST">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="tab" value="support">

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Default Ticket Priority</label>
                                    <select name="default_priority" class="form-select">
                                        <?php foreach (VALID_PRIORITIES as $pr): ?>
                                            <option value="<?= e($pr); ?>" <?= (($settings['default_priority'] ?? 'medium') === $pr) ? 'selected' : ''; ?>>
                                                <?= ucfirst($pr); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Default Ticket Status</label>
                                    <select name="default_status" class="form-select">
                                        <?php foreach (VALID_TICKET_STATUSES as $st): ?>
                                            <option value="<?= e($st); ?>" <?= (($settings['default_status'] ?? 'open') === $st) ? 'selected' : ''; ?>>
                                                <?= ucfirst(str_replace('_', ' ', $st)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="allow_attachments" name="allow_customer_attachments" value="1" <?= (!empty($settings['allow_customer_attachments'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="allow_attachments">
                                        Allow Customers to Upload Attachments
                                    </label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Maximum File Size Limit (MB)</label>
                                <input type="number" class="form-control" name="max_attachment_size_mb" min="1" max="50" value="<?= (int)($settings['max_attachment_size_mb'] ?? 10); ?>" style="max-width: 160px;">
                            </div>

                            <h6 class="fw-bold text-dark mt-4 mb-3 pt-3 border-top">
                                <i class="bi bi-book me-2 text-primary"></i>Knowledge Base & FAQ Portals
                            </h6>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="knowledge_base_enabled" name="knowledge_base_enabled" value="1" <?= (!empty($settings['knowledge_base_enabled']) && $settings['knowledge_base_enabled'] === '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="knowledge_base_enabled">
                                        Enable Public Knowledge Base Portal
                                    </label>
                                    <div class="form-text small text-muted">When disabled, public article pages and search will be hidden.</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="faq_enabled" name="faq_enabled" value="1" <?= (!empty($settings['faq_enabled']) && $settings['faq_enabled'] === '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="faq_enabled">
                                        Enable Frequently Asked Questions (FAQ) Section
                                    </label>
                                    <div class="form-text small text-muted">Displays the FAQ accordion on the public Support Center.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2"></i> Save Support & KB Settings
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Tab 3: Email / SMTP Settings -->
                    <?php if ($activeTab === 'email'): ?>
                        <h5 class="card-title h6 fw-bold mb-3 pb-2 border-bottom">
                            <i class="bi bi-envelope me-2 text-primary"></i>SMTP Server & Email Delivery
                        </h5>
                        <form action="<?= url('modules/settings/index.php'); ?>" method="POST">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="tab" value="email">

                            <div class="form-check form-switch mb-4 p-3 bg-light rounded border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="mail_enabled" name="mail_enabled" value="1" <?= (!empty($settings['mail_enabled'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="mail_enabled">
                                    Enable Automated Email Delivery
                                </label>
                                <div class="form-text small text-muted">
                                    When disabled, the system operates in local in-app mode without attempting SMTP connections.
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-semibold">SMTP Host</label>
                                    <input type="text" class="form-control" name="smtp_host" value="<?= e($settings['smtp_host'] ?? 'localhost'); ?>">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold">SMTP Port</label>
                                    <input type="number" class="form-control" name="smtp_port" value="<?= (int)($settings['smtp_port'] ?? 587); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">SMTP Username</label>
                                    <input type="text" class="form-control" name="smtp_user" value="<?= e($settings['smtp_user'] ?? ''); ?>" autocomplete="off">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">SMTP Password</label>
                                    <input type="password" class="form-control" name="smtp_pass" placeholder="•••••••• (Leave blank to keep existing)" autocomplete="new-password">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold">Encryption</label>
                                    <select name="smtp_encryption" class="form-select">
                                        <option value="tls" <?= (($settings['smtp_encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?= (($settings['smtp_encryption'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?= (($settings['smtp_encryption'] ?? '') === 'none') ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold">From Name</label>
                                    <input type="text" class="form-control" name="mail_from_name" value="<?= e($settings['mail_from_name'] ?? APP_NAME); ?>">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold">From Email Address</label>
                                    <input type="email" class="form-control" name="mail_from_email" value="<?= e($settings['mail_from_email'] ?? 'no-reply@supportmgt.local'); ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2"></i> Save Email Configuration
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Tab 4: Notifications Settings -->
                    <?php if ($activeTab === 'notifications'): ?>
                        <h5 class="card-title h6 fw-bold mb-3 pb-2 border-bottom">
                            <i class="bi bi-bell me-2 text-primary"></i>Global Notification Channels
                        </h5>
                        <form action="<?= url('modules/settings/index.php'); ?>" method="POST">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="tab" value="notifications">

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="enable_in_app" name="enable_in_app_notifications" value="1" <?= (!empty($settings['enable_in_app_notifications'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="enable_in_app">
                                    Enable In-App Notification Center
                                </label>
                                <div class="form-text small text-muted">
                                    Controls topbar notification bell and in-app alerts system-wide.
                                </div>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="enable_email_notifs" name="enable_email_notifications" value="1" <?= (!empty($settings['enable_email_notifications'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="enable_email_notifs">
                                    Enable Email Notifications for Ticket Events
                                </label>
                                <div class="form-text small text-muted">
                                    Master toggle for sending ticket emails to customers and support agents.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2"></i> Save Notification Settings
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
