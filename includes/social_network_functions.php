<?php
/**
 * Funzioni per la gestione dei social network dal database
 */

/**
 * Recupera tutti i social network attivi ordinati
 */
function get_active_social_networks() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM social_networks 
            WHERE is_active = TRUE 
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore nel recupero social networks: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupera un social network per slug
 */
function get_social_network_by_slug($slug) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM social_networks WHERE slug = ? AND is_active = TRUE");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore nel recupero social network per slug: " . $e->getMessage());
        return null;
    }
}

/**
 * Recupera tutti i social network (anche non attivi) per admin
 */
function get_all_social_networks() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM social_networks ORDER BY display_order ASC, name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore nel recupero tutti social networks: " . $e->getMessage());
        return [];
    }
}

/**
 * Crea un nuovo social network
 */
function create_social_network($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO social_networks (name, slug, icon, base_url, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['icon'],
            $data['base_url'],
            $data['display_order'],
            $data['is_active']
        ]);
    } catch (PDOException $e) {
        error_log("Errore nella creazione social network: " . $e->getMessage());
        return false;
    }
}

/**
 * Aggiorna un social network
 */
function update_social_network($id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE social_networks 
            SET name = ?, slug = ?, icon = ?, base_url = ?, display_order = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['icon'],
            $data['base_url'],
            $data['display_order'],
            $data['is_active'],
            $id
        ]);
    } catch (PDOException $e) {
        error_log("Errore nell'aggiornamento social network: " . $e->getMessage());
        return false;
    }
}

/**
 * Elimina un social network
 */
function delete_social_network($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM social_networks WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Errore nell'eliminazione social network: " . $e->getMessage());
        return false;
    }
}

/**
 * Genera le opzioni per i dropdown delle piattaforme
 */
function generate_social_platforms_options($selected_platforms = []) {
    $social_networks = get_active_social_networks();
    $options = '';
    
    foreach ($social_networks as $social) {
        $is_selected = in_array($social['slug'], $selected_platforms) ? 'selected' : '';
        $options .= "<option value=\"{$social['slug']}\" $is_selected>{$social['name']}</option>";
    }
    
    return $options;
}

/**
 * Genera i campi per gli social handles
 */
function generate_social_handles_fields($current_handles = []) {
    $social_networks = get_active_social_networks();
    $fields = '';
    
    foreach ($social_networks as $social) {
        $handle_value = $current_handles[$social['slug'] . '_handle'] ?? '';
        $fields .= "
            <div class=\"mb-3\">
                <label for=\"{$social['slug']}_handle\" class=\"form-label\">
                    <i class=\"{$social['icon']} me-2\"></i>{$social['name']}
                </label>
                <div class=\"input-group\">
                    <span class=\"input-group-text\">{$social['base_url']}</span>
                    <input type=\"text\" class=\"form-control\" id=\"{$social['slug']}_handle\" 
                           name=\"{$social['slug']}_handle\" 
                           value=\"" . htmlspecialchars($handle_value) . "\" 
                           placeholder=\"username\">
                </div>
            </div>
        ";
    }
    
    return $fields;
}
?>