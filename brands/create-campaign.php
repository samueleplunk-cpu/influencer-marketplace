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
// FUNZIONE DI MATCHING INFLUENCER - VERSIONE CORRETTA
// =============================================
function perform_influencer_matching($pdo, $campaign_id, $campaign_niche, $campaign_platforms) {
    // Costruisci dinamicamente le condizioni per le piattaforme
    $platform_conditions = [];
    $params = [$campaign_niche];
    
    // DEBUG: Log per verificare i parametri
    error_log("=== INFLUENCER MATCHING DEBUG ===");
    error_log("Campaign ID: $campaign_id");
    error_log("Niche: $campaign_niche");
    error_log("Platforms selected: " . implode(', ', $campaign_platforms));
    
    if (in_array('instagram', $campaign_platforms)) {
        $platform_conditions[] = "(i.instagram_handle IS NOT NULL AND i.instagram_handle != '')";
    }
    
    if (in_array('tiktok', $campaign_platforms)) {
        $platform_conditions[] = "(i.tiktok_handle IS NOT NULL AND i.tiktok_handle != '')";
    }
    
    if (in_array('youtube', $campaign_platforms)) {
        $platform_conditions[] = "(i.youtube_handle IS NOT NULL AND i.youtube_handle != '')";
    }
    
    if (in_array('facebook', $campaign_platforms)) {
        $platform_conditions[] = "(i.facebook_handle IS NOT NULL AND i.facebook_handle != '')";
    }
    
    if (in_array('twitter', $campaign_platforms)) {
        $platform_conditions[] = "(i.twitter_handle IS NOT NULL AND i.twitter_handle != '')";
    }
    
    // Se non ci sono condizioni piattaforma, non cercare influencer
    if (empty($platform_conditions)) {
        error_log("No platform conditions for campaign $campaign_id");
        return;
    }
    
    $platform_where = implode(' OR ', $platform_conditions);
    
    // Query corretta con logica OR per piattaforme e AND per niche
    $query = "
        SELECT i.*, u.email 
        FROM influencers i 
        JOIN users u ON i.user_id = u.id 
        WHERE i.niche = ? 
        AND ($platform_where)
        AND i.rate <= (SELECT budget FROM campaigns WHERE id = ?) * 0.1
        AND i.id NOT IN (
            SELECT influencer_id FROM campaign_influencers WHERE campaign_id = ?
        )
        ORDER BY i.rating DESC, i.profile_views DESC
        LIMIT 50
    ";
    
    $params[] = $campaign_id; // Per il budget
    $params[] = $campaign_id; // Per l'exclusion check
    
    error_log("Query: " . $query);
    error_log("Params: " . implode(', ', $params));
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $matching_influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($matching_influencers) . " matching influencers");
        
        // Inserisci i match nella tabella
        foreach ($matching_influencers as $influencer) {
            $match_score = calculate_match_score($influencer, $campaign_niche, $campaign_platforms);
            
            $insert_stmt = $pdo->prepare("
                INSERT INTO campaign_influencers (campaign_id, influencer_id, match_score, status)
                VALUES (?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE match_score = ?
            ");
            $insert_stmt->execute([$campaign_id, $influencer['id'], $match_score, $match_score]);
            
            error_log("Inserted match: influencer {$influencer['id']} with score $match_score");
        }
        
    } catch (PDOException $e) {
        error_log("Error in perform_influencer_matching: " . $e->getMessage());
        throw $e;
    }
}

function calculate_match_score($influencer, $campaign_niche, $campaign_platforms) {
    $score = 0;
    
    // Match per niche (50 punti)
    if ($influencer['niche'] === $campaign_niche) {
        $score += 50;
    } else {
        // Match parziale per niche (25 punti)
        $influencer_niche_lower = strtolower($influencer['niche']);
        $campaign_niche_lower = strtolower($campaign_niche);
        
        if (strpos($influencer_niche_lower, $campaign_niche_lower) !== false || 
            strpos($campaign_niche_lower, $influencer_niche_lower) !== false) {
            $score += 25;
        }
    }
    
    // Match per piattaforme (30 punti)
    $platform_matches = 0;
    $total_platforms = count($campaign_platforms);
    
    foreach ($campaign_platforms as $platform) {
        switch ($platform) {
            case 'instagram':
                if (!empty($influencer['instagram_handle'])) $platform_matches++;
                break;
            case 'tiktok':
                if (!empty($influencer['tiktok_handle'])) $platform_matches++;
                break;
            case 'youtube':
                if (!empty($influencer['youtube_handle'])) $platform_matches++;
                break;
            case 'facebook':
                if (!empty($influencer['facebook_handle'])) $platform_matches++;
                break;
            case 'twitter':
                if (!empty($influencer['twitter_handle'])) $platform_matches++;
                break;
        }
    }
    
    if ($total_platforms > 0) {
        $score += ($platform_matches / $total_platforms) * 30;
    }
    
    // Rating influencer (20 punti)
    $rating = floatval($influencer['rating']);
    $score += ($rating / 5) * 20;
    
    // Bonus per profile views (fino a 10 punti extra)
    $profile_views = intval($influencer['profile_views']);
    if ($profile_views > 10000) {
        $score += 10;
    } elseif ($profile_views > 5000) {
        $score += 5;
    } elseif ($profile_views > 1000) {
        $score += 2;
    }
    
    return min(round($score, 2), 100); // Massimo 100 punti
}

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
        
        // Esegui il matching con gli influencer SOLO se la campagna è attiva
        if ($status === 'active') {
            perform_influencer_matching($pdo, $campaign_id, $niche, $platforms_selected);
            $success = "Campagna creata con successo! Matching influencer completato.";
        } else {
            $success = "Campagna salvata come bozza. Il matching verrà eseguito quando la attiverai.";
        }
        
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
    
    // Validazione piattaforme
    const platformCheckboxes = document.querySelectorAll('input[name="platforms[]"]');
    form.addEventListener('submit', function(e) {
        const checkedPlatforms = Array.from(platformCheckboxes).filter(cb => cb.checked);
        if (checkedPlatforms.length === 0) {
            e.preventDefault();
            alert('Seleziona almeno una piattaforma social');
            return false;
        }
    });
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