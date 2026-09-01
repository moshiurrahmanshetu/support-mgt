<?php
/**
 * Master Installation Wizard
 * Customer Support Management System (support-mgt)
 */

require_once __DIR__ . '/functions.php';

$appUrl = detect_app_url();
$isInstalled = is_system_installed();
$step = trim($_GET['step'] ?? 'welcome');

$validSteps = ['welcome', 'requirements', 'database', 'sql_import', 'admin', 'complete'];
if (!in_array($step, $validSteps, true)) {
    $step = 'welcome';
}

// If already installed and trying to access any step other than complete, show locked screen
if ($isInstalled && $step !== 'complete') {
    $step = 'locked';
}

$stepNumbers = [
    'welcome'      => 1,
    'requirements' => 2,
    'database'     => 3,
    'sql_import'   => 4,
    'admin'        => 5,
    'complete'     => 6
];
$currentStepNum = $stepNumbers[$step] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Wizard - SupportDesk CMS</title>
    
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bs-primary: #0d6efd;
            --bs-primary-dark: #0b5ed7;
            --app-bg: #f8fafc;
            --card-border: #e2e8f0;
        }

        body {
            background-color: var(--app-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #334155;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .installer-container {
            max-width: 780px;
            margin: auto;
            width: 100%;
            padding: 2rem 1rem;
        }

        .installer-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .step-progress-bar {
            display: flex;
            border-bottom: 1px solid var(--card-border);
            background: #ffffff;
            padding: 1rem 1.5rem;
            gap: 0.5rem;
            overflow-x: auto;
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #94a3b8;
            font-weight: 500;
            white-space: nowrap;
        }

        .step-item.active {
            color: var(--bs-primary);
            font-weight: 600;
        }

        .step-item.completed {
            color: #10b981;
        }

        .step-number {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            background: #f1f5f9;
            color: #64748b;
        }

        .step-item.active .step-number {
            background: var(--bs-primary);
            color: #ffffff;
        }

        .step-item.completed .step-number {
            background: #10b981;
            color: #ffffff;
        }

        .step-divider {
            color: #cbd5e1;
            display: inline-flex;
            align-items: center;
        }

        .fs-7 { font-size: 0.875rem; }
        .fs-8 { font-size: 0.75rem; }
    </style>
</head>
<body>

<div class="installer-container my-auto">
    <!-- Header Brand -->
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center gap-2">
            <i class="bi bi-headset fs-2 text-primary"></i>
            <span class="fs-4 fw-bold text-dark tracking-tight">SupportDesk CMS</span>
        </div>
        <div class="text-muted small">Installation &amp; Setup Wizard &bull; v1.1.0</div>
    </div>

    <!-- Installer Card -->
    <div class="installer-card">
        <?php if ($step !== 'locked'): ?>
            <!-- Step Progress Indicator -->
            <div class="step-progress-bar">
                <?php
                $stepLabels = [
                    'welcome'      => 'Welcome',
                    'requirements' => 'Requirements',
                    'database'     => 'Database',
                    'sql_import'   => 'Schema',
                    'admin'        => 'Admin Setup',
                    'complete'     => 'Complete'
                ];
                $idx = 1;
                $totalSteps = count($stepLabels);
                foreach ($stepLabels as $sKey => $sLabel):
                    $isDone = $idx < $currentStepNum;
                    $isCurrent = $idx === $currentStepNum;
                    $cls = $isDone ? 'completed' : ($isCurrent ? 'active' : '');
                ?>
                    <div class="step-item <?= $cls; ?>">
                        <span class="step-number">
                            <?php if ($isDone): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <?= $idx; ?>
                            <?php endif; ?>
                        </span>
                        <span class="d-none d-sm-inline"><?= $sLabel; ?></span>
                    </div>
                    <?php if ($idx < $totalSteps): ?>
                        <span class="step-divider"><i class="bi bi-chevron-right fs-8"></i></span>
                    <?php endif; ?>
                <?php 
                $idx++;
                endforeach; 
                ?>
            </div>
        <?php endif; ?>

        <!-- Step Content Area -->
        <div class="p-4 p-md-5">
            <?php
            if ($step === 'locked') {
                ?>
                <div class="text-center py-4">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center bg-secondary text-white rounded-circle" style="width: 72px; height: 72px;">
                            <i class="bi bi-lock-fill fs-1"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-2">Application Already Installed</h3>
                    <p class="text-muted mb-4 px-md-4">
                        SupportDesk CMS is already installed on this server. For security protection, the installation wizard has been permanently disabled.
                    </p>

                    <div class="alert alert-info text-start small d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 text-info"></i>
                        <div>
                            To access your support portal, proceed to the login page. If you are a developer intending to perform a fresh reinstallation, please refer to the reinstallation instructions in <code>README.md</code>.
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-3">
                        <a href="<?= htmlspecialchars($appUrl . '/auth/login.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary px-4 fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Go to Login
                        </a>
                        <a href="<?= htmlspecialchars($appUrl . '/', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary px-4">
                            Open Application
                        </a>
                    </div>
                </div>
                <?php
            } else {
                $stepFile = __DIR__ . '/steps/' . $step . '.php';
                if (file_exists($stepFile)) {
                    include $stepFile;
                } else {
                    include __DIR__ . '/steps/welcome.php';
                }
            }
            ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center mt-3 text-muted small">
        &copy; <?= date('Y'); ?> SupportDesk Management System. All rights reserved.
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>
