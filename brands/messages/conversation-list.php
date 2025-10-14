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

if (!is_brand()) {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// RECUPERO BRAND_ID
// =============================================
$brand_id = null;
$stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$brand) {
    die("Profilo brand non trovato. Completa prima il profilo brand.");
}
$brand_id = $brand['id'];

// =============================================
// RECUPERA CONVERSAZIONI
// =============================================
$stmt = $pdo->prepare("
    SELECT c.*, 
           inf.full_name as influencer_name,
           inf.profile_image as influencer_image,
           camp.name as campaign_title,
           (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) as last_message,
           (SELECT sent_at FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) as last_message_time
    FROM conversations c
    LEFT JOIN influencers inf ON c.influencer_id = inf.id
    LEFT JOIN campaigns camp ON c.campaign_id = camp.id
    WHERE c.brand_id = ?
    ORDER BY c.created_at DESC  -- CORRETTO: specificato c.created_at
");
$stmt->execute([$brand_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            <h2>Le tue conversazioni</h2>
            <a href="../dashboard.php" class="btn btn-outline-primary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- LISTA CONVERSAZIONI -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Conversazioni (<?php echo count($conversations); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($conversations)): ?>
                    <div class="text-center py-5">
                        <h4 class="text-muted">Nessuna conversazione</h4>
                        <p class="text-muted">Inizia una nuova conversazione dalla pagina di ricerca influencer</p>
                        <a href="../search-influencers.php" class="btn btn-primary mt-3">
                            Cerca Influencer
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($conversations as $conversation): ?>
                            <a href="conversation.php?id=<?php echo $conversation['id']; ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <!-- Immagine profilo influencer -->
                                        <?php if (!empty($conversation['influencer_image'])): ?>
                                            <img src="/infl/uploads/<?php echo htmlspecialchars($conversation['influencer_image']); ?>" 
                                                 class="rounded-circle me-3" width="50" height="50" alt="Profile">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-user text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($conversation['influencer_name']); ?></h5>
                                            <?php if (!empty($conversation['campaign_title'])): ?>
                                                <small class="text-muted">Campagna: <?php echo htmlspecialchars($conversation['campaign_title']); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($conversation['last_message'])): ?>
                                                <p class="mb-1 text-muted">
                                                    <?php 
                                                    $last_message = htmlspecialchars($conversation['last_message']);
                                                    echo strlen($last_message) > 100 ? substr($last_message, 0, 100) . '...' : $last_message;
                                                    ?>
                                                </p>
                                            <?php else: ?>
                                                <p class="mb-1 text-muted">Nessun messaggio ancora</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <?php if (!empty($conversation['last_message_time'])): ?>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($conversation['last_message_time'])); ?>
                                            </small>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <span class="badge bg-primary">Apri conversazione</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.list-group-item:hover {
    background-color: #f8f9fa;
    transform: translateX(2px);
    transition: all 0.2s ease;
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