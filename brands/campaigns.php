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

// Parametri di filtro
$search_name = $_GET['search_name'] ?? '';
$filter_category = $_GET['filter_category'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

try {
    // Recupera brand
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: create-profile.php");
        exit();
    }
    
    // Query base per campagne
    $query = "
    SELECT c.*, 
           COUNT(ci.id) as influencer_count,
           COUNT(CASE WHEN ci.status = 'accepted' THEN 1 END) as accepted_count,
           (c.deadline_date IS NOT NULL AND c.deadline_date < CURDATE() AND c.status = 'paused') as is_expired
    FROM campaigns c 
    LEFT JOIN campaign_influencers ci ON c.id = ci.campaign_id
    WHERE c.brand_id = ? AND c.deleted_at IS NULL
    ";
    
    $params = [$brand['id']];
    
    // Applica filtri
    if (!empty($search_name)) {
        $query .= " AND c.name LIKE ?";
        $params[] = "%$search_name%";
    }
    
    if (!empty($filter_category)) {
        $query .= " AND c.niche = ?";
        $params[] = $filter_category;
    }
    
    if (!empty($filter_status)) {
        if ($filter_status === 'expired') {
            $query .= " AND (c.status = 'expired' OR (c.status = 'paused' AND c.deadline_date IS NOT NULL AND c.deadline_date < CURDATE()))";
        } else {
            $query .= " AND c.status = ?";
            $params[] = $filter_status;
        }
    }
    
    $query .= " GROUP BY c.id ORDER BY c.created_at DESC";
    
    // Recupera campagne con filtri applicati
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // MODIFICA CENTRALIZZATA: Recupera le categorie dalla tabella categories gestita dall'admin
    $categories = [];
    try {
        // Verifica se la tabella categories esiste
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'categories'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Recupera categorie attive dalla tabella centralizzata
            $stmt = $pdo->prepare("
                SELECT name 
                FROM categories 
                WHERE is_active = TRUE 
                ORDER BY display_order ASC, name ASC
            ");
            $stmt->execute();
            $category_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Estrai solo i nomi delle categorie
            foreach ($category_results as $category) {
                $categories[] = $category['name'];
            }
        } else {
            // Fallback: usa le categorie hardcoded se la tabella non esiste
            $categories = [
                'Fashion', 'Lifestyle', 'Beauty & Makeup', 'Food', 'Travel',
                'Gaming', 'Fitness & Wellness', 'Entertainment', 'Tech',
                'Finance & Business', 'Pet', 'Education'
            ];
        }
    } catch (PDOException $e) {
        error_log("Errore nel recupero delle categorie: " . $e->getMessage());
        // Fallback alle categorie hardcoded in caso di errore
        $categories = [
            'Fashion', 'Lifestyle', 'Beauty & Makeup', 'Food', 'Travel',
            'Gaming', 'Fitness & Wellness', 'Entertainment', 'Tech',
            'Finance & Business', 'Pet', 'Education'
        ];
    }
    
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
            <!-- Attive -->
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
            <!-- In Pausa -->
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
            <!-- Completate -->
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
            <!-- Scadute -->
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
            <!-- Bozze -->
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
            <!-- Totale Campagne -->
            <div class="col-md-2">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center p-3">
                        <h5 class="card-title"><?php echo count($campaigns); ?></h5>
                        <p class="card-text small">Totale Campagne</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra Filtri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Filtri Campagne
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <!-- Filtro Nome Campagna -->
                    <div class="col-md-4">
                        <label for="search_name" class="form-label">Nome Campagna</label>
                        <input type="text" 
                               class="form-control" 
                               id="search_name" 
                               name="search_name" 
                               placeholder="Cerca per nome campagna..."
                               value="<?php echo htmlspecialchars($search_name); ?>">
                    </div>
                    
                    <!-- Filtro Categoria -->
                    <div class="col-md-3">
                        <label for="filter_category" class="form-label">Categoria</label>
                        <select class="form-select" id="filter_category" name="filter_category">
                            <option value="">Tutte le categorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" 
                                    <?php echo $filter_category === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Filtro Stato -->
                    <div class="col-md-3">
                        <label for="filter_status" class="form-label">Stato</label>
                        <select class="form-select" id="filter_status" name="filter_status">
                            <option value="">Tutti gli stati</option>
                            <option value="draft" <?php echo $filter_status === 'draft' ? 'selected' : ''; ?>>Bozza</option>
                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Attiva</option>
                            <option value="paused" <?php echo $filter_status === 'paused' ? 'selected' : ''; ?>>In Pausa</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completata</option>
                            <option value="expired" <?php echo $filter_status === 'expired' ? 'selected' : ''; ?>>Scaduta</option>
                            <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancellata</option>
                        </select>
                    </div>
                    
                    <!-- Pulsanti -->
                    <div class="col-md-2">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Applica Filtri
                            </button>
                            <a href="campaigns.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
                
                <!-- Indicatore filtri attivi -->
                <?php if (!empty($search_name) || !empty($filter_category) || !empty($filter_status)): ?>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Filtri attivi: 
                            <?php 
                            $active_filters = [];
                            if (!empty($search_name)) {
                                $active_filters[] = "Nome: \"$search_name\"";
                            }
                            if (!empty($filter_category)) {
                                $active_filters[] = "Categoria: \"$filter_category\"";
                            }
                            if (!empty($filter_status)) {
                                $status_labels = [
                                    'draft' => 'Bozza',
                                    'active' => 'Attiva',
                                    'paused' => 'In Pausa',
                                    'completed' => 'Completata',
                                    'expired' => 'Scaduta',
                                    'cancelled' => 'Cancellata'
                                ];
                                $active_filters[] = "Stato: \"{$status_labels[$filter_status]}\"";
                            }
                            echo implode(', ', $active_filters);
                            ?>
                            - <strong><?php echo count($campaigns); ?></strong> campagne trovate
                        </small>
                    </div>
                <?php endif; ?>
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
                        <h4>Nessuna campagna trovata</h4>
                        <p class="text-muted">
                            <?php if (!empty($search_name) || !empty($filter_category) || !empty($filter_status)): ?>
                                Prova a modificare i filtri di ricerca
                            <?php else: ?>
                                Inizia creando la tua prima campagna per trovare influencer
                            <?php endif; ?>
                        </p>
                        <?php if (empty($search_name) && empty($filter_category) && empty($filter_status)): ?>
                            <a href="create-campaign.php" class="btn btn-primary">Crea Prima Campagna</a>
                        <?php else: ?>
                            <a href="campaigns.php" class="btn btn-outline-primary">Azzera Filtri</a>
                        <?php endif; ?>
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
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): 
                                    $is_expired_query = isset($campaign['is_expired']) && $campaign['is_expired'] == 1;
                                    $is_expired_manual = $campaign['status'] === 'expired' || 
                                                        ($campaign['status'] === 'paused' && 
                                                         $campaign['deadline_date'] && 
                                                         strtotime($campaign['deadline_date']) < time());
                                    
                                    $is_expired = $is_expired_query || $is_expired_manual;
                                    
                                    if ($is_expired) {
                                        $effective_status = 'expired';
                                    } else {
                                        $effective_status = $campaign['status'];
                                    }
                                ?>
                                    <tr>
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
                                                'completed' => ['class' => 'info', 'icon' => 'fas fa-check', 'text' => 'Completata'],
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
    <div class="btn-group btn-group-sm">
        <a href="campaign-details.php?id=<?php echo $campaign['id']; ?>" 
           class="btn btn-outline-primary me-2" title="Dettagli campagna">
            <i class="fas fa-eye"></i>
        </a>
        
        <?php if ($campaign['status'] === 'draft'): ?>
            <a href="edit-campaign.php?id=<?php echo $campaign['id']; ?>" 
               class="btn btn-outline-secondary me-1" title="Modifica campagna">
                <i class="fas fa-edit"></i>
            </a>
        <?php endif; ?>
        
        <?php if ($is_expired): ?>
            <button type="button" class="btn btn-outline-primary" 
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
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
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