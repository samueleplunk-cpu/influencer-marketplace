<?php
// =============================================
// CONFIGURAZIONE E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG E AUTENTICAZIONE
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE UTENTE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /infl/auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// INCLUSIONE FUNZIONI SOCIAL NETWORK
// =============================================
require_once dirname(__DIR__) . '/includes/social_network_functions.php';

// =============================================
// VERIFICA E AGGIUNGI COLONNA PROFILE_IMAGE SE MANCANTE
// =============================================
try {
    $check_column_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'influencers' 
        AND COLUMN_NAME = 'profile_image'
    ");
    $check_column_stmt->execute();
    $column_exists = $check_column_stmt->fetchColumn();
    
    if (!$column_exists) {
        $alter_table_stmt = $pdo->prepare("
            ALTER TABLE influencers 
            ADD COLUMN profile_image VARCHAR(255) NULL AFTER rate
        ");
        $alter_table_stmt->execute();
    }
} catch (PDOException $e) {
    // Silenzioso - solo log
}

// =============================================
// RECUPERO DATI INFLUENCER ATTUALI
// =============================================
$influencer = null;
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        $_SESSION['error'] = "Devi prima creare un profilo influencer!";
        header("Location: /infl/influencers/dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo: " . $e->getMessage();
}

// =============================================
// RECUPERO CATEGORIE DAL DATABASE
// =============================================
$categories = [];
try {
    require_once dirname(__DIR__) . '/includes/category_functions.php';
    
    $categories = get_active_categories($pdo);
    
    if (empty($categories)) {
        $error = "Nessuna categoria disponibile. Contatta l'amministratore del sistema.";
    }
} catch (Exception $e) {
    $error = "Errore nel caricamento delle categorie. Riprova più tardi.";
}

// =============================================
// FUNZIONE PER GENERARE SLUG DAL NOME
// =============================================
function generate_slug_from_name($name) {
    if (empty($name)) {
        return '';
    }
    
    $slug = strtolower(trim($name));
    $slug = str_replace(' & ', '-', $slug);
    $slug = str_replace(' ', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    return $slug;
}

// =============================================
// MAPPA SLUG PER COMPATIBILITÀ DINAMICA
// =============================================
function map_category_slug($slug, $all_categories = []) {
    if (empty($slug)) {
        return '';
    }
    
    $slug = strtolower(trim($slug));
    
    // Mappature fisse per compatibilità
    $static_mapping = [
        'beauty-makeup' => 'beauty',
        'fitness-wellness' => 'fitness',
        'finance-business' => 'finance',
        'beauty' => 'beauty',
        'makeup' => 'beauty',
        'fitness' => 'fitness',
        'wellness' => 'fitness',
        'finance' => 'finance',
        'business' => 'finance',
    ];
    
    // Prima controlla le mappature fisse
    if (isset($static_mapping[$slug])) {
        return $static_mapping[$slug];
    }
    
    // Per le altre categorie, cerca nel database
    foreach ($all_categories as $cat) {
        if (is_array($cat)) {
            $cat_slug = $cat['slug'] ?? '';
            $cat_name = $cat['name'] ?? '';
            
            // Confronta slug
            if (strtolower($cat_slug) === $slug) {
                // Se lo slug è già un valore ENUM valido, usalo
                if (in_array($slug, ['entertainment', 'pet', 'education', 'fashion', 
                                     'lifestyle', 'food', 'travel', 'gaming', 'tech'])) {
                    return $slug;
                }
                // Altrimenti usa il nome generato come slug
                return generate_slug_from_name($cat_name);
            }
            
            // Confronta nome
            $cat_name_slug = generate_slug_from_name($cat_name);
            if (strtolower($cat_name_slug) === $slug) {
                return $slug;
            }
        }
    }
    
    // Se non trovato, usa lo slug originale
    return $slug;
}

// =============================================
// FUNZIONE PER CONVERTIRE DA ENUM A SLUG LEGGIBILE
// =============================================
function enum_to_display_name($enum_value, $categories) {
    if (empty($enum_value)) {
        return '';
    }
    
    $enum_value = strtolower(trim($enum_value));
    
    // Mappatura inversa: ENUM -> slug completo
    $reverse_mapping = [
        'beauty' => 'beauty-makeup',
        'fitness' => 'fitness-wellness',
        'finance' => 'finance-business',
        'entertainment' => 'entertainment',
        'pet' => 'pet',
        'education' => 'education',
        'fashion' => 'fashion',
        'lifestyle' => 'lifestyle',
        'food' => 'food',
        'travel' => 'travel',
        'gaming' => 'gaming',
        'tech' => 'tech',
    ];
    
    $target_slug = $reverse_mapping[$enum_value] ?? $enum_value;
    
    // Cerca il nome della categoria corrispondente
    foreach ($categories as $category) {
        if (is_array($category)) {
            $category_name = $category['name'] ?? $category['category_name'] ?? '';
            $category_slug = $category['slug'] ?? generate_slug_from_name($category_name);
            
            if (strtolower($category_slug) === strtolower($target_slug)) {
                return $category_slug;
            }
        }
    }
    
    return $target_slug;
}

// =============================================
// FUNZIONE PER NORMALIZZARE IL VALORE ATTUALE
// =============================================
function get_normalized_niche($current_niche, $categories) {
    if (empty($current_niche)) {
        return '';
    }
    
    // Prima converte il valore ENUM in uno slug leggibile
    $readable_slug = enum_to_display_name($current_niche, $categories);
    
    if (!empty($readable_slug)) {
        return $readable_slug;
    }
    
    // Se la conversione non funziona, usa la logica esistente
    $current_slug = generate_slug_from_name($current_niche);
    $mapped_slug = map_category_slug($current_slug, $categories);
    
    return $mapped_slug;
}

// =============================================
// GESTIONE INVIO FORM DI MODIFICA
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $niche = trim($_POST['niche'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $rate = floatval($_POST['rate'] ?? 0);
    
    // CONVERSIONE: da slug nuovo a valore ENUM
    $niche_for_db = map_category_slug($niche, $categories);
    
    $social_handles = [];
    $social_networks = get_active_social_networks();
    foreach ($social_networks as $social) {
        $handle_field = $social['slug'] . '_handle';
        $social_handles[$handle_field] = trim($_POST[$handle_field] ?? '');
    }
    
    if (empty($full_name) || empty($bio) || empty($niche)) {
        $error = "Nome completo, biografia e categoria sono campi obbligatori!";
    } elseif ($rate < 0) {
        $error = "La tariffa non può essere negativa!";
    } elseif (empty($error)) {
        try {
            $profile_image = $influencer['profile_image'] ?? null;
            $old_image_to_delete = null;
            
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = dirname(__DIR__) . '/uploads/profiles/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    if ($_FILES['profile_image']['size'] <= 5 * 1024 * 1024) {
                        $filename = uniqid() . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                            $new_profile_image = 'profiles/' . $filename;
                            
                            if (!empty($influencer['profile_image'])) {
                                $old_image_to_delete = dirname(__DIR__) . '/uploads/' . $influencer['profile_image'];
                            }
                            
                            $profile_image = $new_profile_image;
                        } else {
                            $error = "Errore nel salvataggio dell'immagine!";
                        }
                    } else {
                        $error = "L'immagine è troppo grande! Dimensione massima: 5MB";
                    }
                } else {
                    $error = "Formato immagine non supportato! Usa JPG, PNG o GIF.";
                }
            } elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite server)',
                    UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
                    UPLOAD_ERR_PARTIAL => 'Upload interrotto',
                    UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante',
                    UPLOAD_ERR_CANT_WRITE => 'Errore scrittura file',
                    UPLOAD_ERR_EXTENSION => 'Estensione non permessa'
                ];
                $error_code = $_FILES['profile_image']['error'];
                $error = "Errore upload immagine: " . ($upload_errors[$error_code] ?? 'Errore sconosciuto');
            }
            
            if (empty($error)) {
                $sql = "UPDATE influencers 
                        SET full_name = :full_name, bio = :bio, niche = :niche, 
                            website = :website, rate = :rate,
                            profile_image = :profile_image, updated_at = NOW()";
                
                $params = [
                    ':full_name' => $full_name,
                    ':bio' => $bio,
                    ':niche' => $niche_for_db,
                    ':website' => $website,
                    ':rate' => $rate,
                    ':profile_image' => $profile_image,
                    ':user_id' => $_SESSION['user_id']
                ];
                
                foreach ($social_handles as $field => $value) {
                    $sql .= ", $field = :$field";
                    $params[":$field"] = $value;
                }
                
                $sql .= " WHERE user_id = :user_id";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute($params);
                
                if ($result && $stmt->rowCount() > 0) {
                    if ($old_image_to_delete && file_exists($old_image_to_delete)) {
                        unlink($old_image_to_delete);
                    }
                    
                    $_SESSION['success'] = "Profilo aggiornato con successo!";
                    header('Location: /infl/influencers/dashboard.php');
                    exit();
                } else {
                    $error = "Nessuna modifica effettuata o profilo non trovato";
                }
            }
        } catch (PDOException $e) {
            $error = "Errore di sistema durante l'aggiornamento del profilo. Riprova più tardi.";
        } catch (Exception $e) {
            $error = "Errore: " . $e->getMessage();
        }
    }
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;

// Ottieni il valore normalizzato per la selezione del dropdown
$normalized_niche = get_normalized_niche($influencer['niche'] ?? '', $categories);
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>✏️ Modifica Profilo Influencer</h2>
            <a href="/infl/influencers/dashboard.php" class="btn btn-outline-secondary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success']); ?>
                <?php unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Modifica Profilo -->
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="row">
                        <!-- Colonna Sinistra: Informazioni Base -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required 
                                       value="<?php echo htmlspecialchars_decode(htmlspecialchars($influencer['full_name'] ?? ''), ENT_QUOTES); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="bio" class="form-label">Biografia *</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4" required 
                                          placeholder="Racconta la tua storia, i tuoi interessi..."><?php echo htmlspecialchars($influencer['bio'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="niche" class="form-label">Categoria *</label>
                                <select class="form-select" id="niche" name="niche" required>
                                    <option value="">Seleziona una categoria</option>
                                    <?php 
                                    if (!empty($categories)) {
                                        foreach ($categories as $category): 
                                            if (is_array($category)) {
                                                $category_name = $category['name'] ?? $category['category_name'] ?? '';
                                                $category_slug = $category['slug'] ?? generate_slug_from_name($category_name);
                                                
                                                if (empty($category_slug) || empty($category_name)) {
                                                    continue;
                                                }
                                                
                                                $option_value = $category_slug;
                                                $display_name = $category_name;
                                                
                                                // Confronto CASE INSENSITIVE
                                                $is_selected = (strtolower($normalized_niche) === strtolower($option_value));
                                            ?>
                                                <option value="<?php echo htmlspecialchars($option_value); ?>" 
                                                        <?php echo $is_selected ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($display_name); ?>
                                                </option>
                                            <?php 
                                            }
                                        endforeach; 
                                    }
                                    ?>
                                </select>
                                
                                <?php if (empty($categories)): ?>
                                    <div class="alert alert-warning mt-2">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Nessuna categoria disponibile. Contatta l'amministratore del sistema.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="rate" class="form-label">Tariffa (€) *</label>
                                <input type="number" class="form-control" id="rate" name="rate" min="0" step="0.01" required 
                                       value="<?php echo htmlspecialchars($influencer['rate'] ?? '0'); ?>">
                                <div class="form-text">Tariffa per collaborazione in Euro</div>
                            </div>
                        </div>

                        <!-- Colonna Destra: Immagine Profilo -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Immagine Profilo</label>
                                
                                <!-- Area Upload Immagine Profilo -->
                                <div id="profileImageUploadArea">
                                    <?php 
                                    $current_image_url = '/infl/uploads/placeholder/influencer_admin_edit.png';
                                    $has_custom_image = false;
                                    
                                    if (!empty($influencer['profile_image'])) {
                                        $full_image_path = dirname(__DIR__) . '/uploads/' . $influencer['profile_image'];
                                        if (file_exists($full_image_path)) {
                                            $current_image_url = '/infl/uploads/' . $influencer['profile_image'];
                                            $has_custom_image = true;
                                        }
                                    }
                                    ?>
                                    
                                    <!-- Immagine attuale o placeholder -->
                                    <div id="currentImageSection" class="mb-3">
                                        <img src="<?php echo htmlspecialchars($current_image_url); ?>" 
                                             alt="<?php echo $has_custom_image ? 'Immagine profilo attuale' : 'Immagine profilo'; ?>" 
                                             class="img-thumbnail mb-2" 
                                             style="max-height: 150px; border-radius: 50%;">
                                        
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="changeImageBtn">
                                                <?php if ($has_custom_image): ?>
                                                    📝 Cambia Immagine
                                                <?php else: ?>
                                                    📁 Carica Immagine Personalizzata
                                                <?php endif; ?>
                                            </button>
                                            
                                            <?php if ($has_custom_image): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm" id="removeCurrentImageBtn">
                                                    🗑️ Rimuovi Immagine
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Input file nascosto -->
                                    <input type="file" class="form-control d-none" id="profile_image" name="profile_image" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif">
                                    
                                    <!-- Anteprima nuova immagine -->
                                    <div id="imagePreviewContainer" class="mt-2" style="display: none;">
                                        <p class="text-muted">Anteprima nuova immagine:</p>
                                        <div class="position-relative d-inline-block">
                                            <img id="previewImage" class="img-thumbnail" style="max-height: 150px; border-radius: 50%;">
                                        </div>
                                        <div class="d-flex gap-2 mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="changeNewImageBtn">
                                                📝 Cambia Immagine
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" id="removeNewImageBtn">
                                                🗑️ Rimuovi Immagine
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-text">
                                    Formati supportati: JPG, PNG, GIF. Dimensione massima: 5MB.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="website" class="form-label">Sito Web</label>
                                <input type="url" class="form-control" id="website" name="website" 
                                       value="<?php echo htmlspecialchars($influencer['website'] ?? ''); ?>" 
                                       placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <!-- Social Handles -->
                    <div class="mb-4">
                        <label class="form-label">Social Handles</label>
                        <div class="row">
                            <?php
                            $social_networks = get_active_social_networks();
                            foreach ($social_networks as $social): 
                                $handle_value = $influencer[$social['slug'] . '_handle'] ?? '';
                            ?>
                                <div class="col-md-6 mb-3">
                                    <label for="<?php echo $social['slug']; ?>_handle" class="form-label">
                                        <i class="<?php echo $social['icon']; ?> me-2"></i><?php echo $social['name']; ?>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?php echo $social['base_url']; ?></span>
                                        <input type="text" class="form-control" id="<?php echo $social['slug']; ?>_handle" 
                                               name="<?php echo $social['slug']; ?>_handle" 
                                               value="<?php echo htmlspecialchars($handle_value); ?>" 
                                               placeholder="username">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Pulsanti -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            💾 Aggiorna Profilo
                        </button>
                        <a href="/infl/influencers/dashboard.php" class="btn btn-secondary">Annulla</a>
                    </div>
                    
                    <!-- Campo hidden per rimozione immagine -->
                    <input type="hidden" name="remove_image" id="removeImageField" value="0">
                </form>
            </div>
        </div>

        <!-- Informazioni Profilo -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Informazioni Profilo</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Ultimo aggiornamento:</strong> 
                            <span class="float-end">
                                <?php echo !empty($influencer['updated_at']) ? date('d/m/Y H:i', strtotime($influencer['updated_at'])) : 'Mai'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Profilo creato:</strong> 
                            <span class="float-end">
                                <?php echo !empty($influencer['created_at']) ? date('d/m/Y H:i', strtotime($influencer['created_at'])) : 'Data non disponibile'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Categoria attuale:</strong> 
                            <span class="float-end">
                                <?php 
                                $current_niche_display = htmlspecialchars($influencer['niche'] ?? 'Non impostata');
                                // Converti il valore ENUM in nome leggibile
                                $niche_display_map = [
                                    'beauty' => 'Beauty & Makeup',
                                    'fitness' => 'Fitness & Wellness',
                                    'finance' => 'Finance & Business',
                                    'entertainment' => 'Entertainment',
                                    'pet' => 'Pet',
                                    'education' => 'Education',
                                    'fashion' => 'Fashion',
                                    'lifestyle' => 'Lifestyle',
                                    'food' => 'Food',
                                    'travel' => 'Travel',
                                    'gaming' => 'Gaming',
                                    'tech' => 'Tech'
                                ];
                                
                                $lower_niche = strtolower(trim($current_niche_display));
                                if (isset($niche_display_map[$lower_niche])) {
                                    $current_niche_display = $niche_display_map[$lower_niche];
                                } else {
                                    // Cerca nel database per nomi personalizzati
                                    foreach ($categories as $cat) {
                                        if (is_array($cat)) {
                                            $cat_slug = $cat['slug'] ?? '';
                                            $cat_name = $cat['name'] ?? '';
                                            
                                            if (strtolower($cat_slug) === $lower_niche || 
                                                strtolower($cat_name) === $lower_niche) {
                                                $current_niche_display = $cat_name;
                                                break;
                                            }
                                        }
                                    }
                                    $current_niche_display = ucfirst($lower_niche);
                                }
                                echo $current_niche_display;
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Categorie disponibili:</strong> 
                            <span class="float-end badge bg-info">
                                <?php echo count($categories); ?> categorie
                            </span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Tariffa attuale:</strong> 
                            <span class="float-end">
                                € <?php echo !empty($influencer['rate']) ? number_format($influencer['rate'], 2) : '0.00'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Social configurati:</strong> 
                            <span class="float-end badge bg-success">
                                <?php 
                                $configured_socials = 0;
                                if ($social_networks) {
                                    foreach ($social_networks as $social) {
                                        $handle_field = $social['slug'] . '_handle';
                                        if (!empty($influencer[$handle_field])) {
                                            $configured_socials++;
                                        }
                                    }
                                }
                                echo $configured_socials . '/' . count($social_networks);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($influencer['profile_image'])): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-2">
                            <strong>Immagine profilo:</strong> 
                            <span class="float-end badge bg-success">Caricata</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <hr>
                <small class="text-muted">
                    <strong>Suggerimento:</strong> Mantieni il tuo profilo sempre aggiornato per attrarre i brand più adatti alla tua nicchia.
                    Assicurati che tutti i campi obbligatori siano compilati e che le informazioni siano accurate.
                </small>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profileForm');
    const profileImageInput = document.getElementById('profile_image');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const previewImage = document.getElementById('previewImage');
    const removeImageField = document.getElementById('removeImageField');
    const currentImageSection = document.getElementById('currentImageSection');
    
    const changeImageBtn = document.getElementById('changeImageBtn');
    const removeCurrentImageBtn = document.getElementById('removeCurrentImageBtn');
    const changeNewImageBtn = document.getElementById('changeNewImageBtn');
    const removeNewImageBtn = document.getElementById('removeNewImageBtn');
    
    if (changeImageBtn) {
        changeImageBtn.addEventListener('click', function() {
            profileImageInput.click();
        });
    }
    
    if (removeCurrentImageBtn) {
        removeCurrentImageBtn.addEventListener('click', function() {
            if (confirm('Sei sicuro di voler rimuovere l\'immagine profilo attuale?')) {
                removeImageField.value = '1';
                showPlaceholderImage();
            }
        });
    }
    
    if (changeNewImageBtn) {
        changeNewImageBtn.addEventListener('click', function() {
            profileImageInput.click();
        });
    }
    
    if (removeNewImageBtn) {
        removeNewImageBtn.addEventListener('click', function() {
            resetImageInput();
            if (currentImageSection && removeImageField.value === '0') {
                imagePreviewContainer.style.display = 'none';
                currentImageSection.style.display = 'block';
            } else {
                imagePreviewContainer.style.display = 'none';
                showPlaceholderImage();
            }
        });
    }
    
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const maxSize = 5 * 1024 * 1024;
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Formato file non supportato. Usa JPG, PNG o GIF.');
                    resetImageInput();
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('Il file è troppo grande. Dimensione massima: 5MB.');
                    resetImageInput();
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    imagePreviewContainer.style.display = 'block';
                    
                    if (currentImageSection) {
                        currentImageSection.style.display = 'none';
                    }
                    
                    removeImageField.value = '0';
                }
                reader.readAsDataURL(file);
            }
        });
    }
    
    function resetImageInput() {
        if (profileImageInput) {
            profileImageInput.value = '';
        }
        removeImageField.value = '0';
    }
    
    function showPlaceholderImage() {
        if (currentImageSection) {
            currentImageSection.innerHTML = `
                <img src="/infl/uploads/placeholder/influencer_admin_edit.png" 
                     alt="Immagine profilo" 
                     class="img-thumbnail mb-2" 
                     style="max-height: 150px; border-radius: 50%;">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="changeImageBtn">
                        📁 Carica Immagine Personalizzata
                    </button>
                </div>
            `;
            currentImageSection.style.display = 'block';
            
            document.getElementById('changeImageBtn').addEventListener('click', function() {
                profileImageInput.click();
            });
        }
        imagePreviewContainer.style.display = 'none';
    }
    
    form.addEventListener('submit', function(e) {
        const fullName = document.getElementById('full_name').value.trim();
        const bio = document.getElementById('bio').value.trim();
        const niche = document.getElementById('niche').value;
        const rate = document.getElementById('rate').value;
        
        if (!fullName) {
            e.preventDefault();
            alert('Il nome completo è obbligatorio');
            document.getElementById('full_name').focus();
            return false;
        }
        
        if (!bio) {
            e.preventDefault();
            alert('La biografia è obbligatoria');
            document.getElementById('bio').focus();
            return false;
        }
        
        if (!niche) {
            e.preventDefault();
            alert('La categoria è obbligatoria');
            document.getElementById('niche').focus();
            return false;
        }
        
        if (!rate || parseFloat(rate) < 0) {
            e.preventDefault();
            alert('Inserisci una tariffa valida (numero positivo)');
            document.getElementById('rate').focus();
            return false;
        }
        
        if (profileImageInput && profileImageInput.files.length > 0) {
            const file = profileImageInput.files[0];
            const maxSize = 5 * 1024 * 1024;
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                alert('Formato file non supportato. Usa JPG, PNG o GIF.');
                return false;
            }
            
            if (file.size > maxSize) {
                e.preventDefault();
                alert('Il file è troppo grande. Dimensione massima: 5MB.');
                return false;
            }
        }
    });
    
    const websiteInput = document.getElementById('website');
    if (websiteInput) {
        websiteInput.addEventListener('blur', function() {
            const url = this.value.trim();
            if (url && !isValidUrl(url)) {
                this.classList.add('is-invalid');
                if (!this.nextElementSibling || !this.nextElementSibling.classList.contains('invalid-feedback')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'Inserisci un URL valido (es. https://example.com)';
                    this.parentNode.appendChild(errorDiv);
                }
            } else {
                this.classList.remove('is-invalid');
                const errorDiv = this.nextElementSibling;
                if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                    errorDiv.remove();
                }
            }
        });
    }
    
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    const nicheSelect = document.getElementById('niche');
    if (nicheSelect && nicheSelect.options.length <= 1) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning mt-2';
        alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Nessuna categoria disponibile. Contatta l\'amministratore del sistema.';
        nicheSelect.parentNode.appendChild(alertDiv);
    }
});
</script>

<style>
.progress {
    height: 20px;
}
.progress-bar {
    font-weight: bold;
}
.alert-info {
    border-left: 4px solid #0dcaf0;
}
.card-header.bg-light {
    background-color: #f8f9fa !important;
    border-bottom: 1px solid #dee2e6;
}
.form-text small {
    font-size: 0.875em;
}
.img-thumbnail {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.25rem;
}
.btn-sm {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
}
.badge {
    font-size: 0.75em;
}
.input-group-text {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    font-size: 0.875rem;
}
</style>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>