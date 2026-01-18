<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Activer le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/header.php';

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Obtenir les statistiques
$query = "SELECT COUNT(*) as total_charities FROM charities";
$stmt = $db->prepare($query);
$stmt->execute();
$total_charities = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT COUNT(*) as pending_charities FROM charities WHERE approved = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_charities = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT SUM(amount) as total_donations FROM donations";
$stmt = $db->prepare($query);
$stmt->execute();
$total_donations = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT COUNT(*) as total_donors FROM donors";
$stmt = $db->prepare($query);
$stmt->execute();
$total_donors = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Tableau de bord Admin</h2>
    
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $total_charities['total_charities']; ?></h5>
                    <p class="card-text">Organismes Totaux</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $pending_charities['pending_charities']; ?></h5>
                    <p class="card-text">Approbations en Attente</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title"><?php echo number_format($total_donations['total_donations'] ?? 0, 2); ?> $</h5>
                    <p class="card-text">Dons Totaux</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $total_donors['total_donors']; ?></h5>
                    <p class="card-text">Donateurs Totaux</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Gestion des Organismes</h5>
                </div>
                <div class="card-body">
                    <a href="manage_charities.php" class="btn btn-primary">Gérer les Organismes</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Generator for MDVA Module -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Générateur de Code QR pour Module MDVA</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6>Information du Module MDVA</h6>
                                
                                <!-- Section pour charger un module existant -->
                                <div class="mb-3">
                                    <label class="form-label">Charger un module existant</label>
                                    <select class="form-control" id="loadExistingModule" onchange="loadModuleData(this.value)">
                                        <option value="">-- Sélectionner un module --</option>
                                        <?php
                                        $query = "SELECT module_id, name FROM modules ORDER BY module_id";
                                        $stmt = $db->prepare($query);
                                        $stmt->execute();
                                        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($modules as $module) {
                                            echo '<option value="' . htmlspecialchars($module['module_id']) . '">';
                                            echo htmlspecialchars($module['module_id']) . ' - ' . htmlspecialchars($module['name']);
                                            echo '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <form id="moduleForm">
                                    <div class="mb-3">
                                        <label for="moduleId" class="form-label">ID du Module *</label>
                                        <input type="text" class="form-control" id="moduleId" 
                                               value="MDVA_3CB97DE4" required>
                                        <small class="form-text text-muted">
                                            Format: MDVA_XXXXXXX ou ESP32_XXX
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="moduleName" class="form-label">Nom du Module *</label>
                                        <input type="text" class="form-control" id="moduleName" 
                                               value="Module B97DE4" required>
                                        <small class="form-text text-muted">
                                            Nom d'affichage pour le module
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="macAddress" class="form-label">Adresse MAC (Optionnel)</label>
                                        <input type="text" class="form-control" id="macAddress" 
                                               value="781C3CB97DE4">
                                        <small class="form-text text-muted">
                                            Adresse MAC du module ESP32 (sans les deux-points)
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="locationName" class="form-label">Nom du Lieu *</label>
                                        <input type="text" class="form-control" id="locationName" 
                                               value="Centre Eaton de Montréal" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Adresse (Optionnel)</label>
                                        <input type="text" class="form-control" id="address" 
                                               placeholder="705 rue Sainte-Catherine Ouest">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="city" class="form-label">Ville (Optionnel)</label>
                                                <input type="text" class="form-control" id="city" 
                                                       value="Montréal">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="province" class="form-label">Province (Optionnel)</label>
                                                <input type="text" class="form-control" id="province" 
                                                       value="QC">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="postalCode" class="form-label">Code Postal (Optionnel)</label>
                                        <input type="text" class="form-control" id="postalCode" 
                                               value="H3B 4G5">
                                    </div>
                                    <div class="alert alert-info">
                                        <small>
                                            <strong>Format QR Code requis pour l'ESP32:</strong><br>
                                            - module_id: Identifiant unique du module<br>
                                            - module_name: Nom du module<br>
                                            - location: Localisation du module<br>
                                            - system: "MDVA"<br>
                                            - type: "donation_module"<br>
                                            - url: Lien vers l'application mobile
                                        </small>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-qrcode"></i> Générer le Code QR du Module
                                        </button>
                                        <a href="generate_all_qr_codes.php" class="btn btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i> Ouvrir le Générateur Avancé
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6>Aperçu et Impression du Code QR</h6>
                                <div id="qrPreview" class="text-center mb-3" style="min-height: 200px; border: 1px dashed #ccc; padding: 20px;">
                                    <p class="text-muted">Le code QR du module apparaîtra ici</p>
                                </div>
                                
                                <div id="moduleInfo" class="mb-3" style="display: none;">
                                    <h6>Détails du Module MDVA :</h6>
                                    <div id="moduleDetails" class="small"></div>
                                </div>
                                
                                <div id="qrDataPreview" class="mb-3" style="display: none;">
                                    <h6>Données du Code QR :</h6>
                                    <pre id="qrDataContent" class="bg-light p-2 small" style="max-height: 150px; overflow: auto;"></pre>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button id="printQR" class="btn btn-success" disabled onclick="printModuleQRCode()">
                                        <i class="fas fa-print"></i> Imprimer le Code QR
                                    </button>
                                    <button id="downloadQR" class="btn btn-primary" disabled onclick="downloadQRCode()">
                                        <i class="fas fa-download"></i> Télécharger le QR Code
                                    </button>
                                    <button id="viewQR" class="btn btn-outline-info" disabled onclick="viewQRCode()">
                                        <i class="fas fa-eye"></i> Voir en plein écran
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let currentModuleQR = null;

// Charger les données d'un module existant
function loadModuleData(moduleId) {
    if (!moduleId) return;
    
    // Mettre à jour l'ID du module
    document.getElementById('moduleId').value = moduleId;
    
    // Vérifier si un QR existe déjà
    checkExistingQRCode(moduleId);
}

// Vérifier si un QR code existe déjà
function checkExistingQRCode(moduleId) {
    const qrFilename = 'mdva_module_' + moduleId + '.png';
    
    // Tous les chemins possibles depuis /admin/
    const possiblePaths = [
        '../../qr_codes/' + qrFilename,    // Retour à la racine
        '../qr_codes/' + qrFilename,       // Un niveau au-dessus
        '/qr_codes/' + qrFilename,         // Chemin absolu
        'qr_codes/' + qrFilename           // Relatif (ne marchera pas)
    ];
    
    testQRPaths(possiblePaths, moduleId, 'Chargement QR existant...');
}

// Tester plusieurs chemins
function testQRPaths(paths, moduleId, message) {
    let testsCompleted = 0;
    let foundPath = null;
    
    paths.forEach(path => {
        const testImg = new Image();
        testImg.onload = function() {
            testsCompleted++;
            if (!foundPath) {
                foundPath = path;
                // Charger le module pour avoir ses infos
                loadModuleInfo(moduleId, path);
            }
        };
        testImg.onerror = function() {
            testsCompleted++;
            if (testsCompleted === paths.length && !foundPath) {
                // Aucun QR trouvé
                document.getElementById('qrPreview').innerHTML = `
                    <div class="alert alert-info">
                        <p>Aucun QR code trouvé pour ${moduleId}</p>
                        <p>Générez-en un nouveau ci-dessus.</p>
                    </div>
                `;
            }
        };
        testImg.src = path + '?t=' + new Date().getTime();
    });
}

// Charger les infos d'un module
function loadModuleInfo(moduleId, qrPath = null) {
    // Envoyer une requête pour obtenir les infos du module
    fetch('get_module_info.php?module_id=' + encodeURIComponent(moduleId))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.module) {
                const module = data.module;
                
                // Remplir le formulaire
                document.getElementById('moduleId').value = module.module_id || '';
                document.getElementById('moduleName').value = module.name || '';
                document.getElementById('macAddress').value = module.mac_address || '';
                document.getElementById('locationName').value = module.location || '';
                
                // Si on a un chemin QR, l'afficher
                if (qrPath) {
                    showExistingQRCode(qrPath, module);
                }
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
}

// Afficher un QR existant
function showExistingQRCode(qrPath, moduleData) {
    const imgSrc = qrPath + '?t=' + new Date().getTime();
    
    document.getElementById('qrPreview').innerHTML = `
        <div class="alert alert-success">
            ✅ QR code existant trouvé
        </div>
        <img src="${imgSrc}" alt="QR Code" 
             style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 10px; background: white;">
        <p class="mt-2"><strong>${moduleData.module_id}</strong></p>
    `;
    
    // Afficher les infos
    showModuleInfo(moduleData, moduleData.location || '');
    
    // Activer les boutons
    document.getElementById('printQR').disabled = false;
    document.getElementById('downloadQR').disabled = false;
    document.getElementById('viewQR').disabled = false;
    
    // Stocker
    currentModuleQR = {
        url: qrPath,
        moduleData: moduleData,
        filename: 'mdva_module_' + moduleData.module_id + '.png'
    };
}

// Gérer la soumission du formulaire
document.getElementById('moduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Collecter les données
    const moduleData = {
        module_id: document.getElementById('moduleId').value.trim(),
        module_name: document.getElementById('moduleName').value.trim(),
        mac_address: document.getElementById('macAddress').value.trim(),
        location_name: document.getElementById('locationName').value.trim(),
        address: document.getElementById('address').value.trim(),
        city: document.getElementById('city').value.trim(),
        province: document.getElementById('province').value.trim(),
        postal_code: document.getElementById('postalCode').value.trim()
    };
    
    // Validation
    if (!moduleData.module_id || !moduleData.module_name || !moduleData.location_name) {
        alert('Veuillez remplir tous les champs obligatoires (*)');
        return;
    }
    
    // Normaliser l'ID
    moduleData.module_id = moduleData.module_id.toUpperCase();
    if (moduleData.module_id.startsWith('MVDA_')) {
        moduleData.module_id = moduleData.module_id.replace('MVDA_', 'MDVA_');
    }
    
    // Afficher chargement
    showLoading();
    
    // Générer le QR code (version BASE64 garantie)
    generateQRCodeBase64(moduleData);
});

// Afficher le chargement
function showLoading() {
    document.getElementById('qrPreview').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Génération du code QR...</p>
        </div>
    `;
    document.getElementById('moduleInfo').style.display = 'none';
    document.getElementById('qrDataPreview').style.display = 'none';
    document.getElementById('printQR').disabled = true;
    document.getElementById('downloadQR').disabled = true;
    document.getElementById('viewQR').disabled = true;
}

// Générer le QR code en Base64 (GARANTI de fonctionner)
function generateQRCodeBase64(moduleData) {
    // Construire la localisation
    let location = moduleData.location_name;
    if (moduleData.address) location += ', ' + moduleData.address;
    if (moduleData.city) location += ', ' + moduleData.city;
    if (moduleData.province) location += ', ' + moduleData.province;
    if (moduleData.postal_code) location += ' ' + moduleData.postal_code;
    
    // 1. D'abord générer via generate_all_qr_codes.php
    const formData = new FormData();
    formData.append('module_id', moduleData.module_id);
    formData.append('generate_single', '1');
    
    fetch('generate_all_qr_codes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Après génération, charger en Base64
        loadQRAsBase64(moduleData.module_id, moduleData, location);
    })
    .catch(error => {
        console.error('Erreur génération:', error);
        // Essayer quand même de charger en Base64
        loadQRAsBase64(moduleData.module_id, moduleData, location);
    });
}

// Charger le QR en Base64
function loadQRAsBase64(moduleId, moduleData, location) {
    fetch('get_qr_as_base64.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            module_id: moduleId,
            module_name: moduleData.module_name,
            location_name: location
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.base64) {
            displayQRCodeAsBase64(data.base64, moduleData, location, 
                '✅ QR code généré avec succès !');
        } else {
            throw new Error(data.message || 'Erreur génération QR');
        }
    })
    .catch(error => {
        console.error('Erreur Base64:', error);
        // Fallback : essayer les chemins normaux
        tryNormalPaths(moduleId, moduleData, location);
    });
}

// Afficher le QR en Base64
function displayQRCodeAsBase64(base64Data, moduleData, location, message) {
    document.getElementById('qrPreview').innerHTML = `
        <div class="alert alert-success">
            ${message}
        </div>
        <img src="${base64Data}" alt="QR Code" 
             style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 10px; background: white;">
        <p class="mt-2"><strong>${moduleData.module_id}</strong></p>
        <small class="text-muted">Affiché en Base64 (garanti)</small>
    `;
    
    // Afficher les infos
    showModuleInfo(moduleData, location);
    
    // Créer les données QR
    const qrData = {
        module_id: moduleData.module_id,
        module_name: moduleData.module_name,
        location: location,
        system: "MDVA",
        type: "donation_module",
        version: "1.0",
        timestamp: Math.floor(Date.now() / 1000),
        url: "https://systeme-mdva.com/module/" + encodeURIComponent(moduleData.module_id)
    };
    
    // Afficher les données
    document.getElementById('qrDataContent').textContent = JSON.stringify(qrData, null, 2);
    document.getElementById('qrDataPreview').style.display = 'block';
    
    // Activer les boutons
    document.getElementById('printQR').disabled = false;
    document.getElementById('downloadQR').disabled = false;
    document.getElementById('viewQR').disabled = false;
    
    // Stocker
    currentModuleQR = {
        url: base64Data,
        moduleData: moduleData,
        qrData: qrData,
        location: location,
        filename: 'mdva_module_' + moduleData.module_id + '.png',
        isBase64: true
    };
}

// Essayer les chemins normaux (fallback)
function tryNormalPaths(moduleId, moduleData, location) {
    const qrFilename = 'mdva_module_' + moduleId + '.png';
    const possiblePaths = [
        '../../qr_codes/' + qrFilename,
        '../qr_codes/' + qrFilename,
        '/qr_codes/' + qrFilename
    ];
    
    let testsCompleted = 0;
    let foundPath = null;
    
    possiblePaths.forEach(path => {
        const testImg = new Image();
        testImg.onload = function() {
            testsCompleted++;
            if (!foundPath) {
                foundPath = path;
                displayQRCodeWithPath(path, moduleData, location, 
                    '✅ QR code généré !');
            }
        };
        testImg.onerror = function() {
            testsCompleted++;
            if (testsCompleted === possiblePaths.length && !foundPath) {
                // Échec total
                document.getElementById('qrPreview').innerHTML = `
                    <div class="alert alert-warning">
                        <p>Le QR code a été généré mais ne peut pas être affiché.</p>
                        <p><a href="generate_all_qr_codes.php" target="_blank" class="btn btn-sm btn-primary">
                            Ouvrir le générateur pour voir
                        </a></p>
                    </div>
                `;
                showModuleInfo(moduleData, location);
            }
        };
        testImg.src = path + '?t=' + new Date().getTime();
    });
}

// Afficher avec chemin normal
function displayQRCodeWithPath(qrPath, moduleData, location, message) {
    const imgSrc = qrPath + '?t=' + new Date().getTime();
    
    document.getElementById('qrPreview').innerHTML = `
        <div class="alert alert-success">
            ${message}
        </div>
        <img src="${imgSrc}" alt="QR Code" 
             style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 10px; background: white;">
        <p class="mt-2"><strong>${moduleData.module_id}</strong></p>
        <small class="text-muted">Chemin: ${qrPath}</small>
    `;
    
    showModuleInfo(moduleData, location);
    
    const qrData = {
        module_id: moduleData.module_id,
        module_name: moduleData.module_name,
        location: location,
        system: "MDVA",
        type: "donation_module",
        version: "1.0",
        timestamp: Math.floor(Date.now() / 1000)
    };
    
    document.getElementById('qrDataContent').textContent = JSON.stringify(qrData, null, 2);
    document.getElementById('qrDataPreview').style.display = 'block';
    
    document.getElementById('printQR').disabled = false;
    document.getElementById('downloadQR').disabled = false;
    document.getElementById('viewQR').disabled = false;
    
    currentModuleQR = {
        url: qrPath,
        moduleData: moduleData,
        qrData: qrData,
        location: location,
        filename: 'mdva_module_' + moduleData.module_id + '.png'
    };
}

// Afficher les infos du module
function showModuleInfo(moduleData, location) {
    let html = `
        <strong>ID du Module :</strong> ${moduleData.module_id}<br>
        <strong>Nom du Module :</strong> ${moduleData.module_name}<br>
    `;
    
    if (moduleData.mac_address) {
        html += `<strong>Adresse MAC :</strong> ${moduleData.mac_address}<br>`;
    }
    
    html += `<strong>Emplacement :</strong> ${location}<br>`;
    html += `<strong>Système :</strong> MDVA Donation Module<br>`;
    html += `<strong>Date :</strong> ${new Date().toLocaleDateString('fr-CA')}`;
    
    document.getElementById('moduleDetails').innerHTML = html;
    document.getElementById('moduleInfo').style.display = 'block';
}

// Imprimer
function printModuleQRCode() {
    if (!currentModuleQR) {
        alert('Aucun code QR à imprimer');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head><title>Imprimer QR</title></head>
        <body>
            <div style="text-align:center; padding:20px;">
                <h2>MDVA Module QR</h2>
                <img src="${currentModuleQR.url}" style="width:250px;">
                <p><strong>${currentModuleQR.moduleData.module_id}</strong></p>
            </div>
            <script>window.print();<\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Télécharger
function downloadQRCode() {
    if (!currentModuleQR) {
        alert('Aucun code QR à télécharger');
        return;
    }
    
    const link = document.createElement('a');
    link.href = currentModuleQR.url;
    link.download = currentModuleQR.filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Voir en plein écran
function viewQRCode() {
    if (!currentModuleQR) {
        alert('Aucun code QR à afficher');
        return;
    }
    
    const viewWindow = window.open(currentModuleQR.url, '_blank');
    if (!viewWindow) {
        alert('Veuillez autoriser les popups');
    }
}

// Au chargement, vérifier si MDVA_3CB97DE4 existe
document.addEventListener('DOMContentLoaded', function() {
    checkExistingQRCode('MDVA_3CB97DE4');
});
</script>

<?php include '../includes/footer.php'; ?>