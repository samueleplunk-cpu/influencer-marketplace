<?php
// Avvia sessione all'inizio
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = 'localhost';
$dbname = 'influencer_marketplace';
$username = 'sam';
$password = 'A6Hd&Q%plvx4lxp7';

// Base URL - versione semplificata e sicura
$base_url = 'http://' . $_SERVER['HTTP_HOST'];
$script_path = dirname($_SERVER['SCRIPT_NAME']);
if ($script_path !== '/') {
    $base_url .= $script_path;
}
define('BASE_URL', rtrim($base_url, '/'));

// Connessione database - con controllo errori migliorato
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Log dell'errore ma non blocca l'esecuzione
    error_log("Database connection warning: " . $e->getMessage());
    $pdo = null; // Imposta a null per evitare errori
}

// =============================================================================
// FUNZIONI DI AUTENTICAZIONE - RINOMINATE
// =============================================================================

/**
 * Verifica se l'utente è loggato
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Restituisce i dati dell'utente corrente - RINOMINATA
 */
function get_current_session_user() {
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return [
        'name' => $_SESSION['user_name'] ?? 'Utente',
        'type' => $_SESSION['user_type'] ?? 'guest'
    ];
}

/**
 * Verifica se l'utente è un influencer
 */
function is_influencer() {
    return ($_SESSION['user_type'] ?? '') === 'influencer';
}

/**
 * Verifica se l'utente è un brand
 */
function is_brand() {
    return ($_SESSION['user_type'] ?? '') === 'brand';
}
?>