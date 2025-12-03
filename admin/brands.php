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
    if (isset($_POST['save_brand'])) {
        $data = [
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if (empty($data['name']) || empty($data['email'])) {
            $message = '<div class="alert alert-danger">Nome ed email sono obbligatori</div>';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Email non valida</div>';
        } else {
            $success = saveBrand($data, $id);
            if ($success) {
                $message = '<div class="alert alert-success">Brand salvato con successo!</div>';
                if (!$id) {
                    $action = 'list';
                }
            } else {
                $message = '<div class="alert alert-danger">Errore nel salvataggio</div>';
            }
        }
    }
    
    // Gestione azioni rapide
    if (isset($_POST['action_type']) && isset($_POST['brand_id'])) {
        $brand_id = $_POST['brand_id'];
        $action_type = $_POST['action_type'];
        
        switch($action_type) {
            case 'suspend':
                $suspension_end = $_POST['suspension_end'] ?? null;
                updateBrandStatus($brand_id, 'suspend', $suspension_end);
                $message = '<div class="alert alert-warning">Brand sospeso</div>';
                break;
                
            case 'unsuspend':
                updateBrandStatus($brand_id, 'unsuspend');
                $message = '<div class="alert alert-success">Sospensione rimossa</div>';
                break;
                
            case 'block':
                updateBrandStatus($brand_id, 'block');
                $message = '<div class="alert alert-danger">Brand bloccato</div>';
                break;
                
            case 'unblock':
                updateBrandStatus($brand_id, 'unblock');
                $message = '<div class="alert alert-success">Blocco rimosso</div>';
                break;
                
            case 'delete':
                updateBrandStatus($brand_id, 'delete');
                $message = '<div class="alert alert-info">Brand eliminato</div>';
                break;
                
            case 'restore':
                updateBrandStatus($brand_id, 'restore');
                $message = '<div class="alert alert-success">Brand ripristinato</div>';
                break;
        }
    }
}

// Pagina lista brands
if ($action === 'list') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 15;
    
    $filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];
    
    $result = getBrands($page, $per_page, $filters);
    $brands = $result['data'];
    $total_pages = $result['total_pages'];
    $total_count = $result['total'];
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">Gestione Brands</h1>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Aggiungi Brand
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Filtri -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Filtri</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="list">
            
            <div class="col-md-4">
                <label for="search" class="form-label">Cerca</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                       placeholder="Nome, email o azienda...">
            </div>
            
            <div class="col-md-4">
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
            
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill">Applica</button>
                <a href="?action=list" class="btn btn-outline-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>
</div>
                
                <!-- Tabella Brands -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Lista Brands 
                            <span class="badge bg-secondary"><?php echo $total_count; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($brands)): ?>
                            <div class="alert alert-info">Nessun brand trovato</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Azienda</th> <!-- MODIFICATO: da "Nome" a "Azienda" -->
                                            <th>Email</th>
                                            <th>Stato</th>
                                            <th>Data Registrazione</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($brands as $brand): ?>
                                            <tr>
                                                <td><?php echo $brand['id']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($brand['name']); ?> <!-- MODIFICATO: solo nome testuale -->
                                                </td>
                                                <td><?php echo htmlspecialchars($brand['email']); ?></td>
                                                <td>
                                                    <?php 
                                                    $status_badge = '';
                                                    if ($brand['deleted_at']) {
                                                        $status_badge = '<span class="badge bg-dark">Eliminato</span>';
                                                    } elseif ($brand['is_blocked']) {
                                                        $status_badge = '<span class="badge bg-danger">Bloccato</span>';
                                                    } elseif ($brand['is_suspended']) {
                                                        $status_badge = '<span class="badge bg-warning">Sospeso</span>';
                                                    } elseif ($brand['is_active']) {
                                                        $status_badge = '<span class="badge bg-success">Attivo</span>';
                                                    } else {
                                                        $status_badge = '<span class="badge bg-secondary">Inattivo</span>';
                                                    }
                                                    echo $status_badge;
                                                    ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($brand['created_at'])); ?></td>
                                                <td>
                                                    <div class="d-flex gap-1 flex-wrap">
        <a href="?action=edit&id=<?php echo $brand['id']; ?>" 
           class="btn btn-outline-primary btn-sm" title="Modifica">
            <i class="fas fa-edit"></i>
        </a>
        
        <?php if ($brand['deleted_at']): ?>
            <form method="post" class="d-inline">
                <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                <input type="hidden" name="action_type" value="restore">
                <button type="submit" class="btn btn-outline-success btn-sm" title="Ripristina">
                    <i class="fas fa-undo"></i>
                </button>
            </form>
        <?php else: ?>
            <?php if ($brand['is_suspended']): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                    <input type="hidden" name="action_type" value="unsuspend">
                    <button type="submit" class="btn btn-outline-warning btn-sm" title="Rimuovi sospensione">
                        <i class="fas fa-play"></i>
                    </button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn-outline-warning btn-sm" 
                        data-bs-toggle="modal" data-bs-target="#suspendModal<?php echo $brand['id']; ?>"
                        title="Sospendi">
                    <i class="fas fa-pause"></i>
                </button>
            <?php endif; ?>
            
            <?php if ($brand['is_blocked']): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                    <input type="hidden" name="action_type" value="unblock">
                    <button type="submit" class="btn btn-outline-info btn-sm" title="Sblocca">
                        <i class="fas fa-unlock"></i>
                    </button>
                </form>
            <?php else: ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                    <input type="hidden" name="action_type" value="block">
                    <button type="submit" class="btn btn-outline-danger btn-sm" 
                            onclick="return confirm('Sei sicuro di voler bloccare questo brand?')"
                            title="Blocca">
                        <i class="fas fa-ban"></i>
                    </button>
                </form>
            <?php endif; ?>
            
            <form method="post" class="d-inline">
                <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                <input type="hidden" name="action_type" value="delete">
                <button type="submit" class="btn btn-outline-dark btn-sm" 
                        onclick="return confirm('Sei sicuro di voler eliminare questo brand?')"
                        title="Elimina">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
                                                    
                                                    <!-- Modal Sospensione -->
                                                    <?php if (!$brand['deleted_at'] && !$brand['is_suspended']): ?>
                                                    <div class="modal fade" id="suspendModal<?php echo $brand['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post">
                                                                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="suspend">
                                                                    
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Sospendi Brand</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="suspension_end<?php echo $brand['id']; ?>" class="form-label">
                                                                                Data fine sospensione
                                                                            </label>
                                                                            <input type="datetime-local" class="form-control" 
                                                                                   id="suspension_end<?php echo $brand['id']; ?>" 
                                                                                   name="suspension_end" required>
                                                                            <div class="form-text">
                                                                                Il brand non potrà accedere al sistema fino a questa data.
                                                                            </div>
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
                                    (Totale: <?php echo $total_count; ?> brands)
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
}

// Pagina aggiungi/modifica brand
elseif ($action === 'add' || $action === 'edit') {
    $brand = null;
    $title = $action === 'add' ? 'Aggiungi Brand' : 'Modifica Brand';
    
    if ($action === 'edit' && $id) {
        $brand = getBrandById($id);
        if (!$brand) {
            header('Location: brands.php');
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
                                        <label for="name" class="form-label">Nome Contatto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($brand['name'] ?? ''); ?>" 
                                               required>
                                        <div class="form-text">Il nome della persona di riferimento</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($brand['email'] ?? ''); ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">Nome Azienda</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="<?php echo htmlspecialchars($brand['company_name'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="website" class="form-label">Sito Web</label>
                                        <input type="url" class="form-control" id="website" name="website" 
                                               value="<?php echo htmlspecialchars($brand['website'] ?? ''); ?>"
                                               placeholder="https://">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4"><?php echo htmlspecialchars($brand['description'] ?? ''); ?></textarea>
                                <div class="form-text">Breve descrizione del brand e delle sue attività</div>
                            </div>
                            
                            <div class="mb-3 form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo ($brand['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Account attivo</label>
                                <div class="form-text">Se disattivato, il brand non potrà accedere al sistema</div>
                            </div>
                            
                            <?php if ($action === 'add'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                Alla creazione verrà generata una password temporanea che il brand potrà cambiare al primo accesso.
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="save_brand" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salva
                                </button>
                                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                                
                                <?php if ($action === 'edit' && $brand): ?>
                                <div class="ms-auto">
                                    <small class="text-muted">
                                        Creato il: <?php echo date('d/m/Y H:i', strtotime($brand['created_at'])); ?>
                                        <?php if ($brand['updated_at'] && $brand['updated_at'] != $brand['created_at']): ?>
                                            <br>Modificato il: <?php echo date('d/m/Y H:i', strtotime($brand['updated_at'])); ?>
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
}

require_once '../includes/admin_footer.php';
?>