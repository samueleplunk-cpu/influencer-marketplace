<?php
/**
 * Funzioni per la gestione delle pagine e menu
 */

/**
 * Salva le impostazioni del footer nel database
 */
function save_footer_settings($data) {
    global $pdo;
    
    try {
        // Prepara i dati per il salvataggio
        $footer_settings = [
            'title' => trim($data['footer_title'] ?? 'Kibbiz'),
            'description' => trim($data['footer_description'] ?? 'Uniamo Brand e Influencer per crescere insieme.'),
            'quick_links' => $data['quick_links'] ?? [],
            'support_links' => $data['support_links'] ?? [],
            'social_links' => []
        ];
        
        // Processa i social links
        foreach ($data['social_links'] as $key => $social) {
            if (!empty(trim($social['url']))) {
                $footer_settings['social_links'][$key] = [
                    'platform' => trim($social['platform']),
                    'url' => trim($social['url']),
                    'icon' => trim($social['icon'])
                ];
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
            'home' => ['label' => 'Home', 'url' => '/infl/'],
            'features' => ['label' => 'Funzionalità', 'url' => '#features'],
            'how_it_works' => ['label' => 'Come Funziona', 'url' => '#how-it-works'],
            'login' => ['label' => 'Login', 'url' => '/infl/auth/login.php'],
            'register' => ['label' => 'Registrati', 'url' => '/infl/auth/register.php']
        ],
        'support_links' => [
            'contact' => ['label' => 'Contattaci', 'url' => '#'],
            'faq' => ['label' => 'FAQ', 'url' => '#'],
            'privacy' => ['label' => 'Privacy Policy', 'url' => '#'],
            'terms' => ['label' => 'Termini di Servizio', 'url' => '#']
        ],
        'social_links' => [
            'instagram' => ['platform' => 'instagram', 'url' => '#', 'icon' => 'fab fa-instagram'],
            'tiktok' => ['platform' => 'tiktok', 'url' => '#', 'icon' => 'fab fa-tiktok'],
            'linkedin' => ['platform' => 'linkedin', 'url' => '#', 'icon' => 'fab fa-linkedin']
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
                    <h3><?php echo htmlspecialchars($settings['title']); ?></h3>
                    <p><?php echo htmlspecialchars($settings['description']); ?></p>
                </div>
                <div class="footer-section">
                    <h3>Link Veloci</h3>
                    <?php foreach ($settings['quick_links'] as $link): ?>
                        <a href="<?php echo htmlspecialchars($link['url']); ?>">
                            <?php echo htmlspecialchars($link['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="footer-section">
                    <h3>Supporto</h3>
                    <?php foreach ($settings['support_links'] as $link): ?>
                        <a href="<?php echo htmlspecialchars($link['url']); ?>">
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
?>