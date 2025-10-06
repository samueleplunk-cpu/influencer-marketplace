<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
require_once '../includes/admin_header.php';

checkAdminLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_influencer'])) {
        $data = [
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if (empty($data['name']) || empty($data['email'])) {
            $message = '<div class="alert alert-danger">Nome ed email sono obbligatori</div>';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Email non valida</div>';
        } else {
            $success = saveInfluencer($data, $id);
            if ($success) {
                $message = '<div class="alert alert-success">Influencer salvato con successo!</div>';
                if (!$id) {
                    $action = 'list';
                }
            } else {
                $message = '<div class="alert alert-danger">Errore nel salvataggio</div>';
            }
        }
    }
    
    // Gestione azioni rapide
    if (isset($_POST['action_type']) && isset($_POST['influencer_id'])) {
        $influencer_id = $_POST['influencer_id'];
        $action_type = $_POST['action_type'];
        
        switch($action_type) {
            case 'suspend':
                $suspension_end = $_POST['suspension_end'] ?? null;
                updateInfluencerStatus($influencer_id, 'suspend', $suspension_end);
                $message = '<div class="alert alert-warning">Influencer sospeso</div>';
                break;
                
            case 'unsuspend':
                updateInfluencerStatus($influencer_id, 'unsuspend');
                $message = '<div class="alert alert-success">Sospensione rimossa</div>';
                break;
                
            case 'block':
                updateInfluencerStatus($influencer_id, 'block');
                $message = '<div class="alert alert-danger">Influencer bloccato</div>';
                break;
                
            case 'unblock':
                updateInfluencerStatus($influencer_id, 'unblock');
                $message = '<div class="alert alert-success">Blocco rimosso</div>';
                break;
                
            case 'delete':
                updateInfluencerStatus($influencer_id, 'delete');
                $message = '<div class="alert alert-info">Influencer eliminato</div>';
                break;
                
            case 'restore':
                updateInfluencerStatus($influencer_id, 'restore');
                $message = '<div class="alert alert-success">Influencer ripristinato</div>';
                break;
        }
    }
}

// Pagina lista influencer
if ($action === 'list') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 15;
    
    $filters = [
        'status' => $_GET['status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? ''
    ];
    
    $result = getInfluencers($page, $per_page, $filters);
    $influencers = $result['data'];
    $total_pages = $result['total_pages'];
    $total_count = $result['total'];
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">Gestione Influencer</h1>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Aggiungi Influencer
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Filtri</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <input type="hidden" name="action" value="list">
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome o email...">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Attivi</option>
                                    <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inattivi</option>
                                    <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Sospesi</option>
                                    <option value="blocked" <?php echo $filters['status'] === 'blocked' ? 'selected' : ''; ?>>Bloccati</option>
                                    <option value="deleted" <?php echo $filters['status'] === 'deleted' ? 'selected' : ''; ?>>Eliminati</option>
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
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary w-100">Applica</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Influencer -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Lista Influencer 
                            <span class="badge bg-secondary"><?php echo $total_count; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($influencers)): ?>
                            <div class="alert alert-info">Nessun influencer trovato</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Stato</th>
                                            <th>Data Registrazione</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($influencers as $influencer): ?>
                                            <tr>
                                                <td><?php echo $influencer['id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($influencer['avatar'])): ?>
                                                            <img src="<?php echo htmlspecialchars($influencer['avatar']); ?>" 
                                                                 class="rounded-circle me-2" width="32" height="32" 
                                                                 alt="<?php echo htmlspecialchars($influencer['name']); ?>">
                                                        <?php else: ?>
                                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                                 style="width: 32px; height: 32px;">
                                                                <i class="fas fa-user text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($influencer['name']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($influencer['email']); ?></td>
                                                <td>
                                                    <?php 
                                                    $status_badge = '';
                                                    if ($influencer['deleted_at']) {
                                                        $status_badge = '<span class="badge bg-dark">Eliminato</span>';
                                                    } elseif ($influencer['is_blocked']) {
                                                        $status_badge = '<span class="badge bg-danger">Bloccato</span>';
                                                    } elseif ($influencer['is_suspended']) {
                                                        $status_badge = '<span class="badge bg-warning">Sospeso</span>';
                                                    } elseif ($influencer['is_active']) {
                                                        $status_badge = '<span class="badge bg-success">Attivo</span>';
                                                    } else {
                                                        $status_badge = '<span class="badge bg-secondary">Inattivo</span>';
                                                    }
                                                    echo $status_badge;
                                                    ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($influencer['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?action=edit&id=<?php echo $influencer['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Modifica">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if ($influencer['deleted_at']): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                <input type="hidden" name="action_type" value="restore">
                                                                <button type="submit" class="btn btn-outline-success" title="Ripristina">
                                                                    <i class="fas fa-undo"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <?php if ($influencer['is_suspended']): ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="unsuspend">
                                                                    <button type="submit" class="btn btn-outline-warning" title="Rimuovi sospensione">
                                                                        <i class="fas fa-play"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-outline-warning" 
                                                                        data-bs-toggle="modal" data-bs-target="#suspendModal<?php echo $influencer['id']; ?>"
                                                                        title="Sospendi">
                                                                    <i class="fas fa-pause"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($influencer['is_blocked']): ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="unblock">
                                                                    <button type="submit" class="btn btn-outline-info" title="Sblocca">
                                                                        <i class="fas fa-unlock"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="block">
                                                                    <button type="submit" class="btn btn-outline-danger" 
                                                                            onclick="return confirm('Sei sicuro di voler bloccare questo influencer?')"
                                                                            title="Blocca">
                                                                        <i class="fas fa-ban"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                <input type="hidden" name="action_type" value="delete">
                                                                <button type="submit" class="btn btn-outline-dark" 
                                                                        onclick="return confirm('Sei sicuro di voler eliminare questo influencer?')"
                                                                        title="Elimina">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Modal Sospensione -->
                                                    <?php if (!$influencer['deleted_at'] && !$influencer['is_suspended']): ?>
                                                    <div class="modal fade" id="suspendModal<?php echo $influencer['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post">
                                                                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="suspend">
                                                                    
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Sospendi Influencer</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="suspension_end<?php echo $influencer['id']; ?>" class="form-label">
                                                                                Data fine sospensione
                                                                            </label>
                                                                            <input type="datetime-local" class="form-control" 
                                                                                   id="suspension_end<?php echo $influencer['id']; ?>" 
                                                                                   name="suspension_end" required>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                                        <button type="submit" class="btn btn-warning">Conferma Sospensione</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginazione -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Paginazione">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=1<?php echo buildQueryString($filters); ?>">Prima</a>
                                    </li>
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $page - 1; ?><?php echo buildQueryString($filters); ?>">Prec</a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=list&page=<?php echo $i; ?><?php echo buildQueryString($filters); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $page + 1; ?><?php echo buildQueryString($filters); ?>">Succ</a>
                                    </li>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $total_pages; ?><?php echo buildQueryString($filters); ?>">Ultima</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}

// Pagina aggiungi/modifica influencer
elseif ($action === 'add' || $action === 'edit') {
    $influencer = null;
    $title = $action === 'add' ? 'Aggiungi Influencer' : 'Modifica Influencer';
    
    if ($action === 'edit' && $id) {
        $influencer = getInfluencerById($id);
        if (!$influencer) {
            header('Location: influencers.php');
            exit;
        }
    }
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3"><?php echo $title; ?></h1>
                    <a href="?action=list" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna alla lista
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($influencer['name'] ?? ''); ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($influencer['email'] ?? ''); ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo ($influencer['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Account attivo</label>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="save_influencer" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salva
                                </button>
                                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}

require_once '../includes/admin_footer.php';
?>