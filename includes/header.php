<?php
// Includi la configurazione all'inizio
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>">InfluencerMarket</a>
            <div class="navbar-nav ms-auto">
                <?php if(is_logged_in()): ?>
                    <span class="navbar-text me-3">
                        Ciao, <?php echo get_current_user()['name']; ?>
                    </span>
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/auth/logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/auth/login.php">Login</a>
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/auth/register.php">Registrati</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container mt-4">