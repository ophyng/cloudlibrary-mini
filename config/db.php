<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('BASE_URL')) {
    if (getenv('RENDER')) {
        define('BASE_URL', '');
    } else {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $parts  = explode('/', trim($script, '/'));
        define('BASE_URL', '/' . $parts[0]);
    }
}
$host = getenv('DB_HOST') ?: 'localhost';
$name = getenv('DB_NAME') ?: 'cloudlibrary_mini';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '3306';
$char = 'utf8mb4';
define('DB_HOST', $host);
define('DB_NAME', $name);
define('DB_USER', $user);
define('DB_PASS', $pass);
define('DB_CHAR', $char);
$dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$char}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    PDO::MYSQL_ATTR_SSL_CA => '',
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die("Koneksi database gagal: " . $e->getMessage());
}
