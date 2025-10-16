<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG E FUNZIONI
// =============================================
$config_file = dirname(dirname(__DIR__)) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

$functions_file = dirname(dirname(__DIR__)) . '/includes/functions.php';
if (!file_exists($functions_file)) {
    die("Errore: File funzioni non trovato in: " . $functions_file);
}
require_once $functions_file;

// =============================================
// VERIFICA AUTENTICAZIONE UTENTE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /infl/auth/login.php");
    exit();
}

if (!is_influencer()) {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// VERIFICA PARAMETRI
// =============================================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID conversazione non valido");
}

$conversation_id = intval($_GET['id']);

// =============================================
// RECUPERO INFLUENCER_ID E INFLUENCER_IMAGE
// =============================================
$influencer_id = null;
$influencer_image = null;
$stmt = $pdo->prepare("SELECT id, profile_image FROM influencers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$influencer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$influencer) {
    die("Profilo influencer non trovato. Completa prima il profilo influencer.");
}
$influencer_id = $influencer['id'];
$influencer_image = $influencer['profile_image'];

// =============================================
// RECUPERA CONVERSAZIONE
// =============================================
$stmt = $pdo->prepare("
    SELECT c.*, 
           b.company_name as brand_name,
           b.logo as brand_image,
           b.description as brand_description,
           camp.name as campaign_title,
           camp.id as campaign_id,
           camp.budget as campaign_budget
    FROM conversations c
    LEFT JOIN brands b ON c.brand_id = b.id
    LEFT JOIN campaigns camp ON c.campaign_id = camp.id
    WHERE c.id = ? AND c.influencer_id = ?
");
$stmt->execute([$conversation_id, $influencer_id]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    die("Conversazione non trovata o accesso negato");
}

// =============================================
// RECUPERA MESSAGGI
// =============================================
$messages_stmt = $pdo->prepare("
    SELECT m.*, 
           CASE 
               WHEN m.sender_type = 'brand' THEN b.company_name
               WHEN m.sender_type = 'influencer' THEN inf.full_name
           END as sender_name
    FROM messages m
    LEFT JOIN brands b ON m.sender_type = 'brand' AND m.sender_id = b.id
    LEFT JOIN influencers inf ON m.sender_type = 'influencer' AND m.sender_id = inf.id
    WHERE m.conversation_id = ?
    ORDER BY m.sent_at ASC
");
$messages_stmt->execute([$conversation_id]);
$messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// SEGNA I MESSAGGI COME LETTI
// =============================================
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    mark_messages_as_read($pdo, $conversation_id, $_SESSION['user_type'], $_SESSION['user_id']);
}

// =============================================
// GESTIONE INVIO NUOVO MESSAGGIO
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty(trim($_POST['message']))) {
    $new_message = trim($_POST['message']);
    
    try {
        $insert_stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at) 
            VALUES (?, ?, 'influencer', ?, NOW())
        ");
        $insert_stmt->execute([$conversation_id, $influencer_id, $new_message]);
        
        // Aggiorna data ultimo aggiornamento conversazione
        $update_stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$conversation_id]);
        
        // Messaggio di successo
        $_SESSION['success_message'] = "Messaggio inviato con successo!";
        
        // Ricarica la pagina per mostrare il nuovo messaggio
        header("Location: conversation.php?id=" . $conversation_id);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Errore nell'invio del messaggio: " . $e->getMessage();
        header("Location: conversation.php?id=" . $conversation_id);
        exit();
    }
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(dirname(__DIR__)) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Conversazione con <?php echo htmlspecialchars($conversation['brand_name']); ?></h2>
                <?php if (!empty($conversation['campaign_title'])): ?>
                    <small class="text-muted">Campagna: <?php echo htmlspecialchars($conversation['campaign_title']); ?></small>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <?php if (!empty($conversation['campaign_id'])): ?>
                    <a href="/infl/influencers/campaigns/view.php?id=<?php echo $conversation['campaign_id']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-bullhorn me-1"></i> Dettagli Campagna
                    </a>
                <?php endif; ?>
                <a href="conversation-list.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i> Torna ai Messaggi
                </a>
            </div>
        </div>

        <!-- INFO CONVERSAZIONE -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="card-title">
                            <i class="fas fa-building me-2"></i>Brand
                        </h5>
                        <div class="d-flex align-items-center">
                            <?php if (!empty($conversation['brand_image']) && image_exists($conversation['brand_image'])): ?>
                                <img src="<?php echo get_image_path($conversation['brand_image'], 'brand'); ?>" 
                                     class="rounded-circle me-3" width="60" height="60" alt="Brand Logo" 
                                     style="object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" 
                                     style="width: 60px; height: 60px;">
                                    <i class="fas fa-building text-muted fa-lg"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong class="h6"><?php echo htmlspecialchars($conversation['brand_name']); ?></strong>
                                <?php if (!empty($conversation['brand_description'])): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php 
                                        $description = htmlspecialchars($conversation['brand_description']);
                                        echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($conversation['campaign_title'])): ?>
                    <div class="col-md-6">
                        <h5 class="card-title">
                            <i class="fas fa-bullhorn me-2"></i>Campagna
                        </h5>
                        <div>
                            <p class="mb-1"><strong><?php echo htmlspecialchars($conversation['campaign_title']); ?></strong></p>
                            <?php if (!empty($conversation['campaign_budget'])): ?>
                                <p class="mb-1">
                                    <small class="text-muted">
                                        <i class="fas fa-euro-sign me-1"></i>
                                        Budget: <?php echo number_format($conversation['campaign_budget'], 2, ',', '.'); ?>€
                                    </small>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($conversation['campaign_id'])): ?>
                                <small>
                                    <a href="/infl/influencers/campaigns/view.php?id=<?php echo $conversation['campaign_id']; ?>" class="text-decoration-none">
                                        <i class="fas fa-external-link-alt me-1"></i>Vedi dettagli completi
                                    </a>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MESSAGGI -->
        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-comments me-2"></i>Messaggi
                    <span class="badge bg-primary ms-2"><?php echo count($messages); ?></span>
                </h5>
                <div class="d-flex gap-2">
                    <?php if (count($messages) > 5): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="scrollToTop()">
                            <i class="fas fa-arrow-up me-1"></i> Inizio
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="scrollToBottom()">
                        <i class="fas fa-arrow-down me-1"></i> Ultimo
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="messages-container" style="max-height: 500px; overflow-y: auto; padding: 1.25rem;">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">Nessun messaggio ancora</h4>
                            <p class="text-muted">Inizia la conversazione inviando il primo messaggio!</p>
                            <?php if (!empty($conversation['campaign_title'])): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <small class="text-muted">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        <strong>Suggerimento:</strong> Presentati e chiedi informazioni sulla campagna "<?php echo htmlspecialchars($conversation['campaign_title']); ?>"
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <?php 
                            $is_own_message = $message['sender_type'] === 'influencer';
                            $message_class = $is_own_message ? 'text-end' : 'text-start';
                            $bubble_class = $is_own_message ? 'bg-primary text-white' : 'bg-light';
                            $time_class = $is_own_message ? 'text-white-50' : 'text-muted';
                            
                            // Usa l'immagine fissa del profilo invece di cercarla per ogni messaggio
                            $sender_image = $is_own_message ? $influencer_image : $conversation['brand_image'];
                            $sender_image_type = $is_own_message ? 'influencer' : 'brand';
                            ?>
                            
                            <div class="message mb-4 <?php echo $message_class; ?>" id="message-<?php echo $message['id']; ?>">
                                <div class="d-flex <?php echo $is_own_message ? 'justify-content-end' : 'justify-content-start'; ?>">
                                    <?php if (!$is_own_message): ?>
                                        <!-- Avatar brand - usa sempre il logo del brand -->
                                        <?php if (!empty($sender_image) && image_exists($sender_image)): ?>
                                            <img src="<?php echo get_image_path($sender_image, $sender_image_type); ?>" 
                                                 class="rounded-circle me-3" width="40" height="40" alt="Brand Logo"
                                                 style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-building text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <div class="message-bubble <?php echo $bubble_class; ?> rounded-3 p-3 position-relative" 
                                         style="max-width: 70%;">
                                        <!-- Nome mittente per i messaggi del brand -->
                                        <?php if (!$is_own_message): ?>
                                            <div class="sender-name mb-1">
                                                <strong><?php echo htmlspecialchars($message['sender_name']); ?></strong>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Contenuto messaggio -->
                                        <div class="message-text">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        </div>
                                        
                                        <!-- Data e ora -->
                                        <div class="message-time mt-2">
                                            <small class="<?php echo $time_class; ?>">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($message['sent_at'])); ?>
                                                
                                                <?php if ($is_own_message && $message['is_read']): ?>
                                                    <i class="fas fa-check-double ms-2 text-success" title="Messaggio letto"></i>
                                                <?php elseif ($is_own_message): ?>
                                                    <i class="fas fa-check ms-2" title="Messaggio inviato"></i>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($is_own_message): ?>
                                        <!-- Avatar proprio (influencer) - usa sempre l'immagine del profilo influencer -->
                                        <?php if (!empty($sender_image) && image_exists($sender_image)): ?>
                                            <img src="<?php echo get_image_path($sender_image, $sender_image_type); ?>" 
                                                 class="rounded-circle ms-3" width="40" height="40" alt="Profile"
                                                 style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center ms-3" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- FORM INVIO MESSAGGIO -->
        <div class="card">
            <div class="card-body">
                <form method="POST" action="" id="message-form">
                    <div class="input-group">
                        <textarea name="message" class="form-control" placeholder="Scrivi il tuo messaggio..." 
                                  rows="3" required id="message-input" 
                                  placeholder="Scrivi il tuo messaggio...<?php echo empty($messages) ? ' Presentati e chiedi informazioni sulla collaborazione!' : ''; ?>"></textarea>
                        <button type="submit" class="btn btn-primary" id="send-button">
                            <i class="fas fa-paper-plane me-1"></i> Invia
                        </button>
                    </div>
                    <div class="mt-2 d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Premi Invio per inviare, Shift+Invio per andare a capo
                        </small>
                        <small class="text-muted" id="char-count">0/1000 caratteri</small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.message-bubble {
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.bg-primary.message-bubble {
    border: none;
}

.message-bubble:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.message-text {
    line-height: 1.5;
    word-wrap: break-word;
}

.message-time {
    font-size: 0.75rem;
    opacity: 0.8;
}

.sender-name {
    font-size: 0.85rem;
    opacity: 0.9;
}

#messages-container {
    scroll-behavior: smooth;
    background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
}

/* Scrollbar personalizzata */
#messages-container::-webkit-scrollbar {
    width: 8px;
}

#messages-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#messages-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
}

#messages-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Indicatore nuovi messaggi */
.new-message-indicator {
    position: sticky;
    top: 10px;
    background: #007bff;
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    z-index: 10;
    text-align: center;
    margin: 0 auto;
    width: fit-content;
}

.char-count-warning {
    color: #dc3545;
    font-weight: bold;
}
</style>

<script>
// Scroll automatico all'ultimo messaggio
function scrollToBottom() {
    const container = document.getElementById('messages-container');
    container.scrollTop = container.scrollHeight;
}

// Scroll al primo messaggio
function scrollToTop() {
    const container = document.getElementById('messages-container');
    container.scrollTop = 0;
}

// Contatore caratteri
function updateCharCount() {
    const messageInput = document.getElementById('message-input');
    const charCount = document.getElementById('char-count');
    const maxChars = 1000;
    
    if (messageInput && charCount) {
        const length = messageInput.value.length;
        charCount.textContent = `${length}/${maxChars} caratteri`;
        
        if (length > maxChars * 0.9) {
            charCount.classList.add('char-count-warning');
        } else {
            charCount.classList.remove('char-count-warning');
        }
    }
}

// Scroll al caricamento della pagina
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
    
    const messageInput = document.getElementById('message-input');
    const messageForm = document.getElementById('message-form');
    
    // Gestione invio messaggio con Enter (senza Shift)
    if (messageInput && messageForm) {
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                messageForm.dispatchEvent(new Event('submit'));
            }
        });
        
        // Contatore caratteri in tempo reale
        messageInput.addEventListener('input', updateCharCount);
        
        // Auto-resize del textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        });
        
        // Focus automatico sul campo messaggio
        messageInput.focus();
        
        // Inizializza contatore caratteri
        updateCharCount();
    }
    
    // Aggiorna il contatore messaggi dopo che la conversazione è stata visualizzata
    setTimeout(() => {
        if (typeof updateMessageCount === 'function') {
            updateMessageCount();
        }
    }, 1000);
    
    // Evidenzia l'ultimo messaggio non letto (se presente)
    highlightUnreadMessages();
});

// Evidenzia i messaggi non letti (per debug)
function highlightUnreadMessages() {
    const unreadMessages = document.querySelectorAll('.message-bubble:not(.bg-primary) .fa-check-double');
    if (unreadMessages.length > 0) {
        console.log('Messaggi non letti trovati:', unreadMessages.length);
    }
}

// Conferma invio messaggio lungo
document.getElementById('message-form')?.addEventListener('submit', function(e) {
    const messageInput = document.getElementById('message-input');
    const maxChars = 1000;
    
    if (messageInput) {
        const message = messageInput.value.trim();
        
        // Validazione lunghezza
        if (message.length > maxChars) {
            e.preventDefault();
            alert(`Il messaggio è troppo lungo. Massimo ${maxChars} caratteri consentiti.`);
            return false;
        }
        
        // Validazione contenuto vuoto
        if (message.length === 0) {
            e.preventDefault();
            alert('Il messaggio non può essere vuoto.');
            return false;
        }
        
        // Conferma per messaggi molto lunghi
        if (message.length > 500) {
            if (!confirm('Il messaggio è piuttosto lungo. Vuoi inviarlo comunque?')) {
                e.preventDefault();
                return false;
            }
        }
    }
    
    // Disabilita il pulsante durante l'invio
    const sendButton = document.getElementById('send-button');
    if (sendButton) {
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Invio...';
    }
});

// Gestione visibilità pagina - aggiorna contatore quando ritorna visibile
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // Ricarica i messaggi quando la pagina ritorna visibile
        setTimeout(() => {
            if (typeof updateMessageCount === 'function') {
                updateMessageCount();
            }
        }, 500);
    }
});

// Auto-scroll quando nuovi messaggi vengono aggiunti (per future implementazioni real-time)
function observeNewMessages() {
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // Nuovi messaggi aggiunti, scrolla in basso
                    scrollToBottom();
                }
            });
        });
        
        observer.observe(messagesContainer, { childList: true, subtree: true });
    }
}

// Inizia l'osservazione dopo il caricamento
setTimeout(observeNewMessages, 1000);
</script>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(dirname(__DIR__)) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
}
?>