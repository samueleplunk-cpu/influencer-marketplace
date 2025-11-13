<?php
/**
 * Funzioni per la gestione delle pagine e menu
 */

/**
 * Salva le impostazioni del footer nel database
 */
function save_footer_settings($data, $files = []) {
    global $pdo;
    
    try {
        // Prepara i dati per il salvataggio
        $footer_settings = [
            'title' => trim($data['footer_title'] ?? 'Kibbiz'),
            'description' => trim($data['footer_description'] ?? 'Uniamo Brand e Influencer per crescere insieme.'),
            'quick_links' => [],
            'support_links' => [],
            'social_links' => []
        ];
        
        // Gestione upload logo
        $logo_url = handle_footer_logo_upload($files, $data['remove_logo'] ?? false);
        if ($logo_url !== null) {
            $footer_settings['logo_url'] = $logo_url;
        } elseif (isset($data['remove_logo']) && $data['remove_logo']) {
            $footer_settings['logo_url'] = '';
        }
        
        // Processa i quick links
        if (isset($data['quick_links']) && is_array($data['quick_links'])) {
            foreach ($data['quick_links'] as $link) {
                if (!empty(trim($link['label'])) && !empty(trim($link['url']))) {
                    $footer_settings['quick_links'][] = [
                        'label' => trim($link['label']),
                        'url' => trim($link['url']),
                        'target_blank' => !empty($link['target_blank'])
                    ];
                }
            }
        }
        
        // Processa i support links
        if (isset($data['support_links']) && is_array($data['support_links'])) {
            foreach ($data['support_links'] as $link) {
                if (!empty(trim($link['label'])) && !empty(trim($link['url']))) {
                    $footer_settings['support_links'][] = [
                        'label' => trim($link['label']),
                        'url' => trim($link['url']),
                        'target_blank' => !empty($link['target_blank'])
                    ];
                }
            }
        }
        
        // Processa i social links
        if (isset($data['social_links']) && is_array($data['social_links'])) {
            foreach ($data['social_links'] as $social) {
                if (!empty(trim($social['url']))) {
                    $footer_settings['social_links'][] = [
                        'platform' => trim($social['platform']),
                        'url' => trim($social['url']),
                        'icon' => trim($social['icon'])
                    ];
                }
            }
        }
        
        // Verifica se esiste già un record
        $check_stmt = $pdo->prepare("SELECT id FROM page_settings WHERE setting_type = 'footer'");
        $check_stmt->execute();
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Aggiorna record esistente
            $stmt = $pdo->prepare("
                UPDATE page_settings 
                SET setting_value = ?, updated_at = NOW() 
                WHERE setting_type = 'footer'
            ");
        } else {
            // Crea nuovo record
            $stmt = $pdo->prepare("
                INSERT INTO page_settings (setting_type, setting_value, created_at, updated_at)
                VALUES ('footer', ?, NOW(), NOW())
            ");
        }
        
        $stmt->execute([json_encode($footer_settings, JSON_UNESCAPED_UNICODE)]);
        
        return [
            'success' => true,
            'message' => 'Impostazioni footer salvate con successo!'
        ];
        
    } catch (Exception $e) {
        error_log("Errore salvataggio footer settings: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore durante il salvataggio: ' . $e->getMessage()
        ];
    }
}

/**
 * Salva le impostazioni dell'header nel database
 */
function save_header_settings($data, $files = []) {
    global $pdo;
    
    try {
        // Prepara i dati per il salvataggio
        $header_settings = [
            'logo_text' => trim($data['header_logo_text'] ?? 'Kibbiz'),
            'nav_menus' => []
        ];
        
        // Gestione upload logo header - SOLO se viene caricata una nuova immagine o se si richiede la rimozione
        $logo_url = handle_header_logo_upload($files, $data['remove_header_logo'] ?? false);
        
        if ($logo_url !== null) {
            // Se c'è un nuovo upload o rimozione esplicita
            $header_settings['logo_url'] = $logo_url;
        } else {
            // Se non c'è nuovo upload, mantieni il logo esistente
            $current_settings = get_header_settings();
            if (!empty($current_settings['logo_url']) && !isset($data['remove_header_logo'])) {
                $header_settings['logo_url'] = $current_settings['logo_url'];
            }
        }
        
        // Processa i menu di navigazione
        if (isset($data['nav_menus']) && is_array($data['nav_menus'])) {
            foreach ($data['nav_menus'] as $menu) {
                if (!empty(trim($menu['label'])) && !empty(trim($menu['url']))) {
                    $header_settings['nav_menus'][] = [
                        'label' => trim($menu['label']),
                        'url' => trim($menu['url']),
                        'target_blank' => !empty($menu['target_blank'])
                    ];
                }
            }
        }
        
        // Verifica se esiste già un record
        $check_stmt = $pdo->prepare("SELECT id FROM page_settings WHERE setting_type = 'header'");
        $check_stmt->execute();
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Aggiorna record esistente
            $stmt = $pdo->prepare("
                UPDATE page_settings 
                SET setting_value = ?, updated_at = NOW() 
                WHERE setting_type = 'header'
            ");
        } else {
            // Crea nuovo record
            $stmt = $pdo->prepare("
                INSERT INTO page_settings (setting_type, setting_value, created_at, updated_at)
                VALUES ('header', ?, NOW(), NOW())
            ");
        }
        
        $stmt->execute([json_encode($header_settings, JSON_UNESCAPED_UNICODE)]);
        
        return [
            'success' => true,
            'message' => 'Impostazioni header salvate con successo!'
        ];
        
    } catch (Exception $e) {
        error_log("Errore salvataggio header settings: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore durante il salvataggio: ' . $e->getMessage()
        ];
    }
}

/**
 * Salva le impostazioni dell'header brands nel database
 */
function save_header_brands_settings($data) {
    global $pdo;
    
    try {
        // Prepara i dati per il salvataggio
        $header_brands_settings = [
            'main_menus' => [],
            'profile_menus' => []
        ];
        
        // Processa i menu principali
        if (isset($data['main_menus']) && is_array($data['main_menus'])) {
            foreach ($data['main_menus'] as $menu) {
                if (!empty(trim($menu['label'])) && !empty(trim($menu['url']))) {
                    $header_brands_settings['main_menus'][] = [
                        'label' => trim($menu['label']),
                        'url' => trim($menu['url']),
                        'target_blank' => !empty($menu['target_blank']),
                        'order' => intval($menu['order'])
                    ];
                }
            }
            
            // Ordina i menu per ordine
            usort($header_brands_settings['main_menus'], function($a, $b) {
                return $a['order'] - $b['order'];
            });
        }
        
        // Processa i menu profilo
        if (isset($data['profile_menus']) && is_array($data['profile_menus'])) {
            foreach ($data['profile_menus'] as $menu) {
                if (!empty(trim($menu['label'])) && !empty(trim($menu['url']))) {
                    $header_brands_settings['profile_menus'][] = [
                        'label' => trim($menu['label']),
                        'url' => trim($menu['url']),
                        'target_blank' => !empty($menu['target_blank']),
                        'order' => intval($menu['order'])
                    ];
                }
            }
            
            // Ordina i menu profilo per ordine
            usort($header_brands_settings['profile_menus'], function($a, $b) {
                return $a['order'] - $b['order'];
            });
        }
        
        // Verifica se esiste già un record
        $check_stmt = $pdo->prepare("SELECT id FROM page_settings WHERE setting_type = 'header_brands'");
        $check_stmt->execute();
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Aggiorna record esistente
            $stmt = $pdo->prepare("
                UPDATE page_settings 
                SET setting_value = ?, updated_at = NOW() 
                WHERE setting_type = 'header_brands'
            ");
        } else {
            // Crea nuovo record
            $stmt = $pdo->prepare("
                INSERT INTO page_settings (setting_type, setting_value, created_at, updated_at)
                VALUES ('header_brands', ?, NOW(), NOW())
            ");
        }
        
        $stmt->execute([json_encode($header_brands_settings, JSON_UNESCAPED_UNICODE)]);
        
        return [
            'success' => true,
            'message' => 'Impostazioni header brands salvate con successo!'
        ];
        
    } catch (Exception $e) {
        error_log("Errore salvataggio header brands settings: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore durante il salvataggio: ' . $e->getMessage()
        ];
    }
}

/**
 * Gestisce l'upload del logo footer
 */
function handle_footer_logo_upload($files, $remove_logo = false) {
    return handle_logo_upload($files, 'footer_logo', $remove_logo, 'footer');
}

/**
 * Gestisce l'upload del logo header
 */
function handle_header_logo_upload($files, $remove_logo = false) {
    // Se richiesta rimozione logo esplicita
    if ($remove_logo) {
        // Elimina il file logo esistente se presente
        $current_settings = get_header_settings();
        if (!empty($current_settings['logo_url'])) {
            $logo_path = $_SERVER['DOCUMENT_ROOT'] . parse_url($current_settings['logo_url'], PHP_URL_PATH);
            if (file_exists($logo_path)) {
                unlink($logo_path);
            }
        }
        return ''; // Logo rimosso
    }
    
    // Gestione upload nuovo logo
    if (isset($files['header_logo']) && $files['header_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '/infl/uploads/logos/';
        $absolute_upload_dir = $_SERVER['DOCUMENT_ROOT'] . $upload_dir;
        
        // Crea la directory se non esiste
        if (!file_exists($absolute_upload_dir)) {
            mkdir($absolute_upload_dir, 0755, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $files['header_logo']['tmp_name']);
        finfo_close($file_info);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Tipo file non supportato. Usa JPG, PNG, GIF o WebP.');
        }
        
        // Verifica dimensioni (max 2MB)
        if ($files['header_logo']['size'] > 2 * 1024 * 1024) {
            throw new Exception('Il file è troppo grande. Dimensione massima: 2MB.');
        }
        
        // Genera nome file univoco
        $file_extension = pathinfo($files['header_logo']['name'], PATHINFO_EXTENSION);
        $filename = 'header_logo_' . time() . '_' . uniqid() . '.' . $file_extension;
        $destination = $absolute_upload_dir . $filename;
        
        // Sposta il file
        if (move_uploaded_file($files['header_logo']['tmp_name'], $destination)) {
            // Elimina il vecchio logo se esiste
            $current_settings = get_header_settings();
            if (!empty($current_settings['logo_url'])) {
                $old_logo_path = $_SERVER['DOCUMENT_ROOT'] . parse_url($current_settings['logo_url'], PHP_URL_PATH);
                if (file_exists($old_logo_path) && is_file($old_logo_path)) {
                    unlink($old_logo_path);
                }
            }
            
            return $upload_dir . $filename;
        } else {
            throw new Exception('Errore durante il caricamento del file.');
        }
    }
    
    return null; // Nessun nuovo upload e nessuna rimozione richiesta
}

/**
 * Gestisce l'upload del logo (funzione generica)
 */
function handle_logo_upload($files, $field_name, $remove_logo = false, $type = 'footer') {
    // Se richiesta rimozione logo
    if ($remove_logo) {
        // Elimina il file logo esistente se presente
        if ($type === 'footer') {
            $current_settings = get_footer_settings();
        } else {
            $current_settings = get_header_settings();
        }
        
        if (!empty($current_settings['logo_url'])) {
            $logo_path = $_SERVER['DOCUMENT_ROOT'] . parse_url($current_settings['logo_url'], PHP_URL_PATH);
            if (file_exists($logo_path)) {
                unlink($logo_path);
            }
        }
        return '';
    }
    
    // Gestione upload nuovo logo
    if (isset($files[$field_name]) && $files[$field_name]['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '/infl/uploads/logos/';
        $absolute_upload_dir = $_SERVER['DOCUMENT_ROOT'] . $upload_dir;
        
        // Crea la directory se non esiste
        if (!file_exists($absolute_upload_dir)) {
            mkdir($absolute_upload_dir, 0755, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $files[$field_name]['tmp_name']);
        finfo_close($file_info);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Tipo file non supportato. Usa JPG, PNG, GIF o WebP.');
        }
        
        // Verifica dimensioni (max 2MB)
        if ($files[$field_name]['size'] > 2 * 1024 * 1024) {
            throw new Exception('Il file è troppo grande. Dimensione massima: 2MB.');
        }
        
        // Genera nome file univoco
        $file_extension = pathinfo($files[$field_name]['name'], PATHINFO_EXTENSION);
        $filename = $type . '_logo_' . time() . '_' . uniqid() . '.' . $file_extension;
        $destination = $absolute_upload_dir . $filename;
        
        // Sposta il file
        if (move_uploaded_file($files[$field_name]['tmp_name'], $destination)) {
            // Elimina il vecchio logo se esiste
            if ($type === 'footer') {
                $current_settings = get_footer_settings();
            } else {
                $current_settings = get_header_settings();
            }
            
            if (!empty($current_settings['logo_url'])) {
                $old_logo_path = $_SERVER['DOCUMENT_ROOT'] . parse_url($current_settings['logo_url'], PHP_URL_PATH);
                if (file_exists($old_logo_path) && is_file($old_logo_path)) {
                    unlink($old_logo_path);
                }
            }
            
            return $upload_dir . $filename;
        } else {
            throw new Exception('Errore durante il caricamento del file.');
        }
    }
    
    return null; // Nessun nuovo upload
}

/**
 * Recupera le impostazioni del footer dal database
 */
function get_footer_settings() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM page_settings WHERE setting_type = 'footer'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            return json_decode($result['setting_value'], true);
        }
    } catch (Exception $e) {
        error_log("Errore recupero footer settings: " . $e->getMessage());
    }
    
    // Valori di default
    return [
        'title' => 'Kibbiz',
        'description' => 'Uniamo Brand e Influencer per crescere insieme.',
        'quick_links' => [
            ['label' => 'Home', 'url' => '/infl/', 'target_blank' => false],
            ['label' => 'Funzionalità', 'url' => '#features', 'target_blank' => false],
            ['label' => 'Come Funziona', 'url' => '#how-it-works', 'target_blank' => false],
            ['label' => 'Login', 'url' => '/infl/auth/login.php', 'target_blank' => false],
            ['label' => 'Registrati', 'url' => '/infl/auth/register.php', 'target_blank' => false]
        ],
        'support_links' => [
            ['label' => 'Contattaci', 'url' => '#', 'target_blank' => false],
            ['label' => 'FAQ', 'url' => '#', 'target_blank' => false],
            ['label' => 'Privacy Policy', 'url' => '#', 'target_blank' => false],
            ['label' => 'Termini di Servizio', 'url' => '#', 'target_blank' => false]
        ],
        'social_links' => [
            ['platform' => 'instagram', 'url' => '#', 'icon' => 'fab fa-instagram'],
            ['platform' => 'tiktok', 'url' => '#', 'icon' => 'fab fa-tiktok'],
            ['platform' => 'linkedin', 'url' => '#', 'icon' => 'fab fa-linkedin']
        ]
    ];
}

/**
 * Recupera le impostazioni dell'header dal database
 */
function get_header_settings() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM page_settings WHERE setting_type = 'header'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            return json_decode($result['setting_value'], true);
        }
    } catch (Exception $e) {
        error_log("Errore recupero header settings: " . $e->getMessage());
    }
    
    // Valori di default
    return [
        'logo_text' => 'Kibbiz',
        'nav_menus' => [
            ['label' => 'Funzionalità', 'url' => '#features', 'target_blank' => false],
            ['label' => 'Come Funziona', 'url' => '#how-it-works', 'target_blank' => false],
            ['label' => 'Chi Siamo', 'url' => '#about', 'target_blank' => false]
        ]
    ];
}

/**
 * Recupera le impostazioni dell'header brands dal database
 */
function get_header_brands_settings() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM page_settings WHERE setting_type = 'header_brands'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            return json_decode($result['setting_value'], true);
        }
    } catch (Exception $e) {
        error_log("Errore recupero header brands settings: " . $e->getMessage());
    }
    
    // Valori di default
    return [
        'main_menus' => [
            [
                'label' => 'Dashboard',
                'url' => '/infl/brands/dashboard.php',
                'target_blank' => false,
                'order' => 1
            ],
            [
                'label' => 'Campagne', 
                'url' => '/infl/brands/campaigns.php',
                'target_blank' => false,
                'order' => 2
            ],
            [
                'label' => 'Messaggi',
                'url' => '/infl/brands/messages/conversation-list.php',
                'target_blank' => false,
                'order' => 3
            ],
            [
                'label' => 'Cerca Influencer',
                'url' => '/infl/brands/search-influencers.php',
                'target_blank' => false,
                'order' => 4
            ]
        ],
        'profile_menus' => [
            [
                'label' => 'Impostazioni',
                'url' => '/infl/brands/settings.php',
                'target_blank' => false,
                'order' => 1
            ],
            [
                'label' => 'Logout',
                'url' => '/infl/auth/logout.php',
                'target_blank' => false,
                'order' => 2
            ]
        ]
    ];
}

/**
 * Renderizza il footer dinamico per la homepage
 */
function render_dynamic_footer() {
    $settings = get_footer_settings();
    
    ob_start();
    ?>
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <?php if (!empty($settings['logo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($settings['logo_url']); ?>" 
                             alt="<?php echo htmlspecialchars($settings['title']); ?>" 
                             class="footer-logo" style="max-height: 50px; margin-bottom: 15px;">
                    <?php else: ?>
                        <h3><?php echo htmlspecialchars($settings['title']); ?></h3>
                    <?php endif; ?>
                    <p><?php echo htmlspecialchars($settings['description']); ?></p>
                </div>
                <div class="footer-section">
                    <h3>Link Veloci</h3>
                    <?php foreach ($settings['quick_links'] as $link): ?>
                        <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                           <?php echo !empty($link['target_blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                            <?php echo htmlspecialchars($link['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="footer-section">
                    <h3>Supporto</h3>
                    <?php foreach ($settings['support_links'] as $link): ?>
                        <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                           <?php echo !empty($link['target_blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                            <?php echo htmlspecialchars($link['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="footer-section">
                    <h3>Seguici su</h3>
                    <div class="social-icons">
                        <?php foreach ($settings['social_links'] as $social): ?>
                            <a href="<?php echo htmlspecialchars($social['url']); ?>" 
                               class="social-link" 
                               aria-label="<?php echo htmlspecialchars($social['platform']); ?>"
                               target="_blank" rel="noopener noreferrer">
                                <i class="<?php echo htmlspecialchars($social['icon']); ?>"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo htmlspecialchars($settings['title']); ?> <?php echo date('Y'); ?>. Tutti i diritti riservati.</p>
            </div>
        </div>
    </footer>
    <?php
    return ob_get_clean();
}

/**
 * Renderizza l'header dinamico per la homepage
 */
function render_dynamic_header() {
    $settings = get_header_settings();
    
    ob_start();
    ?>
    <!-- Navigation -->
    <nav class="navbar" style="<?php echo (is_user_admin() && is_maintenance_mode($GLOBALS['pdo'])) ? 'margin-top: 40px;' : ''; ?>">
        <div class="nav-container">
            <a href="/infl/" class="logo">
                <?php if (!empty($settings['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($settings['logo_url']); ?>" 
                         alt="<?php echo htmlspecialchars($settings['logo_text']); ?>" 
                         style="max-height: 40px;">
                <?php else: ?>
                    <?php echo htmlspecialchars($settings['logo_text']); ?>
                <?php endif; ?>
            </a>
            <div class="nav-links">
                <?php foreach ($settings['nav_menus'] as $menu): ?>
                    <a href="<?php echo htmlspecialchars($menu['url']); ?>" 
                       <?php echo !empty($menu['target_blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                        <?php echo htmlspecialchars($menu['label']); ?>
                    </a>
                <?php endforeach; ?>
                
                <?php 
                // Gestione pulsanti di autenticazione
                $is_logged_in = is_logged_in();
                $user_name = $is_logged_in ? ($_SESSION['user_name'] ?? 'Utente') : '';
                
                // Determina il percorso della dashboard in base al tipo di utente
                $dashboard_url = "/infl/influencers/dashboard.php"; // default
                if (isset($_SESSION['user_type'])) {
                    if ($_SESSION['user_type'] === 'brand') {
                        $dashboard_url = "/infl/brands/dashboard.php";
                    } elseif ($_SESSION['user_type'] === 'admin') {
                        $dashboard_url = "/infl/admin/dashboard.php";
                    }
                }
                ?>
                
                <?php if ($is_logged_in): ?>
                    <div class="auth-buttons">
                        <span>Ciao, <?php echo htmlspecialchars($user_name); ?>!</span>
                        <a href="<?php echo $dashboard_url; ?>" class="btn btn-primary">Dashboard</a>
                        <a href="/infl/auth/logout.php" class="btn btn-outline">Logout</a>
                    </div>
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="/infl/auth/login.php" class="btn btn-outline">Login</a>
                        <a href="/infl/auth/register.php" class="btn btn-primary">Sign Up</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php
    return ob_get_clean();
}
?>