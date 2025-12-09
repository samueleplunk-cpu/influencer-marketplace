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
    // Verifica se la colonna profile_image esiste
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
        // Aggiungi la colonna profile_image
        $alter_table_stmt = $pdo->prepare("
            ALTER TABLE influencers 
            ADD COLUMN profile_image VARCHAR(255) NULL AFTER rate
        ");
        $alter_table_stmt->execute();
        
        error_log("Colonna profile_image aggiunta alla tabella influencers");
    }
} catch (PDOException $e) {
    error_log("Errore nella verifica/aggiunta colonna profile_image: " . $e->getMessage());
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
    // Includi le funzioni per le categorie
    require_once dirname(__DIR__) . '/includes/category_functions.php';
    
    // Recupera tutte le categorie attive
    $categories = get_active_categories($pdo);
    
    if (empty($categories)) {
        error_log("Nessuna categoria attiva trovata nel database");
        // Fallback alle categorie predefinite
        $categories = [
            ['id' => 'fashion', 'name' => 'Fashion'],
            ['id' => 'lifestyle', 'name' => 'Lifestyle'],
            ['id' => 'beauty', 'name' => 'Beauty & Makeup'],
            ['id' => 'food', 'name' => 'Food'],
            ['id' => 'travel', 'name' => 'Travel'],
            ['id' => 'gaming', 'name' => 'Gaming'],
            ['id' => 'fitness', 'name' => 'Fitness & Wellness'],
            ['id' => 'entertainment', 'name' => 'Entertainment'],
            ['id' => 'tech', 'name' => 'Tech'],
            ['id' => 'finance', 'name' => 'Finance & Business'],
            ['id' => 'pet', 'name' => 'Pet'],
            ['id' => 'education', 'name' => 'Education']
        ];
    }
} catch (Exception $e) {
    error_log("Errore nel recupero delle categorie: " . $e->getMessage());
    // Fallback in caso di errore
    $categories = [
        ['id' => 'fashion', 'name' => 'Fashion'],
        ['id' => 'lifestyle', 'name' => 'Lifestyle'],
        ['id' => 'beauty', 'name' => 'Beauty & Makeup'],
        ['id' => 'food', 'name' => 'Food'],
        ['id' => 'travel', 'name' => 'Travel'],
        ['id' => 'gaming', 'name' => 'Gaming'],
        ['id' => 'fitness', 'name' => 'Fitness & Wellness'],
        ['id' => 'entertainment', 'name' => 'Entertainment'],
        ['id' => 'tech', 'name' => 'Tech'],
        ['id' => 'finance', 'name' => 'Finance & Business'],
        ['id' => 'pet', 'name' => 'Pet'],
        ['id' => 'education', 'name' => 'Education']
    ];
}

// =============================================
// GESTIONE INVIO FORM DI MODIFICA
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validazione e sanitizzazione dei dati
    $full_name = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $niche = trim($_POST['niche'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $rate = floatval($_POST['rate'] ?? 0);
    
    // Recupera gli handle social dai social network attivi
    $social_handles = [];
    $social_networks = get_active_social_networks();
    foreach ($social_networks as $social) {
        $handle_field = $social['slug'] . '_handle';
        $social_handles[$handle_field] = trim($_POST[$handle_field] ?? '');
    }
    
    // Validazione campi obbligatori
    if (empty($full_name) || empty($bio) || empty($niche)) {
        $error = "Nome completo, biografia e categoria sono campi obbligatori!";
    } elseif ($rate < 0) {
        $error = "La tariffa non può essere negativa!";
    } else {
        try {
            // CORREZIONE: Gestione sicura dell'immagine profilo
            $profile_image = $influencer['profile_image'] ?? null;
            $old_image_to_delete = null;
            
            // Gestione upload nuova immagine profilo
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = dirname(__DIR__) . '/uploads/profiles/';
                
                // Crea directory se non esiste
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
                            
                            // Se c'era un'immagine precedente, segnala per la cancellazione
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
                // Gestisci errori di upload (tranne "nessun file selezionato")
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
                // Costruisci dinamicamente la query per gli handle social
                $sql = "UPDATE influencers 
                        SET full_name = :full_name, bio = :bio, niche = :niche, 
                            website = :website, rate = :rate,
                            profile_image = :profile_image, updated_at = NOW()";
                
                // Aggiungi i campi per gli handle social
                $params = [
                    ':full_name' => $full_name,
                    ':bio' => $bio,
                    ':niche' => $niche,
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
                    // Cancella vecchia immagine SOLO dopo update riuscito
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
            error_log("Database error in edit-profile: " . $e->getMessage());
            $error = "Errore di sistema durante l'aggiornamento del profilo. Riprova più tardi.";
        } catch (Exception $e) {
            error_log("General error in edit-profile: " . $e->getMessage());
            $error = "Errore: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Profilo - Influencer Marketplace</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 700px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background-color: #fee;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-success {
            background-color: #e8f5e8;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        input[type="number"],
        textarea,
        select,
        input[type="file"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            text-decoration: none;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .btn-outline {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
        }

        .form-help {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .current-image {
            margin-top: 10px;
            text-align: center;
        }

        .current-image img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 50%;
            border: 3px solid #e1e5e9;
            object-fit: cover;
        }

        .image-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            border: 3px solid #e1e5e9;
            color: #666;
            font-size: 14px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .button-group .btn {
            flex: 1;
        }

        .social-handles {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .input-group {
            display: flex;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 2px solid #e1e5e9;
            border-right: none;
            padding: 12px 15px;
            border-radius: 8px 0 0 8px;
            font-size: 14px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .input-group .form-control {
            border-radius: 0 8px 8px 0;
            border-left: none;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }

            .input-group-text {
                max-width: 150px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ Modifica Profilo</h1>
            <p>Aggiorna le informazioni del tuo profilo influencer</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="full_name">Nome Completo *</label>
                <input type="text" id="full_name" name="full_name" required 
                       value="<?php echo htmlspecialchars($influencer['full_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="bio">Biografia *</label>
                <textarea id="bio" name="bio" required 
                          placeholder="Racconta la tua storia, i tuoi interessi..."><?php echo htmlspecialchars($influencer['bio'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="niche">Categoria *</label>
                <select id="niche" name="niche" required>
                    <option value="">Seleziona una categoria</option>
                    <?php foreach ($categories as $category): 
                        // Gestisce sia il nuovo formato (con id) che il vecchio (array semplice)
                        $category_id = isset($category['id']) ? $category['id'] : $category;
                        $category_name = isset($category['name']) ? $category['name'] : ucfirst($category);
                        $is_selected = ($influencer['niche'] ?? '') === $category_id;
                    ?>
                        <option value="<?php echo htmlspecialchars($category_id); ?>" 
                                <?php echo $is_selected ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Social Handles</label>
                <div class="social-handles">
                    <?php
                    $social_networks = get_active_social_networks();
                    foreach ($social_networks as $social): 
                        $handle_value = $influencer[$social['slug'] . '_handle'] ?? '';
                    ?>
                        <div class="mb-3">
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

            <div class="form-group">
                <label for="website">Sito Web</label>
                <input type="text" id="website" name="website" 
                       value="<?php echo htmlspecialchars($influencer['website'] ?? ''); ?>" 
                       placeholder="https://...">
            </div>

            <div class="form-group">
                <label for="rate">Tariffa (€) *</label>
                <input type="number" id="rate" name="rate" min="0" step="0.01" required 
                       value="<?php echo htmlspecialchars($influencer['rate'] ?? '0'); ?>">
                <div class="form-help">Tariffa per collaborazione in Euro</div>
            </div>

            <div class="form-group">
                <label for="profile_image">Nuova Immagine Profilo</label>
                <input type="file" id="profile_image" name="profile_image" 
                       accept="image/jpeg,image/png,image/gif">
                <div class="form-help">Formati supportati: JPG, PNG, GIF (max 5MB)</div>
                
                <div class="current-image">
    <p><strong>Immagine attuale:</strong></p>
    <?php 
    $current_image_src = '/infl/uploads/placeholder/influencer_admin_edit.png';
    $has_custom_image = false;
    
    if (!empty($influencer['profile_image'])) {
        $full_image_path = dirname(__DIR__) . '/uploads/' . $influencer['profile_image'];
        if (file_exists($full_image_path)) {
            $current_image_src = '/infl/uploads/' . $influencer['profile_image'];
            $has_custom_image = true;
        }
    }
    ?>
    
    <img src="<?php echo htmlspecialchars($current_image_src); ?>" 
         alt="<?php echo $has_custom_image ? 'Immagine profilo attuale' : 'Placeholder profilo'; ?>"
         style="max-width: 150px; max-height: 150px; border-radius: 50%; border: 3px solid #e1e5e9; object-fit: cover;">
    
    <?php if ($has_custom_image): ?>
        <p class="form-help mt-2">Immagine personalizzata caricata</p>
    <?php else: ?>
        <p class="form-help mt-2">Placeholder predefinito - carica una tua immagine</p>
    <?php endif; ?>
</div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-block">Aggiorna Profilo</button>
                <a href="/infl/influencers/dashboard.php" class="btn btn-outline">Annulla</a>
            </div>
        </form>

        <div class="back-link">
            <a href="/infl/influencers/dashboard.php">← Torna alla Dashboard</a>
        </div>
    </div>
</body>
</html>