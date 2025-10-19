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
 * Controlla login admin e reindirizza se non loggato
 */
function checkAdminLogin() {
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
 * MODIFICA: Rinomina la funzione per evitare conflitto
 */
function get_admin_platform_stats() {
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

// RIMUOVI QUESTA FUNZIONE - È GIÀ IN functions.php
// /**
//  * Pulizia automatica soft delete dopo 90 giorni
//  */
// function cleanup_soft_deleted_users() {
//     global $pdo;
//     $stmt = $pdo->prepare("DELETE FROM users WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
//     return $stmt->execute();
// }

// =============================================================================
// FUNZIONI PER GESTIONE INFLUENCER E BRANDS
// =============================================================================

/**
 * Costruisce la stringa query per i filtri
 */
function buildQueryString($filters) {
    $params = [];
    foreach ($filters as $key => $value) {
        if (!empty($value)) {
            $params[] = $key . '=' . urlencode($value);
        }
    }
    return $params ? '&' . implode('&', $params) : '';
}

/**
 * Ottiene gli influencer con paginazione e filtri
 */
function getInfluencers($page = 1, $per_page = 15, $filters = []) {
    global $pdo;
    
    $offset = ($page - 1) * $per_page;
    $where_conditions = ["user_type = 'influencer'"];
    $params = [];
    
    // Filtro per stato
    if (!empty($filters['status'])) {
        switch($filters['status']) {
            case 'active':
                $where_conditions[] = "is_active = 1 AND deleted_at IS NULL AND is_blocked = 0 AND (is_suspended = 0 OR suspension_end < NOW())";
                break;
            case 'suspended':
                $where_conditions[] = "is_suspended = 1 AND deleted_at IS NULL AND suspension_end >= NOW()";
                break;
            case 'blocked':
                $where_conditions[] = "is_blocked = 1 AND deleted_at IS NULL";
                break;
            case 'deleted':
                $where_conditions[] = "deleted_at IS NOT NULL";
                break;
            case 'inactive':
                $where_conditions[] = "is_active = 0 AND deleted_at IS NULL";
                break;
        }
    } else {
        // Di default mostra solo utenti non eliminati
        $where_conditions[] = "deleted_at IS NULL";
    }
    
    // Filtro per data
    if (!empty($filters['date_from'])) {
        $where_conditions[] = "created_at >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where_conditions[] = "created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Ricerca per nome/email
    if (!empty($filters['search'])) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    // Query per il conteggio totale
    $count_sql = "SELECT COUNT(*) as total FROM users WHERE $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query per i dati
    $sql = "SELECT * FROM users WHERE $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $influencers,
        'total' => $total_count,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_count / $per_page)
    ];
}

/**
 * Ottiene un singolo influencer per ID
 */
function getInfluencerById($id) {
    global $pdo;
    
    $sql = "SELECT * FROM users WHERE id = ? AND user_type = 'influencer'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crea o aggiorna un influencer
 */
function saveInfluencer($data, $id = null) {
    global $pdo;
    
    if ($id) {
        // Update
        $sql = "UPDATE users SET name = ?, email = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$data['name'], $data['email'], $data['is_active'], $id]);
    } else {
        // Insert
        $sql = "INSERT INTO users (name, email, password, user_type, is_active, created_at) VALUES (?, ?, ?, 'influencer', ?, NOW())";
        $password_hash = password_hash('password123', PASSWORD_DEFAULT); // Password temporanea
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$data['name'], $data['email'], $password_hash, $data['is_active']]);
    }
}

/**
 * Gestisce lo stato di un influencer (sospensione, blocco, etc.)
 */
function updateInfluencerStatus($id, $action, $suspension_end = null) {
    global $pdo;
    
    switch($action) {
        case 'suspend':
            $sql = "UPDATE users SET is_suspended = 1, suspension_end = ?, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$suspension_end, $id]);
            
        case 'unsuspend':
            $sql = "UPDATE users SET is_suspended = 0, suspension_end = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'block':
            $sql = "UPDATE users SET is_blocked = 1, is_suspended = 0, suspension_end = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'unblock':
            $sql = "UPDATE users SET is_blocked = 0, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'delete':
            $sql = "UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'restore':
            $sql = "UPDATE users SET deleted_at = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        default:
            return false;
    }
}

/**
 * Ottiene i brands con paginazione e filtri
 */
function getBrands($page = 1, $per_page = 15, $filters = []) {
    global $pdo;
    
    $offset = ($page - 1) * $per_page;
    $where_conditions = ["user_type = 'brand'"];
    $params = [];
    
    // Filtro per stato
    if (!empty($filters['status'])) {
        switch($filters['status']) {
            case 'active':
                $where_conditions[] = "is_active = 1 AND deleted_at IS NULL AND is_blocked = 0 AND (is_suspended = 0 OR suspension_end < NOW())";
                break;
            case 'suspended':
                $where_conditions[] = "is_suspended = 1 AND deleted_at IS NULL AND suspension_end >= NOW()";
                break;
            case 'blocked':
                $where_conditions[] = "is_blocked = 1 AND deleted_at IS NULL";
                break;
            case 'deleted':
                $where_conditions[] = "deleted_at IS NOT NULL";
                break;
            case 'inactive':
                $where_conditions[] = "is_active = 0 AND deleted_at IS NULL";
                break;
        }
    } else {
        // Di default mostra solo utenti non eliminati
        $where_conditions[] = "deleted_at IS NULL";
    }
    
    // Filtro per data
    if (!empty($filters['date_from'])) {
        $where_conditions[] = "created_at >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where_conditions[] = "created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Ricerca per nome/email/azienda
    if (!empty($filters['search'])) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ? OR company_name LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    // Query per il conteggio totale
    $count_sql = "SELECT COUNT(*) as total FROM users WHERE $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query per i dati
    $sql = "SELECT * FROM users WHERE $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $brands,
        'total' => $total_count,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_count / $per_page)
    ];
}

/**
 * Ottiene un singolo brand per ID
 */
function getBrandById($id) {
    global $pdo;
    
    $sql = "SELECT * FROM users WHERE id = ? AND user_type = 'brand'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crea o aggiorna un brand
 */
function saveBrand($data, $id = null) {
    global $pdo;
    
    if ($id) {
        // Update
        $sql = "UPDATE users SET name = ?, email = ?, company_name = ?, website = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['name'], 
            $data['email'], 
            $data['company_name'],
            $data['website'],
            $data['description'],
            $data['is_active'],
            $id
        ]);
    } else {
        // Insert
        $sql = "INSERT INTO users (name, email, password, user_type, company_name, website, description, is_active, created_at) 
                VALUES (?, ?, ?, 'brand', ?, ?, ?, ?, NOW())";
        $password_hash = password_hash('password123', PASSWORD_DEFAULT); // Password temporanea
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['name'], 
            $data['email'], 
            $password_hash,
            $data['company_name'],
            $data['website'],
            $data['description'],
            $data['is_active']
        ]);
    }
}

/**
 * Gestisce lo stato di un brand (sospensione, blocco, etc.)
 */
function updateBrandStatus($id, $action, $suspension_end = null) {
    global $pdo;
    
    switch($action) {
        case 'suspend':
            $sql = "UPDATE users SET is_suspended = 1, suspension_end = ?, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$suspension_end, $id]);
            
        case 'unsuspend':
            $sql = "UPDATE users SET is_suspended = 0, suspension_end = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'block':
            $sql = "UPDATE users SET is_blocked = 1, is_suspended = 0, suspension_end = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'unblock':
            $sql = "UPDATE users SET is_blocked = 0, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'delete':
            $sql = "UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'restore':
            $sql = "UPDATE users SET deleted_at = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        default:
            return false;
    }
}

/**
 * Verifica se l'email esiste già (escludendo l'utente corrente per l'edit)
 */
function emailExists($email, $exclude_user_id = null) {
    global $pdo;
    
    if ($exclude_user_id) {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $exclude_user_id]);
    } else {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
    }
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Ottiene lo stato completo di un utente
 */
function getUserStatus($user) {
    if ($user['deleted_at']) {
        return 'deleted';
    } elseif ($user['is_blocked']) {
        return 'blocked';
    } elseif ($user['is_suspended'] && $user['suspension_end'] && strtotime($user['suspension_end']) > time()) {
        return 'suspended';
    } elseif (!$user['is_active']) {
        return 'inactive';
    } else {
        return 'active';
    }
}

/**
 * Formatta la data per la visualizzazione
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// =============================================================================
// NUOVE FUNZIONI PER IL SISTEMA DI MANUTENZIONE
// =============================================================================

/**
 * Verifica se l'utente corrente è un amministratore (compatibilità con nuovo sistema)
 */
function is_admin_user() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

/**
 * Verifica se la pagina corrente è nel backend admin
 */
function is_admin_page() {
    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    return strpos($current_script, '/infl/admin/') !== false;
}

/**
 * Ottiene statistiche specifiche per l'admin dashboard
 */
function get_admin_dashboard_stats($pdo) {
    try {
        $stats = [];
        
        // Utenti sospesi
        $stmt = $pdo->query("SELECT COUNT(*) as suspended FROM users WHERE is_suspended = 1 AND deleted_at IS NULL");
        $stats['suspended_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['suspended'] ?? 0;
        
        // Utenti eliminati (soft delete)
        $stmt = $pdo->query("SELECT COUNT(*) as deleted FROM users WHERE deleted_at IS NOT NULL");
        $stats['deleted_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['deleted'] ?? 0;
        
        // Campagne attive
        $stmt = $pdo->query("SELECT COUNT(*) as active_campaigns FROM campaigns WHERE status = 'active' AND deleted_at IS NULL");
        $stats['active_campaigns'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_campaigns'] ?? 0;
        
        // Campagne in attesa di moderazione
        $stmt = $pdo->query("SELECT COUNT(*) as pending_campaigns FROM campaigns WHERE status = 'pending' AND deleted_at IS NULL");
        $stats['pending_campaigns'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_campaigns'] ?? 0;
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Errore recupero statistiche admin: " . $e->getMessage());
        return [
            'suspended_users' => 0,
            'deleted_users' => 0,
            'active_campaigns' => 0,
            'pending_campaigns' => 0
        ];
    }
}

/**
 * Esegue operazioni di pulizia del database
 */
function run_admin_cleanup($pdo) {
    try {
        $cleaned = 0;
        
        // Pulizia password reset scaduti
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
        $stmt->execute();
        $cleaned += $stmt->rowCount();
        
        return $cleaned;
    } catch (PDOException $e) {
        error_log("Errore pulizia admin: " . $e->getMessage());
        return 0;
    }
}

/**
 * Verifica se l'utente è un super admin
 */
function is_super_admin($user_id) {
    // Implementa la logica per verificare i permessi super admin
    // Potresti avere una colonna 'is_super_admin' nella tabella users
    return true; // Temporaneamente sempre true
}

/**
 * Ottiene il log delle attività recenti
 */
function get_recent_activity($pdo, $limit = 10) {
    try {
        // Se hai una tabella activity_logs, usa questa query
        if (table_exists($pdo, 'activity_logs')) {
            $stmt = $pdo->prepare("
                SELECT al.*, u.username, u.user_type 
                FROM activity_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                ORDER BY al.created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fallback: log basico dagli utenti
            $stmt = $pdo->prepare("
                SELECT id, name as username, user_type, created_at, 'user_registered' as action 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Errore recupero attività recenti: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se una tabella esiste nel database
 */
function table_exists($pdo, $table_name) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table_name]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

?>