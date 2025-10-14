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
$config_file = dirname(__DIR__) . '/../includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato");
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE UTENTE
// =============================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'brand') {
    header("Location: /infl/auth/login.php");
    exit();
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(__DIR__) . '/../includes/header.php';
require_once $header_file;

// =============================================
// RECUPERO BRAND_ID
// =============================================
$brand_id = null;
$stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch(PDO::FETCH_ASSOC);
if ($brand) {
    $brand_id = $brand['id'];
}

// =============================================
// RECUPERO CONVERSAZIONI
// =============================================
$conversations = [];
if ($brand_id) {
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            i.full_name as influencer_name,
            i.profile_image,
            camp.name as campaign_title,
            (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND is_read = FALSE AND sender_type = 'influencer') as unread_count
        FROM conversations c
        LEFT JOIN influencers i ON c.influencer_id = i.id
        LEFT JOIN campaigns camp ON c.campaign_id = camp.id
        WHERE c.brand_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$brand_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Messaggi</h2>
                <a href="/infl/brands/search-influencers.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuova Conversazione
                </a>
            </div>

            <?php if (empty($conversations)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Nessuna conversazione</h4>
                        <p class="text-muted">Inizia una nuova conversazione con un influencer</p>
                        <a href="/infl/brands/search-influencers.php" class="btn btn-primary">
                            Cerca Influencer
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($conversations as $conv): ?>
                                <a href="conversation.php?id=<?php echo $conv['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($conv['profile_image'])): ?>
                                                <img src="/infl/uploads/<?php echo htmlspecialchars($conv['profile_image']); ?>" 
                                                     class="rounded-circle me-3" 
                                                     style="width: 50px; height: 50px; object-fit: cover;" 
                                                     alt="<?php echo htmlspecialchars($conv['influencer_name']); ?>">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 50px; height: 50px;">
                                                    <span class="text-white"><?php echo substr($conv['influencer_name'], 0, 1); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($conv['influencer_name']); ?>
                                                    <?php if ($conv['unread_count'] > 0): ?>
                                                        <span class="badge bg-danger ms-2"><?php echo $conv['unread_count']; ?></span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="mb-1 text-muted">
                                                    <?php 
                                                    if (!empty($conv['last_message'])) {
                                                        echo htmlspecialchars(
                                                            strlen($conv['last_message']) > 50 
                                                            ? substr($conv['last_message'], 0, 50) . '...' 
                                                            : $conv['last_message']
                                                        );
                                                    } else {
                                                        echo 'Nessun messaggio';
                                                    }
                                                    ?>
                                                </p>
                                                <?php if (!empty($conv['campaign_title'])): ?>
                                                    <small class="text-info">
                                                        Campagna: <?php echo htmlspecialchars($conv['campaign_title']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end">
                                            <?php if (!empty($conv['last_message_time'])): ?>
                                                <small class="text-muted">
                                                    <?php 
                                                    $time = strtotime($conv['last_message_time']);
                                                    echo date('d/m/Y H:i', $time);
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(__DIR__) . '/../includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
}
?>