<?php
/**
 * Customer Management - Edit Customer
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid customer reference.');
    redirect('modules/customers/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer account not found.');
    redirect('modules/customers/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = trim($_POST['status'] ?? STATUS_ACTIVE);

        if (empty($name)) {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (!empty($phone) && (mb_strlen($phone) < 6 || mb_strlen($phone) > 30)) {
            $errors[] = 'Phone number must be between 6 and 30 characters.';
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $status = STATUS_ACTIVE;
        }

        if (empty($errors)) {
            $updateStmt = $db->prepare("
                UPDATE users 
                SET name = ?, phone = ?, status = ?, updated_at = NOW() 
                WHERE id = ? AND role = 'customer'
            ");
            $updateStmt->execute([
                $name,
                empty($phone) ? null : $phone,
                $status,
                $id
            ]);

            require_once __DIR__ . '/../../includes/activity_log.php';
            $user = current_user();
            log_activity($user['id'], 'customer', 'customer_updated', "Updated customer profile for {$name} (ID: {$id})", 'user', $id);

            flash('success', "Customer <strong>" . e($name) . "</strong> has been updated successfully!");
            redirect('modules/customers/view.php?id=' . $id);
        }
    }
}

$pageTitle = 'Edit Customer: ' . $customer['name'];
$pageHeader = 'Edit Customer';
$activePage = 'customers';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 720px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/customers/view.php?id=' . $customer['id']); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Customer Details
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Customer Account
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

            <form action="<?= url('modules/customers/edit.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="id" value="<?= $customer['id']; ?>">

                <!-- Full Name -->
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= e($_POST['name'] ?? $customer['name']); ?>" 
                           required 
                           autofocus>
                </div>

                <!-- Email (Readonly) -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" 
                           class="form-control bg-light" 
                           id="email" 
                           value="<?= e($customer['email']); ?>" 
                           readonly 
                           disabled>
                    <div class="form-text text-muted">
                        Email cannot be changed directly in management view to preserve verification integrity.
                    </div>
                </div>

                <!-- Phone -->
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" 
                           class="form-control" 
                           id="phone" 
                           name="phone" 
                           value="<?= e($_POST['phone'] ?? $customer['phone'] ?? ''); ?>" 
                           placeholder="+1 (555) 000-0000">
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select" style="max-width: 200px;">
                        <option value="active" <?= (($_POST['status'] ?? $customer['status']) === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $customer['status']) === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save Changes
                    </button>
                    <a href="<?= url('modules/customers/view.php?id=' . $customer['id']); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
