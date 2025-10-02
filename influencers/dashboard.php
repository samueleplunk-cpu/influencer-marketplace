<?php
session_start();

// Verifica se l'utente è autenticato e è un influencer
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

// Recupera i dati dell'influencer
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
$stmt->execute([$user_id]);
$influencer = $stmt->fetch(PDO::FETCH_ASSOC);

// Se non esiste il profilo influencer, reindirizza alla creazione
if (!$influencer) {
    header('Location: create-profile.php');
    exit;
}

// Gestione aggiornamento profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $bio = $_POST['bio'];
    $niche = $_POST['niche'];
    $instagram_handle = $_POST['instagram_handle'];
    $tiktok_handle = $_POST['tiktok_handle'];
    $youtube_handle = $_POST['youtube_handle'];
    $website = $_POST['website'];
    $rate = $_POST['rate'];
    
    $update_stmt = $pdo->prepare("
        UPDATE influencers 
        SET full_name = ?, bio = ?, niche = ?, instagram_handle = ?, 
            tiktok_handle = ?, youtube_handle = ?, website = ?, rate = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    
    $update_stmt->execute([
        $full_name, $bio, $niche, $instagram_handle, 
        $tiktok_handle, $youtube_handle, $website, $rate, $user_id
    ]);
    
    $success_message = "Profilo aggiornato con successo!";
    // Ricarica i dati
    $stmt->execute([$user_id]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Influencer - Influencer Marketplace</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .dashboard-nav {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .nav-tabs {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .nav-tab {
            padding: 10px 20px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .nav-tab.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn {
            padding: 12px 30px;
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Benvenuto, <?php echo htmlspecialchars($influencer['full_name'] ?? 'Influencer'); ?>! 👋</h1>
            <p>Gestisci il tuo profilo, portfolio e collaborazioni</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div class="stat-label">Collaborazioni Attive</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div class="stat-label">Proposte Ricevute</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">€<?php echo htmlspecialchars($influencer['rate'] ?? '0'); ?></div>
                <div class="stat-label">Tariffa Media</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">4.8</div>
                <div class="stat-label">Rating</div>
            </div>
        </div>

        <div class="dashboard-nav">
            <div class="nav-tabs">
                <div class="nav-tab active" onclick="switchTab('profile')">Profilo</div>
                <div class="nav-tab" onclick="switchTab('portfolio')">Portfolio</div>
                <div class="nav-tab" onclick="switchTab('collaborations')">Collaborazioni</div>
                <div class="nav-tab" onclick="switchTab('analytics')">Analytics</div>
                <div class="nav-tab" onclick="switchTab('settings')">Impostazioni</div>
            </div>
        </div>

        <!-- Tab Profilo -->
        <div id="profile" class="tab-content active">
            <h2>Modifica Profilo</h2>
            <?php if (isset($success_message)): ?>
                <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="full_name">Nome Completo</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           value="<?php echo htmlspecialchars($influencer['full_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="bio">Biografia</label>
                    <textarea id="bio" name="bio" class="form-control" rows="4" required><?php echo htmlspecialchars($influencer['bio'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="niche">Niche</label>
                    <select id="niche" name="niche" class="form-control" required>
                        <option value="">Seleziona una niche</option>
                        <option value="lifestyle" <?php echo ($influencer['niche'] ?? '') === 'lifestyle' ? 'selected' : ''; ?>>Lifestyle</option>
                        <option value="fashion" <?php echo ($influencer['niche'] ?? '') === 'fashion' ? 'selected' : ''; ?>>Fashion</option>
                        <option value="beauty" <?php echo ($influencer['niche'] ?? '') === 'beauty' ? 'selected' : ''; ?>>Beauty</option>
                        <option value="fitness" <?php echo ($influencer['niche'] ?? '') === 'fitness' ? 'selected' : ''; ?>>Fitness</option>
                        <option value="travel" <?php echo ($influencer['niche'] ?? '') === 'travel' ? 'selected' : ''; ?>>Travel</option>
                        <option value="food" <?php echo ($influencer['niche'] ?? '') === 'food' ? 'selected' : ''; ?>>Food</option>
                        <option value="tech" <?php echo ($influencer['niche'] ?? '') === 'tech' ? 'selected' : ''; ?>>Tech</option>
                        <option value="gaming" <?php echo ($influencer['niche'] ?? '') === 'gaming' ? 'selected' : ''; ?>>Gaming</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rate">Tariffa per Collaborazione (€)</label>
                    <input type="number" id="rate" name="rate" class="form-control" 
                           value="<?php echo htmlspecialchars($influencer['rate'] ?? ''); ?>" required>
                </div>

                <h3>Social Media</h3>
                <div class="form-group">
                    <label for="instagram_handle">Instagram Handle</label>
                    <input type="text" id="instagram_handle" name="instagram_handle" class="form-control" 
                           value="<?php echo htmlspecialchars($influencer['instagram_handle'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="tiktok_handle">TikTok Handle</label>
                    <input type="text" id="tiktok_handle" name="tiktok_handle" class="form-control" 
                           value="<?php echo htmlspecialchars($influencer['tiktok_handle'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="youtube_handle">YouTube Handle</label>
                    <input type="text" id="youtube_handle" name="youtube_handle" class="form-control" 
                           value="<?php echo htmlspecialchars($influencer['youtube_handle'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="website">Sito Web</label>
                    <input type="url" id="website" name="website" class="form-control" 
                           value="<?php echo htmlspecialchars($influencer['website'] ?? ''); ?>">
                </div>

                <button type="submit" name="update_profile" class="btn">Aggiorna Profilo</button>
            </form>
        </div>

        <!-- Altri tab (placeholder per ora) -->
        <div id="portfolio" class="tab-content">
            <h2>Il Tuo Portfolio</h2>
            <p>Qui gestirai le immagini del tuo portfolio.</p>
        </div>

        <div id="collaborations" class="tab-content">
            <h2>Le Tue Collaborazioni</h2>
            <p>Qui vedrai le tue collaborazioni attive e passate.</p>
        </div>

        <div id="analytics" class="tab-content">
            <h2>Analytics</h2>
            <p>Statistiche e performance dei tuoi contenuti.</p>
        </div>

        <div id="settings" class="tab-content">
            <h2>Impostazioni Account</h2>
            <p>Gestisci le impostazioni del tuo account.</p>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        function switchTab(tabName) {
            // Nascondi tutti i tab
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Rimuovi active da tutti i tab nav
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Mostra il tab selezionato
            document.getElementById(tabName).classList.add('active');
            
            // Aggiungi active al tab nav cliccato
            event.target.classList.add('active');
        }
    </script>
</body>
</html>