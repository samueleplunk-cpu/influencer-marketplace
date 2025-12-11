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
// VERIFICA AUTENTICAZIONE UTENTE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /infl/auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// RECUPERO DATI INFLUENCER
// =============================================
$influencer = null;
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        header("Location: create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo influencer: " . $e->getMessage();
}

// =============================================
// RECUPERO SPONSOR DELL'INFLUENCER
// =============================================
$sponsors = [];
$sponsor_stats = [
    'total' => 0,
    'active' => 0,
    'draft' => 0,
    'completed' => 0
];

try {
    $stmt = $pdo->prepare("
        SELECT id, title, image_url, budget, category, status, created_at
        FROM sponsors 
        WHERE influencer_id = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
    ");
    $stmt->execute([$influencer['id']]);
    $sponsors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiche
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
        FROM sponsors 
        WHERE influencer_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$influencer['id']]);
    $sponsor_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento degli sponsor: " . $e->getMessage();
}

// =============================================
// MAPPA CATEGORIE UNIFICATE
// =============================================
$category_mapping = [
    'lifestyle' => 'Lifestyle',
    'fashion' => 'Fashion',
    'beauty' => 'Beauty & Makeup',
    'fitness' => 'Fitness & Wellness',
    'travel' => 'Travel',
    'food' => 'Food',
    'tech' => 'Tech',
    'gaming' => 'Gaming'
];

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>I Miei Sponsor</h2>
            <a href="create-sponsor.php" class="btn btn-primary">
                ➕ Nuovo Sponsor
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'sponsor_created'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Sponsor creato con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiche Sponsor -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center py-3">
                        <h5 class="card-title"><?php echo $sponsor_stats['total']; ?></h5>
                        <p class="card-text small">Sponsor Totali</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body text-center py-3">
                        <h5 class="card-title"><?php echo $sponsor_stats['active']; ?></h5>
                        <p class="card-text small">Attivi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center py-3">
                        <h5 class="card-title"><?php echo $sponsor_stats['draft']; ?></h5>
                        <p class="card-text small">Bozze</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body text-center py-3">
                        <h5 class="card-title"><?php echo $sponsor_stats['completed']; ?></h5>
                        <p class="card-text small">Completati</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista Sponsor -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">I miei Sponsor</h5>
            </div>
            <div class="card-body">
                <?php if (empty($sponsors)): ?>
                    <div class="text-center py-5">
                        <h5>Nessuno sponsor creato</h5>
                        <p class="text-muted mb-4">
                            Crea il tuo primo sponsor per iniziare a collaborare con i brand
                        </p>
                        <a href="create-sponsor.php" class="btn btn-primary">
                            Crea il Primo Sponsor
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Copertina</th>
                                    <th>Titolo</th>
                                    <th>Prezzo</th>
                                    <th>Categoria</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sponsors as $sponsor): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            // Definisci il percorso del placeholder
                                            $placeholder_path = '/infl/uploads/placeholder/sponsor_influencer_preview.png';
                                            
                                            if (!empty($sponsor['image_url'])) {
                                                // Se esiste un'immagine caricata dall'influencer, mostra quella
                                                $image_path = '/infl/uploads/sponsor/' . htmlspecialchars($sponsor['image_url']);
                                                ?>
                                                <img src="<?php echo $image_path; ?>" 
                                                     alt="<?php echo htmlspecialchars($sponsor['title']); ?>" 
                                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"
                                                     onerror="this.onerror=null; this.src='<?php echo $placeholder_path; ?>';">
                                            <?php } else { 
                                                // Se NON esiste un'immagine caricata, mostra il placeholder
                                                ?>
                                                <img src="<?php echo $placeholder_path; ?>" 
                                                     alt="Placeholder - <?php echo htmlspecialchars($sponsor['title']); ?>" 
                                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"
                                                     title="Immagine di copertina non caricata">
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sponsor['title']); ?></strong>
                                        </td>
                                        <td>
                                            <strong>€<?php echo number_format($sponsor['budget'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php 
                                            $display_category = $sponsor['category'];
                                            if (isset($category_mapping[$sponsor['category']])) {
                                                $display_category = $category_mapping[$sponsor['category']];
                                            }
                                            ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($display_category); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'draft' => 'warning',
                                                'active' => 'success',
                                                'completed' => 'info',
                                                'cancelled' => 'danger'
                                            ];
                                            $badge_class = $status_badges[$sponsor['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'draft' => 'Bozza',
                                                    'active' => 'Attivo',
                                                    'completed' => 'Completato',
                                                    'cancelled' => 'Cancellato'
                                                ];
                                                echo $status_labels[$sponsor['status']] ?? ucfirst($sponsor['status']);
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="view-sponsor.php?id=<?php echo $sponsor['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm" title="Visualizza">
                                                    👁️
                                                </a>
                                                <a href="edit-sponsor.php?id=<?php echo $sponsor['id']; ?>" 
                                                   class="btn btn-outline-secondary btn-sm" title="Modifica">
                                                    ✏️
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>