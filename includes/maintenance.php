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
 * MODIFICA: Implementazione più robusta e completa
 */
function is_user_admin() {
    // Se non c'è sessione attiva, non è admin
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // 1. Verifica il sistema di autenticazione admin esistente
    if (function_exists('is_admin_logged_in') && is_admin_logged_in()) {
        return true;
    }
    
    // 2. Verifica il nuovo sistema di autenticazione per user_type
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        return true;
    }
    
    // 3. Verifica sessione admin specifica
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return true;
    }
    
    // 4. Verifica ruolo admin in sessioni più vecchie
    if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
        return true;
    }
    
    // 5. Verifica se l'utente è loggato nel pannello admin
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && 
        isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        return true;
    }
    
    return false;
}

/**
 * Verifica se la pagina corrente è nel backend admin
 * MODIFICA: Riconoscimento più flessibile e completo dei percorsi admin
 */
function is_admin_section() {
    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // DEBUG: Log per tracciare il riconoscimento (commentare in produzione)
    // error_log("DEBUG is_admin_section - Script: $current_script, URI: $current_uri");
    
    // Controlla vari percorsi admin possibili
    $admin_paths = [
        '/infl/admin/', 
        '/admin/', 
        '/backend/',
        '/cp/',
        '/controlpanel/'
    ];
    
    foreach ($admin_paths as $admin_path) {
        if (strpos($current_script, $admin_path) !== false || 
            strpos($current_uri, $admin_path) !== false) {
            return true;
        }
    }
    
    // Controlla anche file specifici che potrebbero essere nel root ma sono admin
    $admin_files = [
        'admin.php',
        'admin-login.php',
        'admin_dashboard.php',
        'cp.php'
    ];
    
    foreach ($admin_files as $admin_file) {
        if (strpos($current_script, $admin_file) !== false || 
            strpos(basename($current_script), $admin_file) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verifica se la richiesta corrente dovrebbe essere esclusa dalla manutenzione
 * (API, AJAX, webhooks, etc.)
 */
function is_excluded_request() {
    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Escludi richieste API/AJAX
    $excluded_paths = [
        '/api/',
        '/ajax/',
        '/webhook/',
        '/callback/',
        '/payment/',
        '/webhook/',
        '/infl/includes/',  // File di inclusione
        '/infl/assets/',    // Assets statici
    ];
    
    foreach ($excluded_paths as $path) {
        if (strpos($current_uri, $path) !== false) {
            return true;
        }
    }
    
    // Escludi file specifici
    $excluded_files = [
        'health-check.php',
        'server-status',
        'ping',
        'robots.txt',
        'sitemap.xml',
        '.well-known/'
    ];
    
    foreach ($excluded_files as $file) {
        if (strpos($current_uri, $file) !== false) {
            return true;
        }
    }
    
    // Escludi bot dei motori di ricerca
    $bots = [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
        'yandexbot', 'sogou', 'exabot', 'facebot', 'ia_archiver'
    ];
    
    $user_agent_lower = strtolower($user_agent);
    foreach ($bots as $bot) {
        if (strpos($user_agent_lower, $bot) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Middleware per controllo manutenzione
 * Da includere in tutte le pagine pubbliche
 */
function check_maintenance_mode($pdo) {
    // Se la richiesta è esclusa, non applicare la manutenzione
    if (is_excluded_request()) {
        return;
    }
    
    // Se siamo in una pagina admin, non applicare la manutenzione
    if (is_admin_section()) {
        return;
    }
    
    // Se la modalità manutenzione NON è attiva, non fare nulla
    if (!is_maintenance_mode($pdo)) {
        return;
    }
    
    // Se l'utente corrente è admin, permettere l'accesso
    if (is_user_admin()) {
        // DEBUG: Log accesso admin durante manutenzione
        error_log("ACCESSO ADMIN IN MANUTENZIONE: " . ($_SESSION['user_name'] ?? 'Unknown') . " - " . ($_SERVER['REQUEST_URI'] ?? 'Unknown'));
        return;
    }
    
    // Se arriviamo qui, mostriamo la pagina di manutenzione
    show_maintenance_page();
    exit;
}

/**
 * Mostra la pagina di manutenzione
 * MODIFICA: Rimossi badge lampeggiante e aggiornamento automatico
 */
function show_maintenance_page() {
    // Percorsi per l'immagine di manutenzione
    $maintenance_image = '/infl/uploads/maintenance/maintenance.webp';
    $placeholder_image = '/infl/assets/img/maintenance-placeholder.png';
    
    // Determina il percorso finale dell'immagine
    $final_image = $placeholder_image; // Default
    
    // Verifica se l'immagine principale esiste
    $main_image_path = $_SERVER['DOCUMENT_ROOT'] . $maintenance_image;
    if (file_exists($main_image_path) && is_file($main_image_path)) {
        $final_image = $maintenance_image;
    } else {
        // Verifica se esiste il placeholder
        $placeholder_path = $_SERVER['DOCUMENT_ROOT'] . $placeholder_image;
        if (!file_exists($placeholder_path)) {
            // Se non esiste nemmeno il placeholder, usa un'immagine di fallback base
            $final_image = 'data:image/svg+xml;base64,' . base64_encode('
                <svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
                    <rect width="400" height="300" fill="#667eea"/>
                    <text x="200" y="150" text-anchor="middle" fill="white" font-family="Arial" font-size="24">
                        🛠️ Manutenzione
                    </text>
                </svg>
            ');
        }
    }
    
    // Headers per manutenzione
    if (!headers_sent()) {
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Retry-After: 3600'); // 1 ora
        header('Content-Type: text/html; charset=utf-8');
        
        // Disabilita caching
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    // HTML della pagina di manutenzione
    echo '<!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Sito in Manutenzione - Influencer Marketplace</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: "Arial", sans-serif;
                line-height: 1.6;
                color: #333;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .maintenance-container {
                text-align: center;
                background: white;
                padding: 3rem;
                border-radius: 15px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                max-width: 600px;
                width: 100%;
                animation: fadeIn 0.8s ease-out;
            }
            
            .maintenance-image {
                max-width: 100%;
                height: auto;
                border-radius: 10px;
                margin-bottom: 2rem;
                max-height: 300px;
                object-fit: cover;
                border: 3px solid #f8f9fa;
            }
            
            .maintenance-title {
                color: #333;
                font-size: 2.5rem;
                margin-bottom: 1rem;
                font-weight: 700;
            }
            
            .maintenance-message {
                color: #666;
                font-size: 1.2rem;
                line-height: 1.6;
                margin-bottom: 2rem;
            }
            
            .maintenance-info {
                background: #f8f9fa;
                padding: 1.5rem;
                border-radius: 8px;
                border-left: 4px solid #667eea;
                text-align: left;
                margin-bottom: 2rem;
            }
            
            .maintenance-info strong {
                color: #333;
                display: block;
                margin-bottom: 0.5rem;
                font-size: 1.1rem;
            }
            
            .maintenance-info ul {
                list-style: none;
                padding: 0;
            }
            
            .maintenance-info li {
                padding: 0.3rem 0;
                color: #555;
            }
            
            .maintenance-info li:before {
                content: "•";
                color: #667eea;
                font-weight: bold;
                display: inline-block;
                width: 1em;
                margin-left: -1em;
            }
            
            .admin-login-link {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 0.8rem 1.5rem;
                border-radius: 5px;
                text-decoration: none;
                font-weight: 500;
                transition: all 0.3s ease;
                border: 2px solid #667eea;
            }
            
            .admin-login-link:hover {
                background: transparent;
                color: #667eea;
            }
            
            /* RIMOSSO: .refresh-notice - sezione aggiornamento automatico */
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @media (max-width: 768px) {
                .maintenance-container {
                    padding: 2rem 1.5rem;
                    margin: 1rem;
                }
                
                .maintenance-title {
                    font-size: 2rem;
                }
                
                .maintenance-message {
                    font-size: 1.1rem;
                }
                
                body {
                    padding: 10px;
                }
            }
            
            @media (max-width: 480px) {
                .maintenance-container {
                    padding: 1.5rem 1rem;
                }
                
                .maintenance-title {
                    font-size: 1.8rem;
                }
                
                .maintenance-info {
                    padding: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            ';
            
    // Gestione dell'immagine
    if (strpos($final_image, 'data:image/svg+xml') === 0) {
        echo '<div style="background: #667eea; color: white; padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🛠️</div>
                <h2 style="margin: 0; font-size: 1.5rem;">Sito in Manutenzione</h2>
              </div>';
    } else {
        echo '<img src="' . $final_image . '" alt="Sito in Manutenzione" class="maintenance-image">';
    }
    
    echo '
            <h1 class="maintenance-title">🛠️ Sito in Manutenzione</h1>
            <div class="maintenance-message">
                Stiamo lavorando per migliorare la tua esperienza. Il sito tornerà presto online!
            </div>
            
            <div class="maintenance-info">
                <strong>Informazioni:</strong>
                <ul>
                    <li>Stiamo effettuando aggiornamenti di sistema</li>
                    <li>Tutti i dati sono al sicuro</li>
                    <li>Torneremo online al più presto</li>
                    <li>Per urgenze, contatta l\'amministrazione</li>
                </ul>
            </div>
            
            <!-- RIMOSSO: Sezione refresh-notice e countdown -->
        </div>
        
        <script>
            // RIMOSSO: Aggiornamento automatico ogni 5 minuti
            // RIMOSSO: Countdown e timer di aggiornamento
            // RIMOSSO: Badge lampeggiante "MANUTENZIONE"
            
            // Mantenuto solo il controllo periodico dello stato ogni 30 secondi
            setInterval(function() {
                fetch(window.location.href, { 
                    method: "HEAD",
                    cache: "no-cache"
                }).then(function(response) {
                    if (response.status !== 503) {
                        window.location.reload();
                    }
                }).catch(function() {
                    // Ignora errori di fetch
                });
            }, 30000);
        </script>
    </body>
    </html>';
    
    // Assicurati che lo script termini
    exit;
}

/**
 * Funzione di utilità per verificare lo stato della manutenzione
 * Utile per debug o per altre parti del sistema
 */
function get_maintenance_status($pdo) {
    $is_active = is_maintenance_mode($pdo);
    $is_admin = is_user_admin();
    $is_admin_section = is_admin_section();
    
    return [
        'maintenance_active' => $is_active,
        'is_admin' => $is_admin,
        'is_admin_section' => $is_admin_section,
        'should_block' => $is_active && !$is_admin && !$is_admin_section,
        'timestamp' => time()
    ];
}

/**
 * Logga gli accessi durante la manutenzione (per debug)
 */
function log_maintenance_access($pdo) {
    if (!is_maintenance_mode($pdo)) {
        return;
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $is_admin = is_user_admin();
    
    $log_message = sprintf(
        "MAINTENANCE ACCESS: IP=%s, Admin=%s, URI=%s, UserAgent=%s",
        $ip,
        $is_admin ? 'YES' : 'NO',
        $uri,
        substr($user_agent, 0, 100)
    );
    
    error_log($log_message);
}

// Esegui il logging degli accessi (solo in modalità manutenzione)
if (function_exists('is_maintenance_mode') && isset($pdo)) {
    log_maintenance_access($pdo);
}
?>