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

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// RECUPERO DATI BRAND
// =============================================
$brand = null;
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo brand: " . $e->getMessage();
}

// =============================================
// ELENCO NICHE E PIATTAFORME
// =============================================
$niches = [
    'Fashion & Beauty',
    'Health & Fitness',
    'Food & Cooking',
    'Travel',
    'Technology',
    'Gaming',
    'Lifestyle',
    'Parenting',
    'Business & Finance',
    'Education',
    'Sports',
    'Entertainment',
    'Art & Design',
    'Home & Garden',
    'Automotive'
];

$platforms = [
    'instagram' => 'Instagram',
    'tiktok' => 'TikTok',
    'youtube' => 'YouTube',
    'facebook' => 'Facebook',
    'twitter' => 'Twitter/X'
];

// =============================================
// GESTIONE INVIO FORM
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $budget = floatval($_POST['budget']);
        $niche = $_POST['niche'];
        $platforms_selected = isset($_POST['platforms']) ? $_POST['platforms'] : [];
        $target_audience = [
            'age_range' => $_POST['age_range'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'location' => $_POST['location'] ?? '',
            'interests' => $_POST['interests'] ?? ''
        ];
        $requirements = trim($_POST['requirements']);
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        $status = $_POST['status'] ?? 'draft';

        // Validazione
        if (empty($name)) {
            throw new Exception("Il nome della campagna è obbligatorio");
        }

        if (empty($description)) {
            throw new Exception("La descrizione della campagna è obbligatoria");
        }

        if ($budget <= 0) {
            throw new Exception("Il budget deve essere maggiore di 0");
        }

        if (empty($niche)) {
            throw new Exception("Seleziona un niche");
        }

        if (empty($platforms_selected)) {
            throw new Exception("Seleziona almeno una piattaforma");
        }

        // Inserimento nel database
        $stmt = $pdo->prepare("
            INSERT INTO campaigns 
            (brand_id, name, description, budget, niche, platforms, target_audience, requirements, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $brand['id'],
            $name,
            $description,
            $budget,
            $niche,
            json_encode($platforms_selected),
            json_encode($target_audience),
            $requirements,
            $start_date,
            $end_date,
            $status
        ]);

        $campaign_id = $pdo->lastInsertId();
        
        // Esegui il matching con gli influencer
        perform_influencer_matching($pdo, $campaign_id, $niche, $platforms_selected);

        $success = "Campagna creata con successo!";
        
        // Reindirizza alla dashboard campagne se salvata come attiva
        if ($status === 'active') {
            header("Location: campaigns.php?success=campaign_created");
            exit();
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// =============================================
// FUNZIONE DI MATCHING INFLUENCER
// =============================================
function perform_influencer_matching($pdo, $campaign_id, $campaign_niche, $campaign_platforms) {
    // Query per trovare influencer matching
    $query = "
        SELECT i.*, u.email 
        FROM influencers i 
        JOIN users u ON i.user_id = u.id 
        WHERE i.niche = ? 
        AND (
            i.instagram_handle IS NOT NULL AND ? = 1 OR
            i.tiktok_handle IS NOT NULL AND ? = 1 OR
            i.youtube_handle IS NOT NULL AND ? = 1
        )
        AND i.rate <= (SELECT budget FROM campaigns WHERE id = ?) * 0.1
        ORDER BY i.rating DESC, i.profile_views DESC
        LIMIT 20
    ";
    
    // Prepara i parametri per le piattaforme
    $instagram = in_array('instagram', $campaign_platforms) ? 1 : 0;
    $tiktok = in_array('tiktok', $campaign_platforms) ? 1 : 0;
    $youtube = in_array('youtube', $campaign_platforms) ? 1 : 0;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$campaign_niche, $instagram, $tiktok, $youtube, $campaign_id]);
    $matching_influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inserisci i match nella tabella
    foreach ($matching_influencers as $influencer) {
        $match_score = calculate_match_score($influencer, $campaign_niche, $campaign_platforms);
        
        $stmt = $pdo->prepare("
            INSERT INTO campaign_influencers (campaign_id, influencer_id, match_score)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$campaign_id, $influencer['id'], $match_score]);
    }
}

function calculate_match_score($influencer, $campaign_niche, $campaign_platforms) {
    $score = 0;
    
    // Match per niche (50 punti)
    if ($influencer['niche'] === $campaign_niche) {
        $score += 50;
    }
    
    // Match per piattaforme (30 punti)
    $platform_count = 0;
    if (in_array('instagram', $campaign_platforms) && !empty($influencer['instagram_handle'])) $platform_count++;
    if (in_array('tiktok', $campaign_platforms) && !empty($influencer['tiktok_handle'])) $platform_count++;
    if (in_array('youtube', $campaign_platforms) && !empty($influencer['youtube_handle'])) $platform_count++;
    
    $score += ($platform_count / count($campaign_platforms)) * 30;
    
    // Rating influencer (20 punti)
    $score += ($influencer['rating'] / 5) * 20;
    
    return round($score, 2);
}

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
            <h2>Crea Nuova Campagna</h2>
            <a href="campaigns.php" class="btn btn-outline-secondary">
                ← Torna alle Campagne
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Creazione Campagna -->
        <div class="card">
            <div class="card-body">
                <form method="POST" id="campaignForm">
                    <div class="row">
                        <!-- Informazioni Base -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nome Campagna *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="budget" class="form-label">Budget (€) *</label>
                                <input type="number" class="form-control" id="budget" name="budget" 
                                       min="0" step="0.01" value="<?php echo htmlspecialchars($_POST['budget'] ?? ''); ?>" required>
                                <div class="form-text">Il budget totale per questa campagna</div>
                            </div>

                            <div class="mb-3">
                                <label for="niche" class="form-label">Niche *</label>
                                <select class="form-select" id="niche" name="niche" required>
                                    <option value="">Seleziona un niche</option>
                                    <?php foreach ($niches as $niche): ?>
                                        <option value="<?php echo htmlspecialchars($niche); ?>" 
                                                <?php echo (($_POST['niche'] ?? '') === $niche) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($niche); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="platforms" class="form-label">Piattaforme Social *</label>
                                <div class="border p-3 rounded">
                                    <?php foreach ($platforms as $key => $platform): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="platforms[]" value="<?php echo $key; ?>" 
                                                   id="platform_<?php echo $key; ?>"
                                                   <?php echo (isset($_POST['platforms']) && in_array($key, $_POST['platforms'])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="platform_<?php echo $key; ?>">
                                                <?php echo htmlspecialchars($platform); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descrizione -->
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrizione Campagna *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Target Audience -->
                    <div class="mb-4">
                        <h5>Target Audience</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="age_range" class="form-label">Fascia d'Età</label>
                                <input type="text" class="form-control" id="age_range" name="age_range" 
                                       value="<?php echo htmlspecialchars($_POST['age_range'] ?? ''); ?>" 
                                       placeholder="es. 18-35">
                            </div>
                            <div class="col-md-3">
                                <label for="gender" class="form-label">Genere</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Tutti</option>
                                    <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Maschile</option>
                                    <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Femminile</option>
                                    <option value="both" <?php echo (($_POST['gender'] ?? '') === 'both') ? 'selected' : ''; ?>>Entrambi</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="location" class="form-label">Localizzazione</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                       placeholder="es. Italia">
                            </div>
                            <div class="col-md-3">
                                <label for="interests" class="form-label">Interessi</label>
                                <input type="text" class="form-control" id="interests" name="interests" 
                                       value="<?php echo htmlspecialchars($_POST['interests'] ?? ''); ?>" 
                                       placeholder="es. tecnologia, gaming">
                            </div>
                        </div>
                    </div>

                    <!-- Date e Requisiti -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Data Inizio</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">Data Fine</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Requisiti Specifici -->
                    <div class="mb-3">
                        <label for="requirements" class="form-label">Requisiti Specifici</label>
                        <textarea class="form-control" id="requirements" name="requirements" rows="3" 
                                  placeholder="Requisiti specifici per gli influencer..."><?php echo htmlspecialchars($_POST['requirements'] ?? ''); ?></textarea>
                    </div>

                    <!-- Stato -->
                    <div class="mb-4">
                        <label for="status" class="form-label">Stato Campagna</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft" <?php echo (($_POST['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>Bozza</option>
                            <option value="active" <?php echo (($_POST['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Attiva</option>
                        </select>
                        <div class="form-text">
                            <strong>Bozza:</strong> La campagna non sarà visibile agli influencer<br>
                            <strong>Attiva:</strong> La campagna sarà attiva e visibile per il matching
                        </div>
                    </div>

                    <!-- Pulsanti -->
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="save_draft" class="btn btn-outline-primary">
                            Salva come Bozza
                        </button>
                        <button type="submit" name="action" value="save_active" class="btn btn-primary">
                            Crea Campagna Attiva
                        </button>
                        <a href="campaigns.php" class="btn btn-secondary">Annulla</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('campaignForm');
    const statusField = document.getElementById('status');
    
    // Cambia stato in base al pulsante cliccato
    form.addEventListener('submit', function() {
        const submitButton = document.activeElement;
        if (submitButton.name === 'action') {
            if (submitButton.value === 'save_draft') {
                statusField.value = 'draft';
            } else if (submitButton.value === 'save_active') {
                statusField.value = 'active';
            }
        }
    });
    
    // Validazione date
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            if (this.value && endDate.value && this.value > endDate.value) {
                endDate.value = '';
            }
        });
        
        endDate.addEventListener('change', function() {
            if (this.value && startDate.value && this.value < startDate.value) {
                alert('La data di fine non può essere precedente alla data di inizio');
                this.value = '';
            }
        });
    }
});
</script>

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