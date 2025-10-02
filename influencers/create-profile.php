<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'influencer') {
    header('Location: ../login.php');
    exit;
}

// Configurazione database - MODIFICA QUESTE CREDENZIALI CON LE TUE
$host = 'localhost';
$dbname = 'influencer_marketplace';
$username = 'sam';
$password = 'A6Hd&Q%plvx4lxp7';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione al database fallita: " . $e->getMessage());
}

// Verifica se esiste già un profilo
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
$stmt->execute([$user_id]);
$existing_profile = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_profile) {
    header('Location: dashboard.php');
    exit;
}

// Gestione creazione profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $bio = $_POST['bio'];
    $niche = $_POST['niche'];
    $instagram_handle = $_POST['instagram_handle'];
    $tiktok_handle = $_POST['tiktok_handle'];
    $youtube_handle = $_POST['youtube_handle'];
    $website = $_POST['website'];
    $rate = $_POST['rate'];
    
    $insert_stmt = $pdo->prepare("
        INSERT INTO influencers (user_id, full_name, bio, niche, instagram_handle, tiktok_handle, youtube_handle, website, rate, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if ($insert_stmt->execute([$user_id, $full_name, $bio, $niche, $instagram_handle, $tiktok_handle, $youtube_handle, $website, $rate])) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error_message = "Errore nella creazione del profilo. Riprova.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Profilo - Influencer Marketplace</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .create-profile-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn {
            padding: 15px 40px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: #5a6fd8;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="create-profile-container">
        <h1>Crea il Tuo Profilo Influencer 🚀</h1>
        <p>Completa il tuo profilo per iniziare a collaborare con i brand.</p>
        
        <?php if (isset($error_message)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="full_name">Nome Completo *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="bio">Biografia *</label>
                <textarea id="bio" name="bio" class="form-control" rows="4" required placeholder="Racconta la tua storia, i tuoi interessi e il tuo valore come influencer..."></textarea>
            </div>

            <div class="form-group">
                <label for="niche">Niche Principale *</label>
                <select id="niche" name="niche" class="form-control" required>
                    <option value="">Seleziona la tua niche</option>
                    <option value="lifestyle">Lifestyle</option>
                    <option value="fashion">Fashion</option>
                    <option value="beauty">Beauty</option>
                    <option value="fitness">Fitness</option>
                    <option value="travel">Travel</option>
                    <option value="food">Food</option>
                    <option value="tech">Tech</option>
                    <option value="gaming">Gaming</option>
                </select>
            </div>

            <div class="form-group">
                <label for="rate">Tariffa per Collaborazione (€) *</label>
                <input type="number" id="rate" name="rate" class="form-control" required placeholder="Es. 500">
            </div>

            <h3>Social Media</h3>
            <div class="form-group">
                <label for="instagram_handle">Instagram Handle</label>
                <input type="text" id="instagram_handle" name="instagram_handle" class="form-control" placeholder="@tuohandle">
            </div>

            <div class="form-group">
                <label for="tiktok_handle">TikTok Handle</label>
                <input type="text" id="tiktok_handle" name="tiktok_handle" class="form-control" placeholder="@tuohandle">
            </div>

            <div class="form-group">
                <label for="youtube_handle">YouTube Handle</label>
                <input type="text" id="youtube_handle" name="youtube_handle" class="form-control" placeholder="@tuohandle">
            </div>

            <div class="form-group">
                <label for="website">Sito Web</label>
                <input type="url" id="website" name="website" class="form-control" placeholder="https://tuosito.com">
            </div>

            <button type="submit" class="btn">Crea Profilo</button>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>