<?php

// Debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Percorso assoluto per config
$config_file = dirname(__DIR__) . '/includes/config.php';

if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die("Config file not found: " . $config_file);
}

// Verifica sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conta messaggi non letti
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        if ($_SESSION['user_type'] === 'brand') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN brands b ON c.brand_id = b.id
                WHERE b.user_id = ? AND m.sender_type = 'influencer' AND m.is_read = FALSE
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $unread_count = $result['unread'] ?? 0;
        } else if ($_SESSION['user_type'] === 'influencer') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN influencers i ON c.influencer_id = i.id
                WHERE i.user_id = ? AND m.sender_type = 'brand' AND m.is_read = FALSE
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $unread_count = $result['unread'] ?? 0;
        }
    } catch (Exception $e) {
        // Silenzioso in caso di errore
        error_log("Errore nel conteggio messaggi non letti: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/infl">Influencer Marketplace</a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_type'] === 'brand'): ?>
                            <a class="nav-link position-relative" href="/infl/brands/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a class="nav-link position-relative" href="/infl/brands/messages/conversation-list.php">
    <i class="fas fa-envelope"></i> Messaggi
    <?php if ($unread_count > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?php echo $unread_count; ?>
            <span class="visually-hidden">messaggi non letti</span>
        </span>
    <?php endif; ?>
</a>
                            <a class="nav-link" href="/infl/brands/search-influencers.php">
                                <i class="fas fa-search"></i> Cerca Influencer
                            </a>
                            <a class="nav-link" href="/infl/brands/campaigns.php">
                                <i class="fas fa-bullhorn"></i> Campagne
                            </a>
                        <?php elseif ($_SESSION['user_type'] === 'influencer'): ?>
                            <a class="nav-link position-relative" href="/infl/influencers/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a class="nav-link position-relative" href="/infl/influencers/messages/">
                                <i class="fas fa-envelope"></i> Messaggi
                                <?php if ($unread_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $unread_count; ?>
                                        <span class="visually-hidden">messaggi non letti</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <a class="nav-link" href="/infl/influencers/campaigns.php">
                                <i class="fas fa-bullhorn"></i> Campagne
                            </a>
                            <a class="nav-link" href="/infl/influencers/analytics.php">
                                <i class="fas fa-chart-bar"></i> Analytics
                            </a>
                        <?php endif; ?>
                        <a class="nav-link" href="/infl/auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a class="nav-link" href="/infl/auth/login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a class="nav-link" href="/infl/auth/register.php">
                            <i class="fas fa-user-plus"></i> Registrati
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="container mt-4">