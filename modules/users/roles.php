<?php
/**
 * User Management - Role Assignment (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('users.edit');

$db = get_db();
$currentUser = current_user();
$userId = (int)($_GET['id'] ?? 0);

// Fetch user record
$stmt = $db->prepare("
    SELECT u.*, ur.role_id, r.name AS role_name, r.slug AS role_slug
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.id = ? AND u.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'User account not found.');
    redirect('modules/users/index.php');
}

$roles = $db->query("SELECT id, name, slug, description FROM roles WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } else {
        $roleId = (int)($_POST['role_id'] ?? 0);

        $selectedRole = null;
        foreach ($roles as $r) {
            if ((int)$r['id'] === $roleId) {
                $selectedRole = $r;
                break;
            }
        }

        if (!$selectedRole) {
            $errors[] = 'Please select a valid system role.';
        } elseif (!can_modify_user_role_or_status($userId, $selectedRole['slug'], $user['status'])) {
            $errors[] = 'You cannot remove or change the role of the last active Administrator in the system.';
        }

        if (empty($errors)) {
            assign_user_role($userId, (int)$selectedRole['id']);

            log_activity($currentUser['id'], 'users', 'user_role_changed', "Changed role of {$user['name']} ({$user['email']}) to {$selectedRole['name']}", 'user', $userId);

            flash('success', "Role for '{$user['name']}' successfully updated to '{$selectedRole['name']}'.");
            redirect('modules/users/index.php');
        }
    }
}

// Current effective role ID
$currentRoleId = (int)($user['role_id'] ?? 0);
if ($currentRoleId === 0) {
    foreach ($roles as $r) {
        if ($r['slug'] === $user['role'] || ($user['role'] === 'admin' && $r['slug'] === 'administrator') || ($user['role'] === 'agent' && $r['slug'] === 'support_agent')) {
            $currentRoleId = (int)$r['id'];
            break;
        }
    }
}

$pageTitle = 'Assign Role: ' . $user['name'];
$pageHeader = 'User Role Assignment';
$activePage = 'users';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 700px;">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= url('modules/users/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Users Directory
                </a>
            </div>
            <h1 class="h4 fw-bold mb-0">Assign Role to: <?= e($user['name']); ?></h1>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm border-0 mb-4">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please correct the following:</div>
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card border shadow-sm">
        <div class="card-body p-4">
            <form action="<?= url('modules/users/roles.php?id=' . $user['id']); ?>" method="POST">
                <?= csrf_field(); ?>

                <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                    <img src="<?= e(get_avatar_url($user['avatar'] ?? null)); ?>" alt="<?= e($user['name']); ?>" class="avatar-img avatar-md">
                    <div>
                        <div class="fw-bold text-dark"><?= e($user['name']); ?></div>
                        <div class="text-muted small"><?= e($user['email']); ?></div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fs-7 fw-bold text-dark mb-2">Select Primary System Role</label>
                    <div class="list-group">
                        <?php foreach ($roles as $r): 
                            $isSelected = ($currentRoleId === (int)$r['id']);
                        ?>
                            <label class="list-group-item list-group-item-action d-flex align-items-start gap-3 p-3 <?= $isSelected ? 'border-primary bg-light' : ''; ?>" style="cursor: pointer;">
                                <input class="form-check-input flex-shrink-0 mt-1" type="radio" name="role_id" value="<?= $r['id']; ?>" <?= $isSelected ? 'checked' : ''; ?>>
                                <div class="w-100">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold text-dark"><?= e($r['name']); ?></span>
                                        <span class="badge badge-role-<?= e($r['slug']); ?>"><?= e($r['slug']); ?></span>
                                    </div>
                                    <p class="small text-muted mb-0 mt-1"><?= e($r['description'] ?: 'Standard permissions and module access'); ?></p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                    <a href="<?= url('modules/users/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-shield-check me-1"></i> Update Role Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
