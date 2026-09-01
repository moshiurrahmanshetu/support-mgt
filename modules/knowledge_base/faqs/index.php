<?php
/**
 * FAQ Management - Index (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/knowledge_base.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$db = get_db();

// Fetch FAQs
$stmt = $db->query("
    SELECT f.*, u.name AS creator_name 
    FROM faqs f
    LEFT JOIN users u ON f.created_by = u.id
    ORDER BY f.sort_order ASC, f.created_at ASC
");
$faqs = $stmt->fetchAll();

$pageTitle = 'FAQ Management';
$pageHeader = 'Frequently Asked Questions';
$activePage = 'kb_faqs';

include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-question-circle me-2 text-primary"></i>Frequently Asked Questions (FAQ)
            </h1>
            <p class="text-secondary-custom small mb-0">
                Manage common questions and answers displayed on the public support portal
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= url('modules/knowledge_base/index.php'); ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> View Support Portal
            </a>
            <a href="<?= url('modules/knowledge_base/faqs/create.php'); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Add New FAQ
            </a>
        </div>
    </div>

    <!-- FAQs Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3" style="width: 70px;">Order</th>
                            <th class="py-3">Question</th>
                            <th class="py-3">Answer Summary</th>
                            <th class="py-3" style="width: 110px;">Status</th>
                            <th class="py-3" style="width: 130px;">Created</th>
                            <th class="pe-3 py-3 text-end" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($faqs)): ?>
                            <?php foreach ($faqs as $faq): ?>
                                <tr>
                                    <!-- Sort Order -->
                                    <td class="ps-3">
                                        <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= (int)$faq['sort_order']; ?></span>
                                    </td>

                                    <!-- Question -->
                                    <td class="fw-semibold text-dark">
                                        <?= e($faq['question']); ?>
                                    </td>

                                    <!-- Answer snippet -->
                                    <td class="text-muted small">
                                        <?= e(mb_strimwidth(strip_tags($faq['answer']), 0, 100, '...')); ?>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <?php if ($faq['status'] === STATUS_ACTIVE): ?>
                                            <span class="badge badge-status-resolved"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-dash-circle-fill me-1"></i>Inactive</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Created -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($faq['created_at'], 'M d, Y')); ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-3 text-end">
                                        <div class="d-flex justify-content-end align-items-center gap-1">
                                            <!-- Status Toggle -->
                                            <form action="<?= url('modules/knowledge_base/faqs/status.php'); ?>" method="POST" class="d-inline">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?= $faq['id']; ?>">
                                                <input type="hidden" name="status" value="<?= ($faq['status'] === STATUS_ACTIVE) ? STATUS_INACTIVE : STATUS_ACTIVE; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary py-1 px-2" title="<?= ($faq['status'] === STATUS_ACTIVE) ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="bi <?= ($faq['status'] === STATUS_ACTIVE) ? 'bi-pause-fill text-warning' : 'bi-play-fill text-success'; ?>"></i>
                                                </button>
                                            </form>

                                            <!-- Edit -->
                                            <a href="<?= url('modules/knowledge_base/faqs/edit.php?id=' . $faq['id']); ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit FAQ">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <!-- Delete -->
                                            <form action="<?= url('modules/knowledge_base/faqs/delete.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?= $faq['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete FAQ">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-question-diamond fs-1 text-secondary mb-2 d-block"></i>
                                    <h5 class="h6 fw-bold">No FAQs added yet</h5>
                                    <p class="small mb-3">Add frequently asked questions to help customers self-resolve inquiries faster.</p>
                                    <a href="<?= url('modules/knowledge_base/faqs/create.php'); ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add First FAQ
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
