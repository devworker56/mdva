<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// AJOUTER CE CODE POUR DÉBOGUER
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/functions.php';

// Include the QR code library - FIXED PATH (same as get_qr_as_base64.php)
require_once __DIR__ . '/qrlib/phpqrcode/phpqrcode.php';

$database = new Database();
$db = $database->getConnection();

// Gérer les soumissions de formulaire
if (isset($_POST['generate_single'])) {
    $module_id = $_POST['module_id'] ?? '';
    $barcode_data = $_POST['barcode_data'] ?? '';
    $type = $_POST['type'] ?? 'QRCODE';
    
    if (!empty($module_id)) {
        // Générer le code QR pour le module dans le format ESP32
        $module = getModuleWithLocation($module_id, $db);
        if ($module) {
            // Build location string in ESP32 format
            $location_string = $module['location_name'] ?: '';
            if ($module['address']) $location_string .= ', ' . $module['address'];
            if ($module['city']) $location_string .= ', ' . $module['city'];
            if ($module['province']) $location_string .= ', ' . $module['province'];
            if ($module['postal_code']) $location_string .= ' ' . $module['postal_code'];
            
            // Create QR data in ESP32 format
            $qr_data = [
                'module_id' => $module['module_id'],
                'module_name' => $module['name'],
                'location' => $location_string,
                'system' => 'MDVA',
                'type' => 'donation_module',
                'version' => '1.0',
                'timestamp' => time(),
                'url' => "https://systeme-mdva.com/module/" . urlencode($module['module_id'])
            ];
            
            $qr_data_json = json_encode($qr_data);
            
            // CHANGEMENT CRITIQUE 1 : Utiliser __DIR__ comme test_qr_browser.php
            $qr_dir = __DIR__ . '/../qr_codes/';
            if (!file_exists($qr_dir)) {
                mkdir($qr_dir, 0755, true);
            }
            
            $qr_filename = "mdva_module_" . $module['module_id'] . ".png";
            $qr_path = $qr_dir . $qr_filename;
            
            // Generate QR code with ESP32 format
            QRcode::png($qr_data_json, $qr_path, QR_ECLEVEL_L, 10, 2);
            
            // Vérifier que le fichier a été créé
            if (file_exists($qr_path) && filesize($qr_path) > 0) {
                $success_message = "Code QR ESP32 généré pour le module : " . htmlspecialchars($module_id);
                $preview_file = $qr_path;
                $preview_data = $module_id;
                $qr_data_preview = $qr_data;
                
                // CHANGEMENT CRITIQUE 2 : Calculer le chemin web CORRECTEMENT
                // Comme test_qr_browser.php : str_replace(__DIR__ . '/', '', $test_file)
                $preview_web_path = str_replace(__DIR__ . '/../', '', $qr_path);
            } else {
                $error_message = "Le fichier QR n'a pas été créé : " . htmlspecialchars($qr_path);
            }
        } else {
            $error_message = "Module non trouvé : " . htmlspecialchars($module_id);
        }
    } elseif (!empty($barcode_data)) {
        // Generate custom QR code
        $custom_data = $barcode_data;
        
        // CHANGEMENT : Utiliser __DIR__
        $custom_qr_dir = __DIR__ . '/../custom_qr_codes/';
        if (!file_exists($custom_qr_dir)) {
            mkdir($custom_qr_dir, 0755, true);
        }
        
        $qr_file = $custom_qr_dir . 'custom_' . md5($custom_data . time()) . '.png';
        
        QRcode::png($custom_data, $qr_file, QR_ECLEVEL_L, 10, 2);
        
        if (file_exists($qr_file) && filesize($qr_file) > 0) {
            $success_message = "Code généré pour : " . htmlspecialchars($barcode_data);
            $preview_file = $qr_file;
            $preview_data = $barcode_data;
            $qr_data_preview = ['custom_data' => $barcode_data];
            $preview_web_path = str_replace(__DIR__ . '/../', '', $qr_file);
        } else {
            $error_message = "Échec de la génération du QR personnalisé";
        }
    }
}

if (isset($_POST['generate_all'])) {
    $generated = generateAllModuleQRCodes($db);
    $success_message = count($generated) . " codes QR générés pour tous les modules actifs";
    
    // Store generated data for preview
    if (!empty($generated)) {
        $first_module = $generated[0];
        $preview_file = __DIR__ . '/../qr_codes/' . $first_module['qr_file'];
        $preview_data = $first_module['module_id'];
        $qr_data_preview = $first_module['qr_data'];
        $preview_web_path = 'qr_codes/' . $first_module['qr_file'];
    }
}

// Obtenir tous les modules pour le menu déroulant
function getActiveModules($db) {
    $query = "SELECT module_id, name FROM modules WHERE status = 'active' ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$modules = getActiveModules($db);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Générer des Codes QR (Format ESP32)</h2>
    
    <?php if(isset($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if(isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <!-- AJOUTER UNE SECTION DEBUG -->
    <div class="alert alert-info small" style="display: none;" id="debugInfo">
        <strong>Debug Info:</strong><br>
        <?php if(isset($preview_file)): ?>
        Fichier: <?php echo $preview_file; ?><br>
        Chemin web: <?php echo isset($preview_web_path) ? $preview_web_path : 'Non défini'; ?><br>
        Existe: <?php echo file_exists($preview_file) ? 'Oui' : 'Non'; ?><br>
        Taille: <?php echo file_exists($preview_file) ? filesize($preview_file) : 0; ?> bytes
        <?php endif; ?>
    </div>
    
    <div class="row">
        <!-- Génération d'un code unique -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Générer un Code Unique (Format ESP32)</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Générer pour un Module</label>
                            <select name="module_id" id="module_id" class="form-control">
                                <option value="">-- Sélectionner un Module --</option>
                                <?php foreach($modules as $module): ?>
                                <option value="<?php echo htmlspecialchars($module['module_id']); ?>">
                                    <?php echo htmlspecialchars($module['name']); ?> (<?php echo htmlspecialchars($module['module_id']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Génère un QR code au format ESP32 avec toutes les informations du module</small>
                        </div>
                        
                        <div class="text-center my-3">OU</div>
                        
                        <div class="mb-3">
                            <label class="form-label">QR Code Personnalisé</label>
                            <input type="text" name="barcode_data" id="barcode_data" class="form-control" 
                                   placeholder="Entrez du texte pour générer un QR Code">
                            <small class="form-text text-muted">Pour les données personnalisées (non-ESP32)</small>
                        </div>
                        
                        <input type="hidden" name="type" value="QRCODE">
                        
                        <button type="submit" name="generate_single" class="btn btn-primary">
                            <i class="fas fa-qrcode"></i> Générer le QR Code
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Section Aperçu -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Aperçu & Téléchargement</h5>
                </div>
                <div class="card-body">
                    <?php 
                    if(isset($preview_file)): 
                        // Get absolute path for file existence check
                        $absolute_path = realpath($preview_file);
                        $file_exists = file_exists($preview_file);
                        $file_size = $file_exists ? filesize($preview_file) : 0;
                        
                        // CHANGEMENT CRITIQUE 3 : Utiliser le bon chemin web
                        if (isset($preview_web_path)) {
                            $web_path = $preview_web_path;
                        } else {
                            // Fallback : méthode originale
                            $web_path = str_replace('../', '', $preview_file);
                        }
                        
                        // AJOUTER UN TIMESTAMP pour éviter le cache (comme test_qr_browser.php)
                        $img_src = $web_path . '?t=' . time();
                    ?>
                    
                    <div id="codePreview" class="text-center mb-3" style="min-height: 200px; padding: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <?php if($file_exists && $file_size > 0): 
                            // Get file info
                            $file_info = @getimagesize($preview_file);
                            $file_size_kb = round($file_size / 1024, 2);
                        ?>
                            <!-- CHANGEMENT CRITIQUE 4 : Utiliser $img_src avec timestamp -->
                            <img src="<?php echo htmlspecialchars($img_src); ?>" 
                                 alt="QR Code" 
                                 style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 10px; background: white;"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;charset=utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"200\" height=\"200\"><rect width=\"100%\" height=\"100%\" fill=\"%23f8f9fa\"/><text x=\"50%\" y=\"50%\" text-anchor=\"middle\" dy=\".3em\" fill=\"%236c757d\">QR Code Error</text></svg>';">
                            <p class="mt-2"><strong><?php echo htmlspecialchars($preview_data); ?></strong></p>
                            <small class="text-muted">
                                <?php echo $file_info ? $file_info[0] . '×' . $file_info[1] . ' px • ' : ''; ?>
                                <?php echo $file_size_kb; ?> KB
                            </small>
                            
                            <?php if(isset($qr_data_preview)): ?>
                            <div class="mt-2 small">
                                <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#qrDataCollapse" aria-expanded="false" aria-controls="qrDataCollapse">
                                    Afficher les données du QR Code
                                </button>
                                <div class="collapse mt-2" id="qrDataCollapse">
                                    <pre class="bg-light p-2 small text-start" style="max-height: 150px; overflow: auto;"><?php echo json_encode($qr_data_preview, JSON_PRETTY_PRINT); ?></pre>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Fichier QR code non trouvé ou vide
                                <br><small><?php echo htmlspecialchars($preview_file); ?></small>
                                <br><small>Taille: <?php echo $file_size; ?> bytes</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <?php if($file_exists && $file_size > 0): ?>
                            <!-- CHANGEMENT CRITIQUE 5 : Utiliser $web_path pour le téléchargement -->
                            <a href="<?php echo htmlspecialchars($web_path); ?>" 
                               download="mdva_qrcode_<?php echo htmlspecialchars($preview_data); ?>.png" 
                               class="btn btn-success">
                                <i class="fas fa-download"></i> Télécharger ce QR Code
                            </a>
                            <!-- CHANGEMENT CRITIQUE 6 : Utiliser $web_path pour l'impression -->
                            <button onclick="printQRCode('<?php echo htmlspecialchars($web_path); ?>', '<?php echo htmlspecialchars($preview_data); ?>')" class="btn btn-outline-primary">
                                <i class="fas fa-print"></i> Imprimer ce Code
                            </button>
                            
                            <!-- AJOUTER UN LIEN DE TEST DIRECT -->
                            <a href="<?php echo htmlspecialchars($web_path); ?>" target="_blank" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-external-link-alt"></i> Tester le lien
                            </a>
                        <?php else: ?>
                            <button class="btn btn-success" disabled>
                                <i class="fas fa-download"></i> Fichier non disponible
                            </button>
                            <button class="btn btn-outline-primary" disabled>
                                <i class="fas fa-print"></i> Imprimer ce Code
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div id="codePreview" class="text-center mb-3" style="min-height: 200px; border: 1px dashed #ccc; padding: 20px; display: flex; align-items: center; justify-content: center;">
                        <p class="text-muted">Le code généré apparaîtra ici</p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" disabled>
                            <i class="fas fa-download"></i> Télécharger ce QR Code
                        </button>
                        <button class="btn btn-outline-primary" disabled>
                            <i class="fas fa-print"></i> Imprimer ce Code
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Génération en masse -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Génération en Masse de Codes QR (ESP32)</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Format ESP32</h6>
                        <p class="mb-2">Les codes QR générés utiliseront le format spécifique pour l'ESP32 :</p>
                        <pre class="small bg-light p-2">{
  "module_id": "MDVA_XXXXXX",
  "module_name": "Nom du Module",
  "location": "Adresse complète",
  "system": "MDVA",
  "type": "donation_module",
  "version": "1.0",
  "timestamp": 1234567890,
  "url": "https://systeme-mdva.com/module/MDVA_XXXXXX"
}</pre>
                    </div>
                    
                    <p><strong>Modules actifs :</strong> <?php echo count($modules); ?> modules disponibles</p>
                    <p><strong>Note :</strong> Les codes QR existants avec le même ID de module seront écrasés.</p>
                    
                    <form method="POST">
                        <button type="submit" name="generate_all" class="btn btn-primary" onclick="return confirm('Générer des codes QR pour tous les modules actifs ?')">
                            <i class="fas fa-qrcode"></i> Générer Tous les Codes QR (ESP32 Format)
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h6>Spécifications d'Impression pour l'ESP32 :</h6>
                    <ul>
                        <li><strong>Format :</strong> QR Code contenant JSON au format ESP32</li>
                        <li><strong>Taille :</strong> 2x2 pouces minimum pour une numérisation fiable</li>
                        <li><strong>Résolution :</strong> 300 DPI minimum</li>
                        <li><strong>Placement :</strong> Fixé sur le module ESP32, visible et facilement numérisable</li>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <small>
                            <strong>Important :</strong> Les codes QR générés ici sont spécifiquement formatés pour être scannés par l'application mobile MDVA et communiquer avec l'ESP32.
                        </small>
                    </div>
                    
                    <div class="alert alert-success">
                        <small>
                            <strong>Compatibilité ESP32 :</strong> Les modules ESP32 enregistrés automatiquement obtiendront leur QR code lors de leur première connexion. Utilisez cette fonction pour régénérer les QR codes si nécessaire.
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Liste des modules -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Modules Disponibles</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($modules)): ?>
                    <p class="text-muted">Aucun module actif trouvé</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID Module</th>
                                    <th>Nom</th>
                                    <th>QR Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($modules as $module): 
                                    // CHANGEMENT : Utiliser __DIR__ pour vérifier l'existence
                                    $qr_file = __DIR__ . '/../qr_codes/mdva_module_' . $module['module_id'] . '.png';
                                    $has_qr = file_exists($qr_file) && filesize($qr_file) > 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($module['module_id']); ?></td>
                                    <td><?php echo htmlspecialchars($module['name']); ?></td>
                                    <td>
                                        <?php if($has_qr): ?>
                                        <span class="badge bg-success">Déjà généré</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">À générer</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printQRCode(qrImagePath, moduleName) {
    if (!qrImagePath || !moduleName) {
        alert('Aucun QR Code à imprimer');
        return;
    }
    
    // AJOUTER UN TIMESTAMP pour éviter le cache
    const timestamp = new Date().getTime();
    const imageUrl = qrImagePath + (qrImagePath.includes('?') ? '&' : '?') + 't=' + timestamp;
    
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Imprimer QR Code ESP32</title>
            <style>
                body { 
                    margin: 0; 
                    padding: 20px; 
                    font-family: Arial, sans-serif;
                    background: white;
                }
                .page-container {
                    max-width: 400px;
                    margin: 0 auto;
                    padding: 20px;
                    border: 1px solid #ccc;
                    border-radius: 5px;
                    background: white;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #2196F3;
                }
                .header h2 {
                    margin: 0;
                    color: #2196F3;
                    font-size: 24px;
                }
                .header p {
                    margin: 5px 0 0 0;
                    color: #666;
                    font-size: 14px;
                }
                .qr-container {
                    text-align: center;
                    margin: 20px 0;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 5px;
                }
                .qr-container img {
                    max-width: 250px;
                    height: auto;
                    margin: 0 auto;
                    display: block;
                    border: 1px solid #ddd;
                    background: white;
                    padding: 10px;
                }
                .module-info {
                    text-align: center;
                    margin: 15px 0;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 5px;
                }
                .module-info h4 {
                    margin: 0 0 10px 0;
                    color: #333;
                }
                .module-id {
                    font-size: 18px;
                    font-weight: bold;
                    color: #2196F3;
                }
                .instructions {
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 1px solid #eee;
                    font-size: 12px;
                    color: #666;
                    text-align: center;
                }
                .footer {
                    margin-top: 20px;
                    font-size: 10px;
                    color: #999;
                    text-align: center;
                    border-top: 1px solid #eee;
                    padding-top: 10px;
                }
                @media print {
                    body { 
                        margin: 0; 
                        padding: 0;
                    }
                    .page-container {
                        border: none;
                        max-width: 100%;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="page-container">
                <div class="header">
                    <h2>MDVA</h2>
                    <p>Système de Donation Modulaire</p>
                </div>
                
                <div class="qr-container">
                    <img src="${imageUrl}" alt="QR Code ESP32" onerror="this.onerror=null; this.src='data:image/svg+xml;charset=utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"250\" height=\"250\"><rect width=\"100%\" height=\"100%\" fill=\"%23f8f9fa\"/><text x=\"50%\" y=\"50%\" text-anchor=\"middle\" dy=\".3em\" fill=\"%236c757d\">QR Code</text></svg>';">
                </div>
                
                <div class="module-info">
                    <h4>Module ESP32</h4>
                    <div class="module-id">${moduleName}</div>
                </div>
                
                <div class="instructions">
                    <p><strong>Instructions :</strong></p>
                    <p>1. Scanner ce code QR avec l'application mobile MDVA</p>
                    <p>2. Le module sera automatiquement reconnu</p>
                    <p>3. Prêt à recevoir des dons</p>
                </div>
                
                <div class="footer">
                    <p>Système MDVA - https://systeme-mdva.com</p>
                    <p>Généré le ${new Date().toLocaleDateString('fr-CA')}</p>
                </div>
                
                <div class="no-print" style="text-align: center; margin-top: 20px;">
                    <button onclick="window.print();" style="padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Imprimer
                    </button>
                    <button onclick="window.close();" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                        Fermer
                    </button>
                </div>
            </div>
            <script>
                window.onload = function() {
                    // Vérifier si l'image est chargée
                    const img = document.querySelector('img');
                    img.onload = function() {
                        console.log('✅ Image chargée');
                    };
                    img.onerror = function() {
                        console.error('❌ Erreur de chargement de l\'image');
                    };
                    
                    // Auto-print after 500ms
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Auto-submit form when module is selected (optional)
document.getElementById('module_id').addEventListener('change', function() {
    if(this.value) {
        document.getElementById('barcode_data').value = '';
    }
});

document.getElementById('barcode_data').addEventListener('input', function() {
    if(this.value) {
        document.getElementById('module_id').value = '';
    }
});

// Afficher le panneau debug avec Ctrl+D
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        document.getElementById('debugInfo').style.display = 'block';
    }
});
</script>

<?php include '../includes/footer.php'; ?>