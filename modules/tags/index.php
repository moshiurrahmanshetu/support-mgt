<?php
/**
 * Tag Management - Tag List (Admin Only)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$db = get_db();

// Fetch Tags with Ticket Usage Count
$tagsSql = "
    SELECT 
        tt.*,
        COUNT(ttr.id) AS ticket_count
    FROM ticket_tags tt
    LEFT JOIN ticket_tag_relations ttr ON ttr.tag_id = tt.id
    GROUP BY tt.id
    ORDER BY tt.name ASC
";
$tagsStmt = $db->query($tagsSql);
$tags = $tagsStmt->fetchAll();

$pageTitle = 'Ticket Tags';
$pageHeader = 'Tag Management';
$activePage = 'tags';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-tags me-2 text-primary"></i>Ticket Tags
            </h1>
            <p class="text-secondary-custom small mb-0">
                Organize, label, and filter customer inquiries using custom tags
            </p>
        </div>

        <div>
            <a href="<?= url('modules/tags/create.php'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Tag
            </a>
        </div>
    </div>

    <!-- Tags Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3">Tag Name</th>
                            <th class="py-3" style="width: 140px;">Color Preview</th>
                            <th class="py-3 text-center" style="width: 130px;">Attached Tickets</th>
                            <th class="py-3" style="width: 160px;">Created Date</th>
                            <th class="pe-3 py-3 text-end" style="width: 140px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tags)): ?>
                            <?php foreach ($tags as $tag): ?>
                                <tr>
                                    <!-- Tag Name -->
                                    <td class="ps-3 fw-bold text-dark">
                                        <i class="bi bi-tag-fill me-2" style="color: <?= e($tag['color']); ?>;"></i>
                                        <?= e($tag['name']); ?>
                                    </td>

                                    <!-- Color Swatch / Badge -->
                                    <td>
                                        <span class="badge" style="background-color: <?= e($tag['color']); ?>; color: #ffffff;">
                                            <?= e($tag['color']); ?>
                                        </span>
                                    </td>

                                    <!-- Attached Tickets Count -->
                                    <td class="text-center">
                                        <a href="<?= url('modules/tickets/index.php?tag=' . $tag['id']); ?>" class="badge bg-light text-dark border text-decoration-none" title="Filter tickets with this tag">
                                            <i class="bi bi-ticket-perforated me-1"></i><?= (int)$tag['ticket_count']; ?>
                                        </a>
                                    </td>

                                    <!-- Created Date -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($tag['created_at'], 'M d, Y')); ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-3 text-end">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="<?= url('modules/tags/edit.php?id=' . $tag['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Edit Tag">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <!-- Delete Tag Form -->
                                            <form action="<?= url('modules/tags/delete.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this tag? (Attached tickets will NOT be deleted)');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?= $tag['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Tag">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-tags fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="h6 fw-bold">No tags found</h5>
                                    <p class="small mb-3">Create custom tags to label and triage support tickets.</p>
                                    <a href="<?= url('modules/tags/create.php'); ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create First Tag
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
