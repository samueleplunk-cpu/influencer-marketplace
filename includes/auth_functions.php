<?php

/**
 * Verifica se l'utente è loggato
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Verifica se l'utente è un influencer
 */
function is_influencer() {
    return is_logged_in() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer';
}

/**
 * Verifica se l'utente è un brand
 */
function is_brand() {
    return is_logged_in() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand';
}

/**
 * Reindirizza utente non loggato alla login
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: /infl/auth/login.php");
        exit();
    }
}

/**
 * Reindirizza utente non influencer
 */
function require_influencer() {
    require_login();
    if (!is_influencer()) {
        header("Location: /infl/auth/access-denied.php");
        exit();
    }
}

/**
 * Reindirizza utente non brand
 */
function require_brand() {
    require_login();
    if (!is_brand()) {
        header("Location: /infl/auth/access-denied.php");
        exit();
    }
}

/**
 * Login utente
 */
function login_user($user_id, $user_type, $username = '') {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_type'] = $user_type;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
}

/**
 * Logout utente
 */
function logout_user() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Verifica timeout sessione (8 ore)
 */
function check_session_timeout() {
    $timeout = 8 * 60 * 60; // 8 ore in secondi
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $timeout)) {
        logout_user();
        header("Location: /infl/auth/login.php?timeout=1");
        exit();
    }
}
?>