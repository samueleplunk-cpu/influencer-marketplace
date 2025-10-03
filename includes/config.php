<?php
// includes/config.php - VERSIONE CORRETTA

// === PERCORSO BASE ASSOLUTO === 
$base_dir = dirname(__DIR__); 
define('BASE_DIR', $base_dir);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'influencer_marketplace');
define('DB_USER', 'sam');
define('DB_PASS', 'A6Hd&Q%plvx4lxp7');

// Error reporting - ESSENZIALE
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// === SESSION CONFIGURATION - DEVE ESSERE PRIMA DI session_start() ===
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start session - SOLO SE NON È GIÀ ATTIVA
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection - PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    if (ini_get('display_errors')) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection error. Please try again later.");
    }
}

// Path constants
define('ROOT_PATH', BASE_DIR);
define('INCLUDES_PATH', BASE_DIR . '/includes');
define('BASE_URL', '/infl');

// Flag per evitare inclusioni multiple
$config_loaded = true;

// Includi funzioni di autenticazione
$auth_functions_file = __DIR__ . '/auth_functions.php';
if (file_exists($auth_functions_file)) {
    require_once $auth_functions_file;
} else {
    error_log("Auth functions file not found: " . $auth_functions_file);
}
?>