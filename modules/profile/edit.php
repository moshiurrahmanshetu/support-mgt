<?php
/**
 * Profile Module - Edit Personal Information
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_login();

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Validation
        if (empty($name)) {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (!empty($phone) && (mb_strlen($phone) < 6 || mb_strlen($phone) > 30)) {
            $errors[] = 'Phone number must be between 6 and 30 characters.';
        }

        if (empty($errors)) {
            $db = get_db();
            $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([
                $name,
                empty($phone) ? null : $phone,
                $user['id']
            ]);

            // Sync updated info with session
            refresh_user_session((int)$user['id']);

            require_once __DIR__ . '/../../includes/activity_log.php';
            log_activity((int)$user['id'], 'profile', 'profile_updated', "Updated profile details (name: {$name})");

            flash('success', 'Profile information updated successfully!');
            redirect('modules/profile/index.php');
        }
    }
}

$pageTitle = 'Edit Profile';
$pageHeader = 'Edit Profile';
$activePage = 'profile';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 720px;">
    <!-- Breadcrumb / Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/profile/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Profile
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Update Profile Information
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

            <form action="<?= url('modules/profile/edit.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>

                <!-- Full Name -->
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= e($_POST['name'] ?? $user['name']); ?>" 
                           required 
                           autocomplete="name">
                </div>

                <!-- Email (Readonly) -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" 
                           class="form-control bg-light" 
                           id="email" 
                           value="<?= e($user['email']); ?>" 
                           readonly 
                           disabled>
                    <div class="form-text text-muted">
                        Email address cannot be changed directly for security reasons.
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="mb-4">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" 
                           class="form-control" 
                           id="phone" 
                           name="phone" 
                           value="<?= e($_POST['phone'] ?? $user['phone'] ?? ''); ?>" 
                           placeholder="+1 (555) 000-0000" 
                           autocomplete="tel">
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save Changes
                    </button>
                    <a href="<?= url('modules/profile/index.php'); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
