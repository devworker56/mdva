<?php
// charity_dashboard.php - UPDATED VERSION WITH ONLY CHARITY-RELEVANT FEATURES
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'charity') {
    header("Location: ../auth/login.php");
    exit();
}
include '../includes/header.php';

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$charity_id = $_SESSION['user_id'];

// Get charity stats
$query = "SELECT 
            SUM(amount) as total_donations, 
            COUNT(*) as donation_count,
            AVG(amount) as average_donation,
            COUNT(DISTINCT session_id) as unique_sessions
          FROM donations 
          WHERE charity_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$charity_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get charity name
$charity_query = "SELECT name FROM charities WHERE id = ?";
$charity_stmt = $db->prepare($charity_query);
$charity_stmt->execute([$charity_id]);
$charity = $charity_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent donations with location info (for charity insight)
$query = "SELECT 
            d.amount, 
            d.created_at, 
            d.coin_count,
            d.session_id,
            m.name as module_name,
            l.name as location_name,
            l.city, 
            l.province
          FROM donations d 
          LEFT JOIN modules m ON d.module_id = m.module_id
          LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
          LEFT JOIN locations l ON ml.location_id = l.id
          WHERE d.charity_id = ? 
          ORDER BY d.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$charity_id]);
$recent_donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's donations
$today_query = "SELECT SUM(amount) as today_total, COUNT(*) as today_count
                FROM donations 
                WHERE charity_id = ? 
                AND DATE(created_at) = CURDATE()";
$today_stmt = $db->prepare($today_query);
$today_stmt->execute([$charity_id]);
$today_stats = $today_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Tableau de Bord - <?php echo htmlspecialchars($charity['name'] ?? $_SESSION['charity_name']); ?></h2>
    
    <!-- Real-time Status Indicator -->
    <div class="alert alert-info d-flex align-items-center" role="alert">
        <div class="spinner-grow spinner-grow-sm me-2" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span id="realtime-status">Connecté aux mises à jour en temps réel</span>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title" id="total-donations"><?php echo number_format($stats['total_donations'] ?? 0, 2); ?> $</h5>
                    <p class="card-text">Dons Totaux</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title" id="donation-count"><?php echo $stats['donation_count'] ?? 0; ?></h5>
                    <p class="card-text">Nombre Total de Dons</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title"><?php echo number_format($stats['average_donation'] ?? 0, 2); ?> $</h5>
                    <p class="card-text">Moyenne par Don</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title" id="today-total"><?php echo number_format($today_stats['today_total'] ?? 0, 2); ?> $</h5>
                    <p class="card-text">Dons Aujourd'hui</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Donations -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Dons Récents</h5>
                    <div>
                        <span class="badge bg-info" id="new-donations-badge" style="display: none;">Nouveau!</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshDonations()">
                            <i class="bi bi-arrow-clockwise"></i> Rafraîchir
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="donations-container">
                        <?php if(empty($recent_donations)): ?>
                            <p class="text-muted">Aucun don pour le moment.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Montant</th>
                                            <th>Pièces</th>
                                            <th>Lieu/Module</th>
                                            <th>Session</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="donations-table">
                                        <?php foreach($recent_donations as $donation): ?>
                                        <tr>
                                            <td><?php echo number_format($donation['amount'], 2); ?> $</td>
                                            <td><?php echo $donation['coin_count']; ?></td>
                                            <td>
                                                <?php 
                                                if ($donation['location_name']) {
                                                    echo htmlspecialchars($donation['location_name']) . '<br>';
                                                    echo '<small class="text-muted">' . 
                                                         htmlspecialchars($donation['city']) . ', ' . 
                                                         htmlspecialchars($donation['province']) . '</small>';
                                                } else if ($donation['module_name']) {
                                                    echo htmlspecialchars($donation['module_name']);
                                                } else {
                                                    echo 'Inconnu';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo substr($donation['session_id'], 0, 8); ?>...</small>
                                            </td>
                                            <td><?php echo date('j M Y H:i', strtotime($donation['created_at'])); ?></td>
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

    <!-- Performance by Location -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Performance par Lieu</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get donations by location for this charity
                    $location_query = "SELECT 
                                        l.name as location_name,
                                        l.city,
                                        l.province,
                                        COUNT(d.id) as donation_count,
                                        SUM(d.amount) as total_amount
                                      FROM donations d
                                      LEFT JOIN modules m ON d.module_id = m.module_id
                                      LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
                                      LEFT JOIN locations l ON ml.location_id = l.id
                                      WHERE d.charity_id = ? 
                                        AND l.name IS NOT NULL
                                      GROUP BY l.id
                                      ORDER BY total_amount DESC
                                      LIMIT 5";
                    $location_stmt = $db->prepare($location_query);
                    $location_stmt->execute([$charity_id]);
                    $location_stats = $location_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if(empty($location_stats)): ?>
                        <p class="text-muted">Aucune donnée de localisation disponible.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach($location_stats as $location): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($location['location_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($location['city']) . ', ' . htmlspecialchars($location['province']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo number_format($location['total_amount'], 2); ?> $
                                        </span>
                                        <br>
                                        <small><?php echo $location['donation_count']; ?> dons</small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Statistiques du Mois</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get monthly stats
                    $monthly_query = "SELECT 
                                        DATE_FORMAT(created_at, '%Y-%m') as month,
                                        SUM(amount) as monthly_total,
                                        COUNT(*) as monthly_count
                                      FROM donations 
                                      WHERE charity_id = ? 
                                        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                                      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                                      ORDER BY month DESC";
                    $monthly_stmt = $db->prepare($monthly_query);
                    $monthly_stmt->execute([$charity_id]);
                    $monthly_stats = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if(empty($monthly_stats)): ?>
                        <p class="text-muted">Aucune donnée pour les derniers mois.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach($monthly_stats as $month): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></span>
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo number_format($month['monthly_total'], 2); ?> $ (<?php echo $month['monthly_count']; ?>)
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Pusher -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
// Initialize Pusher
const pusher = new Pusher('fe6f264f2fba2f7bc4a2', {
    cluster: 'us2',
    encrypted: true
});

// Subscribe to charity channel
const channel = pusher.subscribe('charity_<?php echo $charity_id; ?>');

// Listen for new donations
channel.bind('new_donation', function(data) {
    console.log('Nouveau don reçu:', data);
    
    // Verify this is for the correct charity
    if (data.charity_id != <?php echo $charity_id; ?>) {
        return;
    }
    
    // Update total donations amount
    const totalElement = document.getElementById('total-donations');
    const currentTotal = parseFloat(totalElement.textContent.replace('$', '').replace(',', '').replace(' ', ''));
    const newTotal = (currentTotal + parseFloat(data.amount)).toFixed(2);
    totalElement.textContent = newTotal + ' $';
    
    // Update donation count
    const countElement = document.getElementById('donation-count');
    const currentCount = parseInt(countElement.textContent);
    countElement.textContent = (currentCount + 1).toString();
    
    // Update today's total
    const todayElement = document.getElementById('today-total');
    const todayTotal = parseFloat(todayElement.textContent.replace('$', '').replace(',', '').replace(' ', ''));
    const newTodayTotal = (todayTotal + parseFloat(data.amount)).toFixed(2);
    todayElement.textContent = newTodayTotal + ' $';
    
    // Add new donation to table
    addDonationToTable(data);
    
    // Show notification badge
    const badge = document.getElementById('new-donations-badge');
    badge.style.display = 'inline-block';
    
    // Show toast notification
    showToastNotification(data);
});

// Function to add donation to table
function addDonationToTable(data) {
    const tableBody = document.getElementById('donations-table');
    
    // Format location info
    let locationInfo = data.location_name || data.module_name || 'Inconnu';
    if (data.city && data.province) {
        locationInfo += '<br><small class="text-muted">' + data.city + ', ' + data.province + '</small>';
    }
    
    // Format session ID
    const sessionId = data.session_id ? data.session_id.substring(0, 8) + '...' : 'N/A';
    
    // Format timestamp
    const timestamp = data.timestamp ? new Date(data.timestamp) : new Date();
    
    // Create new row
    const newRow = document.createElement('tr');
    newRow.className = 'new-donation';
    newRow.innerHTML = `
        <td>$${parseFloat(data.amount).toFixed(2)}</td>
        <td>${data.coin_count || 0}</td>
        <td>${locationInfo}</td>
        <td><small class="text-muted">${sessionId}</small></td>
        <td>${timestamp.toLocaleDateString('fr-CA', { 
            day: 'numeric', 
            month: 'short', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })}</td>
    `;
    
    // Add at the top of the table
    if (tableBody.firstChild) {
        tableBody.insertBefore(newRow, tableBody.firstChild);
    } else {
        tableBody.appendChild(newRow);
    }
    
    // Highlight new row
    setTimeout(() => {
        newRow.classList.remove('new-donation');
    }, 3000);
    
    // Remove oldest row if more than 10
    if (tableBody.children.length > 10) {
        tableBody.removeChild(tableBody.lastChild);
    }
}

// Function to show toast notification
function showToastNotification(data) {
    const toastContainer = document.createElement('div');
    toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
    toastContainer.style.zIndex = '1050';
    
    let locationText = '';
    if (data.location_name) {
        locationText = `<br><small>${data.location_name}</small>`;
    } else if (data.module_name) {
        locationText = `<br><small>${data.module_name}</small>`;
    }
    
    toastContainer.innerHTML = `
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">🎉 Nouveau don reçu!</strong>
                <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.parentElement.parentElement.remove()"></button>
            </div>
            <div class="toast-body">
                <strong>$${parseFloat(data.amount).toFixed(2)}</strong>${locationText}
                ${data.coin_count ? `<br><small>${data.coin_count} pièces</small>` : ''}
            </div>
        </div>
    `;
    
    document.body.appendChild(toastContainer);
    
    // Remove toast after 5 seconds
    setTimeout(() => {
        if (toastContainer.parentNode) {
            toastContainer.remove();
        }
    }, 5000);
}

// Function to refresh donations manually
function refreshDonations() {
    fetch(`../api/donations.php?action=charity_donations&charity_id=<?php echo $charity_id; ?>&limit=10`)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                updateDonationsTable(data.donations);
                // Hide notification badge
                document.getElementById('new-donations-badge').style.display = 'none';
            }
        })
        .catch(error => console.error('Erreur de rafraîchissement:', error));
}

function updateDonationsTable(donations) {
    const tableBody = document.getElementById('donations-table');
    tableBody.innerHTML = '';
    
    donations.forEach(donation => {
        // Format location info
        let locationInfo = donation.location_name || donation.module_name || 'Inconnu';
        if (donation.city && donation.province) {
            locationInfo += '<br><small class="text-muted">' + donation.city + ', ' + donation.province + '</small>';
        }
        
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>$${parseFloat(donation.amount).toFixed(2)}</td>
            <td>${donation.coin_count || 0}</td>
            <td>${locationInfo}</td>
            <td><small class="text-muted">${donation.session_id ? donation.session_id.substring(0, 8) + '...' : 'N/A'}</small></td>
            <td>${new Date(donation.created_at).toLocaleDateString('fr-CA', { 
                day: 'numeric', 
                month: 'short', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })}</td>
        `;
        tableBody.appendChild(row);
    });
}
</script>

<style>
.new-donation {
    background-color: rgba(25, 135, 84, 0.1) !important;
    animation: highlight 3s ease-in-out;
}

@keyframes highlight {
    0% { background-color: rgba(25, 135, 84, 0.3); }
    100% { background-color: rgba(25, 135, 84, 0.1); }
}

.toast {
    min-width: 300px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

#realtime-status {
    font-weight: 500;
}
</style>

<?php include '../includes/footer.php'; ?>