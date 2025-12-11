<?php

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// MAPPA CATEGORIE PER VISUALIZZAZIONE
// =============================================
$category_mapping = [
    'lifestyle' => 'Lifestyle',
    'fashion' => 'Fashion',
    'beauty' => 'Beauty & Makeup',
    'fitness' => 'Fitness & Wellness',
    'travel' => 'Travel',
    'food' => 'Food',
    'tech' => 'Tech',
    'gaming' => 'Gaming'
];

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
// RECUPERO CANDIDATURE PER LA SEZIONE AGGIUNTA
// =============================================
$applications = [];
$application_stats = [
    'total_applications' => 0,
    'accepted_applications' => 0,
    'pending_applications' => 0
];

if ($influencer) {
    try {
        // Recupera candidature recenti (SOLO campagne non eliminate)
        $stmt = $pdo->prepare("
            SELECT ca.*, c.name as campaign_name, c.budget, b.company_name,
                   ca.status, ca.created_at as application_date
            FROM campaign_applications ca
            JOIN campaigns c ON ca.campaign_id = c.id
            JOIN brands b ON c.brand_id = b.id
            WHERE ca.influencer_id = ?
            AND c.deleted_at IS NULL
            ORDER BY ca.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$influencer['id']]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistiche candidature (TUTTE le candidature, anche per campagne eliminate)
        // Questo per mantenere le statistiche accurate
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_applications,
                   COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_applications,
                   COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_applications
            FROM campaign_applications 
            WHERE influencer_id = ?
        ");
        $stmt->execute([$influencer['id']]);
        $application_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se non ci sono candidature, imposta valori di default
        if (!$application_stats) {
            $application_stats = [
                'total_applications' => 0,
                'accepted_applications' => 0,
                'pending_applications' => 0
            ];
        }
        
    } catch (PDOException $e) {
        // Se la tabella non esiste ancora, continua senza errori
        $applications = [];
        $application_stats = [
            'total_applications' => 0,
            'accepted_applications' => 0,
            'pending_applications' => 0
        ];
    }
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
            <?php 
            $profile_image_src = '/infl/uploads/placeholder/influencer_admin_edit.png';
            if (!empty($influencer['profile_image'])) {
                // Verifica se l'immagine esiste fisicamente sul server
                $full_image_path = dirname(__DIR__) . '/uploads/' . $influencer['profile_image'];
                if (file_exists($full_image_path)) {
                    $profile_image_src = '/infl/uploads/' . $influencer['profile_image'];
                }
            }
            ?>
            <img src="<?php echo htmlspecialchars($profile_image_src); ?>" 
                 class="rounded-circle mb-3" 
                 alt="Profile Image" 
                 style="width: 150px; height: 150px; object-fit: cover;">
               
                            <h4><?php echo htmlspecialchars_decode($influencer['full_name']); ?></h4>
                            <?php if (!empty($influencer['niche'])): ?>
    <?php 
    $display_niche = htmlspecialchars_decode($influencer['niche']);
    if (isset($category_mapping[$influencer['niche']])) {
        $display_niche = $category_mapping[$influencer['niche']];
    }
    ?>
    <span class="badge bg-info"><?php echo $display_niche; ?></span>
<?php endif; ?>
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
                                        <span class="float-end"><?php echo !empty($influencer['instagram_handle']) ? '@' . htmlspecialchars($influencer['instagram_handle']) : 'Non specificato'; ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>TikTok:</strong>
                                        <span class="float-end"><?php echo !empty($influencer['tiktok_handle']) ? '@' . htmlspecialchars($influencer['tiktok_handle']) : 'Non specificato'; ?></span>
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

            <!-- Sponsor Recenti -->
<?php
// Recupera gli sponsor recenti dell'influencer
$recent_sponsors = [];
if ($influencer) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, image_url, budget, currency, created_at
            FROM sponsors 
            WHERE influencer_id = ? 
            AND status = 'active'
            AND deleted_at IS NULL
            ORDER BY created_at DESC 
            LIMIT 3
        ");
        $stmt->execute([$influencer['id']]);
        $recent_sponsors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silenzioso in caso di errore
    }
}
?>

<?php if (!empty($recent_sponsors)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Sponsor recenti</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($recent_sponsors as $sponsor): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <?php if (!empty($sponsor['image_url'])): ?>
    <img src="/infl/uploads/sponsor/<?php echo htmlspecialchars($sponsor['image_url']); ?>" 
         class="rounded mb-3" 
         alt="<?php echo htmlspecialchars($sponsor['title']); ?>"
         style="width: 100%; height: 120px; object-fit: cover;">
<?php else: ?>
    <img src="/infl/uploads/placeholder/sponsor_influencer_dashboard.png" 
         class="rounded mb-3" 
         alt="Placeholder sponsor"
         style="width: 100%; height: 120px; object-fit: cover;">
<?php endif; ?>
                                
                                <h6 class="card-title"><?php echo htmlspecialchars($sponsor['title']); ?></h6>
                                <p class="card-text text-success fw-bold">
                                    €<?php echo number_format($sponsor['budget'], 2); ?>
                                </p>
                                <a href="/infl/influencers/sponsors/view.php?id=<?php echo $sponsor['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    Visualizza dettagli
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
            <div class="card mb-4">
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
                            <a href="campaigns/list.php" class="btn btn-outline-success w-100 mb-2">
                                🔍 Scopri Campagne
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="applications/list.php" class="btn btn-outline-info w-100 mb-2">
                                📋 Le Mie Candidature
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

            <!-- Sezione Candidature -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Le Mie Candidature</h5>
                    <a href="campaigns/list.php" class="btn btn-sm btn-outline-primary">
                        Scopri Nuove Campagne
                    </a>
                </div>
                <div class="card-body">
                    <!-- Statistiche Candidature -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card text-white bg-primary">
                                <div class="card-body text-center py-3">
                                    <h5 class="card-title"><?php echo $application_stats['total_applications']; ?></h5>
                                    <p class="card-text small">Candidature Totali</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-success">
                                <div class="card-body text-center py-3">
                                    <h5 class="card-title"><?php echo $application_stats['accepted_applications']; ?></h5>
                                    <p class="card-text small">Accettate</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-warning">
                                <div class="card-body text-center py-3">
                                    <h5 class="card-title"><?php echo $application_stats['pending_applications']; ?></h5>
                                    <p class="card-text small">In Attesa</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista Candidature Recenti -->
                    <?php if (empty($applications)): ?>
                        <div class="text-center py-4">
                            <h6>Nessuna candidatura inviata</h6>
                            <p class="text-muted small">
                                Inizia a candidarti alle campagne pubbliche per trovare collaborazioni
                            </p>
                            <a href="campaigns/list.php" class="btn btn-primary btn-sm">
                                Scopri Campagne
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Campagna</th>
                                        <th>Brand</th>
                                        <th>Budget</th>
                                        <th>Stato</th>
                                        <th>Data</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $app): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($app['campaign_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars_decode(htmlspecialchars($app['company_name']), ENT_QUOTES); ?></td>
                                            <td>€<?php echo number_format($app['budget'], 2); ?></td>
                                            <td>
                                                <?php
                                                $status_badges = [
                                                    'pending' => 'warning',
                                                    'accepted' => 'success',
                                                    'rejected' => 'danger'
                                                ];
                                                $badge_class = $status_badges[$app['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($app['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y', strtotime($app['application_date'])); ?></small>
                                            </td>
                                            <td>
                                                <a href="campaigns/view.php?id=<?php echo $app['campaign_id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    Dettagli
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="applications/list.php" class="btn btn-outline-secondary btn-sm">
                                Vedi Tutte le Candidature
                            </a>
                        </div>
                    <?php endif; ?>
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