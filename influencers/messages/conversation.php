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
// RECUPERO INFLUENCER_ID
// =============================================
$influencer_id = null;
$stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$influencer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$influencer) {
    die("Profilo influencer non trovato. Completa prima il profilo influencer.");
}
$influencer_id = $influencer['id'];

// =============================================
// RECUPERA CONVERSAZIONE
// =============================================
$stmt = $pdo->prepare("
    SELECT c.*, 
           b.company_name as brand_name,
           b.logo as brand_image,
           camp.name as campaign_title
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
           END as sender_name,
           CASE 
               WHEN m.sender_type = 'brand' THEN b.logo
               WHEN m.sender_type = 'influencer' THEN inf.profile_image
           END as sender_image
    FROM messages m
    LEFT JOIN brands b ON m.sender_type = 'brand' AND m.sender_id = b.id
    LEFT JOIN influencers inf ON m.sender_type = 'influencer' AND m.sender_id = inf.id
    WHERE m.conversation_id = ?
    ORDER BY m.sent_at ASC
");
$messages_stmt->execute([$conversation_id]);
$messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// GESTIONE INVIO NUOVO MESSAGGIO
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty(trim($_POST['message']))) {
    $new_message = trim($_POST['message']);
    
    $insert_stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at) 
        VALUES (?, ?, 'influencer', ?, NOW())
    ");
    $insert_stmt->execute([$conversation_id, $influencer_id, $new_message]);
    
    // Aggiorna data ultimo aggiornamento conversazione
    $update_stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$conversation_id]);
    
    // Ricarica la pagina per mostrare il nuovo messaggio
    header("Location: conversation.php?id=" . $conversation_id);
    exit();
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
            <h2>Conversazione con <?php echo htmlspecialchars($conversation['brand_name']); ?></h2>
            <a href="conversation-list.php" class="btn btn-outline-primary">
                ← Torna ai Messaggi
            </a>
        </div>

        <!-- INFO CONVERSAZIONE -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="card-title">Brand</h5>
                        <div class="d-flex align-items-center">
                            <?php if (!empty($conversation['brand_image'])): ?>
                                <img src="/infl/uploads/<?php echo htmlspecialchars($conversation['brand_image']); ?>" 
                                     class="rounded-circle me-3" width="50" height="50" alt="Brand Logo">
                            <?php else: ?>
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" 
                                     style="width: 50px; height: 50px;">
                                    <i class="fas fa-building text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($conversation['brand_name']); ?></strong>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($conversation['campaign_title'])): ?>
                    <div class="col-md-6">
                        <h5 class="card-title">Campagna</h5>
                        <p class="mb-0"><?php echo htmlspecialchars($conversation['campaign_title']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MESSAGGI -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Messaggi</h5>
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">Nessun messaggio ancora. Inizia la conversazione!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message mb-3 <?php echo $message['sender_type'] === 'influencer' ? 'text-end' : 'text-start'; ?>">
                            <div class="d-flex <?php echo $message['sender_type'] === 'influencer' ? 'justify-content-end' : 'justify-content-start'; ?>">
                                <?php if ($message['sender_type'] !== 'influencer'): ?>
                                    <?php if (!empty($message['sender_image'])): ?>
                                        <img src="/infl/uploads/<?php echo htmlspecialchars($message['sender_image']); ?>" 
                                             class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" 
                                             style="width: 32px; height: 32px;">
                                            <i class="fas fa-building text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="message-bubble <?php echo $message['sender_type'] === 'influencer' ? 'bg-primary text-white' : 'bg-light'; ?> rounded p-3">
                                    <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                    <small class="message-time <?php echo $message['sender_type'] === 'influencer' ? 'text-white-50' : 'text-muted'; ?>">
                                        <?php echo date('d/m/Y H:i', strtotime($message['sent_at'])); ?>
                                    </small>
                                </div>
                                
                                <?php if ($message['sender_type'] === 'influencer'): ?>
                                    <?php if (!empty($message['sender_image'])): ?>
                                        <img src="/infl/uploads/<?php echo htmlspecialchars($message['sender_image']); ?>" 
                                             class="rounded-circle ms-2" width="32" height="32" alt="Profile">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center ms-2" 
                                             style="width: 32px; height: 32px;">
                                            <i class="fas fa-user text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- FORM INVIO MESSAGGIO -->
        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="input-group">
                        <textarea name="message" class="form-control" placeholder="Scrivi il tuo messaggio..." 
                                  rows="2" required></textarea>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Invia
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.message-bubble {
    max-width: 70%;
    word-wrap: break-word;
}
.message-time {
    font-size: 0.75rem;
    opacity: 0.8;
}
</style>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(dirname(__DIR__)) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
}
?>