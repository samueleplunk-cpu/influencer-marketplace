<?php
// includes/functions.php

/**
 * Sanitizza l'output per prevenire XSS
 */
function sanitize_output($data) {
    if (is_array($data)) {
        return array_map('sanitize_output', $data);
    }
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Reindirizza a una pagina
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// RIMUOVI LE FUNZIONI DUPLICATE - SONO GIA' IN auth_functions.php
// /**
//  * Verifica se l'utente è loggato
//  */
// function is_logged_in() {
//     return isset($_SESSION['user_id']);
// }
// 
// /**
//  * Verifica se l'utente è un influencer
//  */
// function is_influencer() {
//     return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer';
// }
// 
// /**
//  * Verifica se l'utente è un brand
//  */
// function is_brand() {
//     return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand';
// }

/**
 * Formatta i numeri in formato leggibile (1.5K, 2.3M)
 */
function format_number($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}

/**
 * Ottiene il nome della categoria dal ID
 */
function get_category_name($category_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();
        return $category ? $category['name'] : 'Sconosciuto';
    } catch (PDOException $e) {
        error_log("Error getting category name: " . $e->getMessage());
        return 'Sconosciuto';
    }
}

/**
 * Genera un slug da una stringa
 */
function generate_slug($string) {
    $slug = preg_replace('/[^a-zA-Z0-9]/', '-', $string);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return strtolower($slug);
}

/**
 * Valida un'email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash della password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifica la password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Debug function - mostra dati in formato leggibile
 */
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

/**
 * Ottiene l'URL base dell'applicazione
 */
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . $base . $path;
}

/**
 * Mostra messaggi di alert
 */
function display_alert($type = 'info', $message = '') {
    if (!empty($message)) {
        $class = 'alert-' . $type;
        return '<div class="alert ' . $class . '">' . sanitize_output($message) . '</div>';
    }
    return '';
}

/**
 * Crea o recupera una conversazione tra brand e influencer
 */
function startConversation($pdo, $brand_id, $influencer_id, $campaign_id = null, $initial_message = null) {
    try {
        // Verifica se esiste già una conversazione
        if ($campaign_id) {
            $stmt = $pdo->prepare("
                SELECT id FROM conversations 
                WHERE brand_id = ? AND influencer_id = ? AND campaign_id = ?
            ");
            $stmt->execute([$brand_id, $influencer_id, $campaign_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id FROM conversations 
                WHERE brand_id = ? AND influencer_id = ? AND campaign_id IS NULL
            ");
            $stmt->execute([$brand_id, $influencer_id]);
        }
        
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return $existing['id']; // Restituisce ID conversazione esistente
        }
        
        // Crea nuova conversazione
        $stmt = $pdo->prepare("
            INSERT INTO conversations (brand_id, influencer_id, campaign_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$brand_id, $influencer_id, $campaign_id]);
        $conversation_id = $pdo->lastInsertId();
        
        // Aggiungi messaggio iniziale se fornito
        if ($initial_message) {
            $stmt = $pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, sender_type, message) 
                VALUES (?, ?, 'brand', ?)
            ");
            $stmt->execute([$conversation_id, $brand_id, $initial_message]);
        }
        
        return $conversation_id;
        
    } catch (PDOException $e) {
        error_log("Errore creazione conversazione: " . $e->getMessage());
        return false;
    }
}
?>