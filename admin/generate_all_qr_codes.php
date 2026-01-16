<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// FIX: Update the path in functions.php OR include the library directly here
// First, let's include the QR code library directly with the correct path
require_once 'qrlib/phpqrcode/qrlib.php';

$database = new Database();
$db = $database->getConnection();

// Gérer les soumissions de formulaire
if (isset($_POST['generate_single'])) {
    $module_id = $_POST['module_id'] ?? '';
    $barcode_data = $_POST['barcode_data'] ?? '';
    $type = $_POST['type'] ?? 'QRCODE';
    
    if (!empty($module_id)) {
        // Générer le code QR pour le module
        $module = getModuleDetails($module_id, $db);
        if ($module) {
            $qr_file = generateModuleQRCode($module_id, $module['name'], $module['location']);
            $success_message = "Code QR généré pour le module : " . htmlspecialchars($module_id);
            $preview_file = $qr_file;
            $preview_data = $module_id;
        }
    } elseif (!empty($barcode_data)) {
        // Generate QR code for custom data
        $custom_data = $barcode_data;
        
        // Create a custom QR code directory if it doesn't exist
        $custom_qr_dir = "../custom_qr_codes/";
        if (!file_exists($custom_qr_dir)) {
            mkdir($custom_qr_dir, 0755, true);
        }
        
        // Generate QR code using the installed library
        $qr_file = $custom_qr_dir . 'custom_' . md5($custom_data . time()) . '.png';
        
        if ($type == 'QRCODE') {
            // Generate QR code
            QRcode::png($custom_data, $qr_file, QR_ECLEVEL_L, 10, 2);
        } else {
            // For barcode types, we only have QR code library installed
            // You'll need additional libraries for CODE128/CODE39 barcodes
            QRcode::png($custom_data, $qr_file, QR_ECLEVEL_L, 10, 2);
        }
        
        $success_message = "Code généré pour : " . htmlspecialchars($barcode_data);
        $preview_file = $qr_file;
        $preview_data = $barcode_data;
    }
}

if (isset($_POST['generate_all'])) {
    $generated = generateAllModuleQRCodes($db);
    $success_message = count($generated) . " codes QR générés";
}

// Obtenir tous les modules pour le menu déroulant
$modules = getActiveModules($db);

function getModuleDetails($module_id, $db) {
    $query = "SELECT * FROM modules WHERE module_id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$module_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getActiveModules($db) {
    $query = "SELECT module_id, name FROM modules WHERE status = 'active' ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to generate QR code for custom data
function generateCustomQRCode($data, $type = 'QRCODE', $filename = null) {
    if ($filename === null) {
        $custom_qr_dir = "../custom_qr_codes/";
        if (!file_exists($custom_qr_dir)) {
            mkdir($custom_qr_dir, 0755, true);
        }
        $filename = $custom_qr_dir . 'custom_' . md5($data . time()) . '.png';
    }
    
    // Only generate QR codes since that's what we have installed
    QRcode::png($data, $filename, QR_ECLEVEL_L, 10, 2);
    return $filename;
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Générer des Codes QR</h2>
    
    <?php if(isset($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Génération d'un code unique -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Générer un Code Unique</h5>
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
                        </div>
                        
                        <div class="text-center my-3">OU</div>
                        
                        <div class="mb-3">
                            <label class="form-label">QR Code Personnalisé</label>
                            <input type="text" name="barcode_data" id="barcode_data" class="form-control" 
                                   placeholder="Entrez du texte pour générer un QR Code">
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
                        <p class="mt-2"><?php echo htmlspecialchars($preview_data); ?></p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="<?php echo htmlspecialchars($preview_file); ?>" download="qrcode_<?php echo htmlspecialchars($preview_data); ?>.png" class="btn btn-success">
                            <i class="fas fa-download"></i> Télécharger ce QR Code
                        </a>
                        <button onclick="printQRCode()" class="btn btn-outline-primary">
                            <i class="fas fa-print"></i> Imprimer ce Code
                        </button>
                        <a href="print_bulk_labels.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tags"></i> Imprimer des Étiquettes Multiples
                        </a>
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
                        <a href="print_bulk_labels.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tags"></i> Imprimer des Étiquettes Multiples
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Génération en masse -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Génération en Masse de Codes QR</h5>
                </div>
                <div class="card-body">
                    <p>Cela générera des codes QR pour tous les modules actifs du système.</p>
                    <p><strong>Note :</strong> Les codes QR existants avec le même ID de module seront écrasés.</p>
                    
                    <form method="POST">
                        <button type="submit" name="generate_all" class="btn btn-primary" onclick="return confirm('Générer des codes QR pour tous les modules actifs ?')">
                            <i class="fas fa-qrcode"></i> Générer Tous les Codes QR
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h6>Spécifications d'Impression :</h6>
                    <ul>
                        <li><strong>Codes QR :</strong> 2x2 pouces minimum pour une numérisation fiable</li>
                        <li><strong>Résolution :</strong> 300 DPI minimum pour une impression de qualité</li>
                        <li><strong>Matériau :</strong> Utiliser du vinyle adhésif pour les modules extérieurs</li>
                        <li><strong>Placement :</strong> Emplacement visible et facilement numérisable</li>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <small>
                            <strong>Note :</strong> La fonctionnalité de code-barres (CODE128/CODE39) nécessite des bibliothèques supplémentaires. 
                            Actuellement, seuls les QR Codes sont disponibles.
                        </small>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <strong>Astuce d'Impression :</strong> Utilisez la boîte de dialogue d'impression du navigateur (Ctrl+P) et sélectionnez votre imprimante préférée. 
                            Pour les feuilles d'étiquettes, utilisez la fonctionnalité "Imprimer des Étiquettes Multiples".
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Instructions d'installation -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Instructions d'Utilisation</h5>
                </div>
                <div class="card-body">
                    <h6>Pour utiliser dans vos propres pages :</h6>
                    <pre><code>&lt;?php
// Inclure la bibliothèque QR Code
require_once 'qrlib/phpqrcode/qrlib.php';

// Générer un QR Code et le sauvegarder
QRcode::png('Vos données ici', 'chemin/fichier.png', QR_ECLEVEL_L, 10);

// Ou afficher directement dans le navigateur
QRcode::png('Vos données ici');
?&gt;</code></pre>
                    
                    <p class="mt-2"><small>Les codes QR générés sont stockés dans :</small></p>
                    <ul class="small">
                        <li>Modules : <code>/qr_codes/</code></li>
                        <li>Données personnalisées : <code>/custom_qr_codes/</code></li>
                    </ul>
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
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Imprimer QR Code</title>
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
                .code-container {
                    text-align: center;
                    padding: 20px;
                }
                .code-container img {
                    max-width: 300px;
                    height: auto;
                    border: 1px solid #000;
                }
                @media print {
                    body { margin: 0; padding: 0; }
                    .code-container { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <div class="code-container">
                <img src="${previewImg.src}" alt="QR Code">
                <div style="margin-top: 10px; font-size: 14px; font-weight: bold;">${previewImg.nextElementSibling.textContent}</div>
                <div style="margin-top: 5px; font-size: 10px; color: #666;">
                    Système MDVA • ${new Date().toLocaleDateString()}
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