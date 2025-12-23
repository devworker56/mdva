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
<!-- Ajoutez ceci au tableau de bord après la section de gestion des organismes existante -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Générateur de Code QR pour Station</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6>Information de la Station</h6>
                                <form id="stationForm">
                                    <div class="mb-3">
                                        <label for="stationId" class="form-label">ID de la Station *</label>
                                        <input type="text" class="form-control" id="stationId" 
                                               placeholder="Entrez l'ID de la station" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="locationName" class="form-label">Nom du Lieu *</label>
                                        <input type="text" class="form-control" id="locationName" 
                                               placeholder="ex: Centre Commercial Montréal Central" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Adresse *</label>
                                        <input type="text" class="form-control" id="address" 
                                               placeholder="Adresse" required>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="city" class="form-label">Ville *</label>
                                                <input type="text" class="form-control" id="city" 
                                                       placeholder="Ville" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="province" class="form-label">Province *</label>
                                                <input type="text" class="form-control" id="province" 
                                                       placeholder="Province" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="postalCode" class="form-label">Code Postal *</label>
                                        <input type="text" class="form-control" id="postalCode" 
                                               placeholder="A1A 1A1" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-qrcode"></i> Générer le Code QR de la Station
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
                                    <p class="text-muted">Le code QR de la station apparaîtra ici</p>
                                </div>
                                
                                <div id="stationInfo" class="mb-3" style="display: none;">
                                    <h6>Détails de la Station :</h6>
                                    <div id="stationDetails" class="small"></div>
                                </div>
                                
                                <button id="printQR" class="btn btn-success w-100" disabled onclick="printStationQRCode()">
                                    <i class="fas fa-print"></i> Imprimer le Code QR de la Station
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
// Gérer la soumission du formulaire de station
document.getElementById('stationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Collecter toutes les données du formulaire
    const stationData = {
        stationId: document.getElementById('stationId').value,
        locationName: document.getElementById('locationName').value,
        address: document.getElementById('address').value,
        city: document.getElementById('city').value,
        province: document.getElementById('province').value,
        postalCode: document.getElementById('postalCode').value
    };
    
    // Valider les champs requis
    for (const key in stationData) {
        if (!stationData[key].trim()) {
            alert('Veuillez remplir tous les champs obligatoires');
            return;
        }
    }
    
    // Afficher le chargement
    document.getElementById('qrPreview').innerHTML = '<p>Génération du code QR de la station...</p>';
    document.getElementById('stationInfo').style.display = 'none';
    
    // Envoyer les données pour générer le code QR
    const formData = new FormData();
    formData.append('data', JSON.stringify(stationData));
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
            
            // Afficher les détails de la station
            document.getElementById('stationDetails').innerHTML = `
                <strong>ID de la Station :</strong> ${stationData.stationId}<br>
                <strong>Lieu :</strong> ${stationData.locationName}<br>
                <strong>Adresse :</strong> ${stationData.address}<br>
                <strong>Ville :</strong> ${stationData.city}<br>
                <strong>Province :</strong> ${stationData.province}<br>
                <strong>Code Postal :</strong> ${stationData.postalCode}
            `;
            document.getElementById('stationInfo').style.display = 'block';
            
            // Activer le bouton d'impression
            document.getElementById('printQR').disabled = false;
            
            // Stocker les données pour l'impression
            window.currentStationQR = {
                url: data.barcode_url,
                stationData: stationData
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

function printStationQRCode() {
    if (!window.currentStationQR) {
        alert('Aucun code QR à imprimer');
        return;
    }
    
    const station = window.currentStationQR.stationData;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Imprimer le Code QR de la Station - ${station.stationId}</title>
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
                .station-info {
                    font-size: 12px;
                    line-height: 1.4;
                }
                .station-info strong {
                    display: block;
                    margin-top: 5px;
                }
                .header {
                    text-align: center;
                    border-bottom: 2px solid #333;
                    padding-bottom: 10px;
                    margin-bottom: 10px;
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
                    <p style="margin: 0; font-size: 14px;">Station de Don</p>
                </div>
                
                <div class="qr-code">
                    <img src="${window.currentStationQR.url}" alt="Code QR de la Station">
                </div>
                
                <div class="station-info">
                    <strong>ID de la Station :</strong> ${station.stationId}
                    <strong>Lieu :</strong> ${station.locationName}
                    <strong>Adresse :</strong> ${station.address}
                    <strong>Ville :</strong> ${station.city}, ${station.province}
                    <strong>Code Postal :</strong> ${station.postalCode}
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
</script>
<?php include '../includes/footer.php'; ?>