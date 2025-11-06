<?php
require_once '../includes/config.php';
require_once '../includes/notification_functions.php';

// Verifica che l'utente sia un influencer loggato
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'influencer') {
    header("Location: /infl/auth/login.php");
    exit;
}

// Gestione salvataggio preferenze
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    $preferences = [];
    
    foreach ($_POST['preferences'] as $type_id => $settings) {
        $preferences[$type_id] = [
            'enabled' => isset($settings['enabled']),
            'email_enabled' => isset($settings['email_enabled'])
        ];
    }
    
    if (update_notification_preferences($pdo, $_SESSION['user_id'], 'influencer', $preferences)) {
        $_SESSION['success_message'] = "Preferenze notifiche aggiornate con successo";
    } else {
        $_SESSION['error_message'] = "Errore durante l'aggiornamento delle preferenze";
    }
    
    header("Location: settings.php#notifications");
    exit;
}

// Ottieni le preferenze attuali
$preferences = get_notification_preferences($pdo, $_SESSION['user_id'], 'influencer');
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impostazioni Influencer - Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="container mt-4">
        <div class="row">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/infl/influencers/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Impostazioni</li>
                    </ol>
                </nav>
                
                <h1 class="h2 mb-4">Impostazioni Influencer</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="list-group">
                    <a href="#notifications" class="list-group-item list-group-item-action active">
                        <i class="fas fa-bell me-2"></i>Preferenze Notifiche
                    </a>
                    <a href="/infl/influencers/profile.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user me-2"></i>Profilo Influencer
                    </a>
                    <a href="/infl/auth/profile-settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user me-2"></i>Profilo Utente
                    </a>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card" id="notifications">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bell me-2"></i>Preferenze Notifiche
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tipo Notifica</th>
                                            <th class="text-center">Notifica In-App</th>
                                            <th class="text-center">Email</th>
                                            <th>Descrizione</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($preferences as $pref): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($pref['name']); ?></strong>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-inline-block">
                                                        <input class="form-check-input" 
                                                               type="checkbox" 
                                                               name="preferences[<?php echo $pref['id']; ?>][enabled]"
                                                               id="enabled_<?php echo $pref['id']; ?>"
                                                               <?php echo $pref['enabled'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-inline-block">
                                                        <input class="form-check-input" 
                                                               type="checkbox" 
                                                               name="preferences[<?php echo $pref['id']; ?>][email_enabled]"
                                                               id="email_enabled_<?php echo $pref['id']; ?>"
                                                               <?php echo $pref['email_enabled'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($pref['description']); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="update_preferences" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Salva Preferenze
                                </button>
                                <a href="/infl/influencers/dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Ultime Notifiche
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $recent_notifications = get_all_notifications($pdo, $_SESSION['user_id'], 'influencer', 5);
                        if (empty($recent_notifications)): ?>
                            <p class="text-muted">Nessuna notifica recente</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_notifications as $notification): ?>
                                    <div class="list-group-item <?php echo !$notification['is_read'] ? 'list-group-item-warning' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <small><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <?php if (!$notification['is_read']): ?>
                                            <small class="text-warning">
                                                <i class="fas fa-circle me-1"></i>Non letta
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <!-- RIMOSSO: Bootstrap JS già incluso in footer.php -->
</body>
</html>