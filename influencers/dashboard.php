<?php
// infl/influencers/dashboard.php - VERSIONE CORRETTA COMPLETA

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
    // Reindirizza al login se non autenticato
    header("Location: /infl/auth/login.php");
    exit();
}

// Verifica che l'utente sia un influencer
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
    // Prepara e esegui query per recuperare i dati dell'influencer
    $stmt = $pdo->prepare("
        SELECT * FROM influencers 
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
                <a href="edit-profile.php" class="btn btn-outline-primary">
                    Modifica Profilo
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
                <!-- Riepilogo Profilo -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Il Tuo Profilo</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Nome:</strong>
                                <span class="float-end"><?php echo htmlspecialchars($influencer['name'] ?? 'Non specificato'); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Niche:</strong>
                                <span class="float-end badge bg-info"><?php echo htmlspecialchars($influencer['niche'] ?? 'Non specificata'); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Followers:</strong>
                                <span class="float-end"><?php echo number_format($influencer['follower_count'] ?? 0); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Social Handle:</strong>
                                <span class="float-end"><?php echo htmlspecialchars($influencer['social_handle'] ?? 'Non specificato'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiche -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Statistiche</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Profilo Completato:</strong>
                                <span class="float-end">
                                    <?php 
                                    $completed = 0;
                                    $total = 5;
                                    if (!empty($influencer['name'])) $completed++;
                                    if (!empty($influencer['niche'])) $completed++;
                                    if (!empty($influencer['bio'])) $completed++;
                                    if (!empty($influencer['social_handle'])) $completed++;
                                    if (!empty($influencer['follower_count'])) $completed++;
                                    echo $completed . '/' . $total;
                                    ?>
                                </span>
                            </div>
                            <div class="progress mb-3">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo ($completed/$total)*100; ?>%">
                                    <?php echo round(($completed/$total)*100); ?>%
                                </div>
                            </div>
                            <small class="text-muted">
                                Completa il tuo profilo per aumentare la visibilità
                            </small>
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

            <!-- Azioni Rapide -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Azioni Rapide</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="edit-profile.php" class="btn btn-outline-primary w-100 mb-2">
                                Modifica Profilo
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="campaigns.php" class="btn btn-outline-success w-100 mb-2">
                                Campagne Attive
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="analytics.php" class="btn btn-outline-info w-100 mb-2">
                                Analytics
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="settings.php" class="btn btn-outline-secondary w-100 mb-2">
                                Impostazioni
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