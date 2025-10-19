<?php
// includes/maintenance.php - Sistema di controllo manutenzione

/**
 * Verifica se la modalità manutenzione è attiva
 */
function is_maintenance_mode($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return isset($result['setting_value']) && $result['setting_value'] === '1';
    } catch (PDOException $e) {
        error_log("Errore verifica modalità manutenzione: " . $e->getMessage());
        return false;
    }
}

/**
 * Attiva/disattiva modalità manutenzione
 */
function set_maintenance_mode($pdo, $enabled) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value) 
            VALUES ('maintenance_mode', ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $value = $enabled ? '1' : '0';
        $stmt->execute([$value, $value]);
        return true;
    } catch (PDOException $e) {
        error_log("Errore impostazione modalità manutenzione: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se l'utente corrente è admin
 * MODIFICA: Usa un nome unico per evitare conflitti
 */
function is_user_admin() {
    // Prima verifica il sistema di autenticazione admin esistente
    if (function_exists('is_admin_logged_in') && is_admin_logged_in()) {
        return true;
    }
    
    // Poi verifica il nuovo sistema di autenticazione
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        return true;
    }
    
    return false;
}

/**
 * Verifica se la pagina corrente è nel backend admin
 * MODIFICA: Usa un nome unico per evitare conflitti
 */
function is_admin_section() {
    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    return strpos($current_script, '/infl/admin/') !== false;
}

/**
 * Middleware per controllo manutenzione
 * Da includere in tutte le pagine pubbliche
 */
function check_maintenance_mode($pdo) {
    // Se siamo in una pagina admin, non applicare la manutenzione
    if (is_admin_section()) {
        return;
    }
    
    // Se la modalità manutenzione è attiva e l'utente non è admin
    if (is_maintenance_mode($pdo) && !is_user_admin()) {
        show_maintenance_page();
        exit;
    }
}

/**
 * Mostra la pagina di manutenzione
 */
function show_maintenance_page() {
    $maintenance_image = '/infl/uploads/maintenance/maintenance.webp';
    
    // Verifica se l'immagine esiste, altrimenti usa un fallback
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $maintenance_image)) {
        $maintenance_image = '/infl/assets/img/maintenance-placeholder.png';
    }
    
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Retry-After: 3600'); // 1 ora
    
    echo '<!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sito in Manutenzione - Influencer Marketplace</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .maintenance-container {
                text-align: center;
                background: white;
                padding: 3rem;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                max-width: 600px;
                margin: 2rem;
            }
            .maintenance-image {
                max-width: 100%;
                height: auto;
                border-radius: 10px;
                margin-bottom: 2rem;
            }
            .maintenance-title {
                color: #333;
                font-size: 2.5rem;
                margin-bottom: 1rem;
            }
            .maintenance-message {
                color: #666;
                font-size: 1.2rem;
                line-height: 1.6;
                margin-bottom: 2rem;
            }
            .maintenance-info {
                background: #f8f9fa;
                padding: 1rem;
                border-radius: 8px;
                border-left: 4px solid #667eea;
                text-align: left;
            }
            @media (max-width: 768px) {
                .maintenance-container {
                    padding: 2rem 1rem;
                    margin: 1rem;
                }
                .maintenance-title {
                    font-size: 2rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            <img src="' . $maintenance_image . '" alt="Sito in Manutenzione" class="maintenance-image">
            <h1 class="maintenance-title">🛠️ Sito in Manutenzione</h1>
            <div class="maintenance-message">
                Stiamo lavorando per migliorare la tua esperienza. Il sito tornerà presto online!
            </div>
            <div class="maintenance-info">
                <strong>Informazioni:</strong><br>
                • Stiamo effettuando aggiornamenti di sistema<br>
                • Tutti i dati sono al sicuro<br>
                • Torneremo online al più presto<br>
                • Per urgenze, contatta l\'amministrazione
            </div>
        </div>
    </body>
    </html>';
}
?>