<?php
/**
 * Step 1: Welcome & System Overview
 */
?>
<div class="text-center py-4">
    <div class="mb-3">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle" style="width: 72px; height: 72px;">
            <i class="bi bi-headset fs-1"></i>
        </div>
    </div>
    <h3 class="fw-bold text-dark mb-2">Welcome to Support Management System</h3>
    <p class="text-muted mb-4 px-md-4">
        Thank you for purchasing <strong>SupportDesk CMS</strong>. This guided installation wizard will verify your server requirements, configure your database connection, import system tables, and set up your primary Administrator account.
    </p>

    <div class="row g-3 text-start mb-4">
        <div class="col-12 col-md-4">
            <div class="p-3 border rounded bg-light h-100">
                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold">
                    <i class="bi bi-check2-circle fs-5"></i> 1. Requirements
                </div>
                <div class="small text-muted">Verify PHP version, extensions, and directory write permissions.</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="p-3 border rounded bg-light h-100">
                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold">
                    <i class="bi bi-database-check fs-5"></i> 2. Database & SQL
                </div>
                <div class="small text-muted">Connect to your MySQL database and import default application schema.</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="p-3 border rounded bg-light h-100">
                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold">
                    <i class="bi bi-person-badge fs-5"></i> 3. Admin Account
                </div>
                <div class="small text-muted">Create your secure superadmin credentials to manage the platform.</div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
        <a href="?step=requirements" class="btn btn-primary px-4 py-2 fw-semibold">
            Start Installation <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
</div>
