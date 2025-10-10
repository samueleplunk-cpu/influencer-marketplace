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
// PAGINAZIONE
// =============================================
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$influencers_per_page = MATCHING_RESULTS_PER_PAGE;
$offset = ($current_page - 1) * $influencers_per_page;

// =============================================
// RECUPERO DATI CAMPAGNA
// =============================================
$campaign = null;
$influencers = [];
$total_influencers = 0;
$total_pages = 0;
$error = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID campagna non specificato");
}

$campaign_id = intval($_GET['id']);

try {
    // Recupera brand
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: create-profile.php");
        exit();
    }
    
    // Recupera campagna specifica
    $stmt = $pdo->prepare("
        SELECT c.*, b.company_name 
        FROM campaigns c 
        JOIN brands b ON c.brand_id = b.id 
        WHERE c.id = ? AND c.brand_id = ?
    ");
    $stmt->execute([$campaign_id, $brand['id']]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        die("Campagna non trovata o accesso negato");
    }
    
    // Calcola budget limit per visualizzazione
    $budget_limit = calculate_budget_limit($campaign['budget']);
    
    // Conta totale influencer
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM campaign_influencers ci
        JOIN influencers i ON ci.influencer_id = i.id
        WHERE ci.campaign_id = ?
    ");
    $count_stmt->execute([$campaign_id]);
    $total_influencers = $count_stmt->fetchColumn();
    $total_pages = ceil($total_influencers / $influencers_per_page);
    
    // Recupera influencer con paginazione
    $stmt = $pdo->prepare("
        SELECT ci.*, i.full_name, i.niche, i.instagram_handle, i.tiktok_handle, 
               i.youtube_handle, i.rate, i.rating, i.profile_views
        FROM campaign_influencers ci
        JOIN influencers i ON ci.influencer_id = i.id
        WHERE ci.campaign_id = ?
        ORDER BY ci.match_score DESC, i.rating DESC, i.profile_views DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $campaign_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $influencers_per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento della campagna: " . $e->getMessage();
}

// =============================================
// GESTIONE AZIONI INFLUENCER
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $influencer_id = intval($_POST['influencer_id']);
        
        switch ($_POST['action']) {
            case 'invite':
                $stmt = $pdo->prepare("
                    UPDATE campaign_influencers 
                    SET status = 'invited', brand_notes = ?
                    WHERE campaign_id = ? AND influencer_id = ?
                ");
                $stmt->execute([$_POST['notes'] ?? '', $campaign_id, $influencer_id]);
                break;
                
            case 'update_status':
                $stmt = $pdo->prepare("
                    UPDATE campaign_influencers 
                    SET status = ?, brand_notes = ?
                    WHERE campaign_id = ? AND influencer_id = ?
                ");
                $stmt->execute([$_POST['status'], $_POST['notes'] ?? '', $campaign_id, $influencer_id]);
                break;
        }
        
        // Ricarica la pagina
        header("Location: campaign-details.php?id=" . $campaign_id . "&page=" . $current_page);
        exit();
        
    } catch (PDOException $e) {
        $error = "Errore nell'aggiornamento: " . $e->getMessage();
    }
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
            <h2>Dettaglio Campagna</h2>
            <a href="campaigns.php" class="btn btn-outline-secondary">
                ← Torna alle Campagne
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Dettagli Campagna -->
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Informazioni Campagna</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4><?php echo htmlspecialchars($campaign['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($campaign['description']); ?></p>
                                
                                <div class="mb-3">
                                    <strong>Budget:</strong> 
                                    <span class="badge bg-success fs-6">€<?php echo number_format($campaign['budget'], 2); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Budget Limit (Tier System):</strong>
                                    <span class="badge bg-info">€<?php echo number_format($budget_limit, 2); ?></span>
                                    <small class="text-muted d-block">Limite calcolato automaticamente per matching ottimale</small>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Niche:</strong>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($campaign['niche']); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Stato:</strong>
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
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Piattaforme:</strong><br>
                                    <?php 
                                    $platforms = json_decode($campaign['platforms'], true);
                                    if ($platforms): 
                                        foreach ($platforms as $platform): 
                                            $platform_names = [
                                                'instagram' => 'Instagram',
                                                'tiktok' => 'TikTok',
                                                'youtube' => 'YouTube',
                                                'facebook' => 'Facebook',
                                                'twitter' => 'Twitter/X'
                                            ];
                                    ?>
                                        <span class="badge bg-light text-dark me-1 mb-1"><?php echo $platform_names[$platform] ?? $platform; ?></span>
                                    <?php endforeach; endif; ?>
                                </div>
                                
                                <?php if ($campaign['start_date']): ?>
                                <div class="mb-3">
                                    <strong>Data Inizio:</strong>
                                    <?php echo date('d/m/Y', strtotime($campaign['start_date'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($campaign['end_date']): ?>
                                <div class="mb-3">
                                    <strong>Data Fine:</strong>
                                    <?php echo date('d/m/Y', strtotime($campaign['end_date'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <strong>Data Creazione:</strong>
                                    <?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informazioni Tier System -->
                        <div class="alert alert-info mt-3">
                            <h6>💡 Sistema di Matching Avanzato</h6>
                            <small>
                                <strong>Tier System Budget:</strong> Budget €<?php echo number_format($campaign['budget'], 2); ?> → 
                                Limite €<?php echo number_format($budget_limit, 2); ?> 
                                (<?php echo round(($budget_limit / $campaign['budget']) * 100); ?>% del budget)<br>
                                <strong>Soft Filter Affordability:</strong> Gli influencer fuori budget non vengono esclusi, ma penalizzati nel punteggio<br>
                                <strong>Doppia Fase:</strong> Prima niche esatto, poi niche simile per garantire risultati
                            </small>
                        </div>
                        
                        <?php if ($campaign['requirements']): ?>
                        <div class="mt-3">
                            <strong>Requisiti Specifici:</strong>
                            <p class="mt-1"><?php echo nl2br(htmlspecialchars($campaign['requirements'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        $target_audience = json_decode($campaign['target_audience'], true);
                        if ($target_audience && array_filter($target_audience)): 
                        ?>
                        <div class="mt-3">
                            <strong>Target Audience:</strong>
                            <div class="row mt-2">
                                <?php if (!empty($target_audience['age_range'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Età:</strong> <?php echo htmlspecialchars($target_audience['age_range']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($target_audience['gender'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Genere:</strong> <?php echo htmlspecialchars($target_audience['gender']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($target_audience['location'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Località:</strong> <?php echo htmlspecialchars($target_audience['location']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($target_audience['interests'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Interessi:</strong> <?php echo htmlspecialchars($target_audience['interests']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Statistiche Matching</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <h3><?php echo $total_influencers; ?></h3>
                            <p class="text-muted">Influencer Trovati</p>
                        </div>
                        
                        <?php 
                        $status_counts = [
                            'pending' => 0,
                            'invited' => 0,
                            'accepted' => 0,
                            'rejected' => 0,
                            'completed' => 0
                        ];
                        
                        foreach ($influencers as $inf) {
                            $status_counts[$inf['status']]++;
                        }
                        ?>
                        
                        <div class="mt-3">
                            <?php foreach ($status_counts as $status => $count): ?>
                                <?php if ($count > 0): ?>
                                <div class="d-flex justify-content-between mb-1">
                                    <small><?php echo ucfirst($status); ?></small>
                                    <small class="fw-bold"><?php echo $count; ?></small>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Info Paginazione -->
                        <div class="mt-3 pt-3 border-top">
                            <small class="text-muted">
                                Pagina <?php echo $current_page; ?> di <?php echo max(1, $total_pages); ?><br>
                                <?php echo $influencers_per_page; ?> influencer per pagina
                            </small>
                        </div>
                    </div>
                </div>
                
                <?php if ($campaign['status'] === 'draft'): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <p>Questa campagna è in bozza e non è visibile agli influencer.</p>
                        <a href="create-campaign.php?edit=<?php echo $campaign['id']; ?>" 
                           class="btn btn-primary w-100">Modifica Campagna</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lista Influencer Matching -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Influencer Matching</h5>
                <div>
                    <small class="text-muted">
                        <?php echo $total_influencers; ?> risultati totali
                    </small>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($influencers)): ?>
                    <div class="text-center py-4">
                        <h5>Nessun influencer trovato</h5>
                        <p class="text-muted">
                            Modifica i criteri della campagna per trovare influencer matching
                        </p>
                    </div>
                <?php else: ?>
                    <!-- PAGINAZIONE TOP -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Paginazione influencer">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $current_page - 1; ?>">
                                    ← Precedente
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Next Page -->
                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $current_page + 1; ?>">
                                    Successiva →
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Influencer</th>
                                    <th>Match Type</th>
                                    <th>Piattaforme</th>
                                    <th>Rate & Affordability</th>
                                    <th>Match Score</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($influencers as $influencer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($influencer['full_name']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($influencer['niche']); ?> • 
                                                Rating: <?php echo number_format($influencer['rating'], 1); ?> ★
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo get_match_badge($influencer['match_score'], $influencer['match_details']); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $platforms = [];
                                            if (!empty($influencer['instagram_handle'])) $platforms[] = '<span title="Instagram">📷</span>';
                                            if (!empty($influencer['tiktok_handle'])) $platforms[] = '<span title="TikTok">🎵</span>';
                                            if (!empty($influencer['youtube_handle'])) $platforms[] = '<span title="YouTube">📺</span>';
                                            echo implode(' ', $platforms);
                                            ?>
                                        </td>
                                        <td>
                                            <strong>€<?php echo number_format($influencer['rate'], 2); ?></strong><br>
                                            <?php echo get_affordability_indicator($influencer['rate'], $budget_limit); ?>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;" 
                                                 title="Match Score: <?php echo $influencer['match_score']; ?>%">
                                                <div class="progress-bar 
                                                    <?php echo $influencer['match_score'] >= 70 ? 'bg-success' : 
                                                          ($influencer['match_score'] >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo $influencer['match_score']; ?>%">
                                                    <?php echo $influencer['match_score']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => 'secondary',
                                                'invited' => 'primary',
                                                'accepted' => 'success',
                                                'rejected' => 'danger',
                                                'completed' => 'info'
                                            ];
                                            $badge_class = $status_badges[$influencer['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php echo ucfirst($influencer['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#influencerModal<?php echo $influencer['influencer_id']; ?>">
                                                    Dettagli
                                                </button>
                                                
                                                <?php if ($influencer['status'] === 'pending' && $campaign['status'] === 'active'): ?>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#inviteModal<?php echo $influencer['influencer_id']; ?>">
                                                        Invita
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Modal Dettagli Influencer -->
                                    <div class="modal fade" id="influencerModal<?php echo $influencer['influencer_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <?php echo htmlspecialchars($influencer['full_name']); ?>
                                                        <?php echo get_match_badge($influencer['match_score'], $influencer['match_details']); ?>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Informazioni Base</h6>
                                                            <p><strong>Niche:</strong> <?php echo htmlspecialchars($influencer['niche']); ?></p>
                                                            <p><strong>Rate:</strong> €<?php echo number_format($influencer['rate'], 2); ?></p>
                                                            <p><strong>Rating:</strong> <?php echo number_format($influencer['rating'], 1); ?> ★</p>
                                                            <p><strong>Profile Views:</strong> <?php echo number_format($influencer['profile_views']); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Dettagli Match</h6>
                                                            <?php 
                                                            $details = json_decode($influencer['match_details'], true);
                                                            if ($details): 
                                                            ?>
                                                                <p><strong>Score Totale:</strong> <?php echo $influencer['match_score']; ?>%</p>
                                                                <?php if (isset($details['niche'])): ?>
                                                                    <p><strong>Niche Match:</strong> 
                                                                        <?php echo $details['niche'] === 'exact' ? '🎯 Esatto' : 
                                                                              ($details['niche'] === 'similar' ? '📈 Simile' : '❌ Nessuno'); ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                                <?php if (isset($details['platforms'])): ?>
                                                                    <p><strong>Piattaforme:</strong> 
                                                                        <?php echo $details['platforms']['matches']; ?>/<?php echo $details['platforms']['total']; ?> 
                                                                        (<?php echo $details['platforms']['score']; ?> punti)
                                                                    </p>
                                                                <?php endif; ?>
                                                                <?php if (isset($details['affordability'])): ?>
                                                                    <p><strong>Affordability:</strong> 
                                                                        <?php echo $details['affordability_score']; ?> punti
                                                                        (<?php echo get_affordability_indicator($influencer['rate'], $budget_limit); ?>)
                                                                    </p>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-12">
                                                            <h6>Piattaforme Social</h6>
                                                            <div class="d-flex gap-2">
                                                                <?php if (!empty($influencer['instagram_handle'])): ?>
                                                                    <span class="badge bg-instagram">Instagram: @<?php echo htmlspecialchars($influencer['instagram_handle']); ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($influencer['tiktok_handle'])): ?>
                                                                    <span class="badge bg-dark">TikTok: @<?php echo htmlspecialchars($influencer['tiktok_handle']); ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($influencer['youtube_handle'])): ?>
                                                                    <span class="badge bg-danger">YouTube: <?php echo htmlspecialchars($influencer['youtube_handle']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($influencer['brand_notes'])): ?>
                                                        <div class="mt-3">
                                                            <strong>Note Brand:</strong>
                                                            <p><?php echo nl2br(htmlspecialchars($influencer['brand_notes'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Invita Influencer -->
                                    <?php if ($influencer['status'] === 'pending' && $campaign['status'] === 'active'): ?>
                                    <div class="modal fade" id="inviteModal<?php echo $influencer['influencer_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Invita <?php echo htmlspecialchars($influencer['full_name']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="influencer_id" value="<?php echo $influencer['influencer_id']; ?>">
                                                        <input type="hidden" name="action" value="invite">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Note (opzionale)</label>
                                                            <textarea class="form-control" name="notes" rows="3" 
                                                                      placeholder="Aggiungi note per l'influencer..."></textarea>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <small>
                                                                <strong>Match Score:</strong> <?php echo $influencer['match_score']; ?>%<br>
                                                                <strong>Rate:</strong> €<?php echo number_format($influencer['rate'], 2); ?><br>
                                                                Invitando questo influencer, gli verrà notificata 
                                                                la tua campagna e potrà accettare o rifiutare la collaborazione.
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" class="btn btn-success">Invia Invito</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINAZIONE BOTTOM -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Paginazione influencer">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $current_page - 1; ?>">
                                    ← Precedente
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Next Page -->
                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $current_page + 1; ?>">
                                    Successiva →
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.bg-instagram {
    background: linear-gradient(45deg, #405DE6, #5851DB, #833AB4, #C13584, #E1306C, #FD1D1D, #F56040, #F77737, #FCAF45, #FFDC80) !important;
}
.progress {
    height: 20px;
}
.progress-bar {
    font-weight: bold;
}
</style>

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