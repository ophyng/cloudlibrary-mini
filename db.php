<?php
// ============================================
//  CloudLibrary Mini — Database Configuration
//  File   : config/db.php
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $parts  = explode('/', trim($script, '/'));
    define('BASE_URL', '/' . $parts[0]);
}

define('DB_HOST', 'sql104.infinityfree.com');
define('DB_NAME', 'if0_41979848_cloudlibrary');
define('DB_USER', 'if0_41979848');
define('DB_PASS', 'XGupKwsGzLp');
define('DB_CHAR', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die("Koneksi database gagal: " . $e->getMessage());
}