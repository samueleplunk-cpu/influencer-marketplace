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

// Verifica che l'utente sia un brand
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// INCLUSIONE FUNZIONI SOCIAL NETWORK E CATEGORIE
// =============================================
require_once dirname(__DIR__) . '/includes/social_network_functions.php';
require_once dirname(__DIR__) . '/includes/category_functions.php';

// =============================================
// INCLUSIONE HEADER CON PERCORSO ASSOLUTO
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;

// =============================================
// RECUPERO BRAND_ID PER MESSAGGI
// =============================================
$brand_id = null;
$stmt_brand = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
$stmt_brand->execute([$_SESSION['user_id']]);
$brand_data = $stmt_brand->fetch(PDO::FETCH_ASSOC);
if ($brand_data) {
    $brand_id = $brand_data['id'];
}

// =============================================
// RECUPERO CATEGORIE ATTIVE PER FILTRO
// =============================================
$active_categories = get_active_categories($pdo);

// =============================================
// PARAMETRI DI RICERCA E FILTRI
// =============================================
$search_query = $_GET['search'] ?? '';
$niche_filter = $_GET['niche'] ?? '';
$platform_filter = $_GET['platform'] ?? '';
$min_rate = $_GET['min_rate'] ?? '';
$max_rate = $_GET['max_rate'] ?? '';
$min_rating = $_GET['min_rating'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Risultati per pagina
$offset = ($page - 1) * $limit;

// =============================================
// COSTRUZIONE QUERY DINAMICA
// =============================================
$where_conditions = [];
$params = [];

// Filtro ricerca per nome/handle
if (!empty($search_query)) {
    $where_conditions[] = "(full_name LIKE ? OR instagram_handle LIKE ? OR tiktok_handle LIKE ? OR youtube_handle LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Filtro per categorie - DINAMICO DAL DATABASE
if (!empty($niche_filter)) {
    // Crea una mappatura nome categoria -> slug per compatibilità
    $category_mapping = [];
    foreach ($active_categories as $category) {
        $category_mapping[$category['name']] = $category['slug'];
    }
    
    // Se la categoria selezionata esiste, usa lo slug corrispondente
    if (isset($category_mapping[$niche_filter])) {
        $where_conditions[] = "niche = ?";
        $params[] = $category_mapping[$niche_filter];
    }
}

// Filtro per piattaforma
if (!empty($platform_filter)) {
    $social_networks = get_active_social_networks();
    $platform_exists = false;
    
    // Verifica che la piattaforma selezionata esista tra quelle attive
    foreach ($social_networks as $social) {
        if ($social['slug'] === $platform_filter) {
            $platform_exists = true;
            break;
        }
    }
    
    if ($platform_exists) {
        $where_conditions[] = "{$platform_filter}_handle IS NOT NULL AND {$platform_filter}_handle != ''";
    }
}

// Filtro per tariffa minima
if (!empty($min_rate) && is_numeric($min_rate)) {
    $where_conditions[] = "rate >= ?";
    $params[] = $min_rate;
}

// Filtro per tariffa massima
if (!empty($max_rate) && is_numeric($max_rate)) {
    $where_conditions[] = "rate <= ?";
    $params[] = $max_rate;
}

// Filtro per rating minimo
if (!empty($min_rating) && is_numeric($min_rating)) {
    $where_conditions[] = "rating >= ?";
    $params[] = $min_rating;
}

// Query base
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Query per il conteggio totale (per paginazione)
$count_sql = "SELECT COUNT(*) as total FROM influencers $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_results = $count_stmt->fetchColumn();
$total_pages = ceil($total_results / $limit);

// Query per i risultati con ordinamento casuale
$results_sql = "SELECT * FROM influencers $where_sql ORDER BY RAND() LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($results_sql);
$stmt->execute($params);
$influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// RECUPERO CONVERSAZIONI ESISTENTI PER TUTTI GLI INFLUENCER
// =============================================
$existing_conversations = [];
if ($brand_id && !empty($influencers)) {
    // Estrai tutti gli ID influencer dai risultati
    $influencer_ids = array_column($influencers, 'id');
    
    // Recupera tutte le conversazioni esistenti in una sola query
    try {
        if (!empty($influencer_ids)) {
            // Crea i placeholder per la query
            $placeholders = implode(',', array_fill(0, count($influencer_ids), '?'));
            
            $stmt = $pdo->prepare("
                SELECT influencer_id, id as conversation_id 
                FROM conversations 
                WHERE brand_id = ? 
                AND influencer_id IN ($placeholders)
                AND campaign_id IS NULL
            ");
            
            // Parametri: brand_id + tutti gli influencer_ids
            $params_conv = array_merge([$brand_id], $influencer_ids);
            $stmt->execute($params_conv);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Trasforma in array associativo influencer_id => conversation_id
            foreach ($results as $row) {
                $existing_conversations[$row['influencer_id']] = $row['conversation_id'];
            }
        }
    } catch (PDOException $e) {
        error_log("Errore recupero conversazioni esistenti: " . $e->getMessage());
        // Continua senza conversazioni esistenti
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Cerca Influencer</h2>
            <a href="dashboard.php" class="btn btn-outline-primary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- FILTRI DI RICERCA -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Filtri di Ricerca</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <!-- Ricerca per Nome/Handle -->
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cerca per Nome/Handle</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Nome, Instagram, TikTok, YouTube...">
                    </div>

                    <!-- Filtro Categoria -->
                    <div class="col-md-3">
                        <label for="niche" class="form-label">Categoria</label>
                        <select class="form-select" id="niche" name="niche">
                            <option value="">Tutte le categorie</option>
                            <?php foreach ($active_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>" 
                                    <?php echo $niche_filter === $category['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro Piattaforma -->
                    <div class="col-md-2">
                        <label for="platform" class="form-label">Piattaforma</label>
                        <select class="form-select" id="platform" name="platform">
                            <option value="">Tutte</option>
                            <?php
                            $social_networks = get_active_social_networks();
                            foreach ($social_networks as $social): ?>
                                <option value="<?php echo $social['slug']; ?>" 
                                    <?php echo $platform_filter === $social['slug'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($social['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro Tariffa -->
                    <div class="col-md-3">
                        <label class="form-label">Tariffa (€)</label>
                        <div class="row g-2">
                            <div class="col">
                                <input type="number" class="form-control" name="min_rate" 
                                       value="<?php echo htmlspecialchars($min_rate); ?>" 
                                       placeholder="Min" min="0" step="10">
                            </div>
                            <div class="col">
                                <input type="number" class="form-control" name="max_rate" 
                                       value="<?php echo htmlspecialchars($max_rate); ?>" 
                                       placeholder="Max" min="0" step="10">
                            </div>
                        </div>
                    </div>

                    <!-- Filtro Rating -->
                    <div class="col-md-2">
                        <label for="min_rating" class="form-label">Rating Minimo</label>
                        <select class="form-select" id="min_rating" name="min_rating">
                            <option value="">Qualsiasi</option>
                            <option value="4" <?php echo $min_rating === '4' ? 'selected' : ''; ?>>4+ Stelle</option>
                            <option value="3" <?php echo $min_rating === '3' ? 'selected' : ''; ?>>3+ Stelle</option>
                            <option value="2" <?php echo $min_rating === '2' ? 'selected' : ''; ?>>2+ Stelle</option>
                        </select>
                    </div>

                    <!-- Pulsanti -->
                    <div class="col-md-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Cerca
                            </button>
                            <a href="search-influencers.php" class="btn btn-outline-secondary">
                                Reset Filtri
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- RISULTATI RICERCA -->
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Risultati (<?php echo $total_results; ?> influencer trovati)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($influencers)): ?>
                    <div class="text-center py-5">
                        <h4 class="text-muted">Nessun influencer trovato</h4>
                        <p class="text-muted">Prova a modificare i filtri di ricerca</p>
                    </div>
                <?php else: ?>
                    <!-- GRIGLIA INFLUENCER -->
                    <div class="row">
                        <?php foreach ($influencers as $influencer): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 influencer-card">
                                    <!-- Immagine Profilo -->
                                    <div class="position-relative">
                                        <?php 
                                        // Costruisci il percorso dell'immagine
                                        $profile_image_path = '';
                                        if (!empty($influencer['profile_image'])) {
                                            // Se l'influencer ha un'immagine personalizzata
                                            $profile_image_path = '/infl/uploads/' . htmlspecialchars($influencer['profile_image']);
                                        } else {
                                            // Se l'influencer NON ha un'immagine personalizzata, usa il placeholder
                                            $profile_image_path = '/infl/uploads/placeholder/sponsor_influencer_dashboard.png';
                                        }
                                        ?>
                                        <img src="<?php echo $profile_image_path; ?>" 
                                             class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($influencer['full_name']); ?>"
                                             style="height: 200px; object-fit: cover;">
                                        
                                        <!-- Badge Rating -->
                                        <?php if (!empty($influencer['rating'])): ?>
                                            <div class="position-absolute top-0 end-0 m-2">
                                                <span class="badge bg-warning text-dark">
                                                    ★ <?php echo number_format($influencer['rating'], 1); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body">
                                        <!-- Nome e Categoria -->
                                        <h5 class="card-title"><?php echo htmlspecialchars($influencer['full_name']); ?></h5>
                                        <?php if (!empty($influencer['niche'])): ?>
                                            <?php
                                            // Crea una mappatura slug -> nome per il display
                                            $slug_to_name_mapping = [];
                                            foreach ($active_categories as $category) {
                                                $slug_to_name_mapping[$category['slug']] = $category['name'];
                                            }
                                            
                                            $original_niche = $influencer['niche'];
                                            $display_niche = $slug_to_name_mapping[$original_niche] ?? $original_niche;
                                            ?>
                                            <span class="badge bg-info mb-2"><?php echo htmlspecialchars($display_niche); ?></span>
                                        <?php endif; ?>

                                        <!-- Bio -->
                                        <?php if (!empty($influencer['bio'])): ?>
                                            <p class="card-text text-muted small">
                                                <?php 
                                                $bio = htmlspecialchars($influencer['bio']);
                                                echo strlen($bio) > 100 ? substr($bio, 0, 100) . '...' : $bio;
                                                ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Handles Social -->
                                        <div class="mb-2">
                                            <?php
                                            $social_networks = get_active_social_networks();
                                            foreach ($social_networks as $social): 
                                                $handle_value = $influencer[$social['slug'] . '_handle'] ?? '';
                                                if (!empty($handle_value)): 
                                            ?>
                                                <small class="text-muted d-block">
                                                    <i class="<?php echo $social['icon']; ?> me-1"></i>
                                                    <strong><?php echo $social['name']; ?>:</strong> 
                                                    @<?php echo htmlspecialchars($handle_value); ?>
                                                </small>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>

                                        <!-- Tariffa e Visualizzazioni -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <?php if (!empty($influencer['rate'])): ?>
                                                <strong class="text-success">
                                                    €<?php echo number_format($influencer['rate'], 2); ?>
                                                </strong>
                                            <?php else: ?>
                                                <span class="text-muted">Tariffa non specificata</span>
                                            <?php endif; ?>
                                            
                                            <small class="text-muted">
                                                <?php echo number_format($influencer['profile_views'] ?? 0); ?> visualizzazioni
                                            </small>
                                        </div>
                                    </div>

                                    <!-- PULSANTI AZIONE -->
                                    <div class="card-footer bg-transparent">
                                        <div>
                                            <a href="/infl/influencers/profile.php?id=<?php echo $influencer['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm w-100 mt-2">
                                                <i class="fas fa-eye"></i> Dettagli profilo
                                            </a>
                                            
                                            <?php if ($brand_id): ?>
                                                <?php 
                                                // Controlla se esiste già una conversazione con questo influencer
                                                $conversation_id = $existing_conversations[$influencer['id']] ?? false;
                                                ?>
                                                
                                                <?php if (!$conversation_id): ?>
                                                    <!-- Se NON esiste conversazione: mostra pulsante per inviare messaggio -->
                                                    <button type="button" 
                                                            class="btn btn-primary btn-sm w-100 mt-2 send-message-btn"
                                                            data-influencer-id="<?php echo $influencer['id']; ?>"
                                                            data-influencer-name="<?php echo htmlspecialchars($influencer['full_name']); ?>">
                                                        <i class="fas fa-envelope"></i> Invia Messaggio
                                                    </button>
                                                    
                                                    <!-- Form fallback per no-JavaScript (nascosto) -->
                                                    <form method="POST" action="start-conversation.php" class="d-none no-js-form">
                                                        <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                        <input type="hidden" name="initial_message" value="Ciao <?php echo htmlspecialchars($influencer['full_name']); ?>, sono interessato a collaborare con te!">
                                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                                            <i class="fas fa-envelope"></i> Invia Messaggio
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <!-- Se ESISTE conversazione: mostra pulsanti per andare alla conversazione o nuovo messaggio -->
                                                    <div class="d-flex gap-1 mt-2">
                                                        <a href="messages/conversation.php?id=<?php echo $conversation_id; ?>" 
                                                           class="btn btn-primary btn-sm flex-grow-1">
                                                            <i class="fas fa-comments"></i> Vai alla Conversazione
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-outline-primary btn-sm send-message-btn"
                                                                data-influencer-id="<?php echo $influencer['id']; ?>"
                                                                data-influencer-name="<?php echo htmlspecialchars($influencer['full_name']); ?>"
                                                                data-conversation-id="<?php echo $conversation_id; ?>"
                                                                title="Aggiungi nuovo messaggio">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Form fallback per no-JavaScript (nascosto) -->
                                                    <form method="POST" action="start-conversation.php" class="d-none no-js-form">
                                                        <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                        <input type="hidden" name="initial_message" value="Ciao <?php echo htmlspecialchars($influencer['full_name']); ?>, sono interessato a collaborare con te!">
                                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                                            <i class="fas fa-envelope"></i> Invia Messaggio
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm w-100 mt-2" disabled title="Completa il profilo brand per inviare messaggi">
                                                    <i class="fas fa-exclamation-circle"></i> Completa Profilo
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- PAGINAZIONE -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginazione risultati">
                            <ul class="pagination justify-content-center mt-4">
                                <!-- Pagina Precedente -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i> Precedente
                                    </a>
                                </li>

                                <!-- Pagine -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" 
                                           href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Pagina Successiva -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        Successiva <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PER MESSAGGIO PERSONALIZZATO -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="messageForm" method="POST" action="start-conversation.php">
                <input type="hidden" name="influencer_id" id="modalInfluencerId">
                <input type="hidden" name="initial_message" id="modalInitialMessage">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel">Invia Messaggio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="customMessage" class="form-label">
                            Scrivi il tuo messaggio:
                        </label>
                        <textarea class="form-control" 
                                  id="customMessage" 
                                  name="custom_message" 
                                  rows="6" 
                                  maxlength="1000" 
                                  placeholder="Es: Ciao, sono [Nome Brand]. Ho visto il tuo profilo e mi piacerebbe collaborare per una campagna su [tema]..."
                                  required></textarea>
                        <div class="d-flex justify-content-end mt-2">
                            <span class="text-muted small">
                                <span id="charCount">0</span>/1000 caratteri
                            </span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Annulla
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Invia Messaggio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.influencer-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.influencer-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.card-img-top {
    border-bottom: 1px solid #dee2e6;
}

.btn-sm {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
}

/* Stili per il modal e textarea */
#customMessage {
    resize: vertical;
    min-height: 120px;
}

#charCount.text-warning {
    font-weight: bold;
}

#charCount.text-danger {
    font-weight: bold;
}

/* Pulsante nel modal */
.modal-footer .btn {
    min-width: 100px;
}

/* Adatta form no-JS */
.no-js-form {
    margin-top: 5px;
}

/* Stili per pulsanti conversazione esistente */
.btn-primary.flex-grow-1 {
    flex: 1 1 auto;
}

.btn-outline-primary.btn-sm {
    width: 40px;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
}

/* Badge per indicare conversazione esistente */
.conversation-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    font-size: 0.7rem;
}

/* Tooltip personalizzato */
[title] {
    cursor: help;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Nascondi form fallback se JavaScript è abilitato
    document.querySelectorAll('.no-js-form').forEach(form => {
        form.style.display = 'none';
    });
    
    // Mostra i pulsanti per il modal
    document.querySelectorAll('.send-message-btn').forEach(btn => {
        btn.style.display = btn.classList.contains('send-message-btn') ? '' : 'none';
    });
    
    // Gestione click sui pulsanti "Invia Messaggio" e "Nuovo Messaggio"
    document.querySelectorAll('.send-message-btn').forEach(button => {
        button.addEventListener('click', function() {
            const influencerId = this.getAttribute('data-influencer-id');
            const influencerName = this.getAttribute('data-influencer-name');
            const conversationId = this.getAttribute('data-conversation-id');
            
            // Imposta i valori nel modal
            document.getElementById('modalInfluencerId').value = influencerId;
            
            // Imposta messaggio predefinito personalizzato
            const defaultMessage = conversationId 
                ? `Ciao ${influencerName}, vorrei aggiungere qualcosa alla nostra conversazione: `
                : `Ciao ${influencerName}, sono interessato a collaborare con te!`;
            
            document.getElementById('customMessage').value = defaultMessage;
            document.getElementById('modalInitialMessage').value = defaultMessage;
            
            // Se esiste conversazione, aggiorna il titolo del modal
            if (conversationId) {
                document.getElementById('messageModalLabel').textContent = 'Aggiungi Nuovo Messaggio';
                // Aggiungi campo nascosto per conversation_id (se necessario per il backend)
                if (!document.getElementById('existingConversationId')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.id = 'existingConversationId';
                    input.name = 'existing_conversation_id';
                    input.value = conversationId;
                    document.getElementById('messageForm').appendChild(input);
                } else {
                    document.getElementById('existingConversationId').value = conversationId;
                }
            } else {
                document.getElementById('messageModalLabel').textContent = 'Invia Messaggio';
                // Rimuovi campo hidden se presente
                const existingInput = document.getElementById('existingConversationId');
                if (existingInput) {
                    existingInput.remove();
                }
            }
            
            // Resetta e aggiorna contatore caratteri
            updateCharCount();
            
            // Mostra il modal
            const messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            messageModal.show();
            
            // Focus sul textarea
            setTimeout(() => {
                document.getElementById('customMessage').focus();
            }, 500);
        });
    });
    
    // Gestione contatore caratteri
    const textarea = document.getElementById('customMessage');
    const charCount = document.getElementById('charCount');
    
    function updateCharCount() {
        if (!textarea || !charCount) return;
        
        const length = textarea.value.length;
        charCount.textContent = length;
        
        // Cambia colore se supera 900 caratteri
        if (length > 900) {
            charCount.className = 'text-warning';
        } else if (length > 990) {
            charCount.className = 'text-danger';
        } else {
            charCount.className = '';
        }
    }
    
    if (textarea) {
        textarea.addEventListener('input', updateCharCount);
        
        // Aggiorna il messaggio nascosto quando l'utente modifica il textarea
        textarea.addEventListener('input', function() {
            document.getElementById('modalInitialMessage').value = this.value;
        });
        
        // Inizializza contatore
        updateCharCount();
    }
    
    // Validazione del form nel modal
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            const message = document.getElementById('customMessage')?.value.trim();
            
            if (!message) {
                e.preventDefault();
                alert('Per favore, scrivi un messaggio prima di inviare.');
                document.getElementById('customMessage')?.focus();
                return false;
            }
            
            if (message.length > 1000) {
                e.preventDefault();
                alert('Il messaggio è troppo lungo (max 1000 caratteri).');
                return false;
            }
            
            // Mostra indicatore di caricamento
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Invio in corso...';
                submitBtn.disabled = true;
            }
        });
    }
    
    // Reset modal quando viene nascosto
    document.getElementById('messageModal')?.addEventListener('hidden.bs.modal', function () {
        // Ripristina titolo default
        document.getElementById('messageModalLabel').textContent = 'Invia Messaggio';
        
        // Rimuovi campo hidden se presente
        const existingInput = document.getElementById('existingConversationId');
        if (existingInput) {
            existingInput.remove();
        }
        
        // Resetta form
        const form = document.getElementById('messageForm');
        if (form) {
            form.reset();
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Invia Messaggio';
                submitBtn.disabled = false;
            }
        }
    });
});
</script>

<?php
// =============================================
// INCLUSIONE FOOTER CON PERCORSO ASSOLUTO
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>