<?php
// includes/config.php - VERSIONE COMPLETA CON RECUPERO PASSWORD

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

// === INCLUSIONE FUNZIONI DI AUTENTICAZIONE ===
$auth_functions_file = __DIR__ . '/auth_functions.php';
if (file_exists($auth_functions_file)) {
    require_once $auth_functions_file;
} else {
    error_log("Auth functions file not found: " . $auth_functions_file);
    // Definisci funzioni base se il file non esiste
    if (!function_exists('is_logged_in')) {
        function is_logged_in() {
            return isset($_SESSION['user_id']);
        }
    }
    if (!function_exists('is_influencer')) {
        function is_influencer() {
            return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer';
        }
    }
    if (!function_exists('login_user')) {
        function login_user($user_id, $user_type, $name) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_name'] = $name;
            $_SESSION['login_time'] = time();
        }
    }
}

// === INCLUSIONE FUNZIONI EMAIL ===
$email_functions_file = __DIR__ . '/email_functions.php';
if (file_exists($email_functions_file)) {
    require_once $email_functions_file;
} else {
    error_log("Email functions file not found: " . $email_functions_file);
    // Definisci funzioni base se il file non esiste
    if (!function_exists('send_password_reset_email')) {
        function send_password_reset_email($email, $reset_link) {
            // Implementazione base per sviluppo
            $subject = "Recupero Password - Influencer Marketplace";
            $message = "Clicca qui per reimpostare la password: $reset_link";
            $headers = "From: no-reply@influencer-marketplace.com";
            
            // Per sviluppo, logghiamo il link invece di inviare email
            error_log("Password reset link for $email: $reset_link");
            return true; // Simula invio riuscito
        }
    }
    if (!function_exists('send_password_changed_email')) {
        function send_password_changed_email($email) {
            // Implementazione base per sviluppo
            error_log("Password changed notification for: $email");
            return true; // Simula invio riuscito
        }
    }
}

// === FUNZIONI UTILITY AGGIUNTIVE ===
if (!function_exists('generate_secure_token')) {
    function generate_secure_token($length = 50) {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists('validate_token')) {
    function validate_token($pdo, $token) {
        try {
            $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reset_request) {
                return ['valid' => false, 'error' => 'Token non valido'];
            }
            
            if ($reset_request['used'] == 1) {
                return ['valid' => false, 'error' => 'Token già utilizzato'];
            }
            
            if (strtotime($reset_request['expires_at']) < time()) {
                return ['valid' => false, 'error' => 'Token scaduto'];
            }
            
            return [
                'valid' => true, 
                'email' => $reset_request['email'],
                'expires_at' => $reset_request['expires_at']
            ];
            
        } catch (PDOException $e) {
            return ['valid' => false, 'error' => 'Errore di sistema: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('cleanup_expired_tokens')) {
    function cleanup_expired_tokens($pdo) {
        try {
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Error cleaning up expired tokens: " . $e->getMessage());
            return 0;
        }
    }
}

// Pulizia automatica dei token scaduti (1 volta su 100 per performance)
if (mt_rand(1, 100) === 1) {
    cleanup_expired_tokens($pdo);
}
?>