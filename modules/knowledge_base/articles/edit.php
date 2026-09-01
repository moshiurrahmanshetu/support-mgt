<?php
/**
 * Knowledge Base - Edit Article (Admin Only - Phase 06)
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
    flash('danger', 'Invalid article reference.');
    redirect('modules/knowledge_base/articles/index.php');
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM knowledge_base_articles WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    flash('danger', 'Article not found.');
    redirect('modules/knowledge_base/articles/index.php');
}

// Fetch all categories for dropdown
$catStmt = $db->query("SELECT id, name, status FROM knowledge_base_categories ORDER BY sort_order ASC, name ASC");
$allCategories = $catStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash('danger', 'Security validation failed. Please try submitting again.');
        redirect('modules/knowledge_base/articles/edit.php?id=' . $id);
    }

    $title = trim($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = trim($_POST['status'] ?? 'published');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    // Validation
    if (empty($title)) {
        $errors[] = 'Article title is required.';
    } elseif (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
        $errors[] = 'Title must be between 3 and 255 characters.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Please select a valid category.';
    } else {
        $catCheck = $db->prepare("SELECT id FROM knowledge_base_categories WHERE id = ? LIMIT 1");
        $catCheck->execute([$categoryId]);
        if (!$catCheck->fetch()) {
            $errors[] = 'Selected category does not exist.';
        }
    }

    if (empty($content)) {
        $errors[] = 'Article content cannot be empty.';
    }

    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'published';
    }

    $storedImageName = $article['featured_image'];

    // Handle Image Removal
    if ($removeImage && !empty($storedImageName)) {
        $oldFilePath = __DIR__ . '/../../../uploads/kb/' . $storedImageName;
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
        $storedImageName = null;
    }

    // Process New Featured Image Upload
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['featured_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed (Error code: ' . $_FILES['featured_image']['error'] . ').';
        } else {
            $file = $_FILES['featured_image'];
            if ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Featured image must not exceed 5MB.';
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedImgExts = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowedImgExts, true)) {
                    $errors[] = 'Only JPG, PNG, and WebP images are permitted.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                    if (!in_array($mime, $allowedMimes, true)) {
                        $errors[] = 'Invalid image MIME type.';
                    } else {
                        $kbUploadDir = __DIR__ . '/../../../uploads/kb';
                        if (!is_dir($kbUploadDir)) {
                            mkdir($kbUploadDir, 0755, true);
                        }

                        // Remove old image if existing
                        if (!empty($storedImageName)) {
                            $oldFilePath = $kbUploadDir . '/' . $storedImageName;
                            if (file_exists($oldFilePath)) {
                                unlink($oldFilePath);
                            }
                        }

                        $storedImageName = 'kb_' . bin2hex(random_bytes(16)) . '.' . $ext;
                        $destination = $kbUploadDir . '/' . $storedImageName;

                        if (!move_uploaded_file($file['tmp_name'], $destination)) {
                            $errors[] = 'Failed to move uploaded image to storage.';
                            $storedImageName = $article['featured_image'];
                        }
                    }
                }
            }
        }
    }

    // Update Article
    if (empty($errors)) {
        $slug = generate_unique_slug('knowledge_base_articles', $title, $id);
        $publishedAt = $article['published_at'];
        if ($status === 'published' && empty($publishedAt)) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $updateStmt = $db->prepare("
            UPDATE knowledge_base_articles 
            SET category_id = ?, title = ?, slug = ?, excerpt = ?, content = ?, featured_image = ?, 
                status = ?, is_featured = ?, updated_at = NOW(), published_at = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $categoryId,
            $title,
            $slug,
            empty($excerpt) ? null : $excerpt,
            $content,
            $storedImageName,
            $status,
            $isFeatured,
            $publishedAt,
            $id
        ]);

        log_activity($user['id'], 'knowledge_base', 'knowledge_base_article_updated', "Updated Knowledge Base article: {$title} (ID: {$id})", 'kb_article', $id);

        flash('success', "Article <strong>" . e($title) . "</strong> updated successfully!");
        redirect('modules/knowledge_base/articles/index.php');
    }
}

$pageTitle = 'Edit Article';
$pageHeader = 'Edit Knowledge Base Article';
$activePage = 'kb_articles';

include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 860px;">
    <!-- Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/knowledge_base/articles/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Articles
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Article Details
            </h5>
            <a href="<?= url('modules/knowledge_base/view.php?slug=' . urlencode($article['slug'])); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> View Live
            </a>
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

            <form action="<?= url('modules/knowledge_base/articles/edit.php?id=' . $id); ?>" method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field(); ?>

                <!-- Article Title -->
                <div class="mb-3">
                    <label for="title" class="form-label">Article Title <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="title" 
                           name="title" 
                           value="<?= e($_POST['title'] ?? $article['title']); ?>" 
                           required>
                </div>

                <!-- Category & Status Row -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($allCategories as $ac): ?>
                                <option value="<?= $ac['id']; ?>" <?= ((int)($_POST['category_id'] ?? $article['category_id']) === (int)$ac['id']) ? 'selected' : ''; ?>>
                                    <?= e($ac['name']); ?><?= ($ac['status'] === 'inactive') ? ' (Inactive)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label">Publishing Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="published" <?= (($_POST['status'] ?? $article['status']) === 'published') ? 'selected' : ''; ?>>Published (Live)</option>
                            <option value="draft" <?= (($_POST['status'] ?? $article['status']) === 'draft') ? 'selected' : ''; ?>>Draft (Hidden from Public)</option>
                        </select>
                    </div>
                </div>

                <!-- Excerpt -->
                <div class="mb-3">
                    <label for="excerpt" class="form-label">Short Summary / Excerpt <span class="text-muted small">(Optional)</span></label>
                    <textarea class="form-control" 
                              id="excerpt" 
                              name="excerpt" 
                              rows="2"><?= e($_POST['excerpt'] ?? $article['excerpt']); ?></textarea>
                </div>

                <!-- Content -->
                <div class="mb-3">
                    <label for="content" class="form-label">Article Body Content <span class="text-danger">*</span></label>
                    <textarea class="form-control font-monospace" 
                              id="content" 
                              name="content" 
                              rows="12" 
                              required><?= e($_POST['content'] ?? $article['content']); ?></textarea>
                </div>

                <!-- Featured Image & Featured Toggle Row -->
                <div class="row g-3 mb-4 align-items-center">
                    <div class="col-12 col-md-7">
                        <label for="featured_image" class="form-label">Cover / Featured Image <span class="text-muted small">(Optional)</span></label>
                        <?php if (!empty($article['featured_image'])): ?>
                            <div class="mb-2 d-flex align-items-center gap-3 p-2 border rounded bg-light">
                                <img src="<?= url('uploads/kb/' . $article['featured_image']); ?>" alt="Cover" style="height: 50px; width: 80px; object-fit: cover; border-radius: 4px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image" value="1">
                                    <label class="form-check-label text-danger small fw-medium" for="remove_image">
                                        <i class="bi bi-trash"></i> Remove current image
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="featured_image" name="featured_image" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="col-12 col-md-5 pt-md-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1" <?= (isset($_POST['is_featured']) ? $_POST['is_featured'] : $article['is_featured']) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold" for="is_featured">
                                <i class="bi bi-star-fill text-warning me-1"></i> Highlight as Featured Article
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= url('modules/knowledge_base/articles/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Update Article
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
