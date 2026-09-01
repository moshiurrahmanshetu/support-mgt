<?php
/**
 * Tag Management - Edit Tag
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid tag ID.');
    redirect('modules/tags/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM ticket_tags WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$tag = $stmt->fetch();

if (!$tag) {
    flash('danger', 'Tag not found.');
    redirect('modules/tags/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6c757d');

        if (empty($name)) {
            $errors[] = 'Please enter a tag name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            $errors[] = 'Tag name must be between 2 and 50 characters.';
        }

        if (!preg_match('/^#[a-fA-F0-9]{6}$/i', $color) && !preg_match('/^#[a-fA-F0-9]{3}$/i', $color)) {
            $errors[] = 'Please provide a valid hexadecimal color code (e.g. #0d6efd).';
        }

        // Uniqueness check excluding current ID
        if (empty($errors)) {
            $checkStmt = $db->prepare("SELECT id FROM ticket_tags WHERE name = ? AND id != ? LIMIT 1");
            $checkStmt->execute([$name, $id]);
            if ($checkStmt->fetch()) {
                $errors[] = 'Another tag with this name already exists.';
            }
        }

        if (empty($errors)) {
            $updateStmt = $db->prepare("
                UPDATE ticket_tags 
                SET name = ?, color = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->execute([$name, $color, $id]);

            flash('success', "Tag <strong>" . e($name) . "</strong> updated successfully!");
            redirect('modules/tags/index.php');
        }
    }
}

$pageTitle = 'Edit Tag';
$pageHeader = 'Edit Tag';
$activePage = 'tags';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 640px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/tags/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Tags
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Ticket Tag
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

            <form action="<?= url('modules/tags/edit.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="id" value="<?= $tag['id']; ?>">

                <!-- Tag Name -->
                <div class="mb-3">
                    <label for="name" class="form-label">Tag Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= e($_POST['name'] ?? $tag['name']); ?>" 
                           required 
                           autofocus>
                </div>

                <!-- Tag Color -->
                <div class="mb-4">
                    <label for="color" class="form-label">Badge Color (HEX) <span class="text-danger">*</span></label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" 
                               class="form-control form-control-color" 
                               id="colorPicker" 
                               value="<?= e($_POST['color'] ?? $tag['color']); ?>" 
                               title="Choose color"
                               oninput="document.getElementById('color').value = this.value">
                        <input type="text" 
                               class="form-control font-monospace" 
                               id="color" 
                               name="color" 
                               value="<?= e($_POST['color'] ?? $tag['color']); ?>" 
                               required
                               oninput="document.getElementById('colorPicker').value = this.value">
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save Changes
                    </button>
                    <a href="<?= url('modules/tags/index.php'); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
