<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Percorso assoluto a config
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Config file not found");
}
require_once $config_file;

// Se già loggato, reindirizza alla dashboard appropriata
if (is_logged_in()) {
    if (is_influencer()) {
        header("Location: /infl/influencers/dashboard.php");
    } else {
        header("Location: /infl/brands/dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        // Cerca utente nella tabella USERS (senza specificare user_type)
        $stmt = $pdo->prepare("SELECT id, email, password, user_type FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful - ora recupera i dettagli specifici in base al user_type
            $user_type = $user['user_type'];
            $name = '';
            
            if ($user_type === 'influencer') {
                $stmt_details = $pdo->prepare("SELECT full_name FROM influencers WHERE user_id = ?");
                $stmt_details->execute([$user['id']]);
                $details = $stmt_details->fetch(PDO::FETCH_ASSOC);
                $name = $details['full_name'] ?? '';
            } else if ($user_type === 'brand') {
                $stmt_details = $pdo->prepare("SELECT company_name FROM brands WHERE user_id = ?");
                $stmt_details->execute([$user['id']]);
                $details = $stmt_details->fetch(PDO::FETCH_ASSOC);
                $name = $details['company_name'] ?? '';
            } else {
                // Tipo utente non riconosciuto
                $error = "Tipo utente non valido";
                $user_type = null;
            }
            
            if ($user_type) {
                login_user($user['id'], $user_type, $name);
                
                // Redirect to appropriate dashboard
                if ($user_type === 'influencer') {
                    header("Location: /infl/influencers/dashboard.php");
                } else {
                    header("Location: /infl/brands/dashboard.php");
                }
                exit();
            }
            
        } else {
            $error = "Email o password non validi";
        }
        
    } catch (PDOException $e) {
        $error = "Errore di sistema: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Accedi</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['timeout'])): ?>
                            <div class="alert alert-warning">Sessione scaduta. Effettua nuovamente il login.</div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Accedi</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="register.php">Non hai un account? Registrati</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>