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
                INSERT INTO messages (conversazione_id, sender_id, sender_type, message) 
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

/**
 * Segna i messaggi come letti quando un utente visualizza una conversazione
 */
function mark_messages_as_read($pdo, $conversation_id, $user_type, $user_id) {
    try {
        if ($user_type === 'brand') {
            // Per i brand: segna come letti i messaggi degli influencer
            $stmt = $pdo->prepare("
                UPDATE messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN brands b ON c.brand_id = b.id
                SET m.is_read = TRUE
                WHERE c.id = ? AND b.user_id = ? AND m.sender_type = 'influencer' AND m.is_read = FALSE
            ");
            $stmt->execute([$conversation_id, $user_id]);
        } else if ($user_type === 'influencer') {
            // Per gli influencer: segna come letti i messaggi dei brand
            $stmt = $pdo->prepare("
                UPDATE messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN influencers i ON c.influencer_id = i.id
                SET m.is_read = TRUE
                WHERE c.id = ? AND i.user_id = ? AND m.sender_type = 'brand' AND m.is_read = FALSE
            ");
            $stmt->execute([$conversation_id, $user_id]);
        }
        return true;
    } catch (Exception $e) {
        error_log("Errore nel segnare i messaggi come letti: " . $e->getMessage());
        return false;
    }
}

/**
 * Conta i messaggi non letti per l'utente corrente
 */
function count_unread_messages($pdo, $user_id, $user_type) {
    try {
        if ($user_type === 'brand') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN brands b ON c.brand_id = b.id
                WHERE b.user_id = ? AND m.sender_type = 'influencer' AND m.is_read = FALSE
            ");
            $stmt->execute([$user_id]);
        } else if ($user_type === 'influencer') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN influencers i ON c.influencer_id = i.id
                WHERE i.user_id = ? AND m.sender_type = 'brand' AND m.is_read = FALSE
            ");
            $stmt->execute([$user_id]);
        } else {
            return 0;
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['unread'] ?? 0;
    } catch (Exception $e) {
        error_log("Errore nel conteggio messaggi non letti: " . $e->getMessage());
        return 0;
    }
}

/**
 * Ottiene il numero di messaggi non letti per una conversazione specifica
 */
function count_unread_messages_in_conversation($pdo, $conversation_id, $user_type, $user_id) {
    try {
        if ($user_type === 'brand') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN brands b ON c.brand_id = b.id
                WHERE c.id = ? AND b.user_id = ? AND m.sender_type = 'influencer' AND m.is_read = FALSE
            ");
            $stmt->execute([$conversation_id, $user_id]);
        } else if ($user_type === 'influencer') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN influencers i ON c.influencer_id = i.id
                WHERE c.id = ? AND i.user_id = ? AND m.sender_type = 'brand' AND m.is_read = FALSE
            ");
            $stmt->execute([$conversation_id, $user_id]);
        } else {
            return 0;
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['unread'] ?? 0;
    } catch (Exception $e) {
        error_log("Errore nel conteggio messaggi non letti per conversazione: " . $e->getMessage());
        return 0;
    }
}

/**
 * Verifica se l'utente ha accesso alla conversazione
 */
function can_access_conversation($pdo, $conversation_id, $user_type, $user_id) {
    try {
        if ($user_type === 'brand') {
            $stmt = $pdo->prepare("
                SELECT c.id 
                FROM conversations c
                JOIN brands b ON c.brand_id = b.id
                WHERE c.id = ? AND b.user_id = ?
            ");
        } else if ($user_type === 'influencer') {
            $stmt = $pdo->prepare("
                SELECT c.id 
                FROM conversations c
                JOIN influencers i ON c.influencer_id = i.id
                WHERE c.id = ? AND i.user_id = ?
            ");
        } else {
            return false;
        }
        
        $stmt->execute([$conversation_id, $user_id]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("Errore nella verifica accesso conversazione: " . $e->getMessage());
        return false;
    }
}

/**
 * Invia una notifica email (funzione base - da implementare con servizio email)
 */
function send_notification_email($to, $subject, $message) {
    // Implementazione base - da personalizzare con il servizio email preferito
    $headers = "From: noreply@influencer-marketplace.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Formatta la data in formato italiano
 */
function format_italian_date($date_string) {
    $timestamp = strtotime($date_string);
    $months = [
        'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
        'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'
    ];
    
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    
    return "$day $month $year alle $time";
}

/**
 * Valida i dati di input
 */
function validate_input($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        $rules_array = explode('|', $rule);
        
        foreach ($rules_array as $single_rule) {
            if ($single_rule === 'required' && empty(trim($value))) {
                $errors[$field] = "Il campo $field è obbligatorio";
                break;
            }
            
            if ($single_rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Il campo $field deve contenere un'email valida";
                break;
            }
            
            if (strpos($single_rule, 'min:') === 0) {
                $min_length = (int) str_replace('min:', '', $single_rule);
                if (strlen(trim($value)) < $min_length) {
                    $errors[$field] = "Il campo $field deve essere lungo almeno $min_length caratteri";
                    break;
                }
            }
            
            if (strpos($single_rule, 'max:') === 0) {
                $max_length = (int) str_replace('max:', '', $single_rule);
                if (strlen(trim($value)) > $max_length) {
                    $errors[$field] = "Il campo $field non può superare $max_length caratteri";
                    break;
                }
            }
        }
    }
    
    return $errors;
}

/**
 * Carica un file con controlli di sicurezza
 */
function upload_file($file, $allowed_types, $max_size, $upload_path) {
    $errors = [];
    
    // Verifica se non ci sono errori di upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Errore nel caricamento del file: " . $file['error'];
        return ['success' => false, 'errors' => $errors];
    }
    
    // Verifica dimensione file
    if ($file['size'] > $max_size) {
        $errors[] = "Il file è troppo grande. Dimensione massima: " . ($max_size / 1024 / 1024) . "MB";
        return ['success' => false, 'errors' => $errors];
    }
    
    // Verifica tipo file
    $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = "Tipo file non consentito. Tipi consentiti: " . implode(', ', $allowed_types);
        return ['success' => false, 'errors' => $errors];
    }
    
    // Genera nome file univoco
    $filename = uniqid() . '_' . time() . '.' . $file_type;
    $filepath = $upload_path . $filename;
    
    // Sposta il file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath
        ];
    } else {
        $errors[] = "Errore nel salvataggio del file";
        return ['success' => false, 'errors' => $errors];
    }
}

/**
 * Genera un token CSRF
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Pulizia input per prevenire SQL injection
 */
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    return trim(stripslashes(htmlspecialchars($data ?? '')));
}

/**
 * Restituisce il percorso del placeholder in base al tipo
 */
function get_placeholder_path($type) {
    $base_url = '/infl/assets/img/';
    
    switch ($type) {
        case 'brand':
            return $base_url . 'brand-placeholder.png';
        case 'influencer':
            return $base_url . 'influencer-placeholder.png';
        default:
            return $base_url . 'user-placeholder.png';
    }
}

/**
 * Ottiene il percorso corretto per un'immagine
 */
function get_image_path($filename, $default_type = 'user') {
    // Se il filename è vuoto, restituisci subito il placeholder
    if (empty($filename)) {
        return get_placeholder_path($default_type);
    }
    
    $base_path = dirname(__DIR__) . '/';
    
    // DEBUG: Log per tracciare il percorso (rimuovere in produzione)
    // error_log("DEBUG get_image_path: filename='$filename', default_type='$default_type'");
    
    // Lista dei percorsi possibili da verificare
    $possible_paths = [];
    
    // 1. Percorso completo (se il filename include già il percorso)
    if (strpos($filename, 'uploads/') === 0) {
        $possible_paths[] = $base_path . $filename;
    }
    
    // 2. Percorsi specifici per tipo
    if ($default_type === 'brand') {
        $possible_paths[] = $base_path . 'uploads/brands/' . $filename;
        $possible_paths[] = $base_path . 'brands/uploads/' . $filename;
    } elseif ($default_type === 'influencer') {
        $possible_paths[] = $base_path . 'uploads/influencers/' . $filename;
        $possible_paths[] = $base_path . 'uploads/profiles/' . $filename;
        $possible_paths[] = $base_path . 'influencers/uploads/' . $filename;
    }
    
    // 3. Percorsi generici
    $possible_paths[] = $base_path . 'uploads/' . $filename;
    $possible_paths[] = $base_path . $filename;
    
    // Verifica ogni percorso possibile
    foreach ($possible_paths as $full_path) {
        if (file_exists($full_path) && is_file($full_path)) {
            // Converti il percorso assoluto in percorso web
            $web_path = str_replace($base_path, '/infl/', $full_path);
            // error_log("DEBUG: Immagine trovata: $web_path");
            return $web_path;
        }
    }
    
    // Se nessun percorso funziona, restituisci il placeholder
    // error_log("DEBUG: Immagine NON trovata, usando placeholder per: $filename");
    return get_placeholder_path($default_type);
}

/**
 * Verifica se un file immagine esiste
 */
function image_exists($filename) {
    if (empty($filename)) {
        return false;
    }
    
    $base_path = dirname(__DIR__) . '/';
    
    // Lista dei percorsi possibili da verificare
    $possible_paths = [];
    
    // 1. Percorso completo
    if (strpos($filename, 'uploads/') === 0) {
        $possible_paths[] = $base_path . $filename;
    }
    
    // 2. Percorsi per brand (priorità alta per brand)
    $possible_paths[] = $base_path . 'uploads/brands/' . $filename;
    $possible_paths[] = $base_path . 'brands/uploads/' . $filename;
    
    // 3. Percorsi per influencer
    $possible_paths[] = $base_path . 'uploads/influencers/' . $filename;
    $possible_paths[] = $base_path . 'uploads/profiles/' . $filename;
    
    // 4. Percorsi generici
    $possible_paths[] = $base_path . 'uploads/' . $filename;
    $possible_paths[] = $base_path . $filename;
    
    // Verifica ogni percorso
    foreach ($possible_paths as $full_path) {
        if (file_exists($full_path) && is_file($full_path)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Ottiene l'ID del brand associato all'utente corrente
 */
function get_brand_id($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $brand = $stmt->fetch(PDO::FETCH_ASSOC);
        return $brand ? $brand['id'] : null;
    } catch (Exception $e) {
        error_log("Errore nel recupero brand ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Ottiene l'ID dell'influencer associato all'utente corrente
 */
function get_influencer_id($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
        return $influencer ? $influencer['id'] : null;
    } catch (Exception $e) {
        error_log("Errore nel recupero influencer ID: " . $e->getMessage());
        return null;
    }
}
?>