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
    // Reindirizza al login se non autenticato
    header("Location: /infl/auth/login.php");
    exit();
}

// Verifica che l'utente sia un brand
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
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
// RECUPERO DATI BRAND DAL DATABASE
// =============================================
$brand = null;
$error = '';
$success = '';

try {
    // Prepara e esegui query per recuperare i dati del brand
    $stmt = $pdo->prepare("
        SELECT * FROM brands 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo brand: " . $e->getMessage();
}

// =============================================
// CONTENUTO PRINCIPALE DELLA DASHBOARD
// =============================================
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dashboard Brand</h2>
            <?php if (!$brand): ?>
                <a href="create-profile.php" class="btn btn-primary">
                    Completa Profilo Aziendale
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
                Profilo brand creato con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Sezione: Profilo Mancante -->
        <?php if (!$brand): ?>
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h4 class="card-title text-warning">Profilo Brand Non Completato</h4>
                    <p class="card-text">
                        Per iniziare a cercare influencer e creare campagne, 
                        completa il profilo della tua azienda.
                    </p>
                    <a href="create-profile.php" class="btn btn-warning btn-lg">
                        Completa Profilo Aziendale
                    </a>
                </div>
            </div>

        <!-- Sezione: Profilo Esistente -->
        <?php else: ?>
            <div class="row">
                <!-- Riepilogo Profilo Brand -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Profilo Aziendale</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Azienda:</strong>
                                <span class="float-end"><?php echo htmlspecialchars($brand['company_name'] ?? 'Non specificato'); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Settore:</strong>
                                <span class="float-end badge bg-info"><?php echo htmlspecialchars($brand['industry'] ?? 'Non specificato'); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Sito Web:</strong>
                                <span class="float-end"><?php echo htmlspecialchars($brand['website'] ?? 'Non specificato'); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Dimensione Azienda:</strong>
                                <span class="float-end"><?php echo htmlspecialchars($brand['company_size'] ?? 'Non specificata'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiche e Azioni -->
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
                                    if (!empty($brand['company_name'])) $completed++;
                                    if (!empty($brand['industry'])) $completed++;
                                    if (!empty($brand['description'])) $completed++;
                                    if (!empty($brand['website'])) $completed++;
                                    if (!empty($brand['company_size'])) $completed++;
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
                                Profilo completo: maggiore visibilità per gli influencer
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Descrizione Azienda -->
            <?php if (!empty($brand['description'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Descrizione Azienda</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($brand['description'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Azioni Rapide Brand -->
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
                            <a href="search-influencers.php" class="btn btn-outline-success w-100 mb-2">
                                Cerca Influencer
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="create-campaign.php" class="btn btn-outline-info w-100 mb-2">
                                Crea Campagna
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="campaigns.php" class="btn btn-outline-warning w-100 mb-2">
                                Le Mie Campagne
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