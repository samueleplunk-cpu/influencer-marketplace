<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO CORRETTO
// =============================================
$config_file = dirname(dirname(dirname(__FILE__))) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// INCLUSIONE FUNZIONI SOCIAL NETWORK
// =============================================
require_once dirname(dirname(dirname(__FILE__))) . '/includes/social_network_functions.php';

// =============================================
// RECUPERO DATI INFLUENCER
// =============================================
$influencer = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        header("Location: ../create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    die("Errore nel caricamento del profilo: " . $e->getMessage());
}

// =============================================
// PARAMETRI RICERCA E FILTRI
// =============================================
$search = $_GET['search'] ?? '';
$niche_filter = $_GET['niche'] ?? '';
$min_budget = $_GET['min_budget'] ?? '';
$max_budget = $_GET['max_budget'] ?? '';
$platform_filter = $_GET['platform'] ?? '';

// Paginazione
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$campaigns_per_page = 12;
$offset = ($current_page - 1) * $campaigns_per_page;

// =============================================
// QUERY CAMPAIGNE CON FILTRI
// =============================================
$campaigns = [];
$total_campaigns = 0;
$total_pages = 0;

try {
    // Query base
    $query = "
        SELECT c.*, b.company_name, b.website as brand_website,
               COUNT(ca.id) as application_count,
               EXISTS(
                   SELECT 1 FROM campaign_applications ca2 
                   WHERE ca2.campaign_id = c.id AND ca2.influencer_id = ?
               ) as has_applied
        FROM campaigns c
        JOIN brands b ON c.brand_id = b.id
        LEFT JOIN campaign_applications ca ON c.id = ca.campaign_id
        WHERE c.status = 'active' 
          AND c.is_public = TRUE 
          AND c.allow_applications = TRUE
          AND c.deleted_at IS NULL
    ";
    
    $count_query = "
        SELECT COUNT(DISTINCT c.id)
        FROM campaigns c
        WHERE c.status = 'active' 
          AND c.is_public = TRUE 
          AND c.allow_applications = TRUE
          AND c.deleted_at IS NULL
    ";
    
    $params = [$influencer['id']];
    $count_params = [];
    
    // Applica filtri
    if (!empty($search)) {
        $query .= " AND (c.name LIKE ? OR c.description LIKE ?)";
        $count_query .= " AND (c.name LIKE ? OR c.description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }
    
    if (!empty($niche_filter)) {
        $query .= " AND c.niche = ?";
        $count_query .= " AND c.niche = ?";
        $params[] = $niche_filter;
        $count_params[] = $niche_filter;
    }
    
    if (!empty($min_budget)) {
        $query .= " AND c.budget >= ?";
        $count_query .= " AND c.budget >= ?";
        $params[] = floatval($min_budget);
        $count_params[] = floatval($min_budget);
    }
    
    if (!empty($max_budget)) {
        $query .= " AND c.budget <= ?";
        $count_query .= " AND c.budget <= ?";
        $params[] = floatval($max_budget);
        $count_params[] = floatval($max_budget);
    }
    
    if (!empty($platform_filter)) {
        $query .= " AND JSON_CONTAINS(c.platforms, ?)";
        $count_query .= " AND JSON_CONTAINS(c.platforms, ?)";
        $params[] = json_encode($platform_filter);
        $count_params[] = json_encode($platform_filter);
    }
    
    // Conteggio totale
    $query .= " GROUP BY c.id ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_campaigns = $stmt->fetchColumn();
    $total_pages = ceil($total_campaigns / $campaigns_per_page);
    
    // Query con paginazione
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $campaigns_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Errore nel caricamento delle campagne: " . $e->getMessage());
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Campagne Pubbliche</h2>
            <a href="../dashboard.php" class="btn btn-outline-secondary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- Filtri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filtri</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Cerca</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nome campagna...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Categoria</label>
                        <select name="niche" class="form-select">
                            <option value="">Tutte</option>
                            <?php
                            // Nuovo set unificato di categorie
                            $niches = [
                                'Fashion', 'Lifestyle', 'Beauty & Makeup', 'Food', 'Travel', 
                                'Gaming', 'Fitness & Wellness', 'Entertainment', 'Tech', 
                                'Finance & Business', 'Pet', 'Education'
                            ];
                            foreach ($niches as $niche): ?>
                                <option value="<?php echo $niche; ?>" 
                                    <?php echo $niche_filter === $niche ? 'selected' : ''; ?>>
                                    <?php echo $niche; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Budget Min</label>
                        <input type="number" name="min_budget" class="form-control" 
                               value="<?php echo htmlspecialchars($min_budget); ?>" 
                               placeholder="€ Min">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Budget Max</label>
                        <input type="number" name="max_budget" class="form-control" 
                               value="<?php echo htmlspecialchars($max_budget); ?>" 
                               placeholder="€ Max">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Piattaforma</label>
                        <select name="platform" class="form-select">
                            <option value="">Tutte</option>
                            <?php
                            $social_networks = get_active_social_networks();
                            foreach ($social_networks as $social): ?>
                                <option value="<?php echo $social['slug']; ?>" 
                                    <?php echo $platform_filter === $social['slug'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($social['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtra</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $total_campaigns; ?></h5>
                        <p class="card-text">Campagne Trovate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { return !$c['has_applied']; })); ?>
                        </h5>
                        <p class="card-text">Nuove Opportunità</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { return $c['has_applied']; })); ?>
                        </h5>
                        <p class="card-text">Già Candidate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo array_sum(array_column($campaigns, 'application_count')); ?>
                        </h5>
                        <p class="card-text">Candidature Totali</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista Campagne -->
        <?php if (empty($campaigns)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <h4>Nessuna campagna trovata</h4>
                    <p class="text-muted">
                        <?php echo $total_campaigns > 0 ? 'Prova a modificare i filtri di ricerca.' : 'Al momento non ci sono campagne pubbliche disponibili.'; ?>
                    </p>
                    <?php if (!empty($search) || !empty($niche_filter)): ?>
                        <a href="list.php" class="btn btn-primary">Rimuovi Filtri</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($campaigns as $campaign): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 <?php echo $campaign['has_applied'] ? 'border-success' : ''; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($campaign['name']); ?></h6>
                                <?php if ($campaign['has_applied']): ?>
                                    <span class="badge bg-success">Già candidato</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p class="card-text text-muted small">
                                    <?php echo strlen($campaign['description']) > 100 ? 
                                        substr(htmlspecialchars($campaign['description']), 0, 100) . '...' : 
                                        htmlspecialchars($campaign['description']); ?>
                                </p>
                                
                                <div class="mb-2">
                                    <strong>Budget:</strong> 
                                    <span class="badge bg-success">€<?php echo number_format($campaign['budget'], 2); ?></span>
                                </div>
                                
                                <div class="mb-2">
                                    <strong>Niche:</strong>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($campaign['niche']); ?></span>
                                </div>
                                
                                <div class="mb-2">
                                    <strong>Brand:</strong>
                                    <?php echo htmlspecialchars($campaign['company_name']); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Piattaforme:</strong><br>
                                    <?php 
                                    $platforms = json_decode($campaign['platforms'], true);
                                    if ($platforms): 
                                        foreach ($platforms as $platform): 
                                            $social_network = get_social_network_by_slug($platform);
                                            if ($social_network):
                                    ?>
                                        <span class="badge bg-light text-dark me-1 mb-1">
                                            <i class="<?php echo $social_network['icon']; ?> me-1"></i>
                                            <?php echo htmlspecialchars($social_network['name']); ?>
                                        </span>
                                    <?php 
                                            endif;
                                        endforeach; 
                                    endif; 
                                    ?>
                                </div>
                                
                                <small class="text-muted">
                                    <?php echo $campaign['application_count']; ?> candidature
                                </small>
                            </div>
                            <div class="card-footer">
                                <div class="d-grid gap-2">
                                    <a href="view.php?id=<?php echo $campaign['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        Dettagli Campagna
                                    </a>
                                    <?php if (!$campaign['has_applied']): ?>
                                        <a href="view.php?id=<?php echo $campaign['id']; ?>&apply=1" 
                                           class="btn btn-success btn-sm">
                                            Candidati Ora
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-sm" disabled>
                                            Già Candidato
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Paginazione campagne">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Page -->
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">
                                ← Precedente
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Next Page -->
                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>">
                                Successiva →
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>