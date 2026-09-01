<?php
/**
 * Step 4: SQL Schema Import
 */
$defaultSqlPath = __DIR__ . '/../../database/install.sql';
$defaultSqlExists = file_exists($defaultSqlPath);
$defaultSqlSize = $defaultSqlExists ? round(filesize($defaultSqlPath) / 1024, 1) : 0;
?>
<div>
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1">Database Schema Import</h4>
        <p class="text-muted small">Choose the SQL schema file to create the necessary tables, indexes, system roles, and permissions.</p>
    </div>

    <!-- Alert Placeholder -->
    <div id="importAlertPlaceholder"></div>

    <form id="sqlImportForm" action="process.php?action=import_sql" method="POST" enctype="multipart/form-data">
        <?= installer_csrf_field(); ?>

        <div class="card border mb-4">
            <div class="card-body p-3">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="sql_source" id="sqlDefault" value="default" checked>
                    <label class="form-check-label fw-semibold text-dark" for="sqlDefault">
                        Use included default schema (<span class="text-primary font-monospace">database/install.sql</span>) <span class="badge bg-primary ms-1">Recommended</span>
                    </label>
                    <div class="form-text fs-8 text-muted ms-4">
                        <?php if ($defaultSqlExists): ?>
                            <span class="text-success"><i class="bi bi-check-circle me-1"></i> Ready to import (<?= $defaultSqlSize; ?> KB, includes all 21 tables & default system roles)</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> File not found at <code>database/install.sql</code>.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="my-3">

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sql_source" id="sqlCustom" value="custom">
                    <label class="form-check-label fw-semibold text-dark" for="sqlCustom">
                        Upload custom schema file (<span class="font-monospace">.sql</span>)
                    </label>
                    <div class="form-text fs-8 text-muted ms-4 mb-2">
                        Advanced option: Upload your own database schema file for customized installations.
                    </div>
                    <div id="customSqlWrapper" class="ms-4 mt-2" style="display: none;">
                        <input type="file" class="form-control" id="customSqlFile" name="custom_sql" accept=".sql">
                        <div class="form-text fs-8 text-muted">Maximum allowed file size: 10MB (.sql only).</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <a href="?step=database" class="btn btn-outline-secondary px-3">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <button type="submit" id="btnImportSql" class="btn btn-primary px-4 fw-semibold">
                <i class="bi bi-cloud-arrow-up me-1"></i> Import Schema & Continue
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radioDefault = document.getElementById('sqlDefault');
    const radioCustom = document.getElementById('sqlCustom');
    const customWrapper = document.getElementById('customSqlWrapper');
    const btnImport = document.getElementById('btnImportSql');
    const alertBox = document.getElementById('importAlertPlaceholder');
    const form = document.getElementById('sqlImportForm');

    function toggleCustomUpload() {
        if (radioCustom.checked) {
            customWrapper.style.display = 'block';
        } else {
            customWrapper.style.display = 'none';
        }
    }

    radioDefault.addEventListener('change', toggleCustomUpload);
    radioCustom.addEventListener('change', toggleCustomUpload);

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        btnImport.disabled = true;
        btnImport.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Importing Tables...';
        alertBox.innerHTML = '';

        const formData = new FormData(form);

        fetch('process.php?action=import_sql', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alertBox.innerHTML = `
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <div>${data.message} Redirecting to Administrator setup...</div>
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = '?step=admin';
                }, 800);
            } else {
                btnImport.disabled = false;
                btnImport.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Import Schema & Continue';
                alertBox.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                        <div>${data.message}</div>
                    </div>
                `;
            }
        })
        .catch(err => {
            btnImport.disabled = false;
            btnImport.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Import Schema & Continue';
            alertBox.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                    <div>An error occurred during database import. Please check your database privileges and retry.</div>
                </div>
            `;
        });
    });
});
</script>
