<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Verifica che l'utente sia autenticato
if (!is_logged_in()) {
    header('Location: /infl/auth/login.php');
    exit();
}

// Verifica che l'utente non abbia già un profilo influencer
$user_id = $_SESSION['user_id'];
$check_sql = "SELECT id FROM influencers WHERE user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $_SESSION['error'] = "Hai già un profilo influencer!";
    header('Location: /infl/influencers/dashboard.php');
    exit();
}

$error = '';
$success = '';

// Gestione del form di creazione profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validazione e sanitizzazione dei dati
    $name = trim($_POST['name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $follower_count = intval($_POST['follower_count'] ?? 0);
    $niche = trim($_POST['niche'] ?? '');
    $social_handle = trim($_POST['social_handle'] ?? '');
    
    // Validazione base
    if (empty($name) || empty($bio) || empty($niche) || empty($social_handle)) {
        $error = "Tutti i campi obbligatori devono essere compilati!";
    } elseif ($follower_count < 0) {
        $error = "Il numero di follower non può essere negativo!";
    } else {
        try {
            // Gestione upload immagine profilo
            $profile_image = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/profiles/';
                
                // Verifica che la cartella esista
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array(strtolower($file_extension), $allowed_extensions)) {
                    // Verifica dimensione file (max 5MB)
                    if ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) {
                        $error = "L'immagine è troppo grande! Dimensione massima: 5MB";
                    } else {
                        $filename = uniqid() . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                            $profile_image = 'profiles/' . $filename;
                        } else {
                            $error = "Errore nel caricamento dell'immagine!";
                        }
                    }
                } else {
                    $error = "Formato immagine non supportato! Usa JPG, PNG o GIF.";
                }
            }
            
            if (empty($error)) {
                // Inserimento nel database
                $sql = "INSERT INTO influencers (user_id, name, bio, follower_count, niche, social_handle, profile_image, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ississs", $user_id, $name, $bio, $follower_count, $niche, $social_handle, $profile_image);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Profilo influencer creato con successo!";
                    header('Location: /infl/influencers/dashboard.php');
                    exit();
                } else {
                    $error = "Errore nella creazione del profilo: " . $conn->error;
                }
            }
        } catch (Exception $e) {
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
    <title>Crea Profilo Influencer - Influencer Marketplace</title>
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
            max-width: 600px;
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
        textarea:focus {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Crea Profilo</h1>
            <p>Completa il tuo profilo influencer per iniziare a collaborare</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Nome Completo *</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="bio">Biografia *</label>
                <textarea id="bio" name="bio" required 
                          placeholder="Racconta la tua storia, i tuoi interessi..."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="follower_count">Numero di Follower</label>
                <input type="number" id="follower_count" name="follower_count" min="0" 
                       value="<?php echo htmlspecialchars($_POST['follower_count'] ?? ''); ?>">
                <div class="form-help">Inserisci il numero totale dei tuoi follower su tutte le piattaforme</div>
            </div>

            <div class="form-group">
                <label for="niche">Niche *</label>
                <input type="text" id="niche" name="niche" required 
                       value="<?php echo htmlspecialchars($_POST['niche'] ?? ''); ?>" 
                       placeholder="Es: Beauty, Gaming, Travel...">
            </div>

            <div class="form-group">
                <label for="social_handle">Social Handle Principale *</label>
                <input type="text" id="social_handle" name="social_handle" required 
                       value="<?php echo htmlspecialchars($_POST['social_handle'] ?? ''); ?>" 
                       placeholder="Es: @tuonome">
            </div>

            <div class="form-group">
                <label for="profile_image">Immagine Profilo</label>
                <input type="file" id="profile_image" name="profile_image" 
                       accept="image/jpeg,image/png,image/gif">
                <div class="form-help">Formati supportati: JPG, PNG, GIF (max 5MB)</div>
            </div>

            <button type="submit" class="btn btn-block">Crea Profilo</button>
        </form>

        <div class="back-link">
            <a href="/infl/influencers/dashboard.php">← Torna alla Dashboard</a>
        </div>
    </div>
</body>
</html>