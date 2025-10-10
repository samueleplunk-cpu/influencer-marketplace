<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /infl/auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// RECUPERO DATI BRAND E CAMPAIGNE
// =============================================
$brand = null;
$campaigns = [];
$error = '';

try {
    // Recupera brand
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: create-profile.php");
        exit();
    }
    
    // Recupera campagne
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(ci.id) as influencer_count,
               COUNT(CASE WHEN ci.status = 'accepted' THEN 1 END) as accepted_count
        FROM campaigns c 
        LEFT JOIN campaign_influencers ci ON c.id = ci.campaign_id
        WHERE c.brand_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$brand['id']]);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento delle campagne: " . $e->getMessage();
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Le Mie Campagne</h2>
            <a href="create-campaign.php" class="btn btn-primary">
                + Nuova Campagna
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'campaign_created'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Campagna creata con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiche Rapide -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo count($campaigns); ?></h5>
                        <p class="card-text">Totale Campagne</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { return $c['status'] === 'active'; })); ?>
                        </h5>
                        <p class="card-text">Campagne Attive</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { return $c['status'] === 'draft'; })); ?>
                        </h5>
                        <p class="card-text">Bozze</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo array_sum(array_column($campaigns, 'accepted_count')); ?>
                        </h5>
                        <p class="card-text">Influencer Accettati</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista Campagne -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Tutte le Campagne</h5>
            </div>
            <div class="card-body">
                <?php if (empty($campaigns)): ?>
                    <div class="text-center py-4">
                        <h4>Nessuna campagna creata</h4>
                        <p class="text-muted">Inizia creando la tua prima campagna per trovare influencer</p>
                        <a href="create-campaign.php" class="btn btn-primary">Crea Prima Campagna</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nome Campagna</th>
                                    <th>Budget</th>
                                    <th>Niche</th>
                                    <th>Stato</th>
                                    <th>Influencer</th>
                                    <th>Data Creazione</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                            <?php if ($campaign['status'] === 'draft'): ?>
                                                <span class="badge bg-secondary ms-1">Bozza</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>€<?php echo number_format($campaign['budget'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($campaign['niche']); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'draft' => 'secondary',
                                                'active' => 'success',
                                                'paused' => 'warning',
                                                'completed' => 'primary',
                                                'cancelled' => 'danger'
                                            ];
                                            $badge_class = $status_badges[$campaign['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php echo ucfirst($campaign['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo $campaign['accepted_count']; ?> accettati / 
                                                <?php echo $campaign['influencer_count']; ?> totali
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($campaign['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="campaign-details.php?id=<?php echo $campaign['id']; ?>" 
                                                   class="btn btn-outline-primary">Dettagli</a>
                                                <?php if ($campaign['status'] === 'draft'): ?>
                                                    <a href="edit-campaign.php?id=<?php echo $campaign['id']; ?>" 
                                                       class="btn btn-outline-secondary">Modifica</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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