<?php

/**
 * Verifica se l'utente è admin loggato
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Reindirizza admin non loggato
 */
function require_admin_login() {
    if (!is_admin_logged_in()) {
        header("Location: /infl/auth/admin_login.php");
        exit();
    }
}

/**
 * Login admin
 */
function login_admin($admin_id, $username, $is_super_admin = false) {
    $_SESSION['admin_id'] = $admin_id;
    $_SESSION['admin_username'] = $username;
    $_SESSION['is_super_admin'] = $is_super_admin;
    $_SESSION['admin_login_time'] = time();
    
    // Aggiorna ultimo login
    global $pdo;
    $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$admin_id]);
}

/**
 * Logout admin
 */
function logout_admin() {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['is_super_admin']);
    unset($_SESSION['admin_login_time']);
}

/**
 * Verifica timeout sessione admin (4 ore)
 */
function check_admin_session_timeout() {
    $timeout = 4 * 60 * 60; // 4 ore
    if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time'] > $timeout)) {
        logout_admin();
        header("Location: /infl/auth/admin_login.php?timeout=1");
        exit();
    }
}

/**
 * Ottiene statistiche piattaforma
 */
function get_platform_stats() {
    global $pdo;
    
    $stats = [];
    
    // Conta utenti totali
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Conta influencer
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'influencer' AND deleted_at IS NULL");
    $stats['total_influencers'] = $stmt->fetchColumn();
    
    // Conta brands
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'brand' AND deleted_at IS NULL");
    $stats['total_brands'] = $stmt->fetchColumn();
    
    // Conta utenti attivi oggi
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL");
    $stats['new_today'] = $stmt->fetchColumn();
    
    // Conta utenti sospesi
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_suspended = TRUE AND deleted_at IS NULL");
    $stats['suspended_users'] = $stmt->fetchColumn();
    
    return $stats;
}

/**
 * Soft delete utente
 */
function soft_delete_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Ripristina utente
 */
function restore_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Sospensione temporanea utente
 */
function suspend_user($user_id, $end_date) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_suspended = TRUE, suspension_end = ? WHERE id = ?");
    return $stmt->execute([$end_date, $user_id]);
}

/**
 * Rimuovi sospensione utente
 */
function unsuspend_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_suspended = FALSE, suspension_end = NULL WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Blocco permanente utente
 */
function block_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = TRUE, is_suspended = FALSE, suspension_end = NULL WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Sblocco utente
 */
function unblock_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = FALSE WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Pulizia automatica soft delete dopo 90 giorni
 */
function cleanup_soft_deleted_users() {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM users WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    return $stmt->execute();
}

?>