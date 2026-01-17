<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
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
                                <form id="moduleForm">
                                    <div class="mb-3">
                                        <label for="moduleId" class="form-label">ID du Module *</label>
                                        <input type="text" class="form-control" id="moduleId" 
                                               placeholder="Ex: MDVA_3CB97DE4" required>
                                        <small class="form-text text-muted">
                                            Format: MDVA_XXXXXXX ou ESP32_XXX
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="moduleName" class="form-label">Nom du Module *</label>
                                        <input type="text" class="form-control" id="moduleName" 
                                               placeholder="Ex: Module B97DE4" required>
                                        <small class="form-text text-muted">
                                            Nom d'affichage pour le module
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="macAddress" class="form-label">Adresse MAC (Optionnel)</label>
                                        <input type="text" class="form-control" id="macAddress" 
                                               placeholder="Ex: 781C3CB97DE4">
                                        <small class="form-text text-muted">
                                            Adresse MAC du module ESP32 (sans les deux-points)
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="locationName" class="form-label">Nom du Lieu *</label>
                                        <input type="text" class="form-control" id="locationName" 
                                               placeholder="Ex: Centre Eaton de Montréal" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Adresse (Optionnel)</label>
                                        <input type="text" class="form-control" id="address" 
                                               placeholder="Adresse">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="city" class="form-label">Ville (Optionnel)</label>
                                                <input type="text" class="form-control" id="city" 
                                                       placeholder="Ville">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="province" class="form-label">Province (Optionnel)</label>
                                                <input type="text" class="form-control" id="province" 
                                                       placeholder="Province">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="postalCode" class="form-label">Code Postal (Optionnel)</label>
                                        <input type="text" class="form-control" id="postalCode" 
                                               placeholder="A1A 1A1">
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
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-qrcode"></i> Générer le Code QR du Module
                                    </button>
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
                                
                                <button id="printQR" class="btn btn-success w-100" disabled onclick="printModuleQRCode()">
                                    <i class="fas fa-print"></i> Imprimer le Code QR du Module
                                </button>
                                <a href="generate_all_qr_codes.php" class="btn btn-outline-primary mt-2 w-100">
                                    <i class="fas fa-qrcode"></i> Générateur Avancé de Codes QR
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gérer la soumission du formulaire de module
document.getElementById('moduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Collecter toutes les données du formulaire
    const moduleData = {
        module_id: document.getElementById('moduleId').value,
        module_name: document.getElementById('moduleName').value,
        mac_address: document.getElementById('macAddress').value,
        location_name: document.getElementById('locationName').value,
        address: document.getElementById('address').value,
        city: document.getElementById('city').value,
        province: document.getElementById('province').value,
        postal_code: document.getElementById('postalCode').value
    };
    
    // Valider les champs requis
    if (!moduleData.module_id.trim() || !moduleData.module_name.trim() || !moduleData.location_name.trim()) {
        alert('Veuillez remplir tous les champs obligatoires (ID du Module, Nom du Module, Nom du Lieu)');
        return;
    }
    
    // Afficher le chargement
    document.getElementById('qrPreview').innerHTML = '<p>Génération du code QR du module...</p>';
    document.getElementById('moduleInfo').style.display = 'none';
    document.getElementById('qrDataPreview').style.display = 'none';
    
    // Envoyer les données pour générer le code QR
    const formData = new FormData();
    formData.append('data', JSON.stringify(moduleData));
    formData.append('type', 'QRCODE');
    
    fetch('generate_barcode.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Afficher le code QR
            document.getElementById('qrPreview').innerHTML = data.html;
            
            // Construire l'emplacement complet
            let location = moduleData.location_name;
            if (moduleData.address) location += ', ' + moduleData.address;
            if (moduleData.city) location += ', ' + moduleData.city;
            if (moduleData.province) location += ', ' + moduleData.province;
            if (moduleData.postal_code) location += ' ' + moduleData.postal_code;
            
            // Afficher les détails du module
            document.getElementById('moduleDetails').innerHTML = `
                <strong>ID du Module :</strong> ${moduleData.module_id}<br>
                <strong>Nom du Module :</strong> ${moduleData.module_name}<br>
                <strong>Adresse MAC :</strong> ${moduleData.mac_address || 'Non spécifiée'}<br>
                <strong>Emplacement :</strong> ${location}<br>
                <strong>Système :</strong> MDVA Donation Module<br>
                <strong>Type :</strong> Station de Don à Pièces
            `;
            document.getElementById('moduleInfo').style.display = 'block';
            
            // Afficher les données JSON qui seront scannées
            const qrData = {
                module_id: moduleData.module_id,
                module_name: moduleData.module_name,
                location: location,
                system: "MDVA",
                type: "donation_module",
                version: "1.0",
                timestamp: Math.floor(Date.now() / 1000),
                url: "https://tech-ideapad.com/donate.php?module=" + encodeURIComponent(moduleData.module_id)
            };
            
            document.getElementById('qrDataContent').textContent = JSON.stringify(qrData, null, 2);
            document.getElementById('qrDataPreview').style.display = 'block';
            
            // Activer le bouton d'impression
            document.getElementById('printQR').disabled = false;
            
            // Stocker les données pour l'impression
            window.currentModuleQR = {
                url: data.barcode_url,
                moduleData: moduleData,
                qrData: qrData,
                location: location
            };
        } else {
            alert('Erreur : ' + data.message);
            document.getElementById('qrPreview').innerHTML = '<p class="text-danger">Erreur : ' + data.message + '</p>';
        }
    })
    .catch(error => {
        console.error('Erreur :', error);
        alert('Erreur lors de la génération du code QR');
        document.getElementById('qrPreview').innerHTML = '<p class="text-danger">Erreur réseau</p>';
    });
});

function printModuleQRCode() {
    if (!window.currentModuleQR) {
        alert('Aucun code QR à imprimer');
        return;
    }
    
    const module = window.currentModuleQR.moduleData;
    const qrData = window.currentModuleQR.qrData;
    const location = window.currentModuleQR.location;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Code QR Module MDVA - ${module.module_id}</title>
            <style>
                body { 
                    margin: 0; 
                    padding: 20px; 
                    font-family: Arial, sans-serif;
                }
                .label-container {
                    border: 2px solid #000;
                    padding: 15px;
                    max-width: 300px;
                    margin: 0 auto;
                }
                .qr-code {
                    text-align: center;
                    margin-bottom: 15px;
                }
                .qr-code img {
                    max-width: 100%;
                    height: auto;
                }
                .module-info {
                    font-size: 12px;
                    line-height: 1.4;
                    margin-bottom: 10px;
                }
                .module-info strong {
                    display: block;
                    margin-top: 5px;
                }
                .header {
                    text-align: center;
                    border-bottom: 2px solid #333;
                    padding-bottom: 10px;
                    margin-bottom: 10px;
                }
                .qr-data {
                    font-size: 10px;
                    background: #f5f5f5;
                    padding: 5px;
                    border-radius: 3px;
                    margin-top: 10px;
                    word-break: break-all;
                }
                @media print {
                    body { margin: 0; padding: 10px; }
                    .label-container { border: 1px solid #000; }
                }
            </style>
        </head>
        <body>
            <div class="label-container">
                <div class="header">
                    <h3 style="margin: 0; color: #2196F3;">MDVA</h3>
                    <p style="margin: 0; font-size: 14px;">Module de Donation</p>
                    <p style="margin: 0; font-size: 12px; color: #666;">Scanner avec l'App MDVA</p>
                </div>
                
                <div class="qr-code">
                    <img src="${window.currentModuleQR.url}" alt="Code QR Module MDVA">
                </div>
                
                <div class="module-info">
                    <strong>Module ID :</strong> ${module.module_id}
                    <strong>Nom :</strong> ${module.module_name}
                    ${module.mac_address ? `<strong>MAC :</strong> ${module.mac_address}` : ''}
                    <strong>Lieu :</strong> ${location}
                </div>
                
                <div class="qr-data">
                    <strong>Données scannées :</strong><br>
                    ${JSON.stringify(qrData)}
                </div>
                
                <div style="text-align: center; margin-top: 10px; font-size: 10px; color: #666;">
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

// Charger les modules existants depuis la base de données
function loadExistingModules() {
    fetch('../api/modules.php?action=get_all')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.modules && data.modules.length > 0) {
                const modules = data.modules;
                
                // Créer une boîte de dialogue pour sélectionner un module
                const moduleList = modules.map(module => 
                    `<option value="${module.module_id}">${module.module_id} - ${module.name}</option>`
                ).join('');
                
                const moduleSelectHTML = `
                    <div class="mb-3">
                        <label class="form-label">Modules existants</label>
                        <select class="form-control" id="existingModuleSelect" onchange="fillModuleData(this.value)">
                            <option value="">-- Sélectionner un module existant --</option>
                            ${moduleList}
                        </select>
                    </div>
                `;
                
                // Insérer avant le formulaire
                const form = document.getElementById('moduleForm');
                const firstField = form.querySelector('.mb-3');
                if (firstField) {
                    firstField.insertAdjacentHTML('beforebegin', moduleSelectHTML);
                }
            }
        })
        .catch(error => {
            console.error('Erreur chargement modules:', error);
        });
}

// Remplir le formulaire avec les données d'un module existant
function fillModuleData(moduleId) {
    if (!moduleId) return;
    
    fetch('../api/modules.php?action=status&module_id=' + moduleId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.module) {
                const module = data.module;
                
                // Remplir le formulaire
                document.getElementById('moduleId').value = module.module_id || '';
                document.getElementById('moduleName').value = module.name || '';
                document.getElementById('locationName').value = module.location_name || module.location || '';
                
                // Remplir les champs d'adresse si disponibles
                if (module.address) document.getElementById('address').value = module.address;
                if (module.city) document.getElementById('city').value = module.city;
                if (module.province) document.getElementById('province').value = module.province;
                if (module.postal_code) document.getElementById('postalCode').value = module.postal_code;
            }
        })
        .catch(error => {
            console.error('Erreur chargement module:', error);
        });
}

// Charger les modules existants au chargement de la page
window.addEventListener('DOMContentLoaded', loadExistingModules);
</script>

<?php include '../includes/footer.php'; ?>