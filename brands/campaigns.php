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
           COUNT(CASE WHEN ci.status = 'accepted' THEN 1 END) as accepted_count,
           (c.deadline_date IS NOT NULL AND c.deadline_date < CURDATE() AND c.status = 'paused') as is_expired
    FROM campaigns c 
    LEFT JOIN campaign_influencers ci ON c.id = ci.campaign_id
    WHERE c.brand_id = ? AND c.deleted_at IS NULL
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

        <?php if (isset($_GET['message']) && $_GET['message'] == 'campaign_expired'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Alcune campagne sono scadute per mancata risposta alle richieste di integrazione.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiche Rapide -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center p-3">
                        <h5 class="card-title"><?php echo count($campaigns); ?></h5>
                        <p class="card-text small">Totale Campagne</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-success">
                    <div class="card-body text-center p-3">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { 
                                return $c['status'] === 'active'; 
                            })); ?>
                        </h5>
                        <p class="card-text small">Attive</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center p-3">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { 
                                return $c['status'] === 'paused' && 
                                       (!$c['deadline_date'] || strtotime($c['deadline_date']) >= time()); 
                            })); ?>
                        </h5>
                        <p class="card-text small">In Pausa</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-secondary">
                    <div class="card-body text-center p-3">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { 
                                return $c['status'] === 'draft'; 
                            })); ?>
                        </h5>
                        <p class="card-text small">Bozze</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-danger">
                    <div class="card-body text-center p-3">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { 
                                return $c['status'] === 'expired' || 
                                       ($c['status'] === 'paused' && $c['deadline_date'] && strtotime($c['deadline_date']) < time()); 
                            })); ?>
                        </h5>
                        <p class="card-text small">Scadute</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-info">
                    <div class="card-body text-center p-3">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { 
                                return $c['status'] === 'completed'; 
                            })); ?>
                        </h5>
                        <p class="card-text small">Completate</p>
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
                                    <th>Categoria</th>
                                    <th>Stato</th>
                                    <th>Scadenza</th>
                                    <th>Influencer</th>
                                    <th>Data Creazione</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): 
                                    // Determina se la campagna è scaduta
                                    $is_expired = $campaign['status'] === 'expired' || 
                                                ($campaign['status'] === 'paused' && 
                                                 $campaign['deadline_date'] && 
                                                 strtotime($campaign['deadline_date']) < time());
                                    
                                    // Stato effettivo per visualizzazione
                                    $effective_status = $is_expired ? 'expired' : $campaign['status'];
                                ?>
                                    <tr class="<?php echo $is_expired ? 'table-danger' : ''; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <?php if (!$campaign['is_public']): ?>
                                                        <span class="badge bg-secondary" title="Campagna privata">
                                                            <i class="fas fa-lock fa-xs"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                    <?php if ($campaign['status'] === 'draft'): ?>
                                                        <span class="badge bg-secondary ms-1">Bozza</span>
                                                    <?php endif; ?>
                                                    <?php if ($is_expired): ?>
                                                        <span class="badge bg-danger ms-1">Scaduta</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>€<?php echo number_format($campaign['budget'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($campaign['niche']); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'draft' => ['class' => 'secondary', 'icon' => 'fas fa-edit', 'text' => 'Bozza'],
                                                'active' => ['class' => 'success', 'icon' => 'fas fa-play', 'text' => 'Attiva'],
                                                'paused' => ['class' => 'warning', 'icon' => 'fas fa-pause', 'text' => 'In pausa'],
                                                'completed' => ['class' => 'primary', 'icon' => 'fas fa-check', 'text' => 'Completata'],
                                                'expired' => ['class' => 'danger', 'icon' => 'fas fa-clock', 'text' => 'Scaduta'],
                                                'cancelled' => ['class' => 'dark', 'icon' => 'fas fa-times', 'text' => 'Cancellata']
                                            ];
                                            
                                            $status_config = $status_badges[$effective_status] ?? $status_badges['draft'];
                                            ?>
                                            <span class="badge bg-<?php echo $status_config['class']; ?>">
                                                <i class="<?php echo $status_config['icon']; ?> me-1"></i>
                                                <?php echo $status_config['text']; ?>
                                            </span>
                                            
                                            <?php if ($campaign['status'] === 'paused' && !$is_expired && $campaign['deadline_date']): ?>
                                                <br>
                                                <small class="text-warning">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Scade il <?php echo date('d/m/Y', strtotime($campaign['deadline_date'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($campaign['deadline_date']): ?>
                                                <small class="<?php echo strtotime($campaign['deadline_date']) < time() ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                                    <?php echo date('d/m/Y', strtotime($campaign['deadline_date'])); ?>
                                                    <?php if (strtotime($campaign['deadline_date']) < time()): ?>
                                                        <br><span class="badge bg-danger">Scaduta</span>
                                                    <?php endif; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted small">Nessuna</span>
                                            <?php endif; ?>
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
                                                   class="btn btn-outline-primary" title="Dettagli campagna">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($campaign['status'] === 'draft'): ?>
                                                    <a href="edit-campaign.php?id=<?php echo $campaign['id']; ?>" 
                                                       class="btn btn-outline-secondary" title="Modifica campagna">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($is_expired): ?>
                                                    <button type="button" class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#reactivateModal<?php echo $campaign['id']; ?>"
                                                            title="Richiedi riattivazione">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Modal Richiesta Riattivazione per Campagne Scadute -->
                                    <?php if ($is_expired): ?>
                                    <div class="modal fade" id="reactivateModal<?php echo $campaign['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title text-warning">
                                                        <i class="fas fa-redo me-2"></i>Richiedi Riattivazione
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        <strong>Campagna Scaduta</strong>
                                                        <p class="mb-0 mt-2">
                                                            Questa campagna è scaduta perché non sono state fornite le informazioni richieste entro la scadenza del 
                                                            <strong><?php echo date('d/m/Y', strtotime($campaign['deadline_date'])); ?></strong>.
                                                        </p>
                                                    </div>
                                                    
                                                    <p>Per riattivare la campagna, contatta l'amministratore e fornisci le informazioni richieste.</p>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Messaggio per l'amministratore</label>
                                                        <textarea class="form-control" rows="4" 
                                                                  placeholder="Spiega perché desideri riattivare la campagna e quali informazioni fornirai..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                    <button type="button" class="btn btn-warning">
                                                        <i class="fas fa-paper-plane me-1"></i> Invia Richiesta
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Informazioni sulle campagne scadute -->
                    <?php 
                    $expired_campaigns = array_filter($campaigns, function($c) { 
                        return $c['status'] === 'expired' || 
                              ($c['status'] === 'paused' && $c['deadline_date'] && strtotime($c['deadline_date']) < time()); 
                    }); 
                    ?>
                    
                    <?php if (count($expired_campaigns) > 0): ?>
                    <div class="mt-4">
                        <div class="alert alert-warning">
                            <h6 class="alert-heading">
                                <i class="fas fa-exclamation-triangle me-2"></i>Campagne Scadute
                            </h6>
                            <p class="mb-2">
                                Le campagne contrassegnate in <strong class="text-danger">rosso</strong> sono scadute per mancata risposta alle richieste di integrazione informazioni.
                            </p>
                            <p class="mb-0">
                                Per riattivare una campagna scaduta, contatta l'amministratore tramite il pulsante 
                                <i class="fas fa-redo text-warning"></i> e fornisci le informazioni richieste.
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.table-danger {
    background-color: rgba(220, 53, 69, 0.05) !important;
}

.table-danger:hover {
    background-color: rgba(220, 53, 69, 0.1) !important;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.card .card-body.text-center {
    padding: 1rem 0.5rem;
}

.card .card-body.text-center .card-title {
    margin-bottom: 0.25rem;
    font-size: 1.5rem;
}

.card .card-body.text-center .card-text {
    font-size: 0.8rem;
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