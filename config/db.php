<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Render / Railway = app jalan di root domain → BASE_URL kosong
$isHosted = getenv('RENDER') || getenv('RAILWAY_ENVIRONMENT') || getenv('RAILWAY_PROJECT_ID');

if (!defined('BASE_URL')) {
    if ($isHosted) {
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
];

// TiDB Cloud Serverless WAJIB SSL. Localhost (XAMPP) ga usah, biar lokal tetep jalan.
if ($host !== 'localhost' && $host !== '127.0.0.1') {
    $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die("Koneksi database gagal: " . $e->getMessage());
}
