<?php
/**
 * Step 2: System Requirements & Permissions Check
 */
$reqResults = check_system_requirements();
$canContinue = $reqResults['all_passed'];
?>
<div>
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1">Server Requirements & Permissions</h4>
        <p class="text-muted small">Please verify that your server configuration meets the minimum requirements before proceeding.</p>
    </div>

    <?php if (!$canContinue): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-exclamation-octagon-fill fs-5 flex-shrink-0"></i>
            <div>
                <strong>Critical Requirement Failed:</strong> One or more required PHP extensions or directory write permissions are missing. Please address the marked items below and refresh this page.
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
            <div>
                <strong>All Requirements Passed!</strong> Your server is fully compatible and ready for installation.
            </div>
        </div>
    <?php endif; ?>

    <!-- PHP Extensions -->
    <h6 class="fw-bold text-secondary mb-2 text-uppercase fs-8">PHP Configuration & Extensions</h6>
    <div class="table-responsive border rounded mb-4">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr class="fs-8 text-secondary">
                    <th class="ps-3 py-2">Requirement</th>
                    <th class="py-2">Current State</th>
                    <th class="py-2 text-end pe-3">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reqResults['requirements'] as $req): ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-medium text-dark"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="small text-muted fs-8"><?= htmlspecialchars($req['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td class="font-monospace small"><?= htmlspecialchars($req['current'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end pe-3">
                            <?php if ($req['passed']): ?>
                                <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i> Passed</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i> Failed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Directory Write Permissions -->
    <h6 class="fw-bold text-secondary mb-2 text-uppercase fs-8">Directory Permissions</h6>
    <div class="table-responsive border rounded mb-4">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr class="fs-8 text-secondary">
                    <th class="ps-3 py-2">Directory</th>
                    <th class="py-2">Required Permission</th>
                    <th class="py-2 text-end pe-3">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reqResults['directories'] as $dir): ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-medium text-dark font-monospace"><?= htmlspecialchars($dir['path'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="small text-muted fs-8"><?= htmlspecialchars($dir['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td class="small">Writable (0755 / 0775)</td>
                        <td class="text-end pe-3">
                            <?php if ($dir['passed']): ?>
                                <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i> Writable</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i> Not Writable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
        <a href="?step=welcome" class="btn btn-outline-secondary px-3">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <div class="d-flex gap-2">
            <a href="?step=requirements" class="btn btn-outline-secondary" title="Re-check requirements">
                <i class="bi bi-arrow-clockwise"></i> Check Again
            </a>
            <?php if ($canContinue): ?>
                <a href="?step=database" class="btn btn-primary px-4 fw-semibold">
                    Continue to Database <i class="bi bi-arrow-right ms-1"></i>
                </a>
            <?php else: ?>
                <button class="btn btn-primary px-4 fw-semibold" disabled title="Fix failed requirements to proceed">
                    Continue to Database <i class="bi bi-arrow-right ms-1"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
