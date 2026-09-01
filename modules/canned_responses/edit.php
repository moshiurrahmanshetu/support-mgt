<?php
/**
 * Canned Responses - Edit Template (Admin Only)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid template ID.');
    redirect('modules/canned_responses/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM canned_responses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$template = $stmt->fetch();

if (!$template) {
    flash('danger', 'Canned response template not found.');
    redirect('modules/canned_responses/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (empty($title)) {
            $errors[] = 'Please provide a response title.';
        } elseif (mb_strlen($title) < 2 || mb_strlen($title) > 150) {
            $errors[] = 'Title must be between 2 and 150 characters.';
        }

        if (empty($content)) {
            $errors[] = 'Please provide response template content.';
        } elseif (mb_strlen($content) < 5) {
            $errors[] = 'Content must be at least 5 characters long.';
        }

        if (empty($errors)) {
            $updateStmt = $db->prepare("
                UPDATE canned_responses 
                SET title = ?, content = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->execute([$title, $content, $id]);

            require_once __DIR__ . '/../../includes/activity_log.php';
            log_activity($user['id'], 'canned_response', 'canned_response_updated', "Updated canned response: {$title} (ID: {$id})", 'canned_response', $id);

            flash('success', "Template <strong>" . e($title) . "</strong> updated successfully!");
            redirect('modules/canned_responses/index.php');
        }
    }
}

$pageTitle = 'Edit Canned Response';
$pageHeader = 'Edit Canned Response';
$activePage = 'canned_responses';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 720px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/canned_responses/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Canned Responses
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Canned Response Template
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

            <form action="<?= url('modules/canned_responses/edit.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="id" value="<?= $template['id']; ?>">

                <!-- Template Title -->
                <div class="mb-3">
                    <label for="title" class="form-label">Template Title <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="title" 
                           name="title" 
                           value="<?= e($_POST['title'] ?? $template['title']); ?>" 
                           required 
                           autofocus>
                </div>

                <!-- Template Content -->
                <div class="mb-4">
                    <label for="content" class="form-label">Template Message Content <span class="text-danger">*</span></label>
                    <textarea class="form-control" 
                              id="content" 
                              name="content" 
                              rows="8" 
                              required><?= e($_POST['content'] ?? $template['content']); ?></textarea>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save Changes
                    </button>
                    <a href="<?= url('modules/canned_responses/index.php'); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
