<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Influencer Marketplace</title>
    
    <!-- PERCORSI CORRETTI -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="index.php" class="logo-link">
                        <i class="fas fa-star"></i>
                        <span>InfluencerMarket</span>
                    </a>
                </div>
                
                <div class="nav-menu" id="nav-menu">
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="index.php" class="nav-link active">Home</a>
                        </li>
                        <li class="nav-item">
                            <a href="#influencers" class="nav-link">Influencer</a>
                        </li>
                        <li class="nav-item">
                            <a href="#brands" class="nav-link">Brand</a>
                        </li>
                        <li class="nav-item">
                            <a href="#about" class="nav-link">About</a>
                        </li>
                        <li class="nav-item">
                            <a href="#contact" class="nav-link">Contact</a>
                        </li>
                    </ul>
                </div>
                
                <div class="nav-actions">
                    <button class="btn btn-login">Login</button>
                    <button class="btn btn-primary">Sign Up</button>
                </div>
                
                <div class="hamburger" id="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">
                    Connetti Brand e <span class="highlight">Influencer</span> di Qualità
                </h1>
                <p class="hero-description">
                    La piattaforma definitiva per collaborazioni autentiche tra marchi e creator. 
                    Trova i partner perfetti per la tua prossima campagna.
                </p>
                <div class="hero-actions">
                    <button class="btn btn-primary btn-large">Inizia Ora</button>
                    <button class="btn btn-secondary btn-large">Scopri di Più</button>
                </div>
                <div class="hero-stats">
                    <div class="stat">
                        <span class="stat-number">500+</span>
                        <span class="stat-label">Influencer</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">200+</span>
                        <span class="stat-label">Brand</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">1K+</span>
                        <span class="stat-label">Collaborazioni</span>
                    </div>
                </div>
            </div>
            <div class="hero-image">
                <div class="placeholder-image">
                    <i class="fas fa-users fa-8x"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Perché Sceglierci</h2>
                <p class="section-description">
                    Scopri i vantaggi della nostra piattaforma
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="feature-title">Ricerca Avanzata</h3>
                    <p class="feature-description">
                        Trova influencer perfetti con il nostro sistema di filtri avanzati
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">Sicurezza</h3>
                    <p class="feature-description">
                        Transazioni sicure e protezione per entrambe le parti
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="feature-title">Analytics</h3>
                    <p class="feature-description">
                        Metriche dettagliate per misurare il successo delle campagne
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3 class="feature-title">Collaborazione</h3>
                    <p class="feature-description">
                        Strumenti integrati per una collaborazione efficiente
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Influencers Section -->
    <section class="influencers-section" id="influencers">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Influencer in Evidenza</h2>
                <p class="section-description">
                    Scopri i creator più popolari della piattaforma
                </p>
            </div>
            
            <div class="influencers-grid">
                <div class="influencer-card">
                    <div class="influencer-avatar">
                        <i class="fas fa-user-circle fa-4x"></i>
                    </div>
                    <div class="influencer-info">
                        <h3 class="influencer-name">Sarah Johnson</h3>
                        <p class="influencer-category">Lifestyle & Travel</p>
                        <div class="influencer-stats">
                            <span class="stat">
                                <i class="fas fa-users"></i>
                                150K
                            </span>
                            <span class="stat">
                                <i class="fas fa-heart"></i>
                                4.9
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="influencer-card">
                    <div class="influencer-avatar">
                        <i class="fas fa-user-circle fa-4x"></i>
                    </div>
                    <div class="influencer-info">
                        <h3 class="influencer-name">Mike Chen</h3>
                        <p class="influencer-category">Tech & Gadgets</p>
                        <div class="influencer-stats">
                            <span class="stat">
                                <i class="fas fa-users"></i>
                                250K
                            </span>
                            <span class="stat">
                                <i class="fas fa-heart"></i>
                                4.8
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="influencer-card">
                    <div class="influencer-avatar">
                        <i class="fas fa-user-circle fa-4x"></i>
                    </div>
                    <div class="influencer-info">
                        <h3 class="influencer-name">Emma Davis</h3>
                        <p class="influencer-category">Fashion & Beauty</p>
                        <div class="influencer-stats">
                            <span class="stat">
                                <i class="fas fa-users"></i>
                                180K
                            </span>
                            <span class="stat">
                                <i class="fas fa-heart"></i>
                                4.9
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="section-actions">
                <button class="btn btn-outline">Vedi Tutti gli Influencer</button>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2 class="cta-title">Pronto a Iniziare?</h2>
                <p class="cta-description">
                    Unisciti a migliaia di brand e influencer che già utilizzano la nostra piattaforma
                </p>
                <div class="cta-actions">
                    <button class="btn btn-primary btn-large">Registrati Gratis</button>
                    <button class="btn btn-secondary btn-large">Contattaci</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <i class="fas fa-star"></i>
                        <span>InfluencerMarket</span>
                    </div>
                    <p class="footer-description">
                        La piattaforma leader per connettere brand e influencer di qualità.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3 class="footer-title">Link Veloci</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#influencers">Influencer</a></li>
                        <li><a href="#brands">Brand</a></li>
                        <li><a href="#about">Chi Siamo</a></li>
                        <li><a href="#contact">Contatti</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3 class="footer-title">Risorse</h3>
                    <ul class="footer-links">
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Guide</a></li>
                        <li><a href="#">Supporto</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3 class="footer-title">Contatti</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-envelope"></i> info@influencermarket.com</li>
                        <li><i class="fas fa-phone"></i> +39 02 1234567</li>
                        <li><i class="fas fa-map-marker-alt"></i> Milano, Italia</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 InfluencerMarket. Tutti i diritti riservati.</p>
            </div>
        </div>
    </footer>

    <!-- PERCORSO JAVASCRIPT CORRETTO -->
    <script src="assets/js/script.js"></script>
</body>
</html>