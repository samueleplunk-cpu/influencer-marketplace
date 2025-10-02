<?php
session_start();

// Debug: Abilita gli errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration - PERCORSO CORRETTO
try {
    require_once 'includes/config.php';
} catch (Exception $e) {
    die("Errore di configurazione: " . $e->getMessage());
}

// Fetch influencers with fallback
$influencers = [];

try {
    // Try to fetch from database
    $stmt = $pdo->query("
        SELECT i.*, u.username, u.profile_picture 
        FROM influencers i 
        JOIN users u ON i.user_id = u.id 
        WHERE i.is_active = 1
        ORDER BY i.created_at DESC 
        LIMIT 12
    ");
    $influencers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Use sample data if database fails
    error_log("Database error: " . $e->getMessage());
    $influencers = getSampleInfluencers();
}

// Sample data function
function getSampleInfluencers() {
    return [
        [
            'id' => 1,
            'username' => 'ChiaraFerragni',
            'profile_picture' => null,
            'category' => 'Fashion',
            'follower_count' => 25000000,
            'engagement_rate' => 4.5,
            'bio' => 'Fashion influencer and entrepreneur'
        ],
        [
            'id' => 2,
            'username' => 'Favij',
            'profile_picture' => null,
            'category' => 'Gaming',
            'follower_count' => 8500000,
            'engagement_rate' => 6.2,
            'bio' => 'Gaming content creator and YouTuber'
        ],
        [
            'id' => 3,
            'username' => 'Giallozafferano',
            'profile_picture' => null,
            'category' => 'Food',
            'follower_count' => 5200000,
            'engagement_rate' => 5.8,
            'bio' => 'Italian food recipes and cooking tips'
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Influencer Marketplace - Trova l'Influencer Perfetto</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="index.php" class="logo-link">
                        <i class="fas fa-star"></i>
                        InfluencerMarket
                    </a>
                </div>
                
                <div class="nav-menu">
                    <a href="index.php" class="nav-link active">Home</a>
                    <a href="browse.php" class="nav-link">Influencer</a>
                    <a href="about.php" class="nav-link">Chi Siamo</a>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="nav-link">Dashboard</a>
                        <a href="auth/logout.php" class="nav-link">Logout</a>
                    <?php else: ?>
                        <a href="auth/login.php" class="nav-link">Login</a>
                        <a href="auth/register.php" class="nav-link">Registrati</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <h1 class="hero-title">Trova l'Influencer Perfetto per il Tuo Brand</h1>
                <p class="hero-description">
                    Connettiti con migliaia di influencer verificati su tutte le piattaforme social. 
                    Le campagne di successo iniziano qui.
                </p>
                <div class="hero-buttons">
                    <a href="auth/register.php" class="btn btn-primary">Inizia Ora</a>
                    <a href="browse.php" class="btn btn-secondary">Esplora Influencer</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="image-placeholder">
                    <i class="fas fa-users fa-5x"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Influencers -->
    <section class="featured-influencers">
        <div class="container">
            <h2 class="section-title">Influencer in Evidenza</h2>
            <p class="section-subtitle">Scopri i creator più popolari della piattaforma</p>
            
            <div class="influencers-grid">
                <?php if(!empty($influencers)): ?>
                    <?php foreach($influencers as $influencer): ?>
                        <div class="influencer-card">
                            <div class="card-header">
                                <div class="influencer-avatar">
                                    <?php if(isset($influencer['profile_picture']) && $influencer['profile_picture']): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($influencer['profile_picture']); ?>" 
                                             alt="<?php echo htmlspecialchars($influencer['username']); ?>">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="influencer-info">
                                    <h3 class="influencer-name"><?php echo htmlspecialchars($influencer['username']); ?></h3>
                                    <p class="influencer-category"><?php echo htmlspecialchars($influencer['category'] ?? 'Generale'); ?></p>
                                </div>
                            </div>
                            
                            <div class="card-stats">
                                <div class="stat">
                                    <span class="stat-value"><?php echo number_format($influencer['follower_count'] ?? 0); ?></span>
                                    <span class="stat-label">Followers</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-value"><?php echo number_format($influencer['engagement_rate'] ?? 0, 1); ?>%</span>
                                    <span class="stat-label">Engagement</span>
                                </div>
                            </div>
                            
                            <div class="card-description">
                                <p><?php echo htmlspecialchars($influencer['bio'] ?? 'Nessuna bio disponibile'); ?></p>
                            </div>
                            
                            <div class="card-actions">
                                <a href="influencer-profile.php?id=<?php echo $influencer['id']; ?>" class="btn btn-outline">Vedi Profilo</a>
                                <button class="btn btn-primary">Contatta</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-influencers">
                        <i class="fas fa-users fa-3x"></i>
                        <p>Nessun influencer disponibile al momento</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <h2 class="section-title">Perché Scegliere Noi</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Influencer Verificati</h3>
                    <p>Tutti i creator sono verificati e autentici con metriche reali</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Metriche Dettagliate</h3>
                    <p>Accesso a dati approfonditi su engagement e performance</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Transazioni Sicure</h3>
                    <p>Pagamenti protetti e sistema di recensioni trasparente</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>InfluencerMarket</h3>
                    <p>La piattaforma leader per connettere brand e influencer</p>
                </div>
                
                <div class="footer-section">
                    <h4>Link Veloci</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="browse.php">Influencer</a></li>
                        <li><a href="about.php">Chi Siamo</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contatti</h4>
                    <ul>
                        <li><i class="fas fa-envelope"></i> info@influencermarket.it</li>
                        <li><i class="fas fa-phone"></i> +39 02 1234567</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 InfluencerMarket. Tutti i diritti riservati.</p>
            </div>
        </div>
    </footer>
</body>
</html>