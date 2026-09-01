<?php
/**
 * Step 5: Administrator Account Setup
 */
$detectedUrl = detect_app_url();
?>
<div>
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1">Administrator Account Setup</h4>
        <p class="text-muted small">Create the primary superadministrator account. This account will have full access to manage users, tickets, roles, and system settings.</p>
    </div>

    <!-- Alert Placeholder -->
    <div id="adminAlertPlaceholder"></div>

    <form id="adminSetupForm" action="process.php?action=create_admin" method="POST">
        <?= installer_csrf_field(); ?>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <label for="adminName" class="form-label fw-semibold fs-7 text-secondary">Full Name <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="adminName" name="name" required placeholder="e.g. John Doe" value="System Administrator">
                </div>
            </div>

            <div class="col-12">
                <label for="adminEmail" class="form-label fw-semibold fs-7 text-secondary">Email Address <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="adminEmail" name="email" required placeholder="admin@yourdomain.com">
                </div>
                <div class="form-text fs-8 text-muted">This email address will be used to log in to the administration portal.</div>
            </div>

            <div class="col-12 col-md-6">
                <label for="adminPass" class="form-label fw-semibold fs-7 text-secondary">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="adminPass" name="password" required minlength="8" placeholder="Minimum 8 characters">
                </div>
            </div>

            <div class="col-12 col-md-6">
                <label for="adminPassConfirm" class="form-label fw-semibold fs-7 text-secondary">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" id="adminPassConfirm" name="password_confirmation" required minlength="8" placeholder="Re-enter password">
                </div>
            </div>

            <div class="col-12">
                <label for="appUrl" class="form-label fw-semibold fs-7 text-secondary">Application URL <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-globe"></i></span>
                    <input type="url" class="form-control" id="appUrl" name="app_url" required value="<?= htmlspecialchars($detectedUrl, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-text fs-8 text-muted">The root URL where this application is hosted. Auto-detected from your current environment.</div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <a href="?step=sql_import" class="btn btn-outline-secondary px-3">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <button type="submit" id="btnCreateAdmin" class="btn btn-primary px-4 fw-semibold">
                <i class="bi bi-check2-circle me-1"></i> Finalize Installation
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('adminSetupForm');
    const btnSubmit = document.getElementById('btnCreateAdmin');
    const alertBox = document.getElementById('adminAlertPlaceholder');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const pass = document.getElementById('adminPass').value;
        const passConfirm = document.getElementById('adminPassConfirm').value;

        if (pass.length < 8) {
            alertBox.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                    <div>Password must be at least 8 characters in length.</div>
                </div>
            `;
            return;
        }

        if (pass !== passConfirm) {
            alertBox.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                    <div>Passwords do not match. Please re-enter confirmation password.</div>
                </div>
            `;
            return;
        }

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Finalizing Installation...';
        alertBox.innerHTML = '';

        const formData = new FormData(form);

        fetch('process.php?action=create_admin', {
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
                        <div>${data.message} Redirecting...</div>
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = '?step=complete';
                }, 800);
            } else {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Finalize Installation';
                alertBox.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                        <div>${data.message}</div>
                    </div>
                `;
            }
        })
        .catch(err => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Finalize Installation';
            alertBox.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                    <div>An error occurred while creating the Administrator account. Please check your inputs.</div>
                </div>
            `;
        });
    });
});
</script>
