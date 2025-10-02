<?php
/**
 * Configurazione Database e Funzioni Base
 * Influencer Marketplace Platform
 */

// Abilita reporting errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurazione Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'influencer_marketplace');
define('DB_USER', 'sam');
define('DB_PASS', 'A6Hd&Q%plvx4lxp7');
define('DB_CHARSET', 'utf8mb4');

// URL Base del sito
define('BASE_URL', 'https://tutorialbay.it/infl/');

// Path assoluti
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('AUTH_PATH', ROOT_PATH . '/auth');

// Connessione al Database
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

/**
 * Funzione per reindirizzamento
 */
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

/**
 * Sanitizza input utente
 */
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Controlla se l'utente è loggato
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Controlla il tipo di utente
 */
function get_user_type() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Controlla se l'utente è un admin
 */
function is_admin() {
    return is_logged_in() && $_SESSION['user_type'] === 'admin';
}

/**
 * Controlla se l'utente è un influencer
 */
function is_influencer() {
    return is_logged_in() && $_SESSION['user_type'] === 'influencer';
}

/**
 * Controlla se l'utente è un brand
 */
function is_brand() {
    return is_logged_in() && $_SESSION['user_type'] === 'brand';
}

/**
 * Ottiene i dati dell'utente corrente
 */
function get_current_user() {
    if (!is_logged_in()) return null;
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Funzione per debug
 */
function debug($data) {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

// Avvia la sessione in ogni pagina che include config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Controlla manutenzione
define('MAINTENANCE_MODE', false);

if (MAINTENANCE_MODE && !is_admin()) {
    die("Sito in manutenzione. Torneremo presto online!");
}
?>