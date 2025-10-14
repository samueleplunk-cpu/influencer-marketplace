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
// VERIFICA PARAMETRI
// =============================================
if (!isset($_POST['influencer_id']) || !is_numeric($_POST['influencer_id'])) {
    die("ID influencer non valido");
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
// ELABORAZIONE PARAMETRI
// =============================================
$influencer_id = intval($_POST['influencer_id']);
$campaign_id = !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;
$initial_message = !empty($_POST['initial_message']) ? trim($_POST['initial_message']) : "Ciao, sono interessato a collaborare con te!";

// =============================================
// VERIFICA ESISTENZA INFLUENCER
// =============================================
$stmt = $pdo->prepare("SELECT full_name FROM influencers WHERE id = ?");
$stmt->execute([$influencer_id]);
$influencer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$influencer) {
    die("Influencer non trovato");
}

// =============================================
// CREA/RECUPERA CONVERSAZIONE
// =============================================
$conversation_id = startConversation($pdo, $brand_id, $influencer_id, $campaign_id, $initial_message);

if ($conversation_id) {
    // Reindirizza alla conversazione
    header("Location: messages/conversation.php?id=" . $conversation_id);
    exit();
} else {
    die("Errore nella creazione della conversazione");
}
?>