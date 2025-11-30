<?php
ob_start();

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
require_once '../includes/admin_header.php';
require_once '../includes/general_settings_functions.php';

// Verifica login
checkAdminLogin();

// Genera CSRF token se non esiste
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Inizializza variabili
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$message = '';

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert alert-danger">Token di sicurezza non valido</div>';
    } else {
        if (isset($_POST['save_sponsor'])) {
            // Gestione salvataggio sponsor
            $data = [
                'influencer_id' => isset($_POST['influencer_id']) ? intval($_POST['influencer_id']) : null,
                'title' => isset($_POST['title']) ? trim($_POST['title']) : '',
                'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
                'budget' => isset($_POST['budget']) ? floatval($_POST['budget']) : 0,
                'category' => isset($_POST['category']) ? trim($_POST['category']) : '',
                'platforms' => isset($_POST['platforms']) ? json_encode($_POST['platforms']) : '[]',
                'target_audience' => isset($_POST['target_audience']) ? trim($_POST['target_audience']) : '',
                'status' => isset($_POST['status']) ? trim($_POST['status']) : 'active'
            ];
            
            if (empty($data['title']) || empty($data['influencer_id'])) {
                $message = '<div class="alert alert-danger">Titolo e influencer sono obbligatori</div>';
            } else {
                $success = saveSponsor($data, $id);
                if ($success) {
                    $message = '<div class="alert alert-success">Sponsor salvato con successo!</div>';
                    if (!$id) {
                        $action = 'list';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Errore nel salvataggio</div>';
                }
            }
        }
        
        // Gestione eliminazione
        if (isset($_POST['delete_sponsor'])) {
            $sponsor_id = intval($_POST['sponsor_id']);
            $success = hardDeleteSponsor($sponsor_id);
            if ($success) {
                $message = '<div class="alert alert-success">Sponsor eliminato definitivamente!</div>';
            } else {
                $message = '<div class="alert alert-danger">Errore nell\'eliminazione dello sponsor</div>';
            }
        }
    }
}

// Gestione delle diverse azioni
if ($action === 'list') {
    // Pagina lista sponsor
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 25;
    
    $filters = [
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'influencer_search' => isset($_GET['influencer_search']) ? $_GET['influencer_search'] : '',
    'category' => isset($_GET['category']) ? $_GET['category'] : ''
];
    
    // Ottieni dati
    $result = getSponsors($page, $per_page, $filters);
    $sponsors = $result['data'];
    $total_pages = $result['total_pages'];
    $total_count = $result['total'];
    $influencers_list = getAllInfluencers();
    
    // MODIFICA: Recupera le categorie dalla stessa fonte usata in general-settings.php
    $categories_list = get_active_categories_for_brands();
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="fas fa-handshake me-2"></i>Gestione Sponsor Influencer
                    </h1>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuovo Sponsor
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Statistiche Rapide -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount('active'); ?></div>
                        <div>Sponsor Attivi</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-play-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount('pending'); ?></div>
                        <div>In Attesa</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-info text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount('completed'); ?></div>
                        <div>Completati</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-danger text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount('rejected'); ?></div>
                        <div>Rifiutati</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-secondary text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount('draft'); ?></div>
                        <div>Bozze</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-edit fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount(); ?></div>
                        <div>Totale Sponsor</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-handshake fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Filtri
                        </h5>
                    </div>
                    <div class="card-body">
    <form method="get" class="row g-3">
        <input type="hidden" name="action" value="list">
        
        <div class="col-md-2">
            <label for="search" class="form-label">Titolo sponsor</label>
            <input type="text" class="form-control" id="search" name="search" 
                   value="<?php echo htmlspecialchars($filters['search']); ?>" 
                   placeholder="Cerca titolo...">
        </div>
        
        <div class="col-md-2">
            <label for="status" class="form-label">Stato</label>
            <select class="form-select" id="status" name="status">
                <option value="">Tutti</option>
                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Attivi</option>
                <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>In attesa</option>
                <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completati</option>
                <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rifiutati</option>
                <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Bozza</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="influencer_search" class="form-label">Nome influencer</label>
            <input type="text" class="form-control" id="influencer_search" name="influencer_search" 
                   value="<?php echo htmlspecialchars($filters['influencer_search'] ?? ''); ?>" 
                   placeholder="Cerca nome...">
        </div>
        
        <div class="col-md-2">
            <label for="category" class="form-label">Categoria</label>
            <select class="form-select" id="category" name="category">
                <option value="">Tutte</option>
                <?php foreach ($categories_list as $category): ?>
                    <option value="<?php echo $category['name']; ?>" 
                            <?php echo $filters['category'] === $category['name'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-search me-1"></i> Cerca
            </button>
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <a href="?action=list" class="btn btn-outline-secondary w-100">
                <i class="fas fa-refresh me-1"></i> Reset
            </a>
        </div>
    </form>
</div>
                </div>
                
                <!-- Tabella Sponsor -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista Sponsor 
                            <span class="badge bg-secondary"><?php echo $total_count; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sponsors)): ?>
                            <div class="alert alert-info text-center py-4">
                                <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
                                <h5>Nessuno sponsor trovato</h5>
                                <p class="text-muted">Utilizza i filtri per trovare gli sponsor o <a href="?action=add">creane uno nuovo</a>.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Copertina</th>
                                            <th>Titolo Sponsor</th>
                                            <th>Influencer</th>
                                            <th>Budget</th>
                                            <th>Categoria</th>
                                            <th>Stato</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sponsors as $sponsor): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($sponsor['image_url']): ?>
                                                        <?php
                                                        // Correzione del percorso immagine
                                                        $image_path = '/infl/uploads/sponsor/' . basename($sponsor['image_url']);
                                                        ?>
                                                        <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                             alt="<?php echo htmlspecialchars($sponsor['title']); ?>" 
                                                             class="rounded" style="width: 40px; height: 40px; object-fit: cover;" 
                                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                        <div class="rounded bg-light d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px; display: none;">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="rounded bg-light d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($sponsor['title']); ?></strong>
                                                        <?php if ($sponsor['platforms']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php
                                                                $platforms = json_decode($sponsor['platforms'], true) ?: [];
                                                                foreach ($platforms as $platform): ?>
                                                                    <i class="fab fa-<?php echo $platform; ?> me-1"></i>
                                                                <?php endforeach; ?>
                                                                <?php echo implode(', ', $platforms); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
    <div class="d-flex align-items-center">
        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2" 
             style="width: 32px; height: 32px;">
            <i class="fas fa-user text-white"></i>
        </div>
        <div>
            <strong><?php echo htmlspecialchars($sponsor['influencer_email']); ?></strong>
            <?php if (!empty($sponsor['influencer_name'])): ?>
                <br>
                <small class="text-muted"><?php echo htmlspecialchars($sponsor['influencer_name']); ?></small>
            <?php endif; ?>
        </div>
    </div>
</td>
                                                <td>
                                                    <strong><?php echo number_format($sponsor['budget'], 2); ?> €</strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($sponsor['category']); ?></span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_badge = '';
                                                    switch ($sponsor['status']) {
                                                        case 'active':
                                                            $status_badge = '<span class="badge bg-success"><i class="fas fa-play me-1"></i> Attivo</span>';
                                                            break;
                                                        case 'pending':
                                                            $status_badge = '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i> In attesa</span>';
                                                            break;
                                                        case 'completed':
                                                            $status_badge = '<span class="badge bg-info"><i class="fas fa-check me-1"></i> Completato</span>';
                                                            break;
                                                        case 'rejected':
                                                            $status_badge = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i> Rifiutato</span>';
                                                            break;
                                                        case 'draft':
                                                            $status_badge = '<span class="badge bg-secondary"><i class="fas fa-edit me-1"></i> Bozza</span>';
                                                            break;
                                                        default:
                                                            $status_badge = '<span class="badge bg-light text-dark">' . htmlspecialchars($sponsor['status']) . '</span>';
                                                    }
                                                    echo $status_badge;
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <!-- Modifica -->
                                                        <a href="?action=edit&id=<?php echo $sponsor['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" title="Modifica">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <!-- Elimina -->
                                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteModal<?php echo $sponsor['id']; ?>"
                                                                title="Elimina">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Modal Eliminazione Sponsor -->
                                            <div class="modal fade" id="deleteModal<?php echo $sponsor['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="post">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <input type="hidden" name="sponsor_id" value="<?php echo $sponsor['id']; ?>">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title text-danger">
                                                                    <i class="fas fa-exclamation-triangle me-2"></i>Conferma Eliminazione
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Sei sicuro di voler eliminare definitivamente lo sponsor <strong>"<?php echo htmlspecialchars($sponsor['title']); ?>"</strong>?</p>
                                                                <p class="text-danger">
                                                                    <i class="fas fa-exclamation-circle me-1"></i>
                                                                    Questa azione non può essere annullata. Lo sponsor e tutte le immagini correlate verranno rimossi permanentemente dal sistema.
                                                                </p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                                <button type="submit" name="delete_sponsor" class="btn btn-danger">
                                                                    <i class="fas fa-trash me-1"></i> Elimina
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginazione -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Paginazione sponsor">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=1<?php echo buildQueryString($filters); ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $page - 1; ?><?php echo buildQueryString($filters); ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=list&page=<?php echo $i; ?><?php echo buildQueryString($filters); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $page + 1; ?><?php echo buildQueryString($filters); ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $total_pages; ?><?php echo buildQueryString($filters); ?>">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                </ul>
                                <div class="text-center text-muted mt-2">
                                    Pagina <?php echo $page; ?> di <?php echo $total_pages; ?> 
                                    (Totale: <?php echo $total_count; ?> sponsor)
                                </div>
                            </nav>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    
} elseif ($action === 'add' || $action === 'edit') {
    // Pagina aggiungi/modifica sponsor
    $sponsor = null;
    if ($action === 'edit' && $id) {
        $sponsor = getSponsorById($id);
        if (!$sponsor) {
            header('Location: sponsors.php');
            exit;
        }
    }
    
    $influencers_list = getAllInfluencers();
    $platforms = ['instagram', 'facebook', 'tiktok', 'youtube', 'twitter', 'linkedin'];
    
    // MODIFICA: Recupera le categorie dalla stessa fonte usata in general-settings.php
    $categories_list = get_active_categories_for_brands();
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="fas fa-handshake me-2"></i><?php echo $action === 'add' ? 'Nuovo Sponsor' : 'Modifica Sponsor'; ?>
                    </h1>
                    <a href="?action=list" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna alla lista
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Titolo Sponsor <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($sponsor['title'] ?? ''); ?>" 
                                               required>
                                        <div class="form-text">Inserisci un titolo descrittivo per lo sponsor</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="influencer_id" class="form-label">Influencer <span class="text-danger">*</span></label>
                                        <select class="form-select" id="influencer_id" name="influencer_id" required>
                                            <option value="">Seleziona un influencer</option>
                                            <?php foreach ($influencers_list as $influencer): ?>
                                                <option value="<?php echo $influencer['id']; ?>" 
                                                        <?php echo ($sponsor['influencer_id'] ?? '') == $influencer['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($influencer['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4"><?php echo htmlspecialchars($sponsor['description'] ?? ''); ?></textarea>
                                <div class="form-text">Descrivi i dettagli dello sponsor e l'offerta</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="budget" class="form-label">Budget (€)</label>
                                        <input type="number" class="form-control" id="budget" name="budget" 
                                               step="0.01" min="0"
                                               value="<?php echo htmlspecialchars($sponsor['budget'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Categoria</label>
                                        <select class="form-select" id="category" name="category">
                                            <option value="">Seleziona categoria</option>
                                            <?php foreach ($categories_list as $category): ?>
                                                <option value="<?php echo $category['name']; ?>" 
                                                        <?php echo ($sponsor['category'] ?? '') === $category['name'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Stato</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?php echo ($sponsor['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Bozza</option>
                                            <option value="pending" <?php echo ($sponsor['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>In attesa</option>
                                            <option value="active" <?php echo ($sponsor['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Attivo</option>
                                            <option value="completed" <?php echo ($sponsor['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completato</option>
                                            <option value="rejected" <?php echo ($sponsor['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rifiutato</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Immagine</label>
                                        <input type="file" class="form-control" id="image" name="image" 
                                               accept="image/*">
                                        <?php if (isset($sponsor['image_url']) && $sponsor['image_url']): ?>
                                            <div class="mt-2">
                                                <?php
                                                // Correzione del percorso immagine
                                                $image_path = '/infl/uploads/sponsor/' . basename($sponsor['image_url']);
                                                ?>
                                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                     alt="Immagine sponsor" 
                                                     style="max-width: 100px; max-height: 100px; object-fit: cover;" 
                                                     class="rounded border"
                                                     onerror="this.style.display='none';">
                                                <small class="d-block text-muted">Immagine attuale</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Piattaforme Social</label>
                                <div class="row">
                                    <?php 
                                    $selected_platforms = [];
                                    if (isset($sponsor['platforms']) && $sponsor['platforms']) {
                                        $selected_platforms = json_decode($sponsor['platforms'], true) ?: [];
                                    }
                                    ?>
                                    <?php foreach ($platforms as $platform): ?>
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="platforms[]" 
                                                       value="<?php echo $platform; ?>" 
                                                       id="platform_<?php echo $platform; ?>"
                                                       <?php echo in_array($platform, $selected_platforms) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="platform_<?php echo $platform; ?>">
                                                    <i class="fab fa-<?php echo $platform; ?> me-1"></i>
                                                    <?php echo ucfirst($platform); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="target_audience" class="form-label">Target Audience</label>
                                <input type="text" class="form-control" id="target_audience" name="target_audience" 
                                       value="<?php echo htmlspecialchars($sponsor['target_audience'] ?? ''); ?>"
                                       placeholder="Es: 18-35 anni, Italia">
                                <div class="form-text">Descrivi il pubblico target dello sponsor</div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="save_sponsor" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salva Sponsor
                                </button>
                                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                                
                                <?php if ($action === 'edit' && $sponsor): ?>
                                <div class="ms-auto">
                                    <small class="text-muted">
                                        Creata il: <?php echo date('d/m/Y H:i', strtotime($sponsor['created_at'])); ?>
                                        <?php if ($sponsor['updated_at'] && $sponsor['updated_at'] != $sponsor['created_at']): ?>
                                            <br>Modificata il: <?php echo date('d/m/Y H:i', strtotime($sponsor['updated_at'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    
} else {
    // Azione non riconosciuta, redirect alla lista
    header('Location: sponsors.php');
    exit;
}

ob_end_flush();

require_once '../includes/admin_footer.php';
?>