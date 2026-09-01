<?php
/**
 * Knowledge Base - Create Article (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/knowledge_base.php';
require_once __DIR__ . '/../../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$user = current_user();
$db = get_db();
$errors = [];

// Fetch active categories for dropdown
$catStmt = $db->query("SELECT id, name FROM knowledge_base_categories WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
$activeCategories = $catStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash('danger', 'Security validation failed. Please try submitting again.');
        redirect('modules/knowledge_base/articles/create.php');
    }

    $title = trim($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = trim($_POST['status'] ?? 'published');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    // Validation
    if (empty($title)) {
        $errors[] = 'Article title is required.';
    } elseif (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
        $errors[] = 'Title must be between 3 and 255 characters.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Please select a valid category.';
    } else {
        $catCheck = $db->prepare("SELECT id FROM knowledge_base_categories WHERE id = ? AND status = 'active' LIMIT 1");
        $catCheck->execute([$categoryId]);
        if (!$catCheck->fetch()) {
            $errors[] = 'Selected category does not exist or is inactive.';
        }
    }

    if (empty($content)) {
        $errors[] = 'Article content cannot be empty.';
    }

    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'published';
    }

    // Process Featured Image (if uploaded)
    $storedImageName = null;
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
                    $errors[] = 'Only JPG, PNG, and WebP images are permitted for featured images.';
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

                        $storedImageName = 'kb_' . bin2hex(random_bytes(16)) . '.' . $ext;
                        $destination = $kbUploadDir . '/' . $storedImageName;

                        if (!move_uploaded_file($file['tmp_name'], $destination)) {
                            $errors[] = 'Failed to move uploaded image to storage.';
                            $storedImageName = null;
                        }
                    }
                }
            }
        }
    }

    // Insert Article
    if (empty($errors)) {
        $slug = generate_unique_slug('knowledge_base_articles', $title);
        $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;

        $insertStmt = $db->prepare("
            INSERT INTO knowledge_base_articles 
            (category_id, title, slug, excerpt, content, featured_image, status, is_featured, view_count, created_by, created_at, updated_at, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW(), ?)
        ");
        $insertStmt->execute([
            $categoryId,
            $title,
            $slug,
            empty($excerpt) ? null : $excerpt,
            $content,
            $storedImageName,
            $status,
            $isFeatured,
            $user['id'],
            $publishedAt
        ]);
        $articleId = (int)$db->lastInsertId();

        log_activity($user['id'], 'knowledge_base', 'knowledge_base_article_created', "Created Knowledge Base article: {$title}", 'kb_article', $articleId);

        clear_old_input();
        flash('success', "Article <strong>" . e($title) . "</strong> has been created successfully!");
        redirect('modules/knowledge_base/articles/index.php');
    }

    if (!empty($errors)) {
        set_old_input([
            'title'       => $title,
            'category_id' => $categoryId,
            'excerpt'     => $excerpt,
            'content'     => $content,
            'status'      => $status,
            'is_featured' => $isFeatured
        ]);
    }
}

$pageTitle = 'Create Article';
$pageHeader = 'Create Knowledge Base Article';
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
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-plus-circle me-2 text-primary"></i>New Article Details
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

            <form action="<?= url('modules/knowledge_base/articles/create.php'); ?>" method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field(); ?>

                <!-- Article Title -->
                <div class="mb-3">
                    <label for="title" class="form-label">Article Title <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="title" 
                           name="title" 
                           value="<?= e(old('title')); ?>" 
                           placeholder="e.g. How to Reset Your Account Password" 
                           required>
                </div>

                <!-- Category & Status Row -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($activeCategories as $ac): ?>
                                <option value="<?= $ac['id']; ?>" <?= ((int)old('category_id') === (int)$ac['id']) ? 'selected' : ''; ?>>
                                    <?= e($ac['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label">Publishing Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="published" <?= (old('status', 'published') === 'published') ? 'selected' : ''; ?>>Published (Live)</option>
                            <option value="draft" <?= (old('status') === 'draft') ? 'selected' : ''; ?>>Draft (Hidden from Public)</option>
                        </select>
                    </div>
                </div>

                <!-- Excerpt -->
                <div class="mb-3">
                    <label for="excerpt" class="form-label">Short Summary / Excerpt <span class="text-muted small">(Optional, displayed in search & cards)</span></label>
                    <textarea class="form-control" 
                              id="excerpt" 
                              name="excerpt" 
                              rows="2" 
                              placeholder="Brief summary of the article..."><?= e(old('excerpt')); ?></textarea>
                </div>

                <!-- Content -->
                <div class="mb-3">
                    <label for="content" class="form-label">Article Body Content <span class="text-danger">*</span></label>
                    <textarea class="form-control font-monospace" 
                              id="content" 
                              name="content" 
                              rows="12" 
                              placeholder="Write your article content here. You can use markdown headings (##, ###), bullet points (- or *), bold (**text**), and numbered lists (1.)." 
                              required><?= e(old('content')); ?></textarea>
                    <div class="form-text small text-muted">
                        Formatting supported: <code>## Heading</code>, <code>**Bold**</code>, <code>*Italic*</code>, <code>`code`</code>, <code>- List Item</code>, <code>1. Numbered Item</code>.
                    </div>
                </div>

                <!-- Featured Image & Featured Toggle Row -->
                <div class="row g-3 mb-4 align-items-center">
                    <div class="col-12 col-md-7">
                        <label for="featured_image" class="form-label">Cover / Featured Image <span class="text-muted small">(Optional, max 5MB)</span></label>
                        <input type="file" class="form-control" id="featured_image" name="featured_image" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="col-12 col-md-5 pt-md-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1" <?= old('is_featured') ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold" for="is_featured">
                                <i class="bi bi-star-fill text-warning me-1"></i> Highlight as Featured Article
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= url('modules/knowledge_base/articles/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save Article
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
