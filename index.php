<?php
// TEMPORANEO - Mostra tutti gli errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

// COMMENTA le query per test - PROBLEMA DATABASE
/*
try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, ip.bio, ip.follower_count, ip.niche, ip.engagement_rate
        FROM users u 
        LEFT JOIN influencer_profiles ip ON u.id = ip.user_id 
        WHERE u.user_type = 'influencer' 
        AND ip.follower_count > 0 
        ORDER BY ip.follower_count DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $featured_influencers = $stmt->fetchAll();
} catch (PDOException $e) {
    $featured_influencers = [];
}
*/

$featured_influencers = [];

/*
try {
    $influencer_count = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'influencer'")->fetchColumn();
    $brand_count = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'brand'")->fetchColumn();
    $campaign_count = 0; // Da implementare in futuro
} catch (PDOException $e) {
    $influencer_count = $brand_count = $campaign_count = 0;
}
*/

$influencer_count = 0;
$brand_count = 0;
$campaign_count = 0;

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Connettiamo Influencer e Brand</h1>
                <p class="lead mb-4">
                    La piattaforma tutto-in-uno per collaborazioni di successo. 
                    Trova gli influencer perfetti per il tuo brand o monetizza i tuoi social media.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?php echo BASE_URL; ?>/auth/register.php?type=brand" class="btn btn-light btn-lg">
                        🚀 Sono un Brand
                    </a>
                    <a href="<?php echo BASE_URL; ?>/auth/register.php?type=influencer" class="btn btn-outline-light btn-lg">
                        💫 Sono un Influencer
                    </a>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <div class="hero-image mt-4 mt-lg-0">
                    <div style="width: 100%; height: 300px; background: rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <span style="color: rgba(255,255,255,0.7);">Illustrazione Marketplace</span>
                    </div>
                    <small class="text-light opacity-75">Illustrazione rappresentativa della connessione tra brand e influencer</small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section py-5 bg-light">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-4">
                <div class="stat-item">
                    <h2 class="display-4 text-primary fw-bold"><?php echo $influencer_count; ?></h2>
                    <p class="lead">Influencer Attivi</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-item">
                    <h2 class="display-4 text-success fw-bold"><?php echo $brand_count; ?></h2>
                    <p class="lead">Brand Registrati</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-item">
                    <h2 class="display-4 text-warning fw-bold"><?php echo $campaign_count; ?></h2>
                    <p class="lead">Campagne Attive</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="how-it-works py-5">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 class="section-title">Come Funziona</h2>
                <p class="lead text-muted">Tre semplici passi per iniziare</p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 text-center">
                <div class="step-card p-4">
                    <div class="step-icon bg-primary text-white rounded-circle mx-auto mb-3" style="width: 80px; height: 80px; line-height: 80px; font-size: 2rem;">
                        1
                    </div>
                    <h4>Registrati</h4>
                    <p class="text-muted">
                        Crea il tuo account come Influencer o Brand in pochi secondi
                    </p>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="step-card p-4">
                    <div class="step-icon bg-success text-white rounded-circle mx-auto mb-3" style="width: 80px; height: 80px; line-height: 80px; font-size: 2rem;">
                        2
                    </div>
                    <h4>Connetti</h4>
                    <p class="text-muted">
                        Gli influencer creano il profilo, i brand cercano i partner ideali
                    </p>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="step-card p-4">
                    <div class="step-icon bg-warning text-white rounded-circle mx-auto mb-3" style="width: 80px; height: 80px; line-height: 80px; font-size: 2rem;">
                        3
                    </div>
                    <h4>Collabora</h4>
                    <p class="text-muted">
                        Avvia campagne di successo e monitora i risultati
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Influencers -->
<section class="featured-influencers py-5 bg-light">
    <div class="container">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="section-title">Influencer in Evidenza</h2>
                <p class="lead text-muted">Scopri alcuni dei nostri creator più popolari</p>
            </div>
        </div>
        
        <?php if (!empty($featured_influencers)): ?>
        <div class="row">
            <?php foreach ($featured_influencers as $influencer): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card influencer-card h-100">
                    <div class="card-body text-center">
                        <div class="influencer-avatar bg-primary rounded-circle mx-auto mb-3" 
                             style="width: 80px; height: 80px; line-height: 80px; font-size: 1.5rem; color: white;">
                            <?php echo strtoupper(substr($influencer['name'], 0, 2)); ?>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($influencer['name']); ?></h5>
                        <?php if ($influencer['niche']): ?>
                            <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($influencer['niche']); ?></span>
                        <?php endif; ?>
                        <p class="card-text text-muted small">
                            <?php 
                            if (!empty($influencer['bio'])) {
                                echo strlen($influencer['bio']) > 100 ? 
                                    substr($influencer['bio'], 0, 100) . '...' : 
                                    $influencer['bio'];
                            } else {
                                echo 'Influencer specializzato nel suo settore.';
                            }
                            ?>
                        </p>
                        <div class="influencer-stats">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong class="text-primary"><?php echo number_format($influencer['follower_count']); ?></strong>
                                    <div class="text-muted small">Followers</div>
                                </div>
                                <div class="col-6">
                                    <strong class="text-success"><?php echo $influencer['engagement_rate'] ? $influencer['engagement_rate'] . '%' : 'N/A'; ?></strong>
                                    <div class="text-muted small">Engagement</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="#" class="btn btn-outline-primary btn-sm w-100">Vedi Profilo</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="row">
            <div class="col-12 text-center">
                <div class="alert alert-info">
                    <h5>Database non connesso</h5>
                    <p>Le funzionalità di database sono temporaneamente disabilitate</p>
                    <a href="<?php echo BASE_URL; ?>/auth/register.php?type=influencer" class="btn btn-primary">
                        Registrati come Influencer
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section py-5 bg-primary text-white">
    <div class="container">
        <div class="row text-center">
            <div class="col-12">
                <h2 class="display-5 fw-bold mb-4">Pronto a Iniziare?</h2>
                <p class="lead mb-4">
                    Unisciti a migliaia di influencer e brand che già collaborano con successo
                </p>
                <div class="d-flex justify-content-center flex-wrap gap-3">
                    <a href="<?php echo BASE_URL; ?>/auth/register.php?type=influencer" class="btn btn-light btn-lg">
                        💫 Diventa Influencer
                    </a>
                    <a href="<?php echo BASE_URL; ?>/auth/register.php?type=brand" class="btn btn-outline-light btn-lg">
                        🚀 Diventa Partner
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="features-section py-5">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 class="section-title">Perché Sceglierci</h2>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 text-center mb-4">
                <div class="feature-icon bg-primary text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; line-height: 60px; font-size: 1.5rem;">
                    ⚡
                </div>
                <h5>Veloce</h5>
                <p class="text-muted">Connettiti con i partner ideali in pochi click</p>
            </div>
            <div class="col-md-3 text-center mb-4">
                <div class="feature-icon bg-success text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; line-height: 60px; font-size: 1.5rem;">
                    🔒
                </div>
                <h5>Sicuro</h5>
                <p class="text-muted">Pagamenti protetti e contratti trasparenti</p>
            </div>
            <div class="col-md-3 text-center mb-4">
                <div class="feature-icon bg-warning text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; line-height: 60px; font-size: 1.5rem;">
                    📊
                </div>
                <h5>Analitiche</h5>
                <p class="text-muted">Monitora le performance delle tue campagne</p>
            </div>
            <div class="col-md-3 text-center mb-4">
                <div class="feature-icon bg-info text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; line-height: 60px; font-size: 1.5rem;">
                    🌍
                </div>
                <h5>Globale</h5>
                <p class="text-muted">Influencer e brand da tutto il mondo</p>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>