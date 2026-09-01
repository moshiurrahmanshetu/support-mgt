<?php
/**
 * Step 3: Database Configuration & Connection Test
 */
$savedDb = $_SESSION['installer_db'] ?? [
    'host' => '127.0.0.1',
    'port' => '3306',
    'name' => 'support_mgt_db',
    'user' => 'root',
    'pass' => ''
];
?>
<div>
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1">Database Configuration</h4>
        <p class="text-muted small">Enter your MySQL / MariaDB database connection details. Make sure you have created an empty database beforehand.</p>
    </div>

    <!-- Alert placeholder for AJAX test connection -->
    <div id="dbAlertPlaceholder"></div>

    <form id="dbConfigForm" action="process.php?action=save_db" method="POST">
        <?= installer_csrf_field(); ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-8">
                <label for="dbHost" class="form-label fw-semibold fs-7 text-secondary">Database Host <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="dbHost" name="host" value="<?= htmlspecialchars($savedDb['host'], ENT_QUOTES, 'UTF-8'); ?>" required placeholder="127.0.0.1 or localhost">
                <div class="form-text fs-8 text-muted">Usually <code>127.0.0.1</code> or <code>localhost</code> on standard hosting.</div>
            </div>
            <div class="col-12 col-md-4">
                <label for="dbPort" class="form-label fw-semibold fs-7 text-secondary">Port <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="dbPort" name="port" value="<?= htmlspecialchars($savedDb['port'], ENT_QUOTES, 'UTF-8'); ?>" required placeholder="3306">
                <div class="form-text fs-8 text-muted">Default MySQL port is <code>3306</code>.</div>
            </div>

            <div class="col-12">
                <label for="dbName" class="form-label fw-semibold fs-7 text-secondary">Database Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="dbName" name="name" value="<?= htmlspecialchars($savedDb['name'], ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. support_mgt_db">
                <div class="form-text fs-8 text-muted">The name of the database where tables will be created.</div>
            </div>

            <div class="col-12 col-md-6">
                <label for="dbUser" class="form-label fw-semibold fs-7 text-secondary">Database Username <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="dbUser" name="user" value="<?= htmlspecialchars($savedDb['user'], ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. root or cpanel_user">
            </div>
            <div class="col-12 col-md-6">
                <label for="dbPass" class="form-label fw-semibold fs-7 text-secondary">Database Password</label>
                <input type="password" class="form-control" id="dbPass" name="pass" value="<?= htmlspecialchars($savedDb['pass'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter password (leave empty if none)">
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 bg-light border rounded mb-4">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-primary fs-4"></i>
                <div>
                    <div class="fw-semibold text-dark fs-7">Verify Database Connection</div>
                    <div class="text-muted fs-8">Test connection before moving to SQL import.</div>
                </div>
            </div>
            <button type="button" id="btnTestDb" class="btn btn-outline-primary btn-sm px-3 fw-semibold">
                <i class="bi bi-lightning-charge me-1"></i> Test Connection
            </button>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <a href="?step=requirements" class="btn btn-outline-secondary px-3">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <button type="submit" id="btnSaveDb" class="btn btn-primary px-4 fw-semibold">
                Continue to SQL Import <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnTest = document.getElementById('btnTestDb');
    const alertBox = document.getElementById('dbAlertPlaceholder');

    btnTest.addEventListener('click', function() {
        btnTest.disabled = true;
        btnTest.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Testing...';
        alertBox.innerHTML = '';

        const formData = new FormData(document.getElementById('dbConfigForm'));

        fetch('process.php?action=test_db', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            btnTest.disabled = false;
            btnTest.innerHTML = '<i class="bi bi-lightning-charge me-1"></i> Test Connection';

            if (data.success) {
                let extraWarning = '';
                if (data.table_count > 0) {
                    extraWarning = `<div class="mt-2 fs-8 text-warning-emphasis">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> 
                        <strong>Notice:</strong> This database currently contains <strong>${data.table_count}</strong> existing table(s). Installing may conflict with existing data. A fresh empty database is recommended.
                    </div>`;
                }
                alertBox.innerHTML = `
                    <div class="alert alert-success d-flex flex-column mb-4" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                            <strong>${data.message}</strong>
                        </div>
                        ${extraWarning}
                    </div>
                `;
            } else {
                alertBox.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                        <div>${data.message}</div>
                    </div>
                `;
            }
        })
        .catch(err => {
            btnTest.disabled = false;
            btnTest.innerHTML = '<i class="bi bi-lightning-charge me-1"></i> Test Connection';
            alertBox.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                    <div>An error occurred while testing the database connection. Please verify credentials.</div>
                </div>
            `;
        });
    });
});
</script>
