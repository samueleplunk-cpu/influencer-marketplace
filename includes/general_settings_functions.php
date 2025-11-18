<?php
/**
 * Funzioni per la gestione delle impostazioni generali
 */

/**
 * Recupera le impostazioni delle categorie dal database
 */
function get_categories_settings() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM settings WHERE setting_key = 'categories_settings'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['setting_value'])) {
            return json_decode($result['setting_value'], true);
        }
    } catch (PDOException $e) {
        error_log("Errore nel recupero delle categorie: " . $e->getMessage());
    }
    
    // Valori di default
    return [
        'categories' => [
            [
                'name' => 'Beauty & Makeup',
                'slug' => 'beauty-makeup',
                'description' => 'Tutto su bellezza e makeup',
                'order' => 1,
                'active' => true
            ],
            [
                'name' => 'Fashion',
                'slug' => 'fashion',
                'description' => 'Moda e tendenze',
                'order' => 2,
                'active' => true
            ]
        ]
    ];
}

/**
 * Recupera le impostazioni dei social network dal database
 */
function get_social_networks_settings() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM settings WHERE setting_key = 'social_networks_settings'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['setting_value'])) {
            return json_decode($result['setting_value'], true);
        }
    } catch (PDOException $e) {
        error_log("Errore nel recupero dei social network: " . $e->getMessage());
    }
    
    // Valori di default
    return [
        'social_networks' => [
            [
                'name' => 'Instagram',
                'slug' => 'instagram',
                'icon' => 'fab fa-instagram',
                'base_url' => 'https://instagram.com/',
                'order' => 1,
                'active' => true
            ],
            [
                'name' => 'TikTok',
                'slug' => 'tiktok',
                'icon' => 'fab fa-tiktok',
                'base_url' => 'https://tiktok.com/@',
                'order' => 2,
                'active' => true
            ]
        ]
    ];
}

/**
 * Salva le impostazioni delle categorie nel database
 */
function save_categories_settings($post_data) {
    global $pdo;
    
    try {
        $categories = [];
        
        if (isset($post_data['categories']) && is_array($post_data['categories'])) {
            foreach ($post_data['categories'] as $category_data) {
                $category = [
                    'name' => trim($category_data['name']),
                    'slug' => trim($category_data['slug']),
                    'description' => trim($category_data['description']),
                    'order' => intval($category_data['order']),
                    'active' => isset($category_data['active']) ? true : false
                ];
                
                // Validazione base
                if (empty($category['name']) || empty($category['slug'])) {
                    continue;
                }
                
                $categories[] = $category;
            }
        }
        
        // Ordina per ordine
        usort($categories, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        $settings_data = ['categories' => $categories];
        $json_data = json_encode($settings_data);
        
        // Salva nel database
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES ('categories_settings', ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([$json_data, $json_data]);
        
        return [
            'success' => true,
            'message' => 'Categorie salvate con successo!'
        ];
        
    } catch (PDOException $e) {
        error_log("Errore nel salvataggio delle categorie: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore nel salvataggio delle categorie: ' . $e->getMessage()
        ];
    }
}

/**
 * Salva le impostazioni dei social network nel database
 */
function save_social_networks_settings($post_data) {
    global $pdo;
    
    try {
        $social_networks = [];
        
        if (isset($post_data['social_networks']) && is_array($post_data['social_networks'])) {
            foreach ($post_data['social_networks'] as $social_data) {
                $social_network = [
                    'name' => trim($social_data['name']),
                    'slug' => trim($social_data['slug']),
                    'icon' => trim($social_data['icon']),
                    'base_url' => trim($social_data['base_url']),
                    'order' => intval($social_data['order']),
                    'active' => isset($social_data['active']) ? true : false
                ];
                
                // Validazione base
                if (empty($social_network['name']) || empty($social_network['slug'])) {
                    continue;
                }
                
                $social_networks[] = $social_network;
            }
        }
        
        // Ordina per ordine
        usort($social_networks, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        $settings_data = ['social_networks' => $social_networks];
        $json_data = json_encode($settings_data);
        
        // Salva nel database
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES ('social_networks_settings', ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([$json_data, $json_data]);
        
        return [
            'success' => true,
            'message' => 'Social network salvati con successo!'
        ];
        
    } catch (PDOException $e) {
        error_log("Errore nel salvataggio dei social network: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore nel salvataggio dei social network: ' . $e->getMessage()
        ];
    }
}