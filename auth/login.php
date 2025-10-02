<?php
require_once '../includes/config.php';

// Se l'utente è già loggato, reindirizza alla dashboard
if (is_logged_in()) {
    $user_type = get_user_type();
    if ($user_type === 'admin') {
        redirect('/admin/dashboard.php');
    } elseif ($user_type === 'influencer') {
        redirect('/influencers/dashboard.php');
    } elseif ($user_type === 'brand') {
        redirect('/brands/dashboard.php');
    } else {
        redirect('/');
    }
    exit;
}

$error = '';
$success = '';

// Gestione del form di login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    // Validazione
    if (empty($email) || empty($password)) {
        $error = 'Inserisci email e password';
    } else {
        try {
            // Cerca l'utente per email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login riuscito - imposta la sessione
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                
                // Reindirizza in base al tipo utente
                $redirect_url = '/';
                switch ($user['user_type']) {
                    case 'admin':
                        $redirect_url = '/admin/dashboard.php';
                        break;
                    case 'influencer':
                        $redirect_url = '/influencers/dashboard.php';
                        break;
                    case 'brand':
                        $redirect_url = '/brands/dashboard.php';
                        break;
                }
                
                $success = 'Login effettuato con successo!';
                redirect($redirect_url);
                
            } else {
                $error = 'Email o password non validi';
            }
            
        } catch (PDOException $e) {
            $error = 'Errore durante il login: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Accedi al tuo account</h4>
            </div>
            <div class="card-body">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Ricordami</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Accedi</button>
                </form>
                
                <div class="mt-3 text-center">
                    <p>Non hai un account? 
                        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="text-decoration-none">
                            Registrati qui
                        </a>
                    </p>
                    <p>
                        <a href="<?php echo BASE_URL; ?>/auth/forgot-password.php" class="text-decoration-none">
                            Password dimenticata?
                        </a>
                    </p>
                </div>
                
                <div class="mt-4">
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_URL; ?>/auth/register.php?type=influencer" 
                           class="btn btn-outline-primary">
                            Registrati come Influencer
                        </a>
                        <a href="<?php echo BASE_URL; ?>/auth/register.php?type=brand" 
                           class="btn btn-outline-success">
                            Registrati come Brand
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>