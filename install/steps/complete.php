<?php
/**
 * Step 6: Installation Complete
 */
$adminEmail = $_SESSION['installed_admin_email'] ?? 'Administrator';
$appUrl = detect_app_url();
$loginUrl = $appUrl . '/auth/login.php';
?>
<div class="text-center py-4">
    <div class="mb-3">
        <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle" style="width: 72px; height: 72px;">
            <i class="bi bi-check-lg fs-1"></i>
        </div>
    </div>
    <h3 class="fw-bold text-dark mb-2">Installation Completed Successfully!</h3>
    <p class="text-muted mb-4 px-md-4">
        SupportDesk CMS has been successfully installed and configured on your server. Your database schema is ready, and your primary Administrator account has been provisioned.
    </p>

    <div class="card border bg-light text-start mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle me-1 text-primary"></i> Summary of Installation</h6>
            
            <div class="row g-2 fs-7">
                <div class="col-sm-4 text-muted">Application URL:</div>
                <div class="col-sm-8 font-monospace"><a href="<?= htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?= htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8'); ?></a></div>

                <div class="col-sm-4 text-muted">Login Portal URL:</div>
                <div class="col-sm-8 font-monospace"><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?></a></div>

                <div class="col-sm-4 text-muted">Administrator Email:</div>
                <div class="col-sm-8 fw-semibold"><?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8'); ?></div>

                <div class="col-sm-4 text-muted">Security Lock:</div>
                <div class="col-sm-8 text-success"><i class="bi bi-lock-fill me-1"></i> <code>config/installed.lock</code> is active</div>
            </div>
        </div>
    </div>

    <div class="alert alert-warning text-start small d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="bi bi-shield-exclamation fs-5 flex-shrink-0 text-warning"></i>
        <div>
            <strong>Security Notice:</strong> The installation wizard has been permanently locked. For security reasons, you may also remove the <code>install/</code> folder from your server if desired.
        </div>
    </div>

    <div class="d-flex justify-content-center gap-3 pt-2">
        <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg px-4 fw-semibold">
            <i class="bi bi-box-arrow-in-right me-1"></i> Go to Login
        </a>
    </div>
</div>
