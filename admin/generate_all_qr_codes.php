<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Include the QR code library
require_once 'qrlib/phpqrcode/qrlib.php';

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
            
            $qr_dir = "../qr_codes/";
            if (!file_exists($qr_dir)) {
                mkdir($qr_dir, 0755, true);
            }
            
            $qr_filename = "mdva_module_" . $module['module_id'] . ".png";
            $qr_path = $qr_dir . $qr_filename;
            
            // Generate QR code with ESP32 format
            QRcode::png($qr_data_json, $qr_path, QR_ECLEVEL_L, 10, 2);
            
            $success_message = "Code QR ESP32 généré pour le module : " . htmlspecialchars($module_id);
            $preview_file = $qr_path;
            $preview_data = $module_id;
            $qr_data_preview = $qr_data;
        } else {
            $error_message = "Module non trouvé : " . htmlspecialchars($module_id);
        }
    } elseif (!empty($barcode_data)) {
        // Generate custom QR code
        $custom_data = $barcode_data;
        
        $custom_qr_dir = "../custom_qr_codes/";
        if (!file_exists($custom_qr_dir)) {
            mkdir($custom_qr_dir, 0755, true);
        }
        
        $qr_file = $custom_qr_dir . 'custom_' . md5($custom_data . time()) . '.png';
        
        QRcode::png($custom_data, $qr_file, QR_ECLEVEL_L, 10, 2);
        
        $success_message = "Code généré pour : " . htmlspecialchars($barcode_data);
        $preview_file = $qr_file;
        $preview_data = $barcode_data;
        $qr_data_preview = ['custom_data' => $barcode_data];
    }
}

if (isset($_POST['generate_all'])) {
    $generated = generateAllModuleQRCodes($db);
    $success_message = count($generated) . " codes QR générés pour tous les modules actifs";
    
    // Store generated data for preview
    if (!empty($generated)) {
        $first_module = $generated[0];
        $preview_file = "../qr_codes/" . $first_module['qr_file'];
        $preview_data = $first_module['module_id'];
        $qr_data_preview = $first_module['qr_data'];
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
                    <?php if(isset($preview_file) && file_exists($preview_file)): ?>
                    <div id="codePreview" class="text-center mb-3" style="min-height: 200px; padding: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <img src="<?php echo htmlspecialchars($preview_file); ?>" alt="QR Code" style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 10px; background: white;">
                        <p class="mt-2"><strong><?php echo htmlspecialchars($preview_data); ?></strong></p>
                        
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
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="<?php echo htmlspecialchars($preview_file); ?>" download="mdva_qrcode_<?php echo htmlspecialchars($preview_data); ?>.png" class="btn btn-success">
                            <i class="fas fa-download"></i> Télécharger ce QR Code
                        </a>
                        <button onclick="printQRCode()" class="btn btn-outline-primary">
                            <i class="fas fa-print"></i> Imprimer ce Code
                        </button>
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
                                    $has_qr = moduleQRCodeExists($module['module_id']);
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
function printQRCode() {
    const previewImg = document.querySelector('#codePreview img');
    if (!previewImg) {
        alert('Aucun QR Code à imprimer');
        return;
    }
    
    const moduleName = previewImg.nextElementSibling.textContent;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Imprimer QR Code ESP32</title>
            <style>
                body { 
                    margin: 0; 
                    padding: 0; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center;
                    min-height: 100vh;
                    font-family: Arial, sans-serif;
                }
                .label-container {
                    text-align: center;
                    padding: 20px;
                    border: 1px solid #000;
                    max-width: 300px;
                    margin: 0 auto;
                }
                .label-container img {
                    max-width: 250px;
                    height: auto;
                    margin: 0 auto 15px auto;
                    display: block;
                }
                .module-info {
                    font-size: 14px;
                    margin-top: 10px;
                }
                .footer {
                    font-size: 10px;
                    color: #666;
                    margin-top: 15px;
                    border-top: 1px solid #ccc;
                    padding-top: 10px;
                }
                @media print {
                    body { margin: 0; padding: 10px; }
                    .label-container { border: 1px solid #000; }
                }
            </style>
        </head>
        <body>
            <div class="label-container">
                <div style="text-align: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #2196F3;">MDVA</h3>
                    <p style="margin: 0; font-size: 14px;">Module de Donation ESP32</p>
                </div>
                
                <img src="${previewImg.src}" alt="QR Code ESP32">
                
                <div class="module-info">
                    <strong>Module :</strong><br>
                    ${moduleName}
                </div>
                
                <div class="footer">
                    Scanner avec l'App MDVA<br>
                    Format: ESP32 JSON<br>
                    ${new Date().toLocaleDateString()}
                </div>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<?php include '../includes/footer.php'; ?>