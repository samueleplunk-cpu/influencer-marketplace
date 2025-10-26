<?php
// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
require_once '../includes/admin_header.php';

// Verifica login
checkAdminLogin();

// Inizializza variabili
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$message = '';

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_campaign'])) {
        // Gestione salvataggio campagna
        $data = [
            'brand_id' => isset($_POST['brand_id']) ? intval($_POST['brand_id']) : null,
            'name' => isset($_POST['name']) ? trim($_POST['name']) : '',
            'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
            'budget' => isset($_POST['budget']) ? floatval($_POST['budget']) : 0,
            'currency' => isset($_POST['currency']) ? trim($_POST['currency']) : 'EUR',
            'niche' => isset($_POST['niche']) ? trim($_POST['niche']) : '',
            'platforms' => isset($_POST['platforms']) ? json_encode($_POST['platforms']) : '[]',
            'target_audience' => isset($_POST['target_audience']) ? trim($_POST['target_audience']) : '',
            'status' => isset($_POST['status']) ? trim($_POST['status']) : 'active',
            'start_date' => isset($_POST['start_date']) ? trim($_POST['start_date']) : '',
            'end_date' => isset($_POST['end_date']) ? trim($_POST['end_date']) : '',
            'requirements' => isset($_POST['requirements']) ? trim($_POST['requirements']) : '',
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
            'allow_applications' => isset($_POST['allow_applications']) ? 1 : 0
        ];
        
        if (empty($data['name']) || empty($data['brand_id'])) {
            $message = '<div class="alert alert-danger">Nome campagna e brand sono obbligatori</div>';
        } else {
            $success = saveCampaign($data, $id);
            if ($success) {
                $message = '<div class="alert alert-success">Campagna salvata con successo!</div>';
                if (!$id) {
                    $action = 'list';
                }
            } else {
                $message = '<div class="alert alert-danger">Errore nel salvataggio</div>';
            }
        }
    }
    
    // Gestione azioni rapide
    if (isset($_POST['action_type']) && isset($_POST['campaign_id'])) {
        $campaign_id = intval($_POST['campaign_id']);
        $action_type = $_POST['action_type'];
        
        switch($action_type) {
            case 'pause':
                updateCampaignStatus($campaign_id, 'paused');
                $message = '<div class="alert alert-warning">Campagna messa in pausa</div>';
                break;
            case 'resume':
                updateCampaignStatus($campaign_id, 'active');
                $message = '<div class="alert alert-success">Campagna ripresa</div>';
                break;
            case 'complete':
                updateCampaignStatus($campaign_id, 'completed');
                $message = '<div class="alert alert-info">Campagna completata</div>';
                break;
            case 'delete':
                updateCampaignStatus($campaign_id, 'deleted');
                $message = '<div class="alert alert-info">Campagna eliminata</div>';
                break;
        }
    }
}

// Gestione delle diverse azioni
if ($action === 'list') {
    // Pagina lista campagne
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 15;
    
    $filters = [
        'status' => isset($_GET['status']) ? $_GET['status'] : '',
        'search' => isset($_GET['search']) ? $_GET['search'] : '',
        'brand_id' => isset($_GET['brand_id']) ? $_GET['brand_id'] : '',
        'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
        'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : ''
    ];
    
    // Ottieni dati
    $result = getCampaigns($page, $per_page, $filters);
    $campaigns = $result['data'];
    $total_pages = $result['total_pages'];
    $total_count = $result['total'];
    $brands_list = getAllBrands();
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="fas fa-bullhorn me-2"></i>Gestione Campagne Brand
                    </h1>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuova Campagna
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-primary text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo getCampaignsCount('active'); ?></div>
                                        <div>Campagne Attive</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-play-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-warning text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo getCampaignsCount('paused'); ?></div>
                                        <div>In Pausa</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-pause-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-success text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo getCampaignsCount('completed'); ?></div>
                                        <div>Completate</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-info text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo getCampaignsCount(); ?></div>
                                        <div>Totale Campagne</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-bullhorn fa-2x"></i>
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
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome campagna...">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Attive</option>
                                    <option value="paused" <?php echo $filters['status'] === 'paused' ? 'selected' : ''; ?>>In pausa</option>
                                    <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completate</option>
                                    <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Bozza</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="brand_id" class="form-label">Brand</label>
                                <select class="form-select" id="brand_id" name="brand_id">
                                    <option value="">Tutti i brand</option>
                                    <?php foreach ($brands_list as $brand): ?>
                                        <option value="<?php echo $brand['id']; ?>" 
                                                <?php echo $filters['brand_id'] == $brand['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($brand['company_name'] ?: $brand['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Da data</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">A data</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                            </div>
                            
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Campagne -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista Campagne 
                            <span class="badge bg-secondary"><?php echo $total_count; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($campaigns)): ?>
                            <div class="alert alert-info text-center py-4">
                                <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                <h5>Nessuna campagna trovata</h5>
                                <p class="text-muted">Utilizza i filtri per trovare le campagne o <a href="?action=add">creane una nuova</a>.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nome Campagna</th>
                                            <th>Brand</th>
                                            <th>Budget</th>
                                            <th>Stato</th>
                                            <th>Date</th>
                                            <th>Statistiche</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <?php if (!$campaign['is_public']): ?>
                                                                <span class="badge bg-secondary" title="Campagna privata">
                                                                    <i class="fas fa-lock"></i>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                            <?php if ($campaign['niche']): ?>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($campaign['niche']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2" 
                                                             style="width: 32px; height: 32px;">
                                                            <i class="fas fa-building text-white"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($campaign['brand_display_name']); ?></strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?php echo number_format($campaign['budget'], 2); ?> <?php echo htmlspecialchars($campaign['currency']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_badge = '';
                                                    switch ($campaign['status']) {
                                                        case 'active':
                                                            $status_badge = '<span class="badge bg-success"><i class="fas fa-play me-1"></i> Attiva</span>';
                                                            break;
                                                        case 'paused':
                                                            $status_badge = '<span class="badge bg-warning"><i class="fas fa-pause me-1"></i> In pausa</span>';
                                                            break;
                                                        case 'completed':
                                                            $status_badge = '<span class="badge bg-info"><i class="fas fa-check me-1"></i> Completata</span>';
                                                            break;
                                                        case 'draft':
                                                            $status_badge = '<span class="badge bg-secondary"><i class="fas fa-edit me-1"></i> Bozza</span>';
                                                            break;
                                                        default:
                                                            $status_badge = '<span class="badge bg-light text-dark">' . htmlspecialchars($campaign['status']) . '</span>';
                                                    }
                                                    echo $status_badge;
                                                    ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong>Inizio:</strong> <?php echo date('d/m/Y', strtotime($campaign['start_date'])); ?><br>
                                                        <?php if ($campaign['end_date']): ?>
                                                            <strong>Fine:</strong> <?php echo date('d/m/Y', strtotime($campaign['end_date'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Nessuna fine</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <span class="badge bg-info" title="Visualizzazioni">
                                                            <i class="fas fa-eye me-1"></i> <?php echo $campaign['views_count'] ?? 0; ?>
                                                        </span>
                                                        <span class="badge bg-success" title="Influencer invitati">
                                                            <i class="fas fa-users me-1"></i> <?php echo $campaign['invited_count'] ?? 0; ?>
                                                        </span>
                                                        <?php if ($campaign['allow_applications']): ?>
                                                            <span class="badge bg-primary" title="Accetta applicazioni">
                                                                <i class="fas fa-user-plus"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <!-- Modifica -->
                                                        <a href="?action=edit&id=<?php echo $campaign['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Modifica">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <!-- Pausa/Riprendi -->
                                                        <?php if ($campaign['status'] === 'active'): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                                <input type="hidden" name="action_type" value="pause">
                                                                <button type="submit" class="btn btn-outline-warning" 
                                                                        onclick="return confirm('Metti in pausa questa campagna?')"
                                                                        title="Metti in pausa">
                                                                    <i class="fas fa-pause"></i>
                                                                </button>
                                                            </form>
                                                        <?php elseif ($campaign['status'] === 'paused'): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                                <input type="hidden" name="action_type" value="resume">
                                                                <button type="submit" class="btn btn-outline-success" 
                                                                        onclick="return confirm('Riprendi questa campagna?')"
                                                                        title="Riprendi">
                                                                    <i class="fas fa-play"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Completa -->
                                                        <?php if (in_array($campaign['status'], ['active', 'paused'])): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                                <input type="hidden" name="action_type" value="complete">
                                                                <button type="submit" class="btn btn-outline-info" 
                                                                        onclick="return confirm('Segnare come completata questa campagna?')"
                                                                        title="Segna come completata">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Elimina -->
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                            <input type="hidden" name="action_type" value="delete">
                                                            <button type="submit" class="btn btn-outline-danger" 
                                                                    onclick="return confirm('Sei sicuro di voler eliminare questa campagna?')"
                                                                    title="Elimina">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginazione -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Paginazione campagne">
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
                                    (Totale: <?php echo $total_count; ?> campagne)
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
    // Pagina aggiungi/modifica campagna
    $campaign = null;
    if ($action === 'edit' && $id) {
        $campaign = getCampaignById($id);
        if (!$campaign) {
            header('Location: brand-campaigns.php');
            exit;
        }
    }
    
    $brands_list = getAllBrands();
    $platforms = ['instagram', 'facebook', 'tiktok', 'youtube', 'twitter', 'linkedin'];
    $niches = ['Fashion', 'Beauty', 'Lifestyle', 'Travel', 'Food', 'Fitness', 'Tech', 'Gaming', 'Parenting', 'Business'];
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="fas fa-bullhorn me-2"></i><?php echo $action === 'add' ? 'Nuova Campagna' : 'Modifica Campagna'; ?>
                    </h1>
                    <a href="?action=list" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna alla lista
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nome Campagna <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($campaign['name'] ?? ''); ?>" 
                                               required>
                                        <div class="form-text">Inserisci un nome descrittivo per la campagna</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="brand_id" class="form-label">Brand <span class="text-danger">*</span></label>
                                        <select class="form-select" id="brand_id" name="brand_id" required>
                                            <option value="">Seleziona un brand</option>
                                            <?php foreach ($brands_list as $brand): ?>
                                                <option value="<?php echo $brand['id']; ?>" 
                                                        <?php echo ($campaign['brand_id'] ?? '') == $brand['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($brand['company_name'] ?: $brand['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4"><?php echo htmlspecialchars($campaign['description'] ?? ''); ?></textarea>
                                <div class="form-text">Descrivi gli obiettivi e i dettagli della campagna</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="budget" class="form-label">Budget</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="budget" name="budget" 
                                                   step="0.01" min="0"
                                                   value="<?php echo htmlspecialchars($campaign['budget'] ?? ''); ?>">
                                            <select class="form-select" name="currency" style="max-width: 100px;">
                                                <option value="EUR" <?php echo ($campaign['currency'] ?? 'EUR') === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                                <option value="USD" <?php echo ($campaign['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD</option>
                                                <option value="GBP" <?php echo ($campaign['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="niche" class="form-label">Nicchia</label>
                                        <select class="form-select" id="niche" name="niche">
                                            <option value="">Seleziona nicchia</option>
                                            <?php foreach ($niches as $niche): ?>
                                                <option value="<?php echo $niche; ?>" 
                                                        <?php echo ($campaign['niche'] ?? '') === $niche ? 'selected' : ''; ?>>
                                                    <?php echo $niche; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Data Inizio</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo htmlspecialchars($campaign['start_date'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">Data Fine</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo htmlspecialchars($campaign['end_date'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Piattaforme Social</label>
                                <div class="row">
                                    <?php 
                                    $selected_platforms = [];
                                    if (isset($campaign['platforms']) && $campaign['platforms']) {
                                        $selected_platforms = json_decode($campaign['platforms'], true) ?: [];
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
                                       value="<?php echo htmlspecialchars($campaign['target_audience'] ?? ''); ?>"
                                       placeholder="Es: 18-35 anni, Italia">
                                <div class="form-text">Descrivi il pubblico target della campagna</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="requirements" class="form-label">Requisiti</label>
                                <textarea class="form-control" id="requirements" name="requirements" 
                                          rows="3"><?php echo htmlspecialchars($campaign['requirements'] ?? ''); ?></textarea>
                                <div class="form-text">Specifica i requisiti per gli influencer (minimo follower, engagement rate, etc.)</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Stato</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?php echo ($campaign['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Bozza</option>
                                            <option value="active" <?php echo ($campaign['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Attiva</option>
                                            <option value="paused" <?php echo ($campaign['status'] ?? '') === 'paused' ? 'selected' : ''; ?>>In pausa</option>
                                            <option value="completed" <?php echo ($campaign['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completata</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3 form-check form-switch">
                                        <input type="checkbox" class="form-check-input" id="is_public" name="is_public" 
                                               <?php echo ($campaign['is_public'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_public">Campagna pubblica</label>
                                        <div class="form-text">Visibile agli influencer</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3 form-check form-switch">
                                        <input type="checkbox" class="form-check-input" id="allow_applications" name="allow_applications" 
                                               <?php echo ($campaign['allow_applications'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_applications">Accetta applicazioni</label>
                                        <div class="form-text">Gli influencer possono candidarsi</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="save_campaign" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salva Campagna
                                </button>
                                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                                
                                <?php if ($action === 'edit' && $campaign): ?>
                                <div class="ms-auto">
                                    <small class="text-muted">
                                        Creata il: <?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?>
                                        <?php if ($campaign['updated_at'] && $campaign['updated_at'] != $campaign['created_at']): ?>
                                            <br>Modificata il: <?php echo date('d/m/Y H:i', strtotime($campaign['updated_at'])); ?>
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
    header('Location: brand-campaigns.php');
    exit;
}

require_once '../includes/admin_footer.php';
?>