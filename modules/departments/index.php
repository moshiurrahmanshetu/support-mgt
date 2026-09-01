<?php
/**
 * Department Management - Department List
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

$db = get_db();

// Fetch Departments with Agent Count and Ticket Count
$deptSql = "
    SELECT 
        d.*,
        COUNT(DISTINCT u.id) AS agent_count,
        COUNT(DISTINCT t.id) AS ticket_count
    FROM departments d
    LEFT JOIN users u ON u.department_id = d.id AND u.role = 'agent'
    LEFT JOIN tickets t ON t.department_id = d.id
    GROUP BY d.id
    ORDER BY d.name ASC
";

$deptStmt = $db->query($deptSql);
$departments = $deptStmt->fetchAll();

$pageTitle = 'Department Management';
$pageHeader = 'Departments';
$activePage = 'departments';

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Header Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-building me-2 text-primary"></i>Support Departments
            </h1>
            <p class="text-secondary-custom small mb-0">
                Organize support teams and categorize incoming customer inquiries
            </p>
        </div>

        <div>
            <a href="<?= url('modules/departments/create.php'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Department
            </a>
        </div>
    </div>

    <!-- Departments Table Card -->
    <div class="card border shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary-custom fs-7 border-bottom">
                            <th class="ps-3 py-3" style="width: 220px;">Department Name</th>
                            <th class="py-3">Description</th>
                            <th class="py-3 text-center" style="width: 110px;">Agents</th>
                            <th class="py-3 text-center" style="width: 110px;">Tickets</th>
                            <th class="py-3" style="width: 120px;">Status</th>
                            <th class="py-3" style="width: 140px;">Created Date</th>
                            <th class="pe-3 py-3 text-end" style="width: 150px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <!-- Department Name -->
                                    <td class="ps-3 fw-bold text-dark">
                                        <i class="bi bi-folder2-open text-primary me-2"></i><?= e($dept['name']); ?>
                                    </td>

                                    <!-- Description -->
                                    <td class="text-secondary-custom small">
                                        <?= !empty($dept['description']) ? e($dept['description']) : '<span class="text-muted fst-italic">No description provided</span>'; ?>
                                    </td>

                                    <!-- Agent Count -->
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-people me-1"></i><?= (int)$dept['agent_count']; ?>
                                        </span>
                                    </td>

                                    <!-- Ticket Count -->
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-ticket-perforated me-1"></i><?= (int)$dept['ticket_count']; ?>
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <span class="badge badge-status-<?= e($dept['status']); ?>">
                                            <?= ucfirst(e($dept['status'])); ?>
                                        </span>
                                    </td>

                                    <!-- Created Date -->
                                    <td class="text-muted fs-8">
                                        <?= e(format_datetime($dept['created_at'], 'M d, Y')); ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-3 text-end">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="<?= url('modules/departments/edit.php?id=' . $dept['id']); ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Edit Department">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <!-- Status Toggle Form -->
                                            <form action="<?= url('modules/departments/status.php'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to change the status of this department?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?= $dept['id']; ?>">
                                                <input type="hidden" name="status" value="<?= ($dept['status'] === STATUS_ACTIVE) ? STATUS_INACTIVE : STATUS_ACTIVE; ?>">
                                                
                                                <?php if ($dept['status'] === STATUS_ACTIVE): ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Deactivate Department">
                                                        <i class="bi bi-slash-circle"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2" title="Activate Department">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-building fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="h6 fw-bold">No departments found</h5>
                                    <p class="small mb-3">Create departments to categorize support tickets and organize agents.</p>
                                    <a href="<?= url('modules/departments/create.php'); ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create First Department
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
