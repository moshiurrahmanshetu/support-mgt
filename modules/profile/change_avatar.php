<?php
/**
 * Profile Module - Change Avatar
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_login();

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired or invalid. Please try again.';
    } else {
        // Verify file was uploaded
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please select an image file to upload.';
        } elseif ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error occurred (Error Code: ' . $_FILES['avatar']['error'] . ').';
        } else {
            $file = $_FILES['avatar'];

            // 1. Validate File Size
            if ($file['size'] > MAX_AVATAR_SIZE) {
                $errors[] = 'File size exceeds the 2MB limit.';
            }

            // 2. Validate Extension
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ALLOWED_AVATAR_EXTENSIONS, true)) {
                $errors[] = 'Invalid file extension. Only JPG, PNG, and WebP images are allowed.';
            }

            // 3. Validate MIME Type securely
            if (empty($errors)) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file['tmp_name']);

                if (!in_array($mimeType, ALLOWED_AVATAR_MIMES, true)) {
                    $errors[] = 'Invalid image type detected (' . e($mimeType) . ').';
                }
            }

            // 4. Validate image dimensions/integrity
            if (empty($errors)) {
                $imageInfo = @getimagesize($file['tmp_name']);
                if ($imageInfo === false) {
                    $errors[] = 'The uploaded file is not a valid image.';
                }
            }

            // 5. Process and store image
            if (empty($errors)) {
                // Ensure upload directory exists
                if (!is_dir(AVATAR_UPLOAD_DIR)) {
                    mkdir(AVATAR_UPLOAD_DIR, 0755, true);
                }

                // Generate random unique filename
                $newFileName = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $extension;
                $destination = AVATAR_UPLOAD_DIR . '/' . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $db = get_db();

                    // Remove old custom avatar file if present
                    if (!empty($user['avatar'])) {
                        $oldFilePath = AVATAR_UPLOAD_DIR . '/' . $user['avatar'];
                        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                            @unlink($oldFilePath);
                        }
                    }

                    // Update user record
                    $updateStmt = $db->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$newFileName, $user['id']]);

                    // Sync session
                    refresh_user_session((int)$user['id']);

                    flash('success', 'Profile photo updated successfully!');
                    redirect('modules/profile/index.php');
                } else {
                    $errors[] = 'Failed to save uploaded image. Please check directory permissions.';
                }
            }
        }
    }
}

$pageTitle = 'Change Avatar';
$pageHeader = 'Change Avatar';
$activePage = 'profile';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0" style="max-width: 720px;">
    <!-- Breadcrumb / Back Link -->
    <div class="mb-3">
        <a href="<?= url('modules/profile/index.php'); ?>" class="text-secondary-custom small text-decoration-none d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to Profile
        </a>
    </div>

    <div class="card border shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title h6 mb-0 fw-bold">
                <i class="bi bi-camera me-2 text-primary"></i>Upload Profile Photo
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

            <form action="<?= url('modules/profile/change_avatar.php'); ?>" method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field(); ?>

                <!-- Current Avatar Preview -->
                <div class="text-center mb-4">
                    <div class="mb-2">
                        <img src="<?= e(get_avatar_url($user['avatar'] ?? null)); ?>" 
                             alt="Current Avatar" 
                             id="avatarPreview"
                             class="avatar-img avatar-xl shadow-sm">
                    </div>
                    <span class="text-muted small">Current Avatar</span>
                </div>

                <!-- File Input -->
                <div class="mb-4">
                    <label for="avatar" class="form-label">Select Image File</label>
                    <input type="file" 
                           class="form-control" 
                           id="avatar" 
                           name="avatar" 
                           accept="image/jpeg,image/png,image/webp" 
                           required>
                    <div class="form-text text-muted">
                        Accepted formats: JPG, PNG, WebP. Maximum file size: 2MB.
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Upload & Save
                    </button>
                    <a href="<?= url('modules/profile/index.php'); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Live Image Preview before submit
document.getElementById('avatar')?.addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function (event) {
            const preview = document.getElementById('avatarPreview');
            if (preview) {
                preview.src = event.target.result;
            }
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
