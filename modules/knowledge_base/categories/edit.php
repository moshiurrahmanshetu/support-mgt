<?php
/**
 * Knowledge Base - Edit Category (Admin Only - Phase 06)
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
    flash('danger', 'Invalid category reference.');
    redirect('modules/knowledge_base/categories/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM knowledge_base_categories WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    flash('danger', 'Category not found.');
    redirect('modules/knowledge_base/categories/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash('danger', 'Security validation failed. Please try submitting again.');
        redirect('modules/knowledge_base/categories/edit.php?id=' . $id);
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'bi-folder');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $status = trim($_POST['status'] ?? STATUS_ACTIVE);

    // Validation
    if (empty($name)) {
        $errors[] = 'Category name is required.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Category name must be between 2 and 100 characters.';
    }

    if (empty($icon)) {
        $icon = 'bi-folder';
    }

    if (!in_array($status, VALID_STATUSES, true)) {
        $status = STATUS_ACTIVE;
    }

    // Check duplicate name excluding current ID
    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM knowledge_base_categories WHERE name = ? AND id != ? LIMIT 1");
        $checkStmt->execute([$name, $id]);
        if ($checkStmt->fetch()) {
            $errors[] = 'Another category with this name already exists.';
        }
    }

    // Update Category
    if (empty($errors)) {
        $slug = generate_unique_slug('knowledge_base_categories', $name, $id);

        $updateStmt = $db->prepare("
            UPDATE knowledge_base_categories 
            SET name = ?, slug = ?, description = ?, icon = ?, sort_order = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([
            $name,
            $slug,
            empty($description) ? null : $description,
            $icon,
            $sortOrder,
            $status,
            $id
        ]);

        log_activity($user['id'], 'knowledge_base', 'knowledge_base_category_updated', "Updated Knowledge Base category: {$name} (ID: {$id})", 'kb_category', $id);

        flash('success', "Category <strong>" . e($name) . "</strong> updated successfully!");
        redirect('modules/knowledge_base/categories/index.php');
    }
}

$pageTitle = 'Edit Category';
$pageHeader = 'Edit Knowledge Base Category';
$activePage = 'kb_categories';

include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 720px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/knowledge_base/categories/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Categories
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Category Details
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

            <form action="<?= url('modules/knowledge_base/categories/edit.php?id=' . $id); ?>" method="POST" novalidate>
                <?= csrf_field(); ?>

                <!-- Category Name -->
                <div class="mb-3">
                    <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= e($_POST['name'] ?? $category['name']); ?>" 
                           required>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label for="description" class="form-label">Description <span class="text-muted small">(Optional)</span></label>
                    <textarea class="form-control" 
                              id="description" 
                              name="description" 
                              rows="3"><?= e($_POST['description'] ?? $category['description']); ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <!-- Icon -->
                    <div class="col-12 col-md-6">
                        <label for="icon" class="form-label">Bootstrap Icon Class</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-star"></i></span>
                            <input type="text" 
                                   class="form-control" 
                                   id="icon" 
                                   name="icon" 
                                   value="<?= e($_POST['icon'] ?? $category['icon']); ?>">
                        </div>
                    </div>

                    <!-- Sort Order -->
                    <div class="col-12 col-md-6">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input type="number" 
                               class="form-control" 
                               id="sort_order" 
                               name="sort_order" 
                               value="<?= (int)($_POST['sort_order'] ?? $category['sort_order']); ?>" 
                               min="0">
                    </div>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="active" <?= (($_POST['status'] ?? $category['status']) === 'active') ? 'selected' : ''; ?>>Active (Publicly Visible)</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $category['status']) === 'inactive') ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= url('modules/knowledge_base/categories/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
