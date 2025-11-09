<?php
ob_start();
// includes/admin_header.php - VERSIONE COMPLETA CON SISTEMA MANUTENZIONE

// Inizia l'output buffering per prevenire problemi di redirect
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Percorso assoluto per config
$config_file = dirname(__DIR__) . '/includes/config.php';

if (file_exists($config_file)) {
    require_once $config_file;
    require_once 'admin_functions.php';
} else {
    die("Config file not found: " . $config_file);
}

// INCLUSIONE SISTEMA MANUTENZIONE - AGGIUNTA IMPORTANTE
$maintenance_file = dirname(__DIR__) . '/includes/maintenance.php';
if (file_exists($maintenance_file)) {
    require_once $maintenance_file;
}

// Controllo accesso admin
require_admin_login();

// Controllo timeout sessione admin
check_admin_session_timeout();

// Determina se siamo nella pagina settings per mantenere il menu aperto
$is_settings_page = basename($_SERVER['PHP_SELF']) == 'settings.php';
$is_notifications_page = basename($_SERVER['PHP_SELF']) == 'notifications.php';
$is_moderation_page = in_array(basename($_SERVER['PHP_SELF']), ['moderation.php', 'brand-campaigns.php']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 0.75rem 1rem;
            cursor: pointer;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
        }
        .main-content {
            padding: 20px;
        }
        .stat-card {
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .navbar-brand {
            font-weight: 600;
        }
        /* Stili per il sottomenu */
        .sidebar .nav-link.collapsed .fa-chevron-down {
            transition: transform 0.2s;
        }
        .sidebar .nav-link:not(.collapsed) .fa-chevron-down {
            transform: rotate(180deg);
        }
        .sidebar .nav .nav-link {
            padding-left: 2rem;
        }
    </style>
</head>
<body>
    <!-- Navbar Top -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/infl/admin/dashboard.php">
                <i class="fas fa-crown me-2"></i>Admin Panel
            </a>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    <i class="fas fa-user-shield me-1"></i>
                    <strong><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></strong>
                </span>
                <div class="btn-group">
                    <a href="/infl/" class="btn btn-outline-info btn-sm me-2" target="_blank">
                        <i class="fas fa-external-link-alt me-1"></i>Vedi Sito
                    </a>
                    <a href="/infl/admin/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-md-block collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="/infl/admin/dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'influencers.php' ? 'active' : ''; ?>" href="/infl/admin/influencers.php">
                                <i class="fas fa-users me-2"></i> Influencer
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'brands.php' ? 'active' : ''; ?>" href="/infl/admin/brands.php">
                                <i class="fas fa-building me-2"></i> Brands
                            </a>
                        </li>
                        
                        <!-- Menu Moderazione a tendina -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $is_moderation_page ? '' : 'collapsed'; ?>" 
                               data-bs-toggle="collapse" 
                               href="#moderationSubmenu" 
                               role="button" 
                               aria-expanded="<?php echo $is_moderation_page ? 'true' : 'false'; ?>" 
                               aria-controls="moderationSubmenu">
                                <i class="fas fa-shield-alt me-2"></i> Moderazione
                                <i class="fas fa-chevron-down float-end mt-1"></i>
                            </a>
                            <div class="collapse <?php echo $is_moderation_page ? 'show' : ''; ?>" id="moderationSubmenu">
                                <ul class="nav flex-column ms-3">
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'brand-campaigns.php' ? 'active' : ''; ?>" 
                                           href="/infl/admin/brand-campaigns.php">
                                            <i class="fas fa-bullhorn me-2"></i> Campagne Brand
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <!-- Menu Impostazioni a tendina -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $is_settings_page || $is_notifications_page ? '' : 'collapsed'; ?>" 
                               data-bs-toggle="collapse" 
                               href="#settingsSubmenu" 
                               role="button" 
                               aria-expanded="<?php echo $is_settings_page || $is_notifications_page ? 'true' : 'false'; ?>" 
                               aria-controls="settingsSubmenu">
                                <i class="fas fa-cog me-2"></i> Impostazioni
                                <i class="fas fa-chevron-down float-end mt-1"></i>
                            </a>
                            <div class="collapse <?php echo $is_settings_page || $is_notifications_page ? 'show' : ''; ?>" id="settingsSubmenu">
                                <ul class="nav flex-column ms-3">
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $is_notifications_page ? 'active' : ''; ?>" 
                                           href="/infl/admin/notifications.php">
                                            <i class="fas fa-bell me-2"></i> Notifiche
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $is_settings_page ? 'active' : ''; ?>" 
                                           href="/infl/admin/settings.php">
                                            <i class="fas fa-wrench me-2"></i> Modalità Manutenzione
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                
                <!-- Messaggi di notifica -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Banner Manutenzione (solo se attiva) -->
                <?php if (is_maintenance_mode($pdo)): ?>
                <div class="alert alert-warning d-flex align-items-center mb-4">
                    <i class="fas fa-tools fa-2x me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-1">Modalità Manutenzione Attiva</h5>
                        <p class="mb-0">Il frontend del sito è temporaneamente non disponibile per gli utenti regolari. 
                        <a href="/infl/admin/settings.php" class="alert-link">Gestisci impostazioni</a></p>
                    </div>
                </div>
                <?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Gestione sidebar che ESCLUDE ESPLICITAMENTE gli accordion
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggles = document.querySelectorAll('.sidebar [data-bs-toggle="collapse"]');
    
    dropdownToggles.forEach(toggle => {
        // ESCLUDI ESPLICITAMENTE gli accordion con l'attributo data-exclude-sidebar-toggle
        if (toggle.closest('[data-exclude-sidebar-toggle="true"]')) {
            return; // Salta completamente gli accordion marcati
        }
        
        toggle.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            
            if (target && target.classList.contains('show')) {
                e.preventDefault();
                e.stopPropagation();
                
                const bsCollapse = bootstrap.Collapse.getInstance(target) || new bootstrap.Collapse(target, { toggle: false });
                bsCollapse.hide();
            } else {
                dropdownToggles.forEach(otherToggle => {
                    if (otherToggle !== this && !otherToggle.closest('[data-exclude-sidebar-toggle="true"]')) {
                        const otherTarget = document.querySelector(otherToggle.getAttribute('href'));
                        if (otherTarget && otherTarget.classList.contains('show')) {
                            const bsOtherCollapse = bootstrap.Collapse.getInstance(otherTarget) || new bootstrap.Collapse(otherTarget, { toggle: false });
                            bsOtherCollapse.hide();
                        }
                    }
                });
            }
        });
    });
});
</script>