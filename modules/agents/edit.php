<?php
/**
 * Agent Management - Edit Agent
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid agent reference.');
    redirect('modules/agents/index.php');
}

$db = get_db();

// 1. Fetch Agent Record
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'agent' LIMIT 1");
$stmt->execute([$id]);
$agent = $stmt->fetch();

if (!$agent) {
    flash('danger', 'Support agent account not found.');
    redirect('modules/agents/index.php');
}

// 2. Fetch Active Departments (and include agent's current department if inactive)
$deptStmt = $db->query("SELECT id, name, status FROM departments ORDER BY name ASC");
$departments = $deptStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $status = trim($_POST['status'] ?? STATUS_ACTIVE);

        if (empty($name)) {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (!empty($phone) && (mb_strlen($phone) < 6 || mb_strlen($phone) > 30)) {
            $errors[] = 'Phone number must be between 6 and 30 characters.';
        }

        if ($departmentId !== null) {
            $deptCheck = $db->prepare("SELECT id FROM departments WHERE id = ? LIMIT 1");
            $deptCheck->execute([$departmentId]);
            if (!$deptCheck->fetch()) {
                $errors[] = 'Selected department is invalid.';
            }
        }

        if (!in_array($status, VALID_STATUSES, true)) {
            $status = STATUS_ACTIVE;
        }

        if (empty($errors)) {
            $updateStmt = $db->prepare("
                UPDATE users 
                SET name = ?, phone = ?, department_id = ?, status = ?, updated_at = NOW() 
                WHERE id = ? AND role = 'agent'
            ");
            $updateStmt->execute([
                $name,
                empty($phone) ? null : $phone,
                $departmentId,
                $status,
                $id
            ]);

            flash('success', "Agent <strong>" . e($name) . "</strong> updated successfully!");
            redirect('modules/agents/view.php?id=' . $id);
        }
    }
}

$pageTitle = 'Edit Agent: ' . $agent['name'];
$pageHeader = 'Edit Support Agent';
$activePage = 'agents';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 720px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/agents/view.php?id=' . $agent['id']); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Agent Profile
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Support Agent
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

            <form action="<?= url('modules/agents/edit.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="id" value="<?= $agent['id']; ?>">

                <!-- Full Name -->
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= e($_POST['name'] ?? $agent['name']); ?>" 
                           required 
                           autofocus>
                </div>

                <!-- Email (Readonly) -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" 
                           class="form-control bg-light" 
                           id="email" 
                           value="<?= e($agent['email']); ?>" 
                           readonly 
                           disabled>
                    <div class="form-text text-muted">
                        Email cannot be changed directly in management view to preserve security.
                    </div>
                </div>

                <!-- Phone -->
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" 
                           class="form-control" 
                           id="phone" 
                           name="phone" 
                           value="<?= e($_POST['phone'] ?? $agent['phone'] ?? ''); ?>" 
                           placeholder="+1 (555) 000-0000">
                </div>

                <!-- Department -->
                <div class="mb-3">
                    <label for="department_id" class="form-label">Assigned Department</label>
                    <select name="department_id" id="department_id" class="form-select">
                        <option value="">-- General / No Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id']; ?>" <?= ((int)($_POST['department_id'] ?? $agent['department_id']) === (int)$dept['id']) ? 'selected' : ''; ?>>
                                <?= e($dept['name']); ?> <?= ($dept['status'] === STATUS_INACTIVE) ? '(Inactive)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select" style="max-width: 200px;">
                        <option value="active" <?= (($_POST['status'] ?? $agent['status']) === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $agent['status']) === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save Changes
                    </button>
                    <a href="<?= url('modules/agents/view.php?id=' . $agent['id']); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
