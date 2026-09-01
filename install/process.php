<?php
/**
 * Installation Wizard Process Endpoint
 * Customer Support Management System (support-mgt)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/functions.php';

// Security: If system is already installed, reject all actions immediately
if (is_system_installed()) {
    echo json_encode([
        'success' => false,
        'message' => 'The application has already been installed. The installer is permanently locked.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

if (!installer_verify_csrf()) {
    echo json_encode([
        'success' => false,
        'message' => 'Security token expired or invalid. Please refresh the page and try again.'
    ]);
    exit;
}

$action = trim($_GET['action'] ?? '');

switch ($action) {
    // ----------------------------------------------------
    // Action 1: Test Database Connection
    // ----------------------------------------------------
    case 'test_db':
        $host = trim($_POST['host'] ?? '127.0.0.1');
        $port = (int)($_POST['port'] ?? 3306);
        $name = trim($_POST['name'] ?? '');
        $user = trim($_POST['user'] ?? 'root');
        $pass = $_POST['pass'] ?? '';

        $res = test_db_connection([
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'pass' => $pass
        ]);

        echo json_encode($res);
        exit;

    // ----------------------------------------------------
    // Action 2: Save DB Configuration & Advance to SQL Import
    // ----------------------------------------------------
    case 'save_db':
        $host = trim($_POST['host'] ?? '127.0.0.1');
        $port = (int)($_POST['port'] ?? 3306);
        $name = trim($_POST['name'] ?? '');
        $user = trim($_POST['user'] ?? 'root');
        $pass = $_POST['pass'] ?? '';

        $testRes = test_db_connection([
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'pass' => $pass
        ]);

        if (!$testRes['success']) {
            $_SESSION['installer_flash_error'] = $testRes['message'];
            header('Location: index.php?step=database');
            exit;
        }

        $_SESSION['installer_db'] = [
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'pass' => $pass
        ];

        header('Location: index.php?step=sql_import');
        exit;

    // ----------------------------------------------------
    // Action 3: Import SQL Schema File
    // ----------------------------------------------------
    case 'import_sql':
        $dbConfig = $_SESSION['installer_db'] ?? null;
        if (!$dbConfig) {
            echo json_encode([
                'success' => false,
                'message' => 'Database configuration session expired. Please return to step 3 and configure your database.'
            ]);
            exit;
        }

        $source = trim($_POST['sql_source'] ?? 'default');
        $sqlPath = __DIR__ . '/../database/install.sql';
        $tempUploadFile = null;

        if ($source === 'custom') {
            if (!isset($_FILES['custom_sql']) || $_FILES['custom_sql']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please choose a valid .sql file to upload.'
                ]);
                exit;
            }

            $uploadedFile = $_FILES['custom_sql'];
            $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

            if ($ext !== 'sql') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid file type. Only files with .sql extension are permitted.'
                ]);
                exit;
            }

            if ($uploadedFile['size'] > 10 * 1024 * 1024) { // 10MB limit
                echo json_encode([
                    'success' => false,
                    'message' => 'Uploaded SQL file size exceeds the 10MB limit.'
                ]);
                exit;
            }

            $sqlPath = $uploadedFile['tmp_name'];
            $tempUploadFile = $sqlPath;
        }

        // Connect to target database
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbConfig['host'], (int)$dbConfig['port'], $dbConfig['name']);
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $importResult = execute_sql_file($pdo, $sqlPath);

            if ($tempUploadFile && file_exists($tempUploadFile)) {
                @unlink($tempUploadFile);
            }

            if ($importResult['success']) {
                $_SESSION['installer_sql_imported'] = true;
                echo json_encode([
                    'success' => true,
                    'message' => 'Database schema imported successfully!'
                ]);
            } else {
                echo json_encode($importResult);
            }
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
        exit;

    // ----------------------------------------------------
    // Action 4: Create Administrator Account & Finalize
    // ----------------------------------------------------
    case 'create_admin':
        $dbConfig = $_SESSION['installer_db'] ?? null;
        if (!$dbConfig) {
            echo json_encode([
                'success' => false,
                'message' => 'Database configuration session not found. Please restart installation.'
            ]);
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $passConfirm = $_POST['password_confirmation'] ?? '';
        $appUrl = trim($_POST['app_url'] ?? detect_app_url());

        if (empty($name) || empty($email) || empty($pass)) {
            echo json_encode([
                'success' => false,
                'message' => 'All fields are required.'
            ]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'Please provide a valid email address.'
            ]);
            exit;
        }

        if (strlen($pass) < 8) {
            echo json_encode([
                'success' => false,
                'message' => 'Password must be at least 8 characters in length.'
            ]);
            exit;
        }

        if ($pass !== $passConfirm) {
            echo json_encode([
                'success' => false,
                'message' => 'Passwords do not match.'
            ]);
            exit;
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbConfig['host'], (int)$dbConfig['port'], $dbConfig['name']);
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Find Administrator role ID
            $rStmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'administrator' LIMIT 1");
            $rStmt->execute();
            $adminRoleId = (int)$rStmt->fetchColumn();

            if (!$adminRoleId) {
                // Fallback: create administrator role if missing
                $insRole = $pdo->prepare("INSERT INTO roles (name, slug, description, status, is_system, created_at, updated_at) VALUES ('Administrator', 'administrator', 'Superuser with unrestricted access', 'active', 1, NOW(), NOW())");
                $insRole->execute();
                $adminRoleId = (int)$pdo->lastInsertId();
            }

            $pdo->beginTransaction();

            $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

            // Check if user with this email already exists
            $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkUser->execute([$email]);
            $existingUserId = $checkUser->fetchColumn();

            if ($existingUserId) {
                // Update existing user
                $uStmt = $pdo->prepare("UPDATE users SET name = ?, password = ?, role = 'admin', status = 'active', updated_at = NOW() WHERE id = ?");
                $uStmt->execute([$name, $hashedPassword, $existingUserId]);
                $adminUserId = (int)$existingUserId;
            } else {
                // Insert new admin user
                $uStmt = $pdo->prepare("INSERT INTO users (role, name, email, password, status, created_at, updated_at) VALUES ('admin', ?, ?, ?, 'active', NOW(), NOW())");
                $uStmt->execute([$name, $email, $hashedPassword]);
                $adminUserId = (int)$pdo->lastInsertId();
            }

            // Assign administrator role in user_roles
            $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$adminUserId]);
            $urStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())");
            $urStmt->execute([$adminUserId, $adminRoleId]);

            // Update app_url and company_email in settings
            $sStmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'app_url'");
            $sStmt->execute([$appUrl]);

            $seStmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_email'");
            $seStmt->execute([$email]);

            $pdo->commit();

            // 1. Write config/config.php
            $configWritten = write_app_config($dbConfig, $appUrl);
            if (!$configWritten) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Unable to write config/config.php. Please ensure the config/ directory has write permissions.'
                ]);
                exit;
            }

            // 2. Create config/installed.lock
            $lockCreated = create_installation_lock($email);
            if (!$lockCreated) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Unable to create config/installed.lock. Please check directory permissions.'
                ]);
                exit;
            }

            // 3. Set completion session & clear sensitive installer state
            $_SESSION['installed_admin_email'] = $email;
            unset($_SESSION['installer_db'], $_SESSION['installer_sql_imported']);

            echo json_encode([
                'success' => true,
                'message' => 'Installation finalized successfully!'
            ]);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create Administrator account: ' . $e->getMessage()
            ]);
        }
        exit;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action specified.'
        ]);
        exit;
}
