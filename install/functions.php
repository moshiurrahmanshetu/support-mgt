<?php
/**
 * Installation Wizard Helper Functions
 * Customer Support Management System (support-mgt)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('support_mgt_installer_session');
    session_start();
}

/**
 * Check if the application has already been installed
 */
function is_system_installed(): bool {
    $lockFile = __DIR__ . '/../config/installed.lock';
    return file_exists($lockFile);
}

/**
 * Auto-detect Base URL of the application
 */
function detect_app_url(): string {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // If inside /install, go up one directory
    if (basename($scriptDir) === 'install') {
        $baseDir = dirname($scriptDir);
    } else {
        $baseDir = $scriptDir;
    }

    $cleanBaseDir = trim(str_replace('\\', '/', $baseDir), '/');
    return $scheme . '://' . $host . (!empty($cleanBaseDir) ? '/' . $cleanBaseDir : '');
}

/**
 * Generate CSRF Token for installer forms
 */
function installer_csrf_token(): string {
    if (empty($_SESSION['installer_csrf'])) {
        $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['installer_csrf'];
}

/**
 * Output CSRF hidden input field
 */
function installer_csrf_field(): string {
    return '<input type="hidden" name="installer_csrf" value="' . htmlspecialchars(installer_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify installer CSRF token
 */
function installer_verify_csrf(): bool {
    $token = $_POST['installer_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($token) && !empty($_SESSION['installer_csrf']) && hash_equals($_SESSION['installer_csrf'], $token);
}

/**
 * Validate System Requirements and Writable Directories
 */
function check_system_requirements(): array {
    $requirements = [
        'php_version' => [
            'name'        => 'PHP Version (>= 8.1.0)',
            'current'     => PHP_VERSION,
            'passed'      => version_compare(PHP_VERSION, '8.1.0', '>='),
            'critical'    => true,
            'description' => 'PHP 8.1 or higher is required for typed properties and modern security algorithms.'
        ],
        'pdo' => [
            'name'        => 'PDO Extension',
            'current'     => extension_loaded('pdo') ? 'Enabled' : 'Disabled',
            'passed'      => extension_loaded('pdo'),
            'critical'    => true,
            'description' => 'Required for secure prepared statements and database abstraction.'
        ],
        'pdo_mysql' => [
            'name'        => 'PDO MySQL Driver',
            'current'     => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
            'passed'      => extension_loaded('pdo_mysql'),
            'critical'    => true,
            'description' => 'Required for connecting to the MySQL/MariaDB database.'
        ],
        'json' => [
            'name'        => 'JSON Extension',
            'current'     => extension_loaded('json') ? 'Enabled' : 'Disabled',
            'passed'      => extension_loaded('json'),
            'critical'    => true,
            'description' => 'Required for JSON payloads and API responses.'
        ],
        'session' => [
            'name'        => 'Session Support',
            'current'     => extension_loaded('session') ? 'Enabled' : 'Disabled',
            'passed'      => extension_loaded('session'),
            'critical'    => true,
            'description' => 'Required for user authentication and state management.'
        ],
        'fileinfo' => [
            'name'        => 'Fileinfo Extension',
            'current'     => extension_loaded('fileinfo') ? 'Enabled' : 'Disabled',
            'passed'      => extension_loaded('fileinfo'),
            'critical'    => false,
            'description' => 'Used for secure MIME-type validation during file uploads.'
        ],
        'mbstring' => [
            'name'        => 'Mbstring Extension',
            'current'     => extension_loaded('mbstring') ? 'Enabled' : 'Disabled',
            'passed'      => extension_loaded('mbstring'),
            'critical'    => false,
            'description' => 'Used for multi-byte UTF-8 string operations.'
        ]
    ];

    // Check writable directories
    $configDir = __DIR__ . '/../config';
    $uploadsDir = __DIR__ . '/../uploads';
    $avatarsDir = __DIR__ . '/../uploads/avatars';
    $ticketsDir = __DIR__ . '/../uploads/tickets';

    // Create directories if missing
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }
    if (!is_dir($avatarsDir)) {
        @mkdir($avatarsDir, 0755, true);
    }
    if (!is_dir($ticketsDir)) {
        @mkdir($ticketsDir, 0755, true);
    }

    $directories = [
        'config_dir' => [
            'name'        => 'config/ Directory Writable',
            'path'        => 'config/',
            'passed'      => is_writable($configDir),
            'critical'    => true,
            'description' => 'Required to write config.php and installed.lock files.'
        ],
        'uploads_dir' => [
            'name'        => 'uploads/ Directory Writable',
            'path'        => 'uploads/',
            'passed'      => is_writable($uploadsDir),
            'critical'    => true,
            'description' => 'Required for storing avatars and ticket attachments.'
        ]
    ];

    $allPassed = true;
    foreach ($requirements as $req) {
        if ($req['critical'] && !$req['passed']) {
            $allPassed = false;
            break;
        }
    }
    if ($allPassed) {
        foreach ($directories as $dir) {
            if ($dir['critical'] && !$dir['passed']) {
                $allPassed = false;
                break;
            }
        }
    }

    return [
        'all_passed'   => $allPassed,
        'requirements' => $requirements,
        'directories'  => $directories
    ];
}

/**
 * Test Database Connection via PDO
 */
function test_db_connection(array $config): array {
    $host = trim($config['host'] ?? '127.0.0.1');
    $port = (int)($config['port'] ?? 3306);
    $name = trim($config['name'] ?? '');
    $user = trim($config['user'] ?? 'root');
    $pass = $config['pass'] ?? '';

    if (empty($host) || empty($name) || empty($user)) {
        return [
            'success' => false,
            'message' => 'Please provide the Database Host, Database Name, and Username.'
        ];
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);

        // Check if database already has tables
        $tableCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($name))->fetchColumn();

        return [
            'success'     => true,
            'message'     => 'Database connection successful!',
            'table_count' => $tableCount
        ];
    } catch (PDOException $e) {
        $errorCode = $e->getCode();
        $msg = 'Unable to connect to MySQL database. Please verify your host, port, credentials, and ensure the database exists.';
        
        if ($errorCode == 1045) {
            $msg = 'Access denied: Invalid database username or password.';
        } elseif ($errorCode == 1049) {
            $msg = 'Database "' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" not found. Please create it first in your MySQL server or cPanel.';
        } elseif ($errorCode == 2002) {
            $msg = 'Could not connect to database host "' . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . '". Check your hostname and port.';
        }

        return [
            'success' => false,
            'message' => $msg
        ];
    }
}

/**
 * Execute an SQL schema file statement by statement
 */
function execute_sql_file(PDO $pdo, string $filePath): array {
    if (!file_exists($filePath)) {
        return [
            'success' => false,
            'message' => 'SQL schema installation file not found: ' . basename($filePath)
        ];
    }

    $sqlContent = file_get_contents($filePath);
    if ($sqlContent === false || trim($sqlContent) === '') {
        return [
            'success' => false,
            'message' => 'SQL schema file is empty or could not be read.'
        ];
    }

    // Split SQL content into separate statements
    $statements = split_sql_statements($sqlContent);
    $executed = 0;

    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        foreach ($statements as $stmt) {
            $trimmed = trim($stmt);
            if (!empty($trimmed)) {
                $pdo->exec($trimmed);
                $executed++;
            }
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        return [
            'success'          => true,
            'message'          => "Successfully executed {$executed} SQL statements.",
            'statements_count' => $executed
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database import error: ' . $e->getMessage()
        ];
    }
}

/**
 * Robust SQL Statement Splitter
 */
function split_sql_statements(string $sql): array {
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $sql));
    $cleanSql = '';
    
    // Remove line comments and preserve structure
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }
        $cleanSql .= $line . "\n";
    }

    // Split by semicolon outside quotes
    $tokens = preg_split('/;\s*(\n|$)/', $cleanSql);
    $statements = [];
    foreach ($tokens as $token) {
        $t = trim($token);
        if (!empty($t)) {
            $statements[] = $t;
        }
    }

    return $statements;
}

/**
 * Generate config/config.php file
 */
function write_app_config(array $dbConfig, string $appUrl): bool {
    $configFile = __DIR__ . '/../config/config.php';

    $appName = 'SupportDesk';
    $appVersion = '1.1.0';
    $dbHost = addcslashes($dbConfig['host'] ?? '127.0.0.1', "'\\");
    $dbPort = (int)($dbConfig['port'] ?? 3306);
    $dbName = addcslashes($dbConfig['name'] ?? 'support_mgt_db', "'\\");
    $dbUser = addcslashes($dbConfig['user'] ?? 'root', "'\\");
    $dbPass = addcslashes($dbConfig['pass'] ?? '', "'\\");
    $cleanAppUrl = rtrim($appUrl, '/');

    $template = "<?php\n"
        . "/**\n"
        . " * Application Configuration\n"
        . " * Customer Support Management System (support-mgt)\n"
        . " * Generated by Installation Wizard on " . date('Y-m-d H:i:s') . "\n"
        . " */\n\n"
        . "// Application Info\n"
        . "define('APP_NAME', '" . $appName . "');\n"
        . "define('APP_VERSION', '" . $appVersion . "');\n"
        . "define('APP_URL', '" . $cleanAppUrl . "');\n\n"
        . "// Database Configuration\n"
        . "define('DB_HOST', '" . $dbHost . "');\n"
        . "define('DB_PORT', '" . $dbPort . "');\n"
        . "define('DB_NAME', '" . $dbName . "');\n"
        . "define('DB_USER', '" . $dbUser . "');\n"
        . "define('DB_PASS', '" . $dbPass . "');\n"
        . "define('DB_CHARSET', 'utf8mb4');\n\n"
        . "// Session Configuration\n"
        . "define('SESSION_NAME', 'support_mgt_session');\n"
        . "define('SESSION_LIFETIME', 86400); // 1 day\n\n"
        . "// Upload Paths\n"
        . "define('UPLOAD_DIR', __DIR__ . '/../uploads');\n"
        . "define('AVATAR_UPLOAD_DIR', __DIR__ . '/../uploads/avatars');\n"
        . "define('AVATAR_URL_PATH', APP_URL . '/uploads/avatars');\n\n"
        . "define('TICKET_UPLOAD_DIR', __DIR__ . '/../uploads/tickets');\n"
        . "define('TICKET_URL_PATH', APP_URL . '/uploads/tickets');\n\n"
        . "// Error Reporting (Turn off display_errors in production)\n"
        . "ini_set('display_errors', '0');\n"
        . "ini_set('display_startup_errors', '0');\n"
        . "error_reporting(E_ALL);\n\n"
        . "// Timezone\n"
        . "date_default_timezone_set('UTC');\n";

    return (bool)file_put_contents($configFile, $template, LOCK_EX);
}

/**
 * Create installation lock file
 */
function create_installation_lock(string $adminEmail): bool {
    $lockFile = __DIR__ . '/../config/installed.lock';
    $data = [
        'installed'    => true,
        'installed_at' => date('Y-m-d H:i:s'),
        'version'      => '1.1.0',
        'admin_email'  => $adminEmail
    ];
    return (bool)file_put_contents($lockFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}
