<?php
require_once '../includes/admin_header.php';
require_once '../includes/functions.php';

// Includi funzioni specifiche per la gestione delle pagine
require_once '../includes/page_functions.php';

$page_title = "Gestione Pagine e Menu";
$active_menu = "pages-menu";

// Gestione form di salvataggio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = save_footer_settings($_POST);
    
    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
    } else {
        $_SESSION['error_message'] = $result['message'];
    }
    
    // Redirect per evitare reinvio form
    header("Location: pages-menu.php");
    exit;
}

// Carica le impostazioni correnti
$footer_settings = get_footer_settings();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestione Pagine e Menu</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Aggiorna
            </button>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" id="pagesMenuTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="footer-tab" data-bs-toggle="tab" data-bs-target="#footer" type="button" role="tab" aria-controls="footer" aria-selected="true">
                    <i class="fas fa-shoe-prints me-2"></i>Footer Homepage
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="headers-tab" data-bs-toggle="tab" data-bs-target="#headers" type="button" role="tab" aria-controls="headers" aria-selected="false">
                    <i class="fas fa-heading me-2"></i>Headers (Prossimamente)
                </button>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content p-4 border border-top-0 rounded-bottom">
            <!-- Tab Footer -->
            <div class="tab-pane fade show active" id="footer" role="tabpanel" aria-labelledby="footer-tab">
                <form method="POST" id="footerForm">
                    <input type="hidden" name="action" value="save_footer_settings">
                    
                    <!-- Sezione Titolo e Descrizione -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-heading me-2"></i>Titolo e Descrizione
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="footer_title" class="form-label">Titolo Footer</label>
                                    <input type="text" class="form-control" id="footer_title" name="footer_title" 
                                           value="<?php echo htmlspecialchars($footer_settings['title'] ?? 'Kibbiz'); ?>" 
                                           placeholder="Inserisci il titolo del footer">
                                    <div class="form-text">Questo sarà il titolo principale nel footer (es. "Kibbiz")</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="footer_description" class="form-label">Descrizione/Sottotitolo</label>
                                    <textarea class="form-control" id="footer_description" name="footer_description" 
                                              rows="3" placeholder="Inserisci la descrizione del footer"><?php echo htmlspecialchars($footer_settings['description'] ?? 'Uniamo Brand e Influencer per crescere insieme.'); ?></textarea>
                                    <div class="form-text">Breve descrizione sotto il titolo</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione Link Veloci -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-link me-2"></i>Link Veloci
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php
                                $quick_links = $footer_settings['quick_links'] ?? [
                                    'home' => ['label' => 'Home', 'url' => '/infl/'],
                                    'features' => ['label' => 'Funzionalità', 'url' => '#features'],
                                    'how_it_works' => ['label' => 'Come Funziona', 'url' => '#how-it-works'],
                                    'login' => ['label' => 'Login', 'url' => '/infl/auth/login.php'],
                                    'register' => ['label' => 'Registrati', 'url' => '/infl/auth/register.php']
                                ];
                                ?>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="quick_link_home" class="form-label">Home</label>
                                    <input type="text" class="form-control" id="quick_link_home" name="quick_links[home][label]" 
                                           value="<?php echo htmlspecialchars($quick_links['home']['label'] ?? 'Home'); ?>">
                                    <input type="text" class="form-control mt-2" name="quick_links[home][url]" 
                                           value="<?php echo htmlspecialchars($quick_links['home']['url'] ?? '/infl/'); ?>" 
                                           placeholder="URL">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="quick_link_features" class="form-label">Funzionalità</label>
                                    <input type="text" class="form-control" id="quick_link_features" name="quick_links[features][label]" 
                                           value="<?php echo htmlspecialchars($quick_links['features']['label'] ?? 'Funzionalità'); ?>">
                                    <input type="text" class="form-control mt-2" name="quick_links[features][url]" 
                                           value="<?php echo htmlspecialchars($quick_links['features']['url'] ?? '#features'); ?>" 
                                           placeholder="URL">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="quick_link_how_it_works" class="form-label">Come Funziona</label>
                                    <input type="text" class="form-control" id="quick_link_how_it_works" name="quick_links[how_it_works][label]" 
                                           value="<?php echo htmlspecialchars($quick_links['how_it_works']['label'] ?? 'Come Funziona'); ?>">
                                    <input type="text" class="form-control mt-2" name="quick_links[how_it_works][url]" 
                                           value="<?php echo htmlspecialchars($quick_links['how_it_works']['url'] ?? '#how-it-works'); ?>" 
                                           placeholder="URL">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="quick_link_login" class="form-label">Login</label>
                                    <input type="text" class="form-control" id="quick_link_login" name="quick_links[login][label]" 
                                           value="<?php echo htmlspecialchars($quick_links['login']['label'] ?? 'Login'); ?>">
                                    <input type="text" class="form-control mt-2" name="quick_links[login][url]" 
                                           value="<?php echo htmlspecialchars($quick_links['login']['url'] ?? '/infl/auth/login.php'); ?>" 
                                           placeholder="URL">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="quick_link_register" class="form-label">Registrati</label>
                                    <input type="text" class="form-control" id="quick_link_register" name="quick_links[register][label]" 
                                           value="<?php echo htmlspecialchars($quick_links['register']['label'] ?? 'Registrati'); ?>">
                                    <input type="text" class="form-control mt-2" name="quick_links[register][url]" 
                                           value="<?php echo htmlspecialchars($quick_links['register']['url'] ?? '/infl/auth/register.php'); ?>" 
                                           placeholder="URL">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione Supporto -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-life-ring me-2"></i>Supporto
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php
                                $support_links = $footer_settings['support_links'] ?? [
                                    'contact' => ['label' => 'Contattaci', 'url' => '#'],
                                    'faq' => ['label' => 'FAQ', 'url' => '#'],
                                    'privacy' => ['label' => 'Privacy Policy', 'url' => '#'],
                                    'terms' => ['label' => 'Termini di Servizio', 'url' => '#']
                                ];
                                ?>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="support_link_contact" class="form-label">Contattaci</label>
                                    <input type="text" class="form-control" id="support_link_contact" name="support_links[contact][label]" 
                                           value="<?php echo htmlspecialchars($support_links['contact']['label'] ?? 'Contattaci'); ?>">
                                    <input type="text" class="form-control mt-2" name="support_links[contact][url]" 
                                           value="<?php echo htmlspecialchars($support_links['contact']['url'] ?? '#'); ?>" 
                                           placeholder="URL">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="support_link_faq" class="form-label">FAQ</label>
                                    <input type="text" class="form-control" id="support_link_faq" name="support_links[faq][label]" 
                                           value="<?php echo htmlspecialchars($support_links['faq']['label'] ?? 'FAQ'); ?>">
                                    <input type="text" class="form-control mt-2" name="support_links[faq][url]" 
                                           value="<?php echo htmlspecialchars($support_links['faq']['url'] ?? '#'); ?>" 
                                           placeholder="URL">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="support_link_privacy" class="form-label">Privacy Policy</label>
                                    <input type="text" class="form-control" id="support_link_privacy" name="support_links[privacy][label]" 
                                           value="<?php echo htmlspecialchars($support_links['privacy']['label'] ?? 'Privacy Policy'); ?>">
                                    <input type="text" class="form-control mt-2" name="support_links[privacy][url]" 
                                           value="<?php echo htmlspecialchars($support_links['privacy']['url'] ?? '#'); ?>" 
                                           placeholder="URL">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="support_link_terms" class="form-label">Termini di Servizio</label>
                                    <input type="text" class="form-control" id="support_link_terms" name="support_links[terms][label]" 
                                           value="<?php echo htmlspecialchars($support_links['terms']['label'] ?? 'Termini di Servizio'); ?>">
                                    <input type="text" class="form-control mt-2" name="support_links[terms][url]" 
                                           value="<?php echo htmlspecialchars($support_links['terms']['url'] ?? '#'); ?>" 
                                           placeholder="URL">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione Social Media -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-share-alt me-2"></i>Seguici su - Social Media
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="socialMediaContainer">
                                <?php
                                $social_links = $footer_settings['social_links'] ?? [
                                    'instagram' => ['platform' => 'instagram', 'url' => '#', 'icon' => 'fab fa-instagram'],
                                    'tiktok' => ['platform' => 'tiktok', 'url' => '#', 'icon' => 'fab fa-tiktok'],
                                    'linkedin' => ['platform' => 'linkedin', 'url' => '#', 'icon' => 'fab fa-linkedin']
                                ];
                                
                                foreach ($social_links as $key => $social): ?>
                                    <div class="social-media-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Piattaforma</label>
                                                    <select class="form-select social-platform" name="social_links[<?php echo $key; ?>][platform]">
                                                        <option value="instagram" <?php echo ($social['platform'] === 'instagram') ? 'selected' : ''; ?>>Instagram</option>
                                                        <option value="tiktok" <?php echo ($social['platform'] === 'tiktok') ? 'selected' : ''; ?>>TikTok</option>
                                                        <option value="linkedin" <?php echo ($social['platform'] === 'linkedin') ? 'selected' : ''; ?>>LinkedIn</option>
                                                        <option value="facebook" <?php echo ($social['platform'] === 'facebook') ? 'selected' : ''; ?>>Facebook</option>
                                                        <option value="twitter" <?php echo ($social['platform'] === 'twitter') ? 'selected' : ''; ?>>Twitter</option>
                                                        <option value="youtube" <?php echo ($social['platform'] === 'youtube') ? 'selected' : ''; ?>>YouTube</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Icona</label>
                                                    <select class="form-select social-icon" name="social_links[<?php echo $key; ?>][icon]">
                                                        <option value="fab fa-instagram" <?php echo ($social['icon'] === 'fab fa-instagram') ? 'selected' : ''; ?>>Instagram</option>
                                                        <option value="fab fa-tiktok" <?php echo ($social['icon'] === 'fab fa-tiktok') ? 'selected' : ''; ?>>TikTok</option>
                                                        <option value="fab fa-linkedin" <?php echo ($social['icon'] === 'fab fa-linkedin') ? 'selected' : ''; ?>>LinkedIn</option>
                                                        <option value="fab fa-facebook" <?php echo ($social['icon'] === 'fab fa-facebook') ? 'selected' : ''; ?>>Facebook</option>
                                                        <option value="fab fa-twitter" <?php echo ($social['icon'] === 'fab fa-twitter') ? 'selected' : ''; ?>>Twitter</option>
                                                        <option value="fab fa-youtube" <?php echo ($social['icon'] === 'fab fa-youtube') ? 'selected' : ''; ?>>YouTube</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">URL Profilo</label>
                                                    <input type="url" class="form-control" name="social_links[<?php echo $key; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($social['url']); ?>" 
                                                           placeholder="https://...">
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-social" onclick="removeSocialItem(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addSocialItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Social
                            </button>
                        </div>
                    </div>

                    <!-- Pulsanti di salvataggio -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Impostazioni
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Ripristina Default
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab Headers -->
            <div class="tab-pane fade" id="headers" role="tabpanel" aria-labelledby="headers-tab">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>Funzionalità in Sviluppo</h5>
                    <p class="mb-0">La gestione degli header e footer delle altre pagine sarà disponibile prossimamente.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let socialCounter = <?php echo count($footer_settings['social_links'] ?? []); ?>;

function addSocialItem() {
    socialCounter++;
    const container = document.getElementById('socialMediaContainer');
    const newItem = document.createElement('div');
    newItem.className = 'social-media-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Piattaforma</label>
                    <select class="form-select social-platform" name="social_links[new_${socialCounter}][platform]">
                        <option value="instagram">Instagram</option>
                        <option value="tiktok">TikTok</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="facebook">Facebook</option>
                        <option value="twitter">Twitter</option>
                        <option value="youtube">YouTube</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Icona</label>
                    <select class="form-select social-icon" name="social_links[new_${socialCounter}][icon]">
                        <option value="fab fa-instagram">Instagram</option>
                        <option value="fab fa-tiktok">TikTok</option>
                        <option value="fab fa-linkedin">LinkedIn</option>
                        <option value="fab fa-facebook">Facebook</option>
                        <option value="fab fa-twitter">Twitter</option>
                        <option value="fab fa-youtube">YouTube</option>
                    </select>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">URL Profilo</label>
                    <input type="url" class="form-control" name="social_links[new_${socialCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-social" onclick="removeSocialItem(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeSocialItem(button) {
    const item = button.closest('.social-media-item');
    if (document.querySelectorAll('.social-media-item').length > 1) {
        item.remove();
    } else {
        alert('Deve esserci almeno un social network!');
    }
}

function resetForm() {
    if (confirm('Sei sicuro di voler ripristinare le impostazioni predefinite? I dati attuali andranno persi.')) {
        document.getElementById('footerForm').reset();
    }
}

// Aggiorna dinamicamente le icone quando cambia la piattaforma
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('social-platform')) {
        const platform = e.target.value;
        const iconSelect = e.target.closest('.row').querySelector('.social-icon');
        const iconMap = {
            'instagram': 'fab fa-instagram',
            'tiktok': 'fab fa-tiktok',
            'linkedin': 'fab fa-linkedin',
            'facebook': 'fab fa-facebook',
            'twitter': 'fab fa-twitter',
            'youtube': 'fab fa-youtube'
        };
        iconSelect.value = iconMap[platform] || 'fab fa-link';
    }
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>