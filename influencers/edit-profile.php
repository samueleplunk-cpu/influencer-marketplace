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
// GESTIONE INVIO FORM DI MODIFICA
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validazione e sanitizzazione dei dati
    $full_name = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $niche = trim($_POST['niche'] ?? '');
    $instagram_handle = trim($_POST['instagram_handle'] ?? '');
    $tiktok_handle = trim($_POST['tiktok_handle'] ?? '');
    $youtube_handle = trim($_POST['youtube_handle'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $rate = floatval($_POST['rate'] ?? 0);
    
    // Validazione campi obbligatori
    if (empty($full_name) || empty($bio) || empty($niche)) {
        $error = "Nome completo, biografia e niche sono campi obbligatori!";
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
                // Query UPDATE con tutti i campi corretti
                $sql = "UPDATE influencers 
                        SET full_name = :full_name, bio = :bio, niche = :niche, 
                            instagram_handle = :instagram_handle, 
                            tiktok_handle = :tiktok_handle, 
                            youtube_handle = :youtube_handle, 
                            website = :website, rate = :rate,
                            profile_image = :profile_image, updated_at = NOW() 
                        WHERE user_id = :user_id";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    ':full_name' => $full_name,
                    ':bio' => $bio,
                    ':niche' => $niche,
                    ':instagram_handle' => $instagram_handle,
                    ':tiktok_handle' => $tiktok_handle,
                    ':youtube_handle' => $youtube_handle,
                    ':website' => $website,
                    ':rate' => $rate,
                    ':profile_image' => $profile_image,
                    ':user_id' => $_SESSION['user_id']
                ]);
                
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

// Valori possibili per categoria (dalla struttura enum)
$niche_options = ['fashion', 'lifestyle', 'beauty', 'food', 'travel', 'gaming', 'fitness', 'entertainment', 'tech', 'finance', 'pet', 'education'];
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
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .social-handles {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
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
        <option value="fashion" <?php echo ($influencer['niche'] ?? '') === 'fashion' ? 'selected' : ''; ?>>Fashion</option>
        <option value="lifestyle" <?php echo ($influencer['niche'] ?? '') === 'lifestyle' ? 'selected' : ''; ?>>Lifestyle</option>
        <option value="beauty" <?php echo ($influencer['niche'] ?? '') === 'beauty' ? 'selected' : ''; ?>>Beauty & Makeup</option>
        <option value="food" <?php echo ($influencer['niche'] ?? '') === 'food' ? 'selected' : ''; ?>>Food</option>
        <option value="travel" <?php echo ($influencer['niche'] ?? '') === 'travel' ? 'selected' : ''; ?>>Travel</option>
        <option value="gaming" <?php echo ($influencer['niche'] ?? '') === 'gaming' ? 'selected' : ''; ?>>Gaming</option>
        <option value="fitness" <?php echo ($influencer['niche'] ?? '') === 'fitness' ? 'selected' : ''; ?>>Fitness & Wellness</option>
        <option value="entertainment" <?php echo ($influencer['niche'] ?? '') === 'entertainment' ? 'selected' : ''; ?>>Entertainment</option>
        <option value="tech" <?php echo ($influencer['niche'] ?? '') === 'tech' ? 'selected' : ''; ?>>Tech</option>
        <option value="finance" <?php echo ($influencer['niche'] ?? '') === 'finance' ? 'selected' : ''; ?>>Finance & Business</option>
        <option value="pet" <?php echo ($influencer['niche'] ?? '') === 'pet' ? 'selected' : ''; ?>>Pet</option>
        <option value="education" <?php echo ($influencer['niche'] ?? '') === 'education' ? 'selected' : ''; ?>>Education</option>
    </select>
</div>

            <div class="form-group">
                <label>Social Handles</label>
                <div class="social-handles">
                    <div>
                        <label for="instagram_handle">Instagram</label>
                        <input type="text" id="instagram_handle" name="instagram_handle" 
                               value="<?php echo htmlspecialchars($influencer['instagram_handle'] ?? ''); ?>" 
                               placeholder="@username">
                    </div>
                    <div>
                        <label for="tiktok_handle">TikTok</label>
                        <input type="text" id="tiktok_handle" name="tiktok_handle" 
                               value="<?php echo htmlspecialchars($influencer['tiktok_handle'] ?? ''); ?>" 
                               placeholder="@username">
                    </div>
                    <div>
                        <label for="youtube_handle">YouTube</label>
                        <input type="text" id="youtube_handle" name="youtube_handle" 
                               value="<?php echo htmlspecialchars($influencer['youtube_handle'] ?? ''); ?>" 
                               placeholder="@username o nome canale">
                    </div>
                    <div>
                        <label for="website">Sito Web</label>
                        <input type="text" id="website" name="website" 
                               value="<?php echo htmlspecialchars($influencer['website'] ?? ''); ?>" 
                               placeholder="https://...">
                    </div>
                </div>
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
                    <?php if (!empty($influencer['profile_image'])): ?>
                        <?php
                        $image_path = '/infl/uploads/' . $influencer['profile_image'];
                        $full_image_path = dirname(__DIR__) . '/uploads/' . $influencer['profile_image'];
                        ?>
                        <?php if (file_exists($full_image_path)): ?>
                            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                 alt="Immagine profilo attuale">
                        <?php else: ?>
                            <div class="image-placeholder">
                                Immagine non trovata sul server
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="image-placeholder">
                            Nessuna immagine
                        </div>
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