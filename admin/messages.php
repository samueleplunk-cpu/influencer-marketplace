<?php
require_once '../includes/admin_header.php';

// Numero di conversazioni per pagina
$per_page = 25;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Recupera i valori dei filtri
$brand_filter = isset($_GET['brand_filter']) ? trim($_GET['brand_filter']) : '';
$influencer_filter = isset($_GET['influencer_filter']) ? trim($_GET['influencer_filter']) : '';
$campaign_filter = isset($_GET['campaign_filter']) ? trim($_GET['campaign_filter']) : '';

// Ottieni tutte le conversazioni senza filtri (per filtrare dopo)
try {
    // Query originale senza filtri
    $sql = "
        SELECT 
            c.id as conversation_id,
            c.created_at as conversation_created,
            c.updated_at as last_message_time,
            c.brand_id,
            c.influencer_id,
            c.campaign_id,
            
            -- Brand info
            COALESCE(
                (SELECT b.company_name FROM brands b WHERE b.id = c.brand_id LIMIT 1),
                (SELECT u.name FROM users u JOIN brands b ON u.id = b.user_id WHERE b.id = c.brand_id LIMIT 1),
                (SELECT u.company_name FROM users u JOIN brands b ON u.id = b.user_id WHERE b.id = c.brand_id LIMIT 1),
                CONCAT('Brand #', c.brand_id)
            ) as brand_name,
            
            -- Brand user_id
            (SELECT u.id FROM users u JOIN brands b ON u.id = b.user_id WHERE b.id = c.brand_id LIMIT 1) as brand_user_id,
            
            -- Influencer info
            COALESCE(
                (SELECT i.full_name FROM influencers i WHERE i.id = c.influencer_id LIMIT 1),
                (SELECT u.name FROM users u JOIN influencers i ON u.id = i.user_id WHERE i.id = c.influencer_id LIMIT 1),
                CONCAT('Influencer #', c.influencer_id)
            ) as influencer_name,
            
            -- Influencer user_id
            (SELECT u.id FROM users u JOIN influencers i ON u.id = i.user_id WHERE i.id = c.influencer_id LIMIT 1) as influencer_user_id,
            
            -- Campagna
            COALESCE(
                (SELECT name FROM campaigns WHERE id = c.campaign_id LIMIT 1),
                'Nessuna campagna'
            ) as campaign_name,
            
            -- Conta messaggi totali
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) as total_messages,
            
            -- Conta messaggi non letti
            (SELECT COUNT(*) FROM messages m 
             WHERE m.conversation_id = c.id 
             AND m.is_read = 0 
             AND m.sender_type = 'influencer') as unread_by_brand,
             
            (SELECT COUNT(*) FROM messages m 
             WHERE m.conversation_id = c.id 
             AND m.is_read = 0 
             AND m.sender_type = 'brand') as unread_by_influencer
            
        FROM conversations c
        ORDER BY c.updated_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $all_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $all_conversations = [];
    error_log("Errore recupero conversazioni: " . $e->getMessage());
}

// Applica i filtri localmente
$filtered_conversations = $all_conversations;

if ($brand_filter !== '' || $influencer_filter !== '' || $campaign_filter !== '') {
    $filtered_conversations = array_filter($all_conversations, function($conv) use ($brand_filter, $influencer_filter, $campaign_filter) {
        $matches = false;
        
        // Applica filtri in OR
        if ($brand_filter !== '') {
            $brand_name = strtolower($conv['brand_name']);
            if (stripos($brand_name, strtolower($brand_filter)) !== false) {
                $matches = true;
            }
        }
        
        if (!$matches && $influencer_filter !== '') {
            $influencer_name = strtolower($conv['influencer_name']);
            if (stripos($influencer_name, strtolower($influencer_filter)) !== false) {
                $matches = true;
            }
        }
        
        if (!$matches && $campaign_filter !== '') {
            $campaign_name = strtolower($conv['campaign_name']);
            $search_term = strtolower($campaign_filter);
            
            if ($campaign_filter === 'Nessuna campagna') {
                if ($campaign_name === 'nessuna campagna') {
                    $matches = true;
                }
            } else {
                if (stripos($campaign_name, $search_term) !== false) {
                    $matches = true;
                }
            }
        }
        
        // Se nessun filtro è specificato, mostra tutto
        if ($brand_filter === '' && $influencer_filter === '' && $campaign_filter === '') {
            $matches = true;
        }
        
        return $matches;
    });
    
    // Reindex array
    $filtered_conversations = array_values($filtered_conversations);
}

// Calcola conteggio totale
$total_conversations = count($filtered_conversations);

// Calcola paginazione
$total_pages = ceil($total_conversations / $per_page);

// Prendi solo le conversazioni per la pagina corrente
$start_index = ($page - 1) * $per_page;
$conversations = array_slice($filtered_conversations, $start_index, $per_page);

// Funzione per generare URL con i filtri mantenuti
function buildUrlWithFilters($page = null) {
    $params = [];
    
    if ($page !== null) {
        $params['page'] = $page;
    }
    
    if (isset($_GET['brand_filter']) && $_GET['brand_filter'] !== '') {
        $params['brand_filter'] = $_GET['brand_filter'];
    }
    
    if (isset($_GET['influencer_filter']) && $_GET['influencer_filter'] !== '') {
        $params['influencer_filter'] = $_GET['influencer_filter'];
    }
    
    if (isset($_GET['campaign_filter']) && $_GET['campaign_filter'] !== '') {
        $params['campaign_filter'] = $_GET['campaign_filter'];
    }
    
    return !empty($params) ? '?' . http_build_query($params) : '';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestione Messaggi</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <span class="text-muted me-3">
                <i class="fas fa-comments me-1"></i>
                <?php echo $total_conversations; ?> conversazioni
            </span>
        </div>
    </div>
</div>

<!-- Filtri di ricerca -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Filtra conversazioni</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <!-- Brand Filter (20%) -->
            <div class="col-md-2 col-lg-2">
                <label for="brand_filter" class="form-label">
                    <i class="fas fa-building me-1"></i> Brand
                </label>
                <input type="text" 
                       class="form-control" 
                       id="brand_filter" 
                       name="brand_filter" 
                       value="<?php echo htmlspecialchars($brand_filter); ?>"
                       placeholder="Cerca brand...">
            </div>
            
            <!-- Influencer Filter (20%) -->
            <div class="col-md-2 col-lg-2">
                <label for="influencer_filter" class="form-label">
                    <i class="fas fa-user-check me-1"></i> Influencer
                </label>
                <input type="text" 
                       class="form-control" 
                       id="influencer_filter" 
                       name="influencer_filter" 
                       value="<?php echo htmlspecialchars($influencer_filter); ?>"
                       placeholder="Cerca influencer...">
            </div>
            
            <!-- Campaign Filter (20%) -->
            <div class="col-md-2 col-lg-2">
                <label for="campaign_filter" class="form-label">
                    <i class="fas fa-bullhorn me-1"></i> Campagna
                </label>
                <input type="text" 
                       class="form-control" 
                       id="campaign_filter" 
                       name="campaign_filter" 
                       value="<?php echo htmlspecialchars($campaign_filter); ?>"
                       placeholder="Cerca campagna...">
            </div>
            
            <!-- Apply Filters Button (20%) -->
            <div class="col-md-2 col-lg-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i> Applica Filtri
                </button>
            </div>
            
            <!-- Reset Filters Button (20%) -->
            <div class="col-md-2 col-lg-2">
                <a href="?page=1" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-redo me-1"></i> Reset Filtri
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabella conversazioni -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Conversazioni Brand ↔ Influencer</h5>
            <?php if ($total_conversations > $per_page): ?>
                <span class="badge bg-primary">
                    Pagina <?php echo $page; ?> di <?php echo $total_pages; ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($conversations)): ?>
            <div class="text-center py-5">
                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                <h4>Nessuna conversazione trovata</h4>
                <p class="text-muted">
                    <?php if ($brand_filter !== '' || $influencer_filter !== '' || $campaign_filter !== ''): ?>
                        Prova a modificare i criteri di ricerca.
                    <?php else: ?>
                        Non ci sono ancora conversazioni tra brand e influencer.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Brand</th>
                            <th>Influencer</th>
                            <th>Campagna</th>
                            <th class="text-center">Ultimo messaggio</th>
                            <th class="text-center">Messaggi</th>
                            <th class="text-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversations as $conv): ?>
                            <?php 
                            // Calcola messaggi non letti totali
                            $unread_total = max(
                                intval($conv['unread_by_brand'] ?? 0),
                                intval($conv['unread_by_influencer'] ?? 0)
                            );
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2">
                                            <i class="fas fa-building text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <?php if ($conv['brand_user_id']): ?>
                                                <a href="/infl/brand/profile.php?id=<?php echo htmlspecialchars($conv['brand_user_id']); ?>" 
                                                   target="_blank" 
                                                   class="text-decoration-none fw-bold">
                                                    <?php echo htmlspecialchars($conv['brand_name']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="fw-bold">
                                                    <?php echo htmlspecialchars($conv['brand_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2">
                                            <i class="fas fa-user-check text-success"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <?php if ($conv['influencer_user_id']): ?>
                                                <a href="/infl/influencer/profile.php?id=<?php echo htmlspecialchars($conv['influencer_user_id']); ?>" 
                                                   target="_blank" 
                                                   class="text-decoration-none fw-bold">
                                                    <?php echo htmlspecialchars($conv['influencer_name']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="fw-bold">
                                                    <?php echo htmlspecialchars($conv['influencer_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <?php if ($conv['campaign_name'] && $conv['campaign_name'] !== 'Nessuna campagna'): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-2">
                                                <i class="fas fa-bullhorn text-warning"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="fw-medium">
                                                    <?php echo htmlspecialchars($conv['campaign_name']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted">
                                            <small><em>Nessuna campagna</em></small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center">
                                    <div class="text-nowrap">
                                        <?php if ($conv['last_message_time']): ?>
                                            <i class="far fa-clock me-1 text-muted"></i>
                                            <?php echo date('d/m/Y - H:i', strtotime($conv['last_message_time'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td class="text-center">
                                    <span class="badge bg-info rounded-pill px-3 py-2">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($conv['total_messages']); ?>
                                    </span>
                                </td>
                                
                                <td class="text-center">
                                    <a href="/infl/admin/conversation.php?id=<?php echo htmlspecialchars($conv['conversation_id']); ?>" 
                                       class="btn btn-sm btn-primary px-3"
                                       title="Visualizza conversazione">
                                        <i class="fas fa-eye me-1"></i> Visualizza
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Paginazione" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Prima pagina -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildUrlWithFilters(1); ?>" aria-label="Prima">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        
                        <!-- Pagina precedente -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildUrlWithFilters($page - 1); ?>" aria-label="Precedente">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <!-- Pagine numerate -->
                        <?php 
                        // Mostra massimo 5 pagine intorno a quella corrente
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo buildUrlWithFilters($i); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Pagina successiva -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildUrlWithFilters($page + 1); ?>" aria-label="Successiva">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        
                        <!-- Ultima pagina -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildUrlWithFilters($total_pages); ?>" aria-label="Ultima">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Info paginazione -->
                    <div class="text-center mt-2">
                        <small class="text-muted">
                            Mostrando <?php echo count($conversations); ?> di <?php echo $total_conversations; ?> conversazioni
                            <?php if ($brand_filter !== '' || $influencer_filter !== '' || $campaign_filter !== ''): ?>
                                (filtrate)
                            <?php endif; ?>
                        </small>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.table th {
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
}

.table th:first-child,
.table th:nth-child(2),
.table th:nth-child(3) {
    text-align: left;
}

.table td {
    vertical-align: middle;
}

.badge.rounded-pill {
    min-width: 70px;
}

.message-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* Distribuzione delle colonne secondo le specifiche */
.table.table-hover.table-striped {
    table-layout: fixed;
    width: 100%;
}

.table.table-hover.table-striped th:nth-child(1),
.table.table-hover.table-striped td:nth-child(1) {
    width: 20%; /* BRAND */
}

.table.table-hover.table-striped th:nth-child(2),
.table.table-hover.table-striped td:nth-child(2) {
    width: 20%; /* INFLUENCER */
}

.table.table-hover.table-striped th:nth-child(3),
.table.table-hover.table-striped td:nth-child(3) {
    width: 20%; /* CAMPAGNA */
}

.table.table-hover.table-striped th:nth-child(4),
.table.table-hover.table-striped td:nth-child(4) {
    width: 20%; /* ULTIMO MESSAGGIO */
    text-align: center;
}

.table.table-hover.table-striped th:nth-child(5),
.table.table-hover.table-striped td:nth-child(5) {
    width: 10%; /* MESSAGGI */
    text-align: center;
}

.table.table-hover.table-striped th:nth-child(6),
.table.table-hover.table-striped td:nth-child(6) {
    width: 10%; /* AZIONI */
    text-align: center;
}

/* Per dispositivi mobili */
@media (max-width: 768px) {
    .table.table-hover.table-striped {
        table-layout: auto;
    }
    
    .table.table-hover.table-striped th,
    .table.table-hover.table-striped td {
        width: auto;
    }
    
    .table th,
    .table td {
        font-size: 0.85rem;
    }
    
    .badge.rounded-pill {
        padding: 0.2rem 0.4rem;
        font-size: 0.8rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
}
</style>

<?php require_once '../includes/admin_footer.php'; ?>