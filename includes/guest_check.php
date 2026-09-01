<?php
/**
 * Guest Check Guard
 * Ensures only unauthenticated users can access login, register, forgot password, etc.
 */

require_once __DIR__ . '/auth_check.php';

function require_guest(): void {
    if (is_logged_in()) {
        redirect('index.php');
    }
}
