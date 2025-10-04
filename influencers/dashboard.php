<?php

// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO
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
// INCLUSIONE HEADER CON PERCORSO ASSOLUTO
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;

// =============================================
// RECUPERO DATI INFLUENCER DAL DATABASE
// =============================================
$influencer = null;
$error = '';
$success = '';

try {
    // QUERY CORRETTA - RATING SENZA 'S'
    $stmt = $pdo->prepare("
        SELECT id, user_id, full_name, bio, niche, 
               instagram_handle, tiktok_handle, youtube_handle, 
               website, rate, profile_image, profile_views, rating,
               created_at, updated_at 
        FROM influencers 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo: " . $e->getMessage();
}

// =============================================
// CONTENUTO PRINCIPALE DELLA DASHBOARD
// =============================================
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dashboard Influencer</h2>
            <?php if (!$influencer): ?>
                <a href="create-profile.php" class="btn btn-primary">
                    Crea Profilo Influencer
                </a>
            <?php else: ?>
                <a href="/infl/auth/logout.php" class="btn btn-outline-primary">
                    Logout
                </a>
            <?php endif; ?>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'profile_created'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Profilo creato con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Sezione: Profilo Mancante -->
        <?php if (!$influencer): ?>
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h4 class="card-title text-warning">Profilo Non Creato</h4>
                    <p class="card-text">
                        Per accedere alle funzionalità complete della piattaforma, 
                        devi prima creare il tuo profilo influencer.
                    </p>
                    <a href="create-profile.php" class="btn btn-warning btn-lg">
                        Crea il Tuo Profilo Ora
                    </a>
                </div>
            </div>

        <!-- Sezione: Profilo Esistente -->
        <?php else: ?>
            <div class="row">
                <!-- Immagine Profilo e Dati Base -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Profilo</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php if (!empty($influencer['profile_image'])): ?>
                                <img src="/infl/uploads/<?php echo htmlspecialchars($influencer['profile_image']); ?>" 
                                     class="rounded-circle mb-3" 
                                     alt="Profile Image" 
                                     style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-3" 
                                     style="width: 150px; height: 150px;">
                                    <span class="text-white">No Image</span>
                                </div>
                            <?php endif; ?>
                            
                            <h4><?php echo htmlspecialchars($influencer['full_name']); ?></h4>
                            <span class="badge bg-info"><?php echo htmlspecialchars($influencer['niche']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Dettagli Profilo -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Dettagli Profilo</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Instagram:</strong>
                                        <span class="float-end"><?php echo !empty($influencer['instagram_handle']) ? htmlspecialchars($influencer['instagram_handle']) : 'Non specificato'; ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>TikTok:</strong>
                                        <span class="float-end"><?php echo !empty($influencer['tiktok_handle']) ? htmlspecialchars($influencer['tiktok_handle']) : 'Non specificato'; ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>YouTube:</strong>
                                        <span class="float-end"><?php echo !empty($influencer['youtube_handle']) ? htmlspecialchars($influencer['youtube_handle']) : 'Non specificato'; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Tariffa:</strong>
                                        <span class="float-end">€<?php echo !empty($influencer['rate']) ? number_format($influencer['rate'], 2) : '0.00'; ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Visualizzazioni:</strong>
                                        <span class="float-end"><?php echo number_format($influencer['profile_views'] ?? 0); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Rating:</strong>
                                        <span class="float-end">
                                            <?php 
                                            if (!empty($influencer['rating']) && $influencer['rating'] > 0) {
                                                echo number_format($influencer['rating'], 1) . '/5';
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($influencer['website'])): ?>
                                <div class="mb-3">
                                    <strong>Website:</strong>
                                    <span class="float-end">
                                        <a href="<?php echo htmlspecialchars($influencer['website']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($influencer['website']); ?>
                                        </a>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bio -->
            <?php if (!empty($influencer['bio'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Biografia</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($influencer['bio'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistiche Completamento Profilo -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Completamento Profilo</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $completed = 0;
                    $total_fields = 8; // full_name, bio, niche, instagram_handle, rate, profile_image, website, almeno un social
                    
                    if (!empty($influencer['full_name'])) $completed++;
                    if (!empty($influencer['bio'])) $completed++;
                    if (!empty($influencer['niche'])) $completed++;
                    if (!empty($influencer['instagram_handle'])) $completed++;
                    if (!empty($influencer['rate'])) $completed++;
                    if (!empty($influencer['profile_image'])) $completed++;
                    if (!empty($influencer['website'])) $completed++;
                    if (!empty($influencer['instagram_handle']) || !empty($influencer['tiktok_handle']) || !empty($influencer['youtube_handle'])) $completed++;
                    
                    $percentage = round(($completed / $total_fields) * 100);
                    ?>
                    <div class="mb-3">
                        <strong>Profilo Completato:</strong>
                        <span class="float-end"><?php echo $completed . '/' . $total_fields . ' (' . $percentage . '%)'; ?></span>
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar <?php echo $percentage >= 80 ? 'bg-success' : ($percentage >= 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                             role="progressbar" 
                             style="width: <?php echo $percentage; ?>%">
                            <?php echo $percentage; ?>%
                        </div>
                    </div>
                    <small class="text-muted">
                        Completa tutti i campi per aumentare la tua visibilità del <?php echo (100 - $percentage); ?>%
                    </small>
                </div>
            </div>

            <!-- Azioni Rapide -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Azioni Rapide</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="edit-profile.php" class="btn btn-outline-primary w-100 mb-2">
                                ✏️ Modifica Profilo
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="campaigns.php" class="btn btn-outline-success w-100 mb-2">
                                📊 Campagne Attive
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="analytics.php" class="btn btn-outline-info w-100 mb-2">
                                📈 Analytics
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="settings.php" class="btn btn-outline-secondary w-100 mb-2">
                                ⚙️ Impostazioni
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// =============================================
// INCLUSIONE FOOTER CON PERCORSO ASSOLUTO
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>