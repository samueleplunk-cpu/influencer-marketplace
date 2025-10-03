<?php
require_once '../includes/config.php';

// Se l'utente è già loggato, reindirizza
if (is_logged_in()) {
    redirect('/');
    exit;
}

$error = '';
$success = '';
$user_type = $_GET['type'] ?? 'influencer'; // Default a influencer

// Tipi di utente validi
$valid_types = ['influencer', 'brand', 'admin'];

if (!in_array($user_type, $valid_types)) {
    $user_type = 'influencer';
}

// Gestione del form di registrazione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitize_input($_POST['user_type']);
    
    // Validazione
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Tutti i campi sono obbligatori';
    } elseif ($password !== $confirm_password) {
        $error = 'Le password non coincidono';
    } elseif (strlen($password) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri';
    } else {
        try {
            // VERIFICA SE L'EMAIL ESISTE GIÀ NELLE TABELLE CORRETTE
            if ($user_type === 'influencer') {
                $stmt = $pdo->prepare("SELECT id FROM influencers WHERE email = ?");
            } else {
                $stmt = $pdo->prepare("SELECT id FROM brands WHERE email = ?");
            }
            
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Questa email è già registrata';
            } else {
                // Hash della password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // INSERISCI NELLA TABELLA CORRETTA
                if ($user_type === 'influencer') {
                    $stmt = $pdo->prepare("INSERT INTO influencers (name, email, password) VALUES (?, ?, ?)");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO brands (company_name, email, password) VALUES (?, ?, ?)");
                }
                
                $stmt->execute([$name, $email, $password_hash]);
                $user_id = $pdo->lastInsertId();
                
                $success = 'Registrazione completata con successo! Ora puoi effettuare il login.';
                
                // Reindirizza al login dopo 2 secondi
                header("refresh:2;url=/infl/auth/login.php");
                exit();
            }
            
        } catch (PDOException $e) {
            $error = 'Errore durante la registrazione: ' . $e->getMessage();
        }
    }
}

// TEMP FIX - Funzioni mancanti
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        return htmlspecialchars(trim($data));
    }
}

// DEFINISCI BASE_URL SE NON ESISTE
if (!defined('BASE_URL')) {
    define('BASE_URL', '/infl');
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
                                <label for="name" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       required>
                                <?php if ($user_type === 'brand'): ?>
                                    <div class="form-text">Inserisci il nome della tua azienda</div>
                                <?php endif; ?>
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
                        <label class="form-label">Tipologia di Influencer</label>
                        <select class="form-select" name="influencer_type">
                            <option value="">Seleziona la tua tipologia</option>
                            <option value="lifestyle">Lifestyle</option>
                            <option value="fashion">Fashion</option>
                            <option value="beauty">Beauty & Makeup</option>
                            <option value="fitness">Fitness & Wellness</option>
                            <option value="travel">Travel</option>
                            <option value="food">Food & Cooking</option>
                            <option value="tech">Tech & Gaming</option>
                            <option value="business">Business & Finance</option>
                            <option value="other">Altro</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Campi specifici per Brand -->
                    <?php if ($user_type === 'brand'): ?>
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Nome Azienda</label>
                        <input type="text" class="form-control" id="company_name" name="company_name"
                               value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="industry" class="form-label">Settore</label>
                        <select class="form-select" id="industry" name="industry">
                            <option value="">Seleziona il settore</option>
                            <option value="fashion">Moda</option>
                            <option value="beauty">Beauty & Cosmesi</option>
                            <option value="food">Food & Beverage</option>
                            <option value="tech">Tecnologia</option>
                            <option value="travel">Travel & Turismo</option>
                            <option value="fitness">Fitness & Wellness</option>
                            <option value="automotive">Automotive</option>
                            <option value="other">Altro</option>
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
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="?type=influencer" class="btn btn-outline-primary">Influencer</a>
                            <a href="?type=brand" class="btn btn-outline-success">Brand</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>