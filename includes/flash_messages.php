<?php
/**
 * Flash Messages Component
 * Renders session flash messages as dismissible Bootstrap alerts
 */

require_once __DIR__ . '/functions.php';

$flashes = get_flashes();

if (!empty($flashes)):
?>
<div class="flash-messages-container mb-3">
    <?php foreach ($flashes as $flash): 
        $type = $flash['type'] ?? 'info';
        // Normalize alert classes
        if ($type === 'error') $type = 'danger';

        $icon = 'bi-info-circle-fill';
        if ($type === 'success') $icon = 'bi-check-circle-fill';
        if ($type === 'danger')  $icon = 'bi-exclamation-triangle-fill';
        if ($type === 'warning') $icon = 'bi-exclamation-circle-fill';
    ?>
    <div class="alert alert-<?= e($type); ?> alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi <?= $icon; ?> me-2 flex-shrink-0 fs-5"></i>
        <div class="flex-grow-1">
            <?= e($flash['message']); ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
