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
// VERIFICA PARAMETRO ID
// =============================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: /infl/brands/search-influencers.php");
    exit();
}

$influencer_id = intval($_GET['id']);

// =============================================
// RECUPERO DATI INFLUENCER E INCREMENTO VISUALIZZAZIONI
// =============================================
$influencer = null;
$error = '';

try {
    // Prima incrementa le visualizzazioni
    $update_views_stmt = $pdo->prepare("
        UPDATE influencers 
        SET profile_views = COALESCE(profile_views, 0) + 1 
        WHERE id = ?
    ");
    $update_views_stmt->execute([$influencer_id]);
    
    // Poi recupera i dati dell'influencer
    $stmt = $pdo->prepare("
        SELECT id, user_id, full_name, bio, niche, 
               instagram_handle, tiktok_handle, youtube_handle, 
               website, rate, profile_image, profile_views, rating,
               created_at, updated_at 
        FROM influencers 
        WHERE id = ?
    ");
    $stmt->execute([$influencer_id]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        $error = "Influencer non trovato!";
    }
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo: " . $e->getMessage();
}

// =============================================
// INCLUSIONE HEADER CON PERCORSO ASSOLUTO
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <!-- Pulsante Torna alla Ricerca -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Profilo Influencer</h2>
            <a href="/infl/brands/search-influencers.php" class="btn btn-outline-primary">
                ← Torna alla Ricerca
            </a>
        </div>

        <!-- Messaggi di errore -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$influencer): ?>
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h4 class="card-title text-danger">Profilo Non Trovato</h4>
                    <p class="card-text">
                        L'influencer che stai cercando non esiste o è stato rimosso.
                    </p>
                    <a href="/infl/brands/search-influencers.php" class="btn btn-danger">
                        Torna alla Ricerca
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- SEZIONE PRINCIPALE PROFILO -->
            <div class="row">
                <!-- Colonna Sinistra: Immagine e Info Base -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Profilo</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php if (!empty($influencer['profile_image'])): ?>
                                <img src="/infl/uploads/<?php echo htmlspecialchars($influencer['profile_image']); ?>" 
                                     class="rounded-circle mb-3" 
                                     alt="<?php echo htmlspecialchars($influencer['full_name']); ?>" 
                                     style="width: 200px; height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-3" 
                                     style="width: 200px; height: 200px;">
                                    <span class="text-white">Nessuna Immagine</span>
                                </div>
                            <?php endif; ?>
                            
                            <h4><?php echo htmlspecialchars($influencer['full_name']); ?></h4>
                            <?php if (!empty($influencer['niche'])): ?>
                                <span class="badge bg-info fs-6"><?php echo htmlspecialchars($influencer['niche']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Statistiche -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Statistiche</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Visualizzazioni Profilo:</strong>
                                <span class="float-end"><?php echo number_format($influencer['profile_views'] ?? 0); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Rating:</strong>
                                <span class="float-end">
                                    <?php if (!empty($influencer['rating']) && $influencer['rating'] > 0): ?>
                                        <span class="text-warning">
                                            ★ <?php echo number_format($influencer['rating'], 1); ?>/5
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Nessun rating</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <strong>Membro dal:</strong>
                                <span class="float-end">
                                    <?php echo !empty($influencer['created_at']) ? date('d/m/Y', strtotime($influencer['created_at'])) : 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Contatta Influencer -->
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">Contatta</h5>
                        </div>
                        <div class="card-body text-center">
                            <p class="card-text">Interessato a collaborare?</p>
                            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand'): ?>
                                <button class="btn btn-warning btn-lg w-100" data-bs-toggle="modal" data-bs-target="#contactModal">
                                    📧 Contatta Influencer
                                </button>
                            <?php else: ?>
                                <a href="/infl/auth/login.php" class="btn btn-outline-warning w-100">
                                    🔐 Accedi come Brand per Contattare
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Colonna Destra: Dettagli Completi -->
                <div class="col-md-8">
                    <!-- Tariffa e Info Principali -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">Informazioni Collaborazione</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Tariffa per Collaborazione:</strong>
                                        <span class="float-end fs-5 text-success">
                                            €<?php echo !empty($influencer['rate']) ? number_format($influencer['rate'], 2) : '0.00'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Disponibilità:</strong>
                                        <span class="float-end text-success">
                                            ✅ Disponibile per collaborazioni
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Biografia -->
                    <?php if (!empty($influencer['bio'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Biografia</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text fs-6"><?php echo nl2br(htmlspecialchars($influencer['bio'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Social Media -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Social Media</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (!empty($influencer['instagram_handle'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">📷</span>
                                            <div>
                                                <strong>Instagram</strong>
                                                <div class="text-muted">@<?php echo htmlspecialchars($influencer['instagram_handle']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($influencer['tiktok_handle'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">🎵</span>
                                            <div>
                                                <strong>TikTok</strong>
                                                <div class="text-muted">@<?php echo htmlspecialchars($influencer['tiktok_handle']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($influencer['youtube_handle'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">📺</span>
                                            <div>
                                                <strong>YouTube</strong>
                                                <div class="text-muted">@<?php echo htmlspecialchars($influencer['youtube_handle']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($influencer['website'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">🌐</span>
                                            <div>
                                                <strong>Sito Web</strong>
                                                <div class="text-muted">
                                                    <a href="<?php echo htmlspecialchars($influencer['website']); ?>" target="_blank">
                                                        Visita Sito
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (empty($influencer['instagram_handle']) && empty($influencer['tiktok_handle']) && empty($influencer['youtube_handle'])): ?>
                                <p class="text-muted text-center">Nessun social media specificato</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Metri di Performance (Placeholder per future implementazioni) -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Metriche di Performance</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-primary">📊</div>
                                        <div class="fw-bold">Engagement Rate</div>
                                        <div class="text-muted">Disponibile su richiesta</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-success">👥</div>
                                        <div class="fw-bold">Follower</div>
                                        <div class="text-muted">Dettagli su contatto</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-warning">🎯</div>
                                        <div class="fw-bold">Audience</div>
                                        <div class="text-muted">Analisi disponibile</div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Contatta l'influencer per ottenere metriche dettagliate e report completi
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Contatto (Placeholder per future implementazioni) -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">Contatta <?php echo htmlspecialchars($influencer['full_name'] ?? ''); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Funzionalità di contatto in fase di sviluppo.</p>
                <p>Per ora, puoi contattare l'influencer tramite i suoi social media elencati sopra.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
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