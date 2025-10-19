<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO CORRETTO
// =============================================
$config_file = dirname(dirname(dirname(__FILE__))) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE
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
try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        header("Location: create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    die("Errore nel caricamento del profilo: " . $e->getMessage());
}

// =============================================
// RECUPERO CAMPAGNA
// =============================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID campagna non specificato");
}

$campaign_id = intval($_GET['id']);
$campaign = null;
$has_applied = false;
$application = null;

try {
    // Recupera campagna
    $stmt = $pdo->prepare("
        SELECT c.*, b.company_name, b.website as brand_website, b.description as brand_description
        FROM campaigns c
        JOIN brands b ON c.brand_id = b.id
        WHERE c.id = ? AND c.status = 'active' AND c.is_public = TRUE AND c.allow_applications = TRUE
    ");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        die("Campagna non trovata o non disponibile");
    }
    
    // Verifica se l'influencer si è già candidato
    $stmt = $pdo->prepare("
        SELECT * FROM campaign_applications 
        WHERE campaign_id = ? AND influencer_id = ?
    ");
    $stmt->execute([$campaign_id, $influencer['id']]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_applied = $application !== false;
    
} catch (PDOException $e) {
    die("Errore nel caricamento della campagna: " . $e->getMessage());
}

// =============================================
// GESTIONE CANDIDATURA (VERSIONE SEMPLIFICATA)
// =============================================
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    if ($has_applied) {
        $error_msg = "Ti sei già candidato a questa campagna.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // 1. Crea candidatura
            $application_message = $_POST['application_message'] ?? '';
            $stmt = $pdo->prepare("
                INSERT INTO campaign_applications (campaign_id, influencer_id, application_message, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$campaign_id, $influencer['id'], $application_message]);
            
            // 2. Crea conversazione automatica (SOLO conversazione - niente participants separati)
            $stmt = $pdo->prepare("
                INSERT INTO conversations (brand_id, influencer_id, campaign_id, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$campaign['brand_id'], $influencer['id'], $campaign_id]);
            $conversation_id = $pdo->lastInsertId();
            
            // 3. Messaggio automatico di presentazione
            $auto_message = "Ciao! Mi chiamo " . htmlspecialchars($influencer['full_name']) . 
                          " e mi sono appena candidato alla tua campagna \"" . htmlspecialchars($campaign['name']) . "\".\n\n";
            
            if (!empty($application_message)) {
                $auto_message .= "Il mio messaggio di presentazione:\n" . $application_message . "\n\n";
            }
            
            $auto_message .= "Sono specializzato in " . htmlspecialchars($influencer['niche']) . 
                          " e spero di poter collaborare con te!";
            
            $stmt = $pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at)
                VALUES (?, ?, 'influencer', ?, NOW())
            ");
            $stmt->execute([$conversation_id, $_SESSION['user_id'], $auto_message]);
            
            $pdo->commit();
            $success_msg = "Candidatura inviata con successo! Il brand è stato notificato.";
            $has_applied = true;
            
            // Ricarica application
            $stmt = $pdo->prepare("
                SELECT * FROM campaign_applications 
                WHERE campaign_id = ? AND influencer_id = ?
            ");
            $stmt->execute([$campaign_id, $influencer['id']]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Errore nell'invio della candidatura: " . $e->getMessage();
        }
    }
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dettaglio Campagna</h2>
            <div>
                <a href="list.php" class="btn btn-outline-secondary">
                    ← Torna alle Campagne
                </a>
            </div>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Dettagli Campagna -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($campaign['name']); ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($campaign['description'])); ?></p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Budget:</strong> 
                                    <span class="badge bg-success fs-6">€<?php echo number_format($campaign['budget'], 2); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Niche:</strong>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($campaign['niche']); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Stato:</strong>
                                    <span class="badge bg-success">Attiva</span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Piattaforme:</strong><br>
                                    <?php 
                                    $platforms = json_decode($campaign['platforms'], true);
                                    if ($platforms): 
                                        foreach ($platforms as $platform): 
                                            $platform_names = [
                                                'instagram' => 'Instagram',
                                                'tiktok' => 'TikTok',
                                                'youtube' => 'YouTube',
                                                'facebook' => 'Facebook',
                                                'twitter' => 'Twitter/X'
                                            ];
                                    ?>
                                        <span class="badge bg-light text-dark me-1 mb-1"><?php echo $platform_names[$platform] ?? $platform; ?></span>
                                    <?php endforeach; endif; ?>
                                </div>
                                
                                <?php if ($campaign['start_date']): ?>
                                <div class="mb-3">
                                    <strong>Data Inizio:</strong>
                                    <?php echo date('d/m/Y', strtotime($campaign['start_date'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($campaign['end_date']): ?>
                                <div class="mb-3">
                                    <strong>Data Fine:</strong>
                                    <?php echo date('d/m/Y', strtotime($campaign['end_date'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($campaign['requirements']): ?>
                        <div class="mt-3">
                            <strong>Requisiti Specifici:</strong>
                            <p class="mt-1"><?php echo nl2br(htmlspecialchars($campaign['requirements'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        $target_audience = json_decode($campaign['target_audience'], true);
                        if ($target_audience && array_filter($target_audience)): 
                        ?>
                        <div class="mt-3">
                            <strong>Target Audience:</strong>
                            <div class="row mt-2">
                                <?php if (!empty($target_audience['age_range'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Età:</strong> <?php echo htmlspecialchars($target_audience['age_range']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($target_audience['gender'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Genere:</strong> <?php echo htmlspecialchars($target_audience['gender']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($target_audience['location'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Località:</strong> <?php echo htmlspecialchars($target_audience['location']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($target_audience['interests'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Interessi:</strong> <?php echo htmlspecialchars($target_audience['interests']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar - Brand e Candidatura -->
            <div class="col-md-4">
                <!-- Informazioni Brand -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">Informazioni Brand</h5>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($campaign['company_name']); ?></h6>
                        <?php if ($campaign['brand_description']): ?>
                            <p class="small"><?php echo nl2br(htmlspecialchars($campaign['brand_description'])); ?></p>
                        <?php endif; ?>
                        <?php if ($campaign['brand_website']): ?>
                            <a href="<?php echo htmlspecialchars($campaign['brand_website']); ?>" 
                               target="_blank" class="btn btn-outline-primary btn-sm w-100">
                                Visita Sito Web
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Stato Candidatura -->
                <div class="card mb-4">
                    <div class="card-header 
                        <?php echo !$has_applied ? 'bg-warning' : 
                              ($application['status'] === 'accepted' ? 'bg-success' : 
                              ($application['status'] === 'rejected' ? 'bg-danger' : 'bg-info')); ?> text-white">
                        <h5 class="card-title mb-0">Stato Candidatura</h5>
                    </div>
                    <div class="card-body text-center">
                        <?php if (!$has_applied): ?>
                            <p class="card-text">Non ti sei ancora candidato a questa campagna</p>
                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#applyModal">
                                Candidati Ora
                            </button>
                        <?php else: ?>
                            <?php
                            $status_texts = [
                                'pending' => 'In Attesa',
                                'accepted' => 'Accettata',
                                'rejected' => 'Rifiutata'
                            ];
                            $status_badges = [
                                'pending' => 'warning',
                                'accepted' => 'success',
                                'rejected' => 'danger'
                            ];
                            ?>
                            <h4 class="text-<?php echo $status_badges[$application['status']]; ?>">
                                <?php echo $status_texts[$application['status']]; ?>
                            </h4>
                            <p class="small text-muted">
                                Candidatura inviata il: <?php echo date('d/m/Y H:i', strtotime($application['created_at'])); ?>
                            </p>
                            
                            <?php if ($application['status'] === 'accepted'): ?>
                                <div class="alert alert-success mt-3">
                                    <strong>Congratulazioni!</strong> Il brand ha accettato la tua candidatura.
                                    Controlla i messaggi per i dettagli della collaborazione.
                                </div>
                            <?php elseif ($application['status'] === 'rejected'): ?>
                                <div class="alert alert-danger mt-3">
                                    <strong>Ci dispiace.</strong> Il brand ha declinato la tua candidatura.
                                    Continua a cercare altre opportunità!
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mt-3">
                                    <strong>In attesa di risposta.</strong> Il brand riceverà una notifica 
                                    e ti risponderà al più presto.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Match con il tuo profilo -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Match con il tuo Profilo</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $match_score = 0;
                        $match_details = [];
                        
                        // Calcola match niche
                        if ($influencer['niche'] === $campaign['niche']) {
                            $match_score += 40;
                            $match_details[] = "🎯 Niche identica (+40%)";
                        } elseif (similar_niche($influencer['niche'], $campaign['niche'])) {
                            $match_score += 25;
                            $match_details[] = "📈 Niche simile (+25%)";
                        }
                        
                        // Calcola match piattaforme
                        $user_platforms = [];
                        if (!empty($influencer['instagram_handle'])) $user_platforms[] = 'instagram';
                        if (!empty($influencer['tiktok_handle'])) $user_platforms[] = 'tiktok';
                        if (!empty($influencer['youtube_handle'])) $user_platforms[] = 'youtube';
                        
                        $campaign_platforms = json_decode($campaign['platforms'], true) ?: [];
                        $common_platforms = array_intersect($user_platforms, $campaign_platforms);
                        
                        if (!empty($common_platforms)) {
                            $platform_score = count($common_platforms) * 15;
                            $match_score += min($platform_score, 30);
                            $match_details[] = "📱 " . count($common_platforms) . " piattaforme in comune (+" . $platform_score . "%)";
                        }
                        
                        // Calcola affordability
                        if (!empty($influencer['rate']) && !empty($campaign['budget'])) {
                            $affordability_ratio = $influencer['rate'] / $campaign['budget'];
                            if ($affordability_ratio <= 0.3) {
                                $match_score += 30;
                                $match_details[] = "💰 Budget ottimale (+30%)";
                            } elseif ($affordability_ratio <= 0.6) {
                                $match_score += 15;
                                $match_details[] = "💸 Budget accettabile (+15%)";
                            }
                        }
                        
                        $match_score = min($match_score, 100);
                        ?>
                        
                        <div class="text-center mb-3">
                            <h3 class="text-<?php echo $match_score >= 70 ? 'success' : ($match_score >= 40 ? 'warning' : 'danger'); ?>">
                                <?php echo $match_score; ?>%
                            </h3>
                            <p class="text-muted">Match Score</p>
                        </div>
                        
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar 
                                <?php echo $match_score >= 70 ? 'bg-success' : 
                                      ($match_score >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                role="progressbar" 
                                style="width: <?php echo $match_score; ?>%">
                                <?php echo $match_score; ?>%
                            </div>
                        </div>
                        
                        <?php if (!empty($match_details)): ?>
                            <div class="small">
                                <?php foreach ($match_details as $detail): ?>
                                    <div class="mb-1"><?php echo $detail; ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Candidatura -->
<?php if (!$has_applied): ?>
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Candidati a "<?php echo htmlspecialchars($campaign['name']); ?>"</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="apply" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Messaggio di Presentazione (opzionale)</label>
                        <textarea class="form-control" name="application_message" rows="4" 
                                  placeholder="Presentati al brand e spiega perché sei perfetto per questa campagna..."></textarea>
                        <div class="form-text">
                            Questo messaggio verrà inviato automaticamente al brand insieme alla tua candidatura.
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <strong>Cosa succede dopo:</strong><br>
                            • Verrà creata una conversazione con il brand<br>
                            • Riceverai una notifica quando il brand risponderà<br>
                            • Puoi seguire lo stato della candidatura dalla tua dashboard
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success">Invia Candidatura</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Funzione helper per niche simili
function similar_niche($niche1, $niche2) {
    $similar_groups = [
        ['Fashion', 'Beauty', 'Lifestyle'],
        ['Fitness', 'Health', 'Wellness'],
        ['Travel', 'Adventure', 'Photography'],
        ['Food', 'Cooking', 'Restaurant'],
        ['Gaming', 'Tech', 'Electronics']
    ];
    
    foreach ($similar_groups as $group) {
        if (in_array($niche1, $group) && in_array($niche2, $group)) {
            return true;
        }
    }
    return false;
}

// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>