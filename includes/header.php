<?php
// includes/header.php - VERSIONE COMPLETA CON MANUTENZIONE

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

// Includi functions.php per avere accesso alle funzioni
$functions_file = dirname(__DIR__) . '/includes/functions.php';
if (file_exists($functions_file)) {
    require_once $functions_file;
}

// INCLUSIONE SISTEMA MANUTENZIONE - AGGIUNTA IMPORTANTE
$maintenance_file = dirname(__DIR__) . '/includes/maintenance.php';
if (file_exists($maintenance_file)) {
    require_once $maintenance_file;
    
    // Controlla se siamo in una pagina pubblica (non admin)
    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $is_admin_page = strpos($current_script, '/infl/admin/') !== false;
    
    // Applica controllo manutenzione solo su pagine pubbliche
    if (!$is_admin_page) {
        check_maintenance_mode($pdo);
    }
}

// Conta messaggi non letti usando la funzione (se disponibile)
$unread_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if (function_exists('count_unread_messages')) {
        $unread_count = count_unread_messages($pdo, $_SESSION['user_id'], $_SESSION['user_type']);
    } else {
        // Fallback: usa la logica originale se la funzione non esiste
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
    <style>
        .navbar-nav .nav-link {
            position: relative;
            padding: 0.5rem 1rem;
        }
        .badge {
            font-size: 0.7rem;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .message-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/infl">
                <i class="fas fa-store me-2"></i>Influencer Marketplace
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_type'] === 'brand'): ?>
                            <!-- Menu Brand -->
                            <a class="nav-link" href="/infl/brands/dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                            <a class="nav-link position-relative" href="/infl/brands/messages/conversation-list.php">
                                <i class="fas fa-envelope me-1"></i> Messaggi
                                <?php if ($unread_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger message-badge">
                                        <?php echo $unread_count; ?>
                                        <span class="visually-hidden">messaggi non letti</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <a class="nav-link" href="/infl/brands/search-influencers.php">
                                <i class="fas fa-search me-1"></i> Cerca Influencer
                            </a>
                            <a class="nav-link" href="/infl/brands/campaigns.php">
                                <i class="fas fa-bullhorn me-1"></i> Campagne
                            </a>
                            <a class="nav-link" href="/infl/brands/profile.php">
                                <i class="fas fa-building me-1"></i> Profilo Brand
                            </a>
                        <?php elseif ($_SESSION['user_type'] === 'influencer'): ?>
                            <!-- Menu Influencer -->
                            <a class="nav-link" href="/infl/influencers/dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                            <a class="nav-link position-relative" href="/infl/influencers/messages/conversation-list.php">
                                <i class="fas fa-envelope me-1"></i> Messaggi
                                <?php if ($unread_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger message-badge">
                                        <?php echo $unread_count; ?>
                                        <span class="visually-hidden">messaggi non letti</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <a class="nav-link" href="/infl/influencers/campaigns.php">
                                <i class="fas fa-bullhorn me-1"></i> Campagne
                            </a>
                            <a class="nav-link" href="/infl/influencers/analytics.php">
                                <i class="fas fa-chart-bar me-1"></i> Analytics
                            </a>
                            <a class="nav-link" href="/infl/influencers/profile.php">
                                <i class="fas fa-user me-1"></i> Profilo
                            </a>
                        <?php endif; ?>
                        
                        <!-- Menu utente loggato -->
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> 
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Utente'); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/infl/auth/profile-settings.php">
                                    <i class="fas fa-cog me-2"></i>Impostazioni
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/infl/auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Menu utente non loggato -->
                        <a class="nav-link" href="/infl/auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                        <a class="nav-link" href="/infl/auth/register.php">
                            <i class="fas fa-user-plus me-1"></i> Registrati
                        </a>
                        <a class="nav-link" href="/infl/about.php">
                            <i class="fas fa-info-circle me-1"></i> Info
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Messaggi di notifica -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-0 rounded-0" role="alert">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-0 rounded-0" role="alert">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <main class="container mt-4">