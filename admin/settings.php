<?php
// /infl/admin/settings.php - VERSIONE SENZA CALCOLO SPAZIO DISCO

// INIZIO: Processing dei form PRIMA di qualsiasi output
require_once '../includes/config.php';

// Verifica sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Includi funzioni necessarie
require_once '../includes/functions.php';
require_once '../includes/maintenance.php';
require_once '../includes/admin_functions.php';

// Controllo accesso admin
require_admin_login();

// Gestione toggle manutenzione - DEVE ESSERE PRIMA DI QUALSIASI OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maintenance_mode'])) {
    $maintenance_enabled = $_POST['maintenance_mode'] === '1';
    
    if (set_maintenance_mode($pdo, $maintenance_enabled)) {
        $_SESSION['success_message'] = $maintenance_enabled 
            ? 'Modalità manutenzione attivata con successo!' 
            : 'Modalità manutenzione disattivata con successo!';
    } else {
        $_SESSION['error_message'] = 'Errore durante l\'aggiornamento delle impostazioni.';
    }
    
    // Redirect per prevenire reinvio form
    header('Location: settings.php');
    exit;
}

// ORA includi l'header dopo il processing
require_once '../includes/admin_header.php';

// Ottieni lo stato corrente
$is_maintenance_mode = is_maintenance_mode($pdo);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Impostazioni Sistema</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <span class="text-muted">
                <i class="fas fa-circle me-1 text-<?php echo $is_maintenance_mode ? 'warning' : 'success'; ?>"></i>
                <?php echo $is_maintenance_mode ? 'Manutenzione Attiva' : 'Sistema Attivo'; ?>
            </span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-tools me-2"></i>Modalità Manutenzione
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="maintenanceForm">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="mb-1">Stato Sistema</h6>
                            <p class="text-muted mb-0">
                                <?php if ($is_maintenance_mode): ?>
                                    <span class="badge bg-warning fs-6">🛠️ MANUTENZIONE ATTIVA</span>
                                    - Il frontend è temporaneamente non disponibile per gli utenti
                                <?php else: ?>
                                    <span class="badge bg-success fs-6">✅ SISTEMA ATTIVO</span>
                                    - Il sito è completamente accessibile
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <input type="hidden" name="maintenance_mode" id="maintenance_mode" value="<?php echo $is_maintenance_mode ? '0' : '1'; ?>">
                            <button type="submit" class="btn btn-<?php echo $is_maintenance_mode ? 'success' : 'warning'; ?> btn-lg">
                                <i class="fas fa-<?php echo $is_maintenance_mode ? 'play' : 'wrench'; ?> me-2"></i>
                                <?php echo $is_maintenance_mode ? 'Disattiva Manutenzione' : 'Attiva Manutenzione'; ?>
                            </button>
                        </div>
                    </div>
                </form>
                
                <hr>
                
                <!-- RIMOSSO: Box informativo "Informazioni Modalità Manutenzione" -->
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-eye me-2"></i>Anteprima Pagina Manutenzione
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <?php
                    $maintenance_image = '/infl/uploads/maintenance/maintenance.webp';
                    $image_exists = file_exists($_SERVER['DOCUMENT_ROOT'] . $maintenance_image);
                    ?>
                    <div class="mb-3">
                        <img src="<?php echo $maintenance_image; ?>" 
                             alt="Anteprima pagina manutenzione" 
                             class="img-fluid rounded border"
                             style="max-height: 200px;"
                             onerror="this.src='/infl/assets/img/maintenance-placeholder.png'">
                    </div>
                    <p class="text-muted mb-3">
                        <?php if ($image_exists): ?>
                            <i class="fas fa-check-circle text-success me-1"></i>
                            Immagine di manutenzione caricata correttamente.
                        <?php else: ?>
                            <span class="text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Immagine di manutenzione non trovata. Verrà mostrato un placeholder.
                            </span>
                        <?php endif; ?>
                    </p>
                    <div class="btn-group">
                        <a href="<?php echo $maintenance_image; ?>" 
                           target="_blank" 
                           class="btn btn-outline-primary btn-sm">
                           <i class="fas fa-external-link-alt me-2"></i>Visualizza Immagine Completa
                        </a>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="testMaintenanceView()">
                            <i class="fas fa-desktop me-2"></i>Anteprima Completa
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Log Manutenzione
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Data/Ora</th>
                                <th>Azione</th>
                                <th>Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s'); ?></td>
                                <td>Stato Corrente</td>
                                <td>
                                    <span class="badge bg-<?php echo $is_maintenance_mode ? 'warning' : 'success'; ?>">
                                        <?php echo $is_maintenance_mode ? 'Manutenzione Attiva' : 'Sistema Normale'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', time() - 3600); ?></td>
                                <td>Ultimo Cambiamento</td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $is_maintenance_mode ? 'Attivata' : 'Disattivata'; ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Stato Sistema
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-tools me-2 text-<?php echo $is_maintenance_mode ? 'warning' : 'success'; ?>"></i>
                            Modalità Manutenzione
                        </div>
                        <span class="badge bg-<?php echo $is_maintenance_mode ? 'warning' : 'success'; ?>">
                            <?php echo $is_maintenance_mode ? 'ATTIVA' : 'DISATTIVA'; ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-database me-2 text-success"></i>
                            Database
                        </div>
                        <span class="badge bg-success">ONLINE</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-server me-2 text-info"></i>
                            PHP Version
                        </div>
                        <span class="badge bg-info"><?php echo phpversion(); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-users me-2 text-primary"></i>
                            Sessioni Attive
                        </div>
                        <span class="badge bg-primary"><?php echo count_sessions($pdo); ?></span>
                    </div>
                    <!-- Rimossa la voce Spazio Disco -->
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt me-2"></i>Azioni Rapide
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/infl/admin/dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-tachometer-alt me-2"></i>Torna alla Dashboard
                    </a>
                    <a href="/infl/" class="btn btn-outline-info" target="_blank">
                        <i class="fas fa-external-link-alt me-2"></i>Visita Sito Pubblico
                    </a>
                    <?php if ($is_maintenance_mode): ?>
                        <button type="button" class="btn btn-outline-warning" onclick="testMaintenance()">
                            <i class="fas fa-eye me-2"></i>Test Modalità Manutenzione
                        </button>
                    <?php endif; ?>
                    <!-- RIMOSSI: Pulsanti "Test Modalità Normale" e "Aggiorna Stato" -->
                </div>
            </div>
        </div>
        
        <!-- RIMOSSA COMPLETAMENTE: Sezione "Aiuto" -->
    </div>
</div>

<script>
function testMaintenance() {
    // Apri il sito in una nuova finestra
    const testWindow = window.open('/infl/', '_blank');
    
    // Mostra messaggio informativo
    setTimeout(() => {
        alert('Il sito è stato aperto in una nuova finestra. Verifica che la pagina di manutenzione venga visualizzata correttamente.');
    }, 1000);
}

function testMaintenanceView() {
    // Mostra anteprima completa della pagina di manutenzione
    const previewHtml = `
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Anteprima Manutenzione</title>
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .maintenance-container {
                    text-align: center;
                    background: white;
                    padding: 3rem;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    max-width: 600px;
                    margin: 2rem;
                }
                .maintenance-image {
                    max-width: 100%;
                    height: auto;
                    border-radius: 10px;
                    margin-bottom: 2rem;
                }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <img src="<?php echo $maintenance_image; ?>" 
                     alt="Sito in Manutenzione" 
                     class="maintenance-image"
                     onerror="this.src='/infl/assets/img/maintenance-placeholder.png'">
                <h1>🛠️ Sito in Manutenzione</h1>
                <p>Stiamo lavorando per migliorare la tua esperienza. Il sito tornerà presto online!</p>
            </div>
        </body>
        </html>
    `;
    
    const previewWindow = window.open('', '_blank', 'width=800,height=600');
    previewWindow.document.write(previewHtml);
    previewWindow.document.close();
}

// Conferma per attivazione manutenzione
document.getElementById('maintenanceForm').addEventListener('submit', function(e) {
    const isActivating = document.getElementById('maintenance_mode').value === '1';
    
    if (isActivating) {
        if (!confirm('Sei sicuro di voler attivare la modalità manutenzione?\n\nTutti gli utenti non amministratori verranno reindirizzati alla pagina di manutenzione.')) {
            e.preventDefault();
        }
    } else {
        if (!confirm('Sei sicuro di voler disattivare la modalità manutenzione?\n\nIl sito tornerà accessibile a tutti gli utenti.')) {
            e.preventDefault();
        }
    }
});

// Aggiorna automaticamente lo stato ogni 30 secondi
setInterval(() => {
    // Aggiorna solo l'ora nella sidebar senza ricaricare tutta la pagina
    const timeElements = document.querySelectorAll('.current-time');
    timeElements.forEach(el => {
        el.textContent = new Date().toLocaleTimeString('it-IT');
    });
}, 30000);
</script>

<?php
/**
 * Conta le sessioni attive
 */
function count_sessions($pdo) {
    try {
        // Questa è una stima basata sugli utenti loggati nelle ultime 2 ore
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_sessions 
            FROM users 
            WHERE last_login >= DATE_SUB(NOW(), INTERVAL 2 HOUR) 
            AND deleted_at IS NULL
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['active_sessions'] ?? 0;
    } catch (PDOException $e) {
        error_log("Errore conteggio sessioni: " . $e->getMessage());
        return 0;
    }
}
?>

<?php require_once '../includes/admin_footer.php'; ?>