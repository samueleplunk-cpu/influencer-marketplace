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
// PARAMETRI DI RICERCA E FILTRI
// =============================================
$search_query = $_GET['search'] ?? '';
$niche_filter = $_GET['niche'] ?? '';
$platform_filter = $_GET['platform'] ?? '';
$min_rate = $_GET['min_rate'] ?? '';
$max_rate = $_GET['max_rate'] ?? '';
$min_rating = $_GET['min_rating'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Risultati per pagina
$offset = ($page - 1) * $limit;

// =============================================
// COSTRUZIONE QUERY DINAMICA
// =============================================
$where_conditions = [];
$params = [];

// Filtro ricerca per nome/handle
if (!empty($search_query)) {
    $where_conditions[] = "(full_name LIKE ? OR instagram_handle LIKE ? OR tiktok_handle LIKE ? OR youtube_handle LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Filtro per niche
if (!empty($niche_filter)) {
    $where_conditions[] = "niche = ?";
    $params[] = $niche_filter;
}

// Filtro per piattaforma
if (!empty($platform_filter)) {
    switch ($platform_filter) {
        case 'instagram':
            $where_conditions[] = "instagram_handle IS NOT NULL AND instagram_handle != ''";
            break;
        case 'tiktok':
            $where_conditions[] = "tiktok_handle IS NOT NULL AND tiktok_handle != ''";
            break;
        case 'youtube':
            $where_conditions[] = "youtube_handle IS NOT NULL AND youtube_handle != ''";
            break;
    }
}

// Filtro per tariffa minima
if (!empty($min_rate) && is_numeric($min_rate)) {
    $where_conditions[] = "rate >= ?";
    $params[] = $min_rate;
}

// Filtro per tariffa massima
if (!empty($max_rate) && is_numeric($max_rate)) {
    $where_conditions[] = "rate <= ?";
    $params[] = $max_rate;
}

// Filtro per rating minimo
if (!empty($min_rating) && is_numeric($min_rating)) {
    $where_conditions[] = "rating >= ?";
    $params[] = $min_rating;
}

// Query base
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Query per il conteggio totale (per paginazione)
$count_sql = "SELECT COUNT(*) as total FROM influencers $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_results = $count_stmt->fetchColumn();
$total_pages = ceil($total_results / $limit);

// MODIFICA: Query per i risultati con ordinamento casuale
$results_sql = "SELECT * FROM influencers $where_sql ORDER BY RAND() LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($results_sql);
$stmt->execute($params);
$influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// RECUPERO NICHE UNICHE PER FILTRO
// =============================================
$niches_stmt = $pdo->query("SELECT DISTINCT niche FROM influencers WHERE niche IS NOT NULL AND niche != '' ORDER BY niche");
$available_niches = $niches_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Cerca Influencer</h2>
            <a href="dashboard.php" class="btn btn-outline-primary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- FILTRI DI RICERCA -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Filtri di Ricerca</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <!-- Ricerca per Nome/Handle -->
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cerca per Nome/Handle</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Nome, Instagram, TikTok, YouTube...">
                    </div>

                    <!-- Filtro Niche -->
                    <div class="col-md-3">
                        <label for="niche" class="form-label">Niche</label>
                        <select class="form-select" id="niche" name="niche">
                            <option value="">Tutte le niche</option>
                            <?php foreach ($available_niches as $niche): ?>
                                <option value="<?php echo htmlspecialchars($niche); ?>" 
                                    <?php echo $niche_filter === $niche ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($niche); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro Piattaforma -->
                    <div class="col-md-2">
                        <label for="platform" class="form-label">Piattaforma</label>
                        <select class="form-select" id="platform" name="platform">
                            <option value="">Tutte</option>
                            <option value="instagram" <?php echo $platform_filter === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                            <option value="tiktok" <?php echo $platform_filter === 'tiktok' ? 'selected' : ''; ?>>TikTok</option>
                            <option value="youtube" <?php echo $platform_filter === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                        </select>
                    </div>

                    <!-- Filtro Tariffa -->
                    <div class="col-md-3">
                        <label class="form-label">Tariffa (€)</label>
                        <div class="row g-2">
                            <div class="col">
                                <input type="number" class="form-control" name="min_rate" 
                                       value="<?php echo htmlspecialchars($min_rate); ?>" 
                                       placeholder="Min" min="0" step="10">
                            </div>
                            <div class="col">
                                <input type="number" class="form-control" name="max_rate" 
                                       value="<?php echo htmlspecialchars($max_rate); ?>" 
                                       placeholder="Max" min="0" step="10">
                            </div>
                        </div>
                    </div>

                    <!-- Filtro Rating -->
                    <div class="col-md-2">
                        <label for="min_rating" class="form-label">Rating Minimo</label>
                        <select class="form-select" id="min_rating" name="min_rating">
                            <option value="">Qualsiasi</option>
                            <option value="4" <?php echo $min_rating === '4' ? 'selected' : ''; ?>>4+ Stelle</option>
                            <option value="3" <?php echo $min_rating === '3' ? 'selected' : ''; ?>>3+ Stelle</option>
                            <option value="2" <?php echo $min_rating === '2' ? 'selected' : ''; ?>>2+ Stelle</option>
                        </select>
                    </div>

                    <!-- Pulsanti -->
                    <div class="col-md-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Cerca
                            </button>
                            <a href="search-influencers.php" class="btn btn-outline-secondary">
                                Reset Filtri
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- RISULTATI RICERCA -->
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Risultati (<?php echo $total_results; ?> influencer trovati)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($influencers)): ?>
                    <div class="text-center py-5">
                        <h4 class="text-muted">Nessun influencer trovato</h4>
                        <p class="text-muted">Prova a modificare i filtri di ricerca</p>
                    </div>
                <?php else: ?>
                    <!-- GRIGLIA INFLUENCER -->
                    <div class="row">
                        <?php foreach ($influencers as $influencer): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 influencer-card">
                                    <!-- Immagine Profilo -->
                                    <div class="position-relative">
                                        <?php if (!empty($influencer['profile_image'])): ?>
                                            <img src="/infl/uploads/<?php echo htmlspecialchars($influencer['profile_image']); ?>" 
                                                 class="card-img-top" alt="<?php echo htmlspecialchars($influencer['full_name']); ?>"
                                                 style="height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                                 style="height: 200px;">
                                                <span class="text-muted">Nessuna immagine</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Badge Rating -->
                                        <?php if (!empty($influencer['rating'])): ?>
                                            <div class="position-absolute top-0 end-0 m-2">
                                                <span class="badge bg-warning text-dark">
                                                    ★ <?php echo number_format($influencer['rating'], 1); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body">
                                        <!-- Nome e Niche -->
                                        <h5 class="card-title"><?php echo htmlspecialchars($influencer['full_name']); ?></h5>
                                        <?php if (!empty($influencer['niche'])): ?>
                                            <span class="badge bg-info mb-2"><?php echo htmlspecialchars($influencer['niche']); ?></span>
                                        <?php endif; ?>

                                        <!-- Bio -->
                                        <?php if (!empty($influencer['bio'])): ?>
                                            <p class="card-text text-muted small">
                                                <?php 
                                                $bio = htmlspecialchars($influencer['bio']);
                                                echo strlen($bio) > 100 ? substr($bio, 0, 100) . '...' : $bio;
                                                ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Handles Social -->
                                        <div class="mb-2">
                                            <?php if (!empty($influencer['instagram_handle'])): ?>
                                                <small class="text-muted d-block">
                                                    <strong>IG:</strong> @<?php echo htmlspecialchars($influencer['instagram_handle']); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if (!empty($influencer['tiktok_handle'])): ?>
                                                <small class="text-muted d-block">
                                                    <strong>TikTok:</strong> @<?php echo htmlspecialchars($influencer['tiktok_handle']); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if (!empty($influencer['youtube_handle'])): ?>
                                                <small class="text-muted d-block">
                                                    <strong>YT:</strong> @<?php echo htmlspecialchars($influencer['youtube_handle']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Tariffa e Visualizzazioni -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <?php if (!empty($influencer['rate'])): ?>
                                                <strong class="text-success">
                                                    €<?php echo number_format($influencer['rate'], 2); ?>
                                                </strong>
                                            <?php else: ?>
                                                <span class="text-muted">Tariffa non specificata</span>
                                            <?php endif; ?>
                                            
                                            <small class="text-muted">
                                                <?php echo number_format($influencer['profile_views'] ?? 0); ?> visualizzazioni
                                            </small>
                                        </div>
                                    </div>

                                    <div class="card-footer bg-transparent">
                                        <a href="/infl/influencers/profile.php?id=<?php echo $influencer['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm w-100">
                                            Vedi Profilo Completo
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- PAGINAZIONE -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginazione risultati">
                            <ul class="pagination justify-content-center mt-4">
                                <!-- Pagina Precedente -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        Precedente
                                    </a>
                                </li>

                                <!-- Pagine -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" 
                                           href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Pagina Successiva -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        Successiva
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