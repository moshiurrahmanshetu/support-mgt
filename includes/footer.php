<?php
/**
 * Master Footer Include
 */

$isAuthLayout = isset($isAuthLayout) && $isAuthLayout === true;
?>
<?php if (!$isAuthLayout): ?>
        </main>

        <!-- App Footer -->
        <footer class="app-footer">
            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
                <div>
                    &copy; <?= date('Y'); ?> <strong><?= e(APP_NAME); ?></strong>. All rights reserved.
                </div>
                <div class="text-muted small">
                    Version <?= e(APP_VERSION); ?>
                </div>
            </div>
        </footer>
    </div> <!-- /app-main -->
</div> <!-- /app-wrapper -->
<?php endif; ?>

<!-- Bootstrap 5 Bundle JS (Local with CDN fallback) -->
<script src="<?= url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'); ?>" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';"></script>

<!-- Application Master JS -->
<script src="<?= url('assets/js/main.js'); ?>"></script>

</body>
</html>
