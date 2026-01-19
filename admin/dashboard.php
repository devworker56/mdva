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
                                    <a href="generate_all_qr_codes.php" class="btn btn-outline-primary ms-2">
                                        <i class="fas fa-external-link-alt"></i> Ouvrir le Générateur Avancé
                                    </a>
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
                                <button id="downloadQR" class="btn btn-primary mt-2 w-100" disabled onclick="downloadQRCode()">
                                    <i class="fas fa-download"></i> Télécharger le QR Code
                                </button>
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
        module_id: document.getElementById('moduleId').value.trim(),
        module_name: document.getElementById('moduleName').value.trim(),
        mac_address: document.getElementById('macAddress').value.trim(),
        location_name: document.getElementById('locationName').value.trim(),
        address: document.getElementById('address').value.trim(),
        city: document.getElementById('city').value.trim(),
        province: document.getElementById('province').value.trim(),
        postal_code: document.getElementById('postalCode').value.trim()
    };
    
    // Valider les champs requis
    if (!moduleData.module_id || !moduleData.module_name || !moduleData.location_name) {
        alert('Veuillez remplir tous les champs obligatoires (ID du Module, Nom du Module, Nom du Lieu)');
        return;
    }
    
    // Afficher le chargement
    document.getElementById('qrPreview').innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Génération du code QR du module...</p>
        </div>
    `;
    document.getElementById('moduleInfo').style.display = 'none';
    document.getElementById('qrDataPreview').style.display = 'none';
    document.getElementById('printQR').disabled = true;
    document.getElementById('downloadQR').disabled = true;
    
    // Create FormData for submission
    const formData = new FormData();
    formData.append('module_id', moduleData.module_id);
    formData.append('module_name', moduleData.module_name);
    formData.append('mac_address', moduleData.mac_address);
    formData.append('location_name', moduleData.location_name);
    formData.append('address', moduleData.address);
    formData.append('city', moduleData.city);
    formData.append('province', moduleData.province);
    formData.append('postal_code', moduleData.postal_code);
    formData.append('generate_single', '1');
    
    // Send to generate_all_qr_codes.php
    fetch('generate_all_qr_codes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        return response.text();
    })
    .then(html => {
        // Parse the HTML response
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Check for errors
        const errorAlert = doc.querySelector('.alert-danger');
        if (errorAlert) {
            throw new Error(errorAlert.textContent.trim());
        }
        
        // Look for success message
        const successAlert = doc.querySelector('.alert-success');
        const successMessage = successAlert ? successAlert.textContent.trim() : null;
        
        // Look for the QR code image in the preview section
        const previewSection = doc.querySelector('#codePreview');
        const qrImg = previewSection ? previewSection.querySelector('img') : null;
        
        // Build location string
        let location = moduleData.location_name;
        if (moduleData.address) location += ', ' + moduleData.address;
        if (moduleData.city) location += ', ' + moduleData.city;
        if (moduleData.province) location += ', ' + moduleData.province;
        if (moduleData.postal_code) location += ' ' + moduleData.postal_code;
        
        if (qrImg && qrImg.src) {
            // Display the QR code
            displayQRCode(qrImg.src, moduleData, location, successMessage);
        } else if (successMessage) {
            // QR might already exist - try to display existing one
            const existingQrPath = 'qr_codes/mdva_module_' + moduleData.module_id + '.png';
            
            // Check if file exists by trying to load it
            const testImg = new Image();
            testImg.onload = function() {
                displayQRCode(existingQrPath, moduleData, location, successMessage + ' (fichier existant)');
            };
            testImg.onerror = function() {
                // File doesn't exist or can't be loaded
                document.getElementById('qrPreview').innerHTML = `
                    <div class="alert alert-success">
                        ${successMessage}
                    </div>
                    <p>Le code QR a été généré avec succès.</p>
                    <p>Redirection vers le générateur pour voir le résultat...</p>
                `;
                // Redirect to the full generator to see the result
                setTimeout(() => {
                    window.open('generate_all_qr_codes.php', '_blank');
                }, 2000);
            };
            testImg.src = existingQrPath + '?t=' + new Date().getTime();
        } else {
            // No QR found, check if we should redirect
            document.getElementById('qrPreview').innerHTML = `
                <div class="alert alert-warning">
                    Génération terminée mais aperçu non disponible.
                </div>
                <p>Ouvrir le générateur complet pour voir le résultat...</p>
                <button onclick="window.open('generate_all_qr_codes.php', '_blank')" class="btn btn-primary">
                    Ouvrir le Générateur
                </button>
            `;
            
            // Still show module info
            showModuleInfo(moduleData, location);
        }
    })
    .catch(error => {
        console.error('Erreur :', error);
        document.getElementById('qrPreview').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> Erreur : ${error.message}
            </div>
            <p>Essayez d'utiliser le <a href="generate_all_qr_codes.php" target="_blank" class="btn btn-sm btn-primary">générateur avancé</a> directement.</p>
        `;
    });
});

// Function to display QR code
function displayQRCode(qrSrc, moduleData, location, successMessage) {
    // Clean the src - remove any ../ from the path
    const cleanSrc = qrSrc.replace(/\.\.\//g, '');
    
    // Display the QR code
    let previewHTML = `
        <img src="${cleanSrc}" alt="QR Code Module MDVA" 
             style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 10px; background: white;"
             onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"200\" height=\"200\"><rect width=\"100%\" height=\"100%\" fill=\"#f8f9fa\"/><text x=\"50%\" y=\"50%\" text-anchor=\"middle\" dy=\".3em\" fill=\"#6c757d\">QR Code</text></svg>'">
        <p class="mt-2"><strong>${moduleData.module_id}</strong></p>
    `;
    
    if (successMessage) {
        previewHTML = `
            <div class="alert alert-success">
                ${successMessage}
            </div>
            ${previewHTML}
        `;
    }
    
    document.getElementById('qrPreview').innerHTML = previewHTML;
    
    // Show module info
    showModuleInfo(moduleData, location);
    
    // Create QR data for display
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
    
    // Display QR data
    document.getElementById('qrDataContent').textContent = JSON.stringify(qrData, null, 2);
    document.getElementById('qrDataPreview').style.display = 'block';
    
    // Enable buttons
    document.getElementById('printQR').disabled = false;
    document.getElementById('downloadQR').disabled = false;
    
    // Store data for printing/downloading
    window.currentModuleQR = {
        url: cleanSrc,
        moduleData: moduleData,
        qrData: qrData,
        location: location,
        filename: 'mdva_module_' + moduleData.module_id + '.png'
    };
}

// Function to show module info
function showModuleInfo(moduleData, location) {
    document.getElementById('moduleDetails').innerHTML = `
        <strong>ID du Module :</strong> ${moduleData.module_id}<br>
        <strong>Nom du Module :</strong> ${moduleData.module_name}<br>
        <strong>Adresse MAC :</strong> ${moduleData.mac_address || 'Non spécifiée'}<br>
        <strong>Emplacement :</strong> ${location}<br>
        <strong>Système :</strong> MDVA Donation Module<br>
        <strong>Type :</strong> Station de Don à Pièces
    `;
    document.getElementById('moduleInfo').style.display = 'block';
}

// Print QR code function
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
                    background: white;
                }
                .label-container {
                    border: 2px solid #2196F3;
                    padding: 20px;
                    max-width: 350px;
                    margin: 0 auto;
                    border-radius: 10px;
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
                    margin: 15px 0;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 5px;
                    font-size: 12px;
                }
                .module-info h4 {
                    margin: 0 0 10px 0;
                    color: #333;
                    font-size: 14px;
                }
                .module-id {
                    font-size: 16px;
                    font-weight: bold;
                    color: #2196F3;
                    margin: 5px 0;
                }
                .instructions {
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 1px solid #eee;
                    font-size: 11px;
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
                    .label-container {
                        border: 1px solid #000;
                        max-width: 100%;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="label-container">
                <div class="header">
                    <h2>MDVA</h2>
                    <p>Système de Donation Modulaire ESP32</p>
                </div>
                
                <div class="qr-container">
                    <img src="${window.currentModuleQR.url}" alt="Code QR Module MDVA" 
                         onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"250\" height=\"250\"><rect width=\"100%\" height=\"100%\" fill=\"#f8f9fa\"/><text x=\"50%\" y=\"50%\" text-anchor=\"middle\" dy=\".3em\" fill=\"#6c757d\">QR Code</text></svg>'">
                </div>
                
                <div class="module-info">
                    <h4>Module ESP32</h4>
                    <div class="module-id">${module.module_id}</div>
                    <p><strong>Nom:</strong> ${module.module_name}</p>
                    ${module.mac_address ? `<p><strong>MAC:</strong> ${module.mac_address}</p>` : ''}
                    <p><strong>Lieu:</strong> ${location}</p>
                </div>
                
                <div class="instructions">
                    <p><strong>Instructions :</strong></p>
                    <p>1. Scanner ce code QR avec l'application mobile MDVA</p>
                    <p>2. Le module sera automatiquement reconnu</p>
                    <p>3. Prêt à recevoir des dons</p>
                </div>
                
                <div class="footer">
                    <p>Système MDVA - https://systeme-mdva.com</p>
                    <p>Généré le ${new Date().toLocaleDateString('fr-CA')} à ${new Date().toLocaleTimeString('fr-CA', {hour: '2-digit', minute:'2-digit'})}</p>
                </div>
                
                <div class="no-print" style="text-align: center; margin-top: 20px;">
                    <button onclick="window.print();" style="padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <button onclick="window.close();" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                        <i class="fas fa-times"></i> Fermer
                    </button>
                </div>
            </div>
            <script>
                window.onload = function() {
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

// Download QR code function
function downloadQRCode() {
    if (!window.currentModuleQR) {
        alert('Aucun code QR à télécharger');
        return;
    }
    
    const link = document.createElement('a');
    link.href = window.currentModuleQR.url;
    link.download = window.currentModuleQR.filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Load existing modules for auto-fill
function loadExistingModules() {
    fetch('../api/modules.php?action=get_all')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.modules && data.modules.length > 0) {
                const modules = data.modules;
                
                // Create a select dropdown for existing modules
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
                
                // Insert before the first form field
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

// Fill form with existing module data
function fillModuleData(moduleId) {
    if (!moduleId) return;
    
    fetch('../api/modules.php?action=status&module_id=' + encodeURIComponent(moduleId))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.module) {
                const module = data.module;
                
                // Fill the form
                document.getElementById('moduleId').value = module.module_id || '';
                document.getElementById('moduleName').value = module.name || '';
                document.getElementById('locationName').value = module.location_name || module.location || '';
                
                // Fill address fields if available
                if (module.address) document.getElementById('address').value = module.address;
                if (module.city) document.getElementById('city').value = module.city;
                if (module.province) document.getElementById('province').value = module.province;
                if (module.postal_code) document.getElementById('postalCode').value = module.postal_code;
                if (module.mac_address) document.getElementById('macAddress').value = module.mac_address;
            }
        })
        .catch(error => {
            console.error('Erreur chargement module:', error);
        });
}

// Load existing modules when page loads
window.addEventListener('DOMContentLoaded', loadExistingModules);
</script>

<?php include '../includes/footer.php'; ?>