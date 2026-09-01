<?php
/**
 * FAQ Management - Edit FAQ (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/knowledge_base.php';
require_once __DIR__ . '/../../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$user = current_user();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid FAQ reference.');
    redirect('modules/knowledge_base/faqs/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM faqs WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$faq = $stmt->fetch();

if (!$faq) {
    flash('danger', 'FAQ not found.');
    redirect('modules/knowledge_base/faqs/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash('danger', 'Security validation failed. Please try submitting again.');
        redirect('modules/knowledge_base/faqs/edit.php?id=' . $id);
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

    // Update FAQ
    if (empty($errors)) {
        $updateStmt = $db->prepare("
            UPDATE faqs 
            SET question = ?, answer = ?, sort_order = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([
            $question,
            $answer,
            $sortOrder,
            $status,
            $id
        ]);

        log_activity($user['id'], 'knowledge_base', 'knowledge_base_faq_updated', "Updated FAQ: {$question} (ID: {$id})", 'faq', $id);

        flash('success', "FAQ updated successfully!");
        redirect('modules/knowledge_base/faqs/index.php');
    }
}

$pageTitle = 'Edit FAQ';
$pageHeader = 'Edit FAQ';
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
                <i class="bi bi-pencil-square me-2 text-primary"></i>Edit FAQ Details
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

            <form action="<?= url('modules/knowledge_base/faqs/edit.php?id=' . $id); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>

                <!-- Question -->
                <div class="mb-3">
                    <label for="question" class="form-label">Question <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="question" 
                           name="question" 
                           value="<?= e($_POST['question'] ?? $faq['question']); ?>" 
                           required>
                </div>

                <!-- Answer -->
                <div class="mb-3">
                    <label for="answer" class="form-label">Answer <span class="text-danger">*</span></label>
                    <textarea class="form-control" 
                              id="answer" 
                              name="answer" 
                              rows="6" 
                              required><?= e($_POST['answer'] ?? $faq['answer']); ?></textarea>
                </div>

                <div class="row g-3 mb-4">
                    <!-- Sort Order -->
                    <div class="col-12 col-md-6">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input type="number" 
                               class="form-control" 
                               id="sort_order" 
                               name="sort_order" 
                               value="<?= (int)($_POST['sort_order'] ?? $faq['sort_order']); ?>" 
                               min="0">
                    </div>

                    <!-- Status -->
                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="active" <?= (($_POST['status'] ?? $faq['status']) === 'active') ? 'selected' : ''; ?>>Active (Visible on Portal)</option>
                            <option value="inactive" <?= (($_POST['status'] ?? $faq['status']) === 'inactive') ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= url('modules/knowledge_base/faqs/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Update FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
