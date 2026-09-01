<?php
/**
 * FAQ Management - Create FAQ (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/knowledge_base.php';
require_once __DIR__ . '/../../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash('danger', 'Security validation failed. Please try submitting again.');
        redirect('modules/knowledge_base/faqs/create.php');
    }

    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $status = trim($_POST['status'] ?? STATUS_ACTIVE);

    // Validation
    if (empty($question)) {
        $errors[] = 'Question is required.';
    } elseif (mb_strlen($question) < 5 || mb_strlen($question) > 255) {
        $errors[] = 'Question must be between 5 and 255 characters.';
    }

    if (empty($answer)) {
        $errors[] = 'Answer is required.';
    } elseif (mb_strlen($answer) < 5) {
        $errors[] = 'Answer must be at least 5 characters long.';
    }

    if (!in_array($status, VALID_STATUSES, true)) {
        $status = STATUS_ACTIVE;
    }

    // Insert FAQ
    if (empty($errors)) {
        $db = get_db();
        $insertStmt = $db->prepare("
            INSERT INTO faqs (question, answer, sort_order, status, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $insertStmt->execute([
            $question,
            $answer,
            $sortOrder,
            $status,
            $user['id']
        ]);
        $faqId = (int)$db->lastInsertId();

        log_activity($user['id'], 'knowledge_base', 'knowledge_base_faq_created', "Created FAQ: {$question}", 'faq', $faqId);

        clear_old_input();
        flash('success', "FAQ has been created successfully!");
        redirect('modules/knowledge_base/faqs/index.php');
    }

    if (!empty($errors)) {
        set_old_input([
            'question'   => $question,
            'answer'     => $answer,
            'sort_order' => $sortOrder,
            'status'     => $status
        ]);
    }
}

$pageTitle = 'Create FAQ';
$pageHeader = 'Create FAQ';
$activePage = 'kb_faqs';

include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 760px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/knowledge_base/faqs/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to FAQs
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-plus-circle me-2 text-primary"></i>New FAQ Details
            </h5>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="<?= url('modules/knowledge_base/faqs/create.php'); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>

                <!-- Question -->
                <div class="mb-3">
                    <label for="question" class="form-label">Question <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="question" 
                           name="question" 
                           value="<?= e(old('question')); ?>" 
                           placeholder="e.g. How long does it usually take to receive a response?" 
                           required>
                </div>

                <!-- Answer -->
                <div class="mb-3">
                    <label for="answer" class="form-label">Answer <span class="text-danger">*</span></label>
                    <textarea class="form-control" 
                              id="answer" 
                              name="answer" 
                              rows="6" 
                              placeholder="Provide a clear and concise answer..." 
                              required><?= e(old('answer')); ?></textarea>
                </div>

                <div class="row g-3 mb-4">
                    <!-- Sort Order -->
                    <div class="col-12 col-md-6">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input type="number" 
                               class="form-control" 
                               id="sort_order" 
                               name="sort_order" 
                               value="<?= (int)(old('sort_order') ?: 0); ?>" 
                               min="0">
                        <div class="form-text small text-muted">Lower numbers appear first on the portal.</div>
                    </div>

                    <!-- Status -->
                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="active" <?= (old('status', 'active') === 'active') ? 'selected' : ''; ?>>Active (Visible on Portal)</option>
                            <option value="inactive" <?= (old('status') === 'inactive') ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= url('modules/knowledge_base/faqs/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
