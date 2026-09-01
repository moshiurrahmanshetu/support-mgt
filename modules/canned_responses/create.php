<?php
/**
 * Canned Responses - Create Template (Admin Only)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

$user = current_user();
$errors = [];
$db = get_db();

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
            $insertStmt = $db->prepare("
                INSERT INTO canned_responses (title, content, created_by, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $insertStmt->execute([$title, $content, $user['id']]);

            clear_old_input();
            flash('success', "Canned response template <strong>" . e($title) . "</strong> created successfully!");
            redirect('modules/canned_responses/index.php');
        }

        if (!empty($errors)) {
            set_old_input([
                'title'   => $title,
                'content' => $content
            ]);
        }
    }
}

$pageTitle = 'Create Canned Response';
$pageHeader = 'Create Canned Response';
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
                <i class="bi bi-plus-circle me-2 text-primary"></i>Create Canned Response Template
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

            <form action="<?= url('modules/canned_responses/create.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>

                <!-- Template Title -->
                <div class="mb-3">
                    <label for="title" class="form-label">Template Title <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="title" 
                           name="title" 
                           value="<?= e(old('title')); ?>" 
                           placeholder="e.g. Password Reset Instructions, Billing Inquiry Reply" 
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
                              placeholder="Write the standardized response message..." 
                              required><?= e(old('content')); ?></textarea>
                    <div class="form-text text-muted">
                        Agents will be able to select this template and customize its text before posting a reply.
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save Template
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
