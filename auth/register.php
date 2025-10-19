<?php
// Includi direttamente functions.php per essere sicuri
require_once '../includes/functions.php';
require_once '../includes/config.php';

// Aggiungi session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DEFINISCI BASE_URL SE NON ESISTE
if (!defined('BASE_URL')) {
    define('BASE_URL', '/infl');
}

// Se l'utente è già loggato, reindirizza
if (isset($_SESSION['user_id'])) {
    header("Location: /");
    exit;
}

$error = '';
$success = '';
$user_type = $_GET['type'] ?? 'influencer';

$valid_types = ['influencer', 'brand', 'admin'];
if (!in_array($user_type, $valid_types)) {
    $user_type = 'influencer';
}

// Funzione di sanitizzazione semplificata
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

// Gestione del form di registrazione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gestisci nome/company_name correttamente
    if ($user_type === 'brand') {
        $name = sanitize_input($_POST['company_name'] ?? '');
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
    }
    
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type_form = sanitize_input($_POST['user_type'] ?? 'influencer');
    
    // Campi aggiuntivi
    $influencer_type = sanitize_input($_POST['influencer_type'] ?? '');
    $industry = sanitize_input($_POST['industry'] ?? '');
    
    // Validazione
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Tutti i campi sono obbligatori';
    } elseif ($password !== $confirm_password) {
        $error = 'Le password non coincidono';
    } elseif (strlen($password) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri';
    } else {
        try {
            // VERIFICA SE L'EMAIL ESISTE GIÀ NELLA TABELLA USERS
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Questa email è già registrata';
            } else {
                // Hash della password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // INIZIA TRANSACTION
                $pdo->beginTransaction();
                
                try {
                    // 1. INSERISCI NELLA TABELLA USERS
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, user_type, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$email, $password_hash, $user_type_form]);
                    $user_id = $pdo->lastInsertId();
                    
                    // 2. INSERISCI NELLA TABELLA SPECIFICA (influencers O brands)
                    if ($user_type_form === 'influencer') {
                        $stmt = $pdo->prepare("INSERT INTO influencers (user_id, full_name, niche, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$user_id, $name, $influencer_type]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO brands (user_id, company_name, industry, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$user_id, $name, $industry]);
                    }
                    
                    // COMMIT TRANSACTION
                    $pdo->commit();
                    
                    $success = 'Registrazione completata con successo! Ora puoi effettuare il login.';
                    
                    // Reindirizza al login dopo 2 secondi
                    header("refresh:2;url=".BASE_URL."/auth/login.php");
                    exit();
                    
                } catch (Exception $e) {
                    // ROLLBACK IN CASO DI ERRORE
                    $pdo->rollBack();
                    throw $e;
                }
            }
            
        } catch (PDOException $e) {
            $error = 'Errore durante la registrazione: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Registrati come <?php echo ucfirst($user_type); ?></h4>
                <p class="mb-0">Compila il form per creare il tuo account</p>
            </div>
            <div class="card-body">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    <?php echo $user_type === 'brand' ? 'Nome Azienda *' : 'Nome Completo *'; ?>
                                </label>
                                <input type="text" class="form-control" id="name" name="<?php echo $user_type === 'brand' ? 'company_name' : 'name'; ?>" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       minlength="6" required>
                                <div class="form-text">Minimo 6 caratteri</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Conferma Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       minlength="6" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campi specifici per Influencer -->
                    <?php if ($user_type === 'influencer'): ?>
                    <div class="mb-3">
                        <label for="influencer_type" class="form-label">Tipologia di Influencer *</label>
                        <select class="form-select" id="influencer_type" name="influencer_type" required>
                            <option value="">Seleziona la tua tipologia</option>
                            <option value="lifestyle" <?php echo (isset($_POST['influencer_type']) && $_POST['influencer_type'] === 'lifestyle') ? 'selected' : ''; ?>>Lifestyle</option>
                            <option value="fashion" <?php echo (isset($_POST['influencer_type']) && $_POST['influencer_type'] === 'fashion') ? 'selected' : ''; ?>>Fashion</option>
                            <option value="beauty" <?php echo (isset($_POST['influencer_type']) && $_POST['influencer_type'] === 'beauty') ? 'selected' : ''; ?>>Beauty & Makeup</option>
                            <option value="fitness" <?php echo (isset($_POST['influencer_type']) && $_POST['influencer_type'] === 'fitness') ? 'selected' : ''; ?>>Fitness & Wellness</option>
                            <option value="travel" <?php echo (isset($_POST['influencer_type']) && $_POST['influencer_type'] === 'travel') ? 'selected' : ''; ?>>Travel</option>
                            <option value="food" <?php echo (isset($_POST['influencer_type']) && $_POST['influencer_type'] === 'food') ? 'selected' : ''; ?>>Food & Cooking</option>
                            <option value="tech" <?php echo (isset($_POST['influencer_type']) && $_POST['influencer_type'] === 'tech') ? 'selected' : ''; ?>>Tech & Gaming</option>
                            <option value="business" <?php echo (isset($_POST['influencer_type']) && $_POST['influencer_type'] === 'business') ? 'selected' : ''; ?>>Business & Finance</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Campi specifici per Brand -->
                    <?php if ($user_type === 'brand'): ?>
                    <div class="mb-3">
                        <label for="industry" class="form-label">Settore *</label>
                        <select class="form-select" id="industry" name="industry" required>
                            <option value="">Seleziona il settore</option>
                            <option value="fashion" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'fashion') ? 'selected' : ''; ?>>Moda</option>
                            <option value="beauty" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'beauty') ? 'selected' : ''; ?>>Beauty & Cosmesi</option>
                            <option value="food" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'food') ? 'selected' : ''; ?>>Food & Beverage</option>
                            <option value="tech" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'tech') ? 'selected' : ''; ?>>Tecnologia</option>
                            <option value="travel" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'travel') ? 'selected' : ''; ?>>Travel & Turismo</option>
                            <option value="fitness" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'fitness') ? 'selected' : ''; ?>>Fitness & Wellness</option>
                            <option value="automotive" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'automotive') ? 'selected' : ''; ?>>Automotive</option>
                            <option value="other" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'other') ? 'selected' : ''; ?>>Altro</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            Accetto i <a href="<?php echo BASE_URL; ?>/terms.php" target="_blank">Termini di Servizio</a> 
                            e l'<a href="<?php echo BASE_URL; ?>/privacy.php" target="_blank">Informativa Privacy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Crea Account</button>
                </form>
                
                <div class="mt-4 text-center">
                    <p>Hai già un account? 
                        <a href="<?php echo BASE_URL; ?>/auth/login.php" class="text-decoration-none">
                            Accedi qui
                        </a>
                    </p>
                    
                    <div class="mt-3">
                        <p>Oppure registrati come:</p>
                        <div class="d-flex gap-2 justify-content-center" id="switcher-buttons">
                            <?php if ($user_type === 'influencer'): ?>
                                <a href="?type=brand" class="btn btn-outline-success">Brand</a>
                            <?php else: ?>
                                <a href="?type=influencer" class="btn btn-outline-primary">Influencer</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Funzione per aggiornare i pulsanti dello switcher
    function updateSwitcherButtons(currentType) {
        const switcherContainer = $('#switcher-buttons');
        
        if (currentType === 'influencer') {
            // Se siamo su influencer, mostra solo brand
            switcherContainer.html('<a href="?type=brand" class="btn btn-outline-success">Brand</a>');
        } else {
            // Se siamo su brand, mostra solo influencer
            switcherContainer.html('<a href="?type=influencer" class="btn btn-outline-primary">Influencer</a>');
        }
    }
    
    // Inizializza con il tipo corrente
    updateSwitcherButtons('<?php echo $user_type; ?>');
    
    // Gestisce il click sui pulsanti (per mantenere la funzionalità)
    $(document).on('click', '#switcher-buttons a', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        window.location.href = url;
    });
});
</script>