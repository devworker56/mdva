<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

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
        // Utiliser AJAX pour générer le code-barres
        $preview_file = ''; // Sera défini via AJAX
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

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Générer des Codes QR et Codes-barres</h2>
    
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
                    <form id="barcodeForm" method="POST">
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
                            <label class="form-label">Code-barres/QR Code Personnalisé</label>
                            <input type="text" name="barcode_data" id="barcode_data" class="form-control" 
                                   placeholder="Entrez du texte ou des nombres personnalisés">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Type de Code</label>
                            <select name="type" id="barcode_type" class="form-control">
                                <option value="QRCODE">QR Code</option>
                                <option value="CODE128">Code-barres (CODE128)</option>
                                <option value="CODE39">Code-barres (CODE39)</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="generate_single" class="btn btn-primary">
                            <i class="fas fa-qrcode"></i> Générer le Code
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Section Aperçu -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Aperçu & Impression</h5>
                </div>
                <div class="card-body">
                    <div id="codePreview" class="text-center mb-3" style="min-height: 200px; border: 1px dashed #ccc; padding: 20px; display: flex; align-items: center; justify-content: center;">
                        <p class="text-muted">Le code généré apparaîtra ici</p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button id="printSingle" class="btn btn-success" disabled onclick="printSingleCode()">
                            <i class="fas fa-print"></i> Imprimer ce Code
                        </button>
                        <a href="print_bulk_labels.php" class="btn btn-outline-primary">
                            <i class="fas fa-tags"></i> Imprimer des Étiquettes Multiples
                        </a>
                    </div>
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
                        <li><strong>Codes-barres :</strong> Ajuster la hauteur selon la distance de numérisation</li>
                        <li><strong>Matériau :</strong> Utiliser du vinyle adhésif pour les modules extérieurs</li>
                        <li><strong>Placement :</strong> Emplacement visible et facilement numérisable</li>
                    </ul>
                    
                    <div class="alert alert-info">
                        <small>
                            <strong>Astuce d'Impression :</strong> Utilisez la boîte de dialogue d'impression du navigateur (Ctrl+P) et sélectionnez votre imprimante préférée. 
                            Pour les feuilles d'étiquettes, utilisez la fonctionnalité "Imprimer des Étiquettes Multiples".
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gérer la soumission du formulaire avec AJAX pour l'aperçu
document.getElementById('barcodeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const moduleId = document.getElementById('module_id').value;
    const barcodeData = document.getElementById('barcode_data').value;
    const barcodeType = document.getElementById('barcode_type').value;
    
    // Si un module est sélectionné, utiliser la soumission normale du formulaire pour les codes QR
    if (moduleId) {
        this.submit();
        return;
    }
    
    // Si données personnalisées, utiliser AJAX
    if (barcodeData) {
        const formData = new FormData();
        formData.append('data', barcodeData);
        formData.append('type', barcodeType);
        formData.append('width', 2);
        formData.append('height', 1);
        
        fetch('generate_barcode.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('codePreview').innerHTML = data.html;
                document.getElementById('printSingle').disabled = false;
                window.currentCode = {
                    url: data.barcode_url,
                    data: barcodeData,
                    type: barcodeType
                };
            } else {
                alert('Erreur : ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur :', error);
            alert('Erreur lors de la génération du code');
        });
    } else {
        alert('Veuillez entrer des données ou sélectionner un module');
    }
});

function printSingleCode() {
    if (!window.currentCode) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Imprimer ${window.currentCode.type}</title>
            <style>
                body { 
                    margin: 0; 
                    padding: 20px; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center;
                    min-height: 100vh;
                    font-family: Arial, sans-serif;
                }
                .code-container {
                    text-align: center;
                    border: 1px solid #000;
                    padding: 20px;
                    max-width: 300px;
                    margin: 0 auto;
                }
                .code-container img {
                    max-width: 100%;
                    height: auto;
                }
                @media print {
                    body { margin: 0; padding: 0; }
                    .code-container { border: none; }
                }
            </style>
        </head>
        <body>
            <div class="code-container">
                <img src="${window.currentCode.url}" alt="${window.currentCode.type}">
                <div style="margin-top: 10px; font-size: 14px; font-weight: bold;">${window.currentCode.data}</div>
                <div style="margin-top: 5px; font-size: 10px; color: #666;">
                    Système MDVA • ${new Date().toLocaleDateString()}
                </div>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 1000);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<?php include '../includes/footer.php'; ?>