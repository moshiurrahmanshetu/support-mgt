<?php
/**
 * Canned Responses - Listing (Admin & Agent)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Allowed for Admin and Agent
require_role([ROLE_ADMIN, ROLE_AGENT]);

$user = current_user();
$db = get_db();

// Fetch Canned Responses
$cannedSql = "
    SELECT 
        cr.*,
        u.name AS author_name
    FROM canned_responses cr
    LEFT JOIN users u ON cr.created_by = u.id
    ORDER BY cr.title ASC
";
$cannedStmt = $db->query($cannedSql);
$responses = $cannedStmt->fetchAll();

$pageTitle = 'Canned Responses';
$pageHeader = 'Canned Responses';
$activePage = 'canned_responses';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-chat-square-quote me-2 text-primary"></i>Canned Responses
            </h1>
            <p class="text-secondary-custom small mb-0">
                Pre-written reply templates to quickly answer frequent customer inquiries
            </p>
        </div>

        <?php if ($user['role'] === ROLE_ADMIN): ?>
            <div>
                <a href="<?= url('modules/canned_responses/create.php'); ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Create Template
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Canned Responses Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3" style="width: 240px;">Title</th>
                            <th class="py-3">Content Preview</th>
                            <th class="py-3" style="width: 160px;">Created By</th>
                            <th class="py-3" style="width: 140px;">Created Date</th>
                            <?php if ($user['role'] === ROLE_ADMIN): ?>
                                <th class="pe-3 py-3 text-end" style="width: 140px;">Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($responses)): ?>
                            <?php foreach ($responses as $resp): ?>
                                <tr>
                                    <!-- Title -->
                                    <td class="ps-3 fw-bold text-dark">
                                        <i class="bi bi-file-text me-2 text-primary"></i>
                                        <?= e($resp['title']); ?>
                                    </td>

                                    <!-- Content Preview -->
                                    <td class="small text-secondary">
                                        <div class="text-truncate" style="max-width: 480px;">
                                            <?= e(mb_substr($resp['content'], 0, 120)) . (mb_strlen($resp['content']) > 120 ? '...' : ''); ?>
                                        </div>
                                    </td>

                                    <!-- Created By -->
                                    <td class="small text-secondary">
                                        <?= !empty($resp['author_name']) ? e($resp['author_name']) : '<span class="text-muted fst-italic">System</span>'; ?>
                                    </td>

                                    <!-- Created Date -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($resp['created_at'], 'M d, Y')); ?>
                                    </td>

                                    <!-- Actions (Admin Only) -->
                                    <?php if ($user['role'] === ROLE_ADMIN): ?>
                                        <td class="pe-3 text-end">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <a href="<?= url('modules/canned_responses/edit.php?id=' . $resp['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Edit Template">
                                                    <i class="bi bi-pencil"></i>
                                                </a>

                                                <form action="<?= url('modules/canned_responses/delete.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this canned response template?');">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="id" value="<?= $resp['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Template">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= ($user['role'] === ROLE_ADMIN) ? 5 : 4; ?>" class="text-center py-5 text-muted">
                                    <i class="bi bi-chat-square-quote fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="h6 fw-bold">No canned responses found</h5>
                                    <p class="small mb-3">Create reusable response templates to streamline support replies.</p>
                                    <?php if ($user['role'] === ROLE_ADMIN): ?>
                                        <a href="<?= url('modules/canned_responses/create.php'); ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle"></i> Create First Template
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
