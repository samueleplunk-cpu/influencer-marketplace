<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE UTENTE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /infl/auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// RECUPERO DATI BRAND ESISTENTI
// =============================================
$brand = null;
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo brand: " . $e->getMessage();
}

// =============================================
// VERIFICA SE LA COLONNA LOGO ESISTE
// =============================================
$logo_column_exists = false;
try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM brands LIKE 'logo'");
    $stmt->execute();
    $logo_column_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    // Se c'è un errore, assumiamo che la colonna non esista
    $logo_column_exists = false;
}

// =============================================
// ELENCO SETTORI
// =============================================
$industries = [
    'Fashion',
    'Lifestyle',
    'Beauty & Makeup',
    'Food',
    'Travel',
    'Gaming',
    'Fitness & Wellness',
    'Entertainment',
    'Tech',
    'Finance & Business',
    'Pet',
    'Education'
];

// =============================================
// GESTIONE INVIO FORM
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitizzazione input
        $company_name = trim($_POST['company_name']);
        $industry = $_POST['industry'];
        $website = trim($_POST['website']);
        $description = trim($_POST['description']);
        
        // Validazione
        if (empty($company_name)) {
            throw new Exception("Il nome dell'azienda è obbligatorio");
        }

        if (empty($industry)) {
            throw new Exception("Il settore è obbligatorio");
        }

        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            throw new Exception("Inserisci un URL valido per il sito web");
        }

        // Gestione upload logo (solo se la colonna esiste)
        $logo_path = $brand['logo'] ?? null;
        
        if ($logo_column_exists && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_file = $_FILES['logo'];
            
            // Validazione file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($logo_file['type'], $allowed_types)) {
                throw new Exception("Formato file non supportato. Usa JPG, PNG o GIF.");
            }
            
            if ($logo_file['size'] > $max_size) {
                throw new Exception("Il file è troppo grande. Dimensione massima: 5MB.");
            }
            
            // Crea directory se non esiste
            $upload_dir = dirname(__DIR__) . '/uploads/brands/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Genera nome file univoco
            $file_extension = pathinfo($logo_file['name'], PATHINFO_EXTENSION);
            $filename = 'brand_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $logo_path = 'uploads/brands/' . $filename;
            $full_path = dirname(__DIR__) . '/' . $logo_path;
            
            // Sposta file
            if (!move_uploaded_file($logo_file['tmp_name'], $full_path)) {
                throw new Exception("Errore nel caricamento del file.");
            }
            
            // Elimina vecchio logo se esiste
            if (!empty($brand['logo']) && file_exists(dirname(__DIR__) . '/' . $brand['logo'])) {
                unlink(dirname(__DIR__) . '/' . $brand['logo']);
            }
        }
        
        // Gestione rimozione logo (solo se la colonna esiste)
        if ($logo_column_exists && isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1' && !empty($brand['logo'])) {
            if (file_exists(dirname(__DIR__) . '/' . $brand['logo'])) {
                unlink(dirname(__DIR__) . '/' . $brand['logo']);
            }
            $logo_path = null;
        }
        
        // Costruisci query dinamica in base alle colonne disponibili
        $update_fields = [
            "company_name = ?",
            "industry = ?", 
            "website = ?",
            "description = ?",
            "updated_at = NOW()"
        ];
        
        $update_params = [
            $company_name,
            $industry,
            $website,
            $description
        ];
        
        // Aggiungi logo solo se la colonna esiste
        if ($logo_column_exists) {
            $update_fields[] = "logo = ?";
            $update_params[] = $logo_path;
        }
        
        $update_params[] = $_SESSION['user_id'];
        
        $update_query = "UPDATE brands SET " . implode(', ', $update_fields) . " WHERE user_id = ?";
        
        // Aggiornamento nel database
        $stmt = $pdo->prepare($update_query);
        $stmt->execute($update_params);

        $success = "Profilo aggiornato con successo!";
        
        // Ricarica i dati del brand
        $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $brand = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Modifica Profilo Brand</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Modifica Profilo -->
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="row">
                        <!-- Informazioni Base -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Nome Azienda *</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($brand['company_name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="industry" class="form-label">Settore *</label>
                                <select class="form-select" id="industry" name="industry" required>
                                    <option value="">Seleziona un settore</option>
                                    <?php foreach ($industries as $industry_option): ?>
                                        <option value="<?php echo htmlspecialchars($industry_option); ?>" 
                                                <?php echo (($brand['industry'] ?? '') === $industry_option) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($industry_option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="website" class="form-label">Sito Web</label>
                                <input type="url" class="form-control" id="website" name="website" 
                                       value="<?php echo htmlspecialchars($brand['website'] ?? ''); ?>" 
                                       placeholder="https://example.com">
                            </div>
                        </div>

                        <!-- Upload Logo (solo se la colonna esiste) -->
                        <?php if ($logo_column_exists): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Logo Aziendale</label>
                                
                                <!-- Area Upload Logo -->
                                <div id="logoUploadArea">
                                    <!-- Logo attuale -->
                                    <?php if (!empty($brand['logo'])): ?>
                                        <div id="currentLogoSection" class="mb-3">
                                            <p class="text-muted">Logo attuale:</p>
                                            <img src="/infl/<?php echo htmlspecialchars($brand['logo']); ?>" 
                                                 alt="Logo attuale" 
                                                 class="img-thumbnail mb-2" 
                                                 style="max-height: 150px;">
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="changeLogoBtn">
                                                    📝 Cambia Immagine
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" id="removeCurrentLogoBtn">
                                                    🗑️ Rimuovi Immagine
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Se non c'è logo, mostra pulsante per caricare -->
                                        <div id="noLogoSection" class="mb-3">
                                            <div class="alert alert-info">
                                                <small>Nessun logo caricato. Gli influencer vedranno un'immagine predefinita.</small>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary" id="uploadLogoBtn">
                                                📁 Scegli Immagine
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Input file nascosto -->
                                    <input type="file" class="form-control d-none" id="logo" name="logo" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif">
                                    
                                    <!-- Anteprima nuovo logo con controlli -->
                                    <div id="logoPreviewContainer" class="mt-2" style="display: none;">
                                        <p class="text-muted">Anteprima nuovo logo:</p>
                                        <div class="position-relative d-inline-block">
                                            <img id="previewImage" class="img-thumbnail" style="max-height: 150px;">
                                        </div>
                                        <div class="d-flex gap-2 mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="changeImageBtn">
                                                📝 Cambia Immagine
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" id="removeImageBtn">
                                                🗑️ Rimuovi Immagine
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-text">
                                    Formati supportati: JPG, PNG, GIF. Dimensione massima: 5MB.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Descrizione Azienda -->
                    <div class="mb-4">
                        <label for="description" class="form-label">Descrizione Azienda</label>
                        <textarea class="form-control" id="description" name="description" rows="6" 
                                  placeholder="Descrivi la tua azienda, la tua mission, i tuoi valori..."><?php echo htmlspecialchars($brand['description'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Una buona descrizione aiuta gli influencer a comprendere meglio la tua azienda e aumenta le possibilità di collaborazione.
                        </div>
                    </div>

                    <!-- Pulsanti -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            💾 Aggiorna Profilo
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">Annulla</a>
                    </div>
                    
                    <!-- Campo hidden per rimozione logo -->
                    <?php if ($logo_column_exists): ?>
                    <input type="hidden" name="remove_logo" id="removeLogoField" value="0">
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Informazioni Profilo -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Informazioni Profilo</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Ultimo aggiornamento:</strong> 
                            <span class="float-end">
                                <?php echo !empty($brand['updated_at']) ? date('d/m/Y H:i', strtotime($brand['updated_at'])) : 'Mai'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Profilo creato:</strong> 
                            <span class="float-end">
                                <?php echo !empty($brand['created_at']) ? date('d/m/Y H:i', strtotime($brand['created_at'])) : 'Data non disponibile'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php if ($logo_column_exists && !empty($brand['logo'])): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-2">
                            <strong>Logo:</strong> 
                            <span class="float-end badge bg-success">Caricato</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <hr>
                <small class="text-muted">
                    <strong>Suggerimento:</strong> Mantieni il tuo profilo sempre aggiornato per attrarre gli influencer più adatti alla tua azienda.
                </small>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profileForm');
    const logoInput = document.getElementById('logo');
    const logoPreviewContainer = document.getElementById('logoPreviewContainer');
    const previewImage = document.getElementById('previewImage');
    const removeLogoField = document.getElementById('removeLogoField');
    const currentLogoSection = document.getElementById('currentLogoSection');
    const noLogoSection = document.getElementById('noLogoSection');
    
    // Elementi dei pulsanti
    const changeLogoBtn = document.getElementById('changeLogoBtn');
    const removeCurrentLogoBtn = document.getElementById('removeCurrentLogoBtn');
    const uploadLogoBtn = document.getElementById('uploadLogoBtn');
    const changeImageBtn = document.getElementById('changeImageBtn');
    const removeImageBtn = document.getElementById('removeImageBtn');
    
    // Gestione click su "Scegli Immagine" (quando non c'è logo)
    if (uploadLogoBtn) {
        uploadLogoBtn.addEventListener('click', function() {
            logoInput.click();
        });
    }
    
    // Gestione click su "Cambia Immagine" (logo attuale)
    if (changeLogoBtn) {
        changeLogoBtn.addEventListener('click', function() {
            logoInput.click();
        });
    }
    
    // Gestione click su "Rimuovi Immagine" (logo attuale)
    if (removeCurrentLogoBtn) {
        removeCurrentLogoBtn.addEventListener('click', function() {
            if (confirm('Sei sicuro di voler rimuovere il logo attuale?')) {
                removeLogoField.value = '1';
                // Nascondi la sezione del logo attuale
                if (currentLogoSection) {
                    currentLogoSection.style.display = 'none';
                }
                // Mostra la sezione senza logo
                showNoLogoSection();
            }
        });
    }
    
    // Gestione click su "Cambia Immagine" (anteprima)
    if (changeImageBtn) {
        changeImageBtn.addEventListener('click', function() {
            logoInput.click();
        });
    }
    
    // Gestione click su "Rimuovi Immagine" (anteprima)
    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', function() {
            resetLogoInput();
            // Se c'era un logo attuale, ripristinalo
            if (currentLogoSection && removeLogoField.value === '0') {
                logoPreviewContainer.style.display = 'none';
                currentLogoSection.style.display = 'block';
            } else {
                logoPreviewContainer.style.display = 'none';
                showNoLogoSection();
            }
        });
    }
    
    // Gestione cambio file
    if (logoInput) {
        logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validazione client-side del file
                const maxSize = 5 * 1024 * 1024; // 5MB
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Formato file non supportato. Usa JPG, PNG o GIF.');
                    resetLogoInput();
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('Il file è troppo grande. Dimensione massima: 5MB.');
                    resetLogoInput();
                    return;
                }
                
                // Mostra anteprima
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    logoPreviewContainer.style.display = 'block';
                    
                    // Nascondi le altre sezioni
                    if (currentLogoSection) {
                        currentLogoSection.style.display = 'none';
                    }
                    if (noLogoSection) {
                        noLogoSection.style.display = 'none';
                    }
                    
                    // Reset del campo remove_logo se stiamo caricando una nuova immagine
                    removeLogoField.value = '0';
                }
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Funzione per resettare l'input file
    function resetLogoInput() {
        if (logoInput) {
            logoInput.value = '';
        }
        removeLogoField.value = '0';
    }
    
    // Funzione per mostrare la sezione senza logo
    function showNoLogoSection() {
        if (noLogoSection) {
            noLogoSection.style.display = 'block';
        } else {
            // Se non esiste, creala dinamicamente
            const logoUploadArea = document.getElementById('logoUploadArea');
            const newNoLogoSection = document.createElement('div');
            newNoLogoSection.id = 'noLogoSection';
            newNoLogoSection.className = 'mb-3';
            newNoLogoSection.innerHTML = `
                <div class="alert alert-info">
                    <small>Nessun logo caricato. Gli influencer vedranno un'immagine predefinita.</small>
                </div>
                <button type="button" class="btn btn-outline-primary" id="uploadLogoBtn">
                    📁 Scegli Immagine
                </button>
            `;
            logoUploadArea.appendChild(newNoLogoSection);
            
            // Re-attach event listener al nuovo pulsante
            document.getElementById('uploadLogoBtn').addEventListener('click', function() {
                logoInput.click();
            });
        }
        logoPreviewContainer.style.display = 'none';
    }
    
    // Validazione client-side del form
    form.addEventListener('submit', function(e) {
        const companyName = document.getElementById('company_name').value.trim();
        const industry = document.getElementById('industry').value;
        
        if (!companyName) {
            e.preventDefault();
            alert('Il nome dell\'azienda è obbligatorio');
            document.getElementById('company_name').focus();
            return false;
        }
        
        if (!industry) {
            e.preventDefault();
            alert('Il settore è obbligatorio');
            document.getElementById('industry').focus();
            return false;
        }
        
        // Validazione file se selezionato
        if (logoInput && logoInput.files.length > 0) {
            const file = logoInput.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                alert('Formato file non supportato. Usa JPG, PNG o GIF.');
                return false;
            }
            
            if (file.size > maxSize) {
                e.preventDefault();
                alert('Il file è troppo grande. Dimensione massima: 5MB.');
                return false;
            }
        }
    });
    
    // Validazione URL in tempo reale
    const websiteInput = document.getElementById('website');
    if (websiteInput) {
        websiteInput.addEventListener('blur', function() {
            const url = this.value.trim();
            if (url && !isValidUrl(url)) {
                this.classList.add('is-invalid');
                // Aggiungi messaggio di errore
                if (!this.nextElementSibling || !this.nextElementSibling.classList.contains('invalid-feedback')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'Inserisci un URL valido (es. https://example.com)';
                    this.parentNode.appendChild(errorDiv);
                }
            } else {
                this.classList.remove('is-invalid');
                // Rimuovi messaggio di errore
                const errorDiv = this.nextElementSibling;
                if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                    errorDiv.remove();
                }
            }
        });
    }
    
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
});
</script>

<style>
.progress {
    height: 20px;
}
.progress-bar {
    font-weight: bold;
}
.alert-info {
    border-left: 4px solid #0dcaf0;
}
.card-header.bg-light {
    background-color: #f8f9fa !important;
    border-bottom: 1px solid #dee2e6;
}
.form-text small {
    font-size: 0.875em;
}
.img-thumbnail {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.25rem;
}
.btn-sm {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
}
</style>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>