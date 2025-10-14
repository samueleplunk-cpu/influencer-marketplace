<?php
// Configurazione e sicurezza
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_file = dirname(__DIR__) . '/../includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato");
}
require_once $config_file;

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'brand') {
    header("Location: /infl/auth/login.php");
    exit();
}

// Verifica parametro conversazione
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID conversazione non valido");
}
$conversation_id = intval($_GET['id']);

// Recupera brand_id
$brand_id = null;
$stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch(PDO::FETCH_ASSOC);
if ($brand) {
    $brand_id = $brand['id'];
}

// Verifica che il brand abbia accesso alla conversazione
$stmt = $pdo->prepare("
    SELECT c.*, i.full_name as influencer_name, i.profile_image, camp.title as campaign_title
    FROM conversations c
    LEFT JOIN influencers i ON c.influencer_id = i.id
    LEFT JOIN campaigns camp ON c.campaign_id = camp.id
    WHERE c.id = ? AND c.brand_id = ?
");
$stmt->execute([$conversation_id, $brand_id]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    die("Conversazione non trovata o accesso negato");
}

// Segna i messaggi come letti
$stmt = $pdo->prepare("
    UPDATE messages 
    SET is_read = TRUE 
    WHERE conversation_id = ? AND sender_type = 'influencer' AND is_read = FALSE
");
$stmt->execute([$conversation_id]);

// Gestione invio nuovo messaggio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty(trim($_POST['message']))) {
    $message = trim($_POST['message']);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, sender_type, message) 
            VALUES (?, ?, 'brand', ?)
        ");
        $stmt->execute([$conversation_id, $brand_id, $message]);
        
        // Aggiorna timestamp conversazione
        $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);
        
        header("Location: conversation.php?id=" . $conversation_id);
        exit();
    } catch (PDOException $e) {
        $error = "Errore nell'invio del messaggio: " . $e->getMessage();
    }
}

// Recupera messaggi della conversazione
$stmt = $pdo->prepare("
    SELECT m.*, 
           CASE 
               WHEN m.sender_type = 'brand' THEN b.company_name
               ELSE i.full_name
           END as sender_name
    FROM messages m
    LEFT JOIN brands b ON m.sender_id = b.id AND m.sender_type = 'brand'
    LEFT JOIN influencers i ON m.sender_id = i.id AND m.sender_type = 'influencer'
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$conversation_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$header_file = dirname(__DIR__) . '/../includes/header.php';
require_once $header_file;
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Header conversazione -->
            <div class="d-flex align-items-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary me-3">
                    ← Indietro
                </a>
                <?php if (!empty($conversation['profile_image'])): ?>
                    <img src="/infl/uploads/<?php echo htmlspecialchars($conversation['profile_image']); ?>" 
                         class="rounded-circle me-3" 
                         style="width: 60px; height: 60px; object-fit: cover;" 
                         alt="<?php echo htmlspecialchars($conversation['influencer_name']); ?>">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3" 
                         style="width: 60px; height: 60px;">
                        <span class="text-white"><?php echo substr($conversation['influencer_name'], 0, 1); ?></span>
                    </div>
                <?php endif; ?>
                
                <div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($conversation['influencer_name']); ?></h4>
                    <?php if (!empty($conversation['campaign_title'])): ?>
                        <small class="text-muted">
                            Campagna: <?php echo htmlspecialchars($conversation['campaign_title']); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messaggi di errore/successo -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Area messaggi -->
            <div class="card mb-4">
                <div class="card-body" style="max-height: 500px; overflow-y: auto;" id="messages-container">
                    <?php if (empty($messages)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <p>Nessun messaggio ancora</p>
                            <p>Inizia la conversazione!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="d-flex mb-3 <?php echo $msg['sender_type'] === 'brand' ? 'justify-content-end' : 'justify-content-start'; ?>">
                                <div class="message-bubble <?php echo $msg['sender_type'] === 'brand' ? 'bg-primary text-white' : 'bg-light'; ?>" 
                                     style="max-width: 70%; padding: 12px 16px; border-radius: 18px;">
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    </div>
                                    <div class="message-time small mt-1 <?php echo $msg['sender_type'] === 'brand' ? 'text-white-50' : 'text-muted'; ?>">
                                        <?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?>
                                        <?php if ($msg['sender_type'] === 'brand' && $msg['is_read']): ?>
                                            <i class="fas fa-check-double ms-1"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form invio messaggio -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" id="message-form">
                        <div class="input-group">
                            <textarea name="message" class="form-control" placeholder="Scrivi un messaggio..." 
                                      rows="2" required style="resize: none;"></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Invia
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Scroll automatico verso l'ultimo messaggio
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
    
    // Focus sul textarea
    document.querySelector('textarea[name="message"]').focus();
});
</script>

<style>
.message-bubble {
    word-wrap: break-word;
}
</style>

<?php
$footer_file = dirname(__DIR__) . '/../includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
}
?>