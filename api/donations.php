<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Obtenir la méthode de requête et l'action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Obtenir les données d'entrée selon la méthode
if ($method == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST; // Retour aux données de formulaire
    }
} else {
    $input = $_GET;
}

// Activer CORS pour l'application React Native
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer la requête OPTIONS de pré-vérification
if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch($action) {
        case 'record_donation':
            if ($method == 'POST') {
                recordDonation($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        case 'stats':
            if ($method == 'GET') {
                getDonationStats($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        case 'history':
            if ($method == 'GET') {
                getDonationHistory($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        case 'tax_receipts':
            if ($method == 'GET') {
                getTaxReceipts($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        case 'charity_donations':
            if ($method == 'GET') {
                getCharityDonations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        case 'recent_donations':
            if ($method == 'GET') {
                getRecentDonations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        case 'session_donations':
            if ($method == 'GET') {
                getSessionDonations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action invalide']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur : ' . $e->getMessage()]);
}

/**
 * Enregistrer un don depuis le Module MDVA - AVEC VALIDATION DE SESSION
 */
function recordDonation($db, $data) {
    error_log("Enregistrement du don : " . json_encode($data));
    
    $module_id = $data['module_id'] ?? '';
    $amount = $data['amount'] ?? 0;
    $coin_count = $data['coin_count'] ?? 0;
    $session_id = $data['session_id'] ?? '';
    $session_token = $data['session_token'] ?? '';
    
    if (empty($module_id) || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champs requis manquants : module_id et montant > 0 requis']);
        return;
    }
    
    try {
        // Vérifier qu'une session active existe pour ce module
        $session_query = "SELECT ds.*, d.id as donor_id, d.user_id as donor_user_id, 
                                 c.id as charity_id, c.name as charity_name
                          FROM donation_sessions ds
                          JOIN donors d ON ds.donor_id = d.id
                          JOIN charities c ON ds.charity_id = c.id
                          WHERE ds.module_id = ? AND ds.status = 'active' 
                          AND ds.expires_at > NOW()
                          ORDER BY ds.started_at DESC LIMIT 1";
        
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$module_id]);
        $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Aucune session de don active pour ce module']);
            return;
        }
        
        $donor_id = $session['donor_id'];
        $charity_id = $session['charity_id'];
        $session_id = $session['id'];
        
        error_log("Session active trouvée : donor_id=$donor_id, charity_id=$charity_id, session_id=$session_id");
        
        // Utiliser la procédure stockée pour enregistrer le don avec validation appropriée
        $stmt = $db->prepare("CALL RecordDonationWithStats(?, ?, ?, ?, ?, ?)");
        $stmt->execute([$donor_id, $charity_id, $amount, $coin_count, $module_id, $session_id]);
        
        $donation_id = $db->lastInsertId();
        error_log("Don enregistré avec succès avec l'ID : " . $donation_id);
        
        // Obtenir les informations de session mises à jour
        $session_query = "SELECT total_amount, total_coins FROM donation_sessions WHERE id = ?";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$session_id]);
        $updated_session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notifier via Pusher pour les mises à jour en temps réel
        notify_pusher('donation_received', [
            'donation_id' => $donation_id,
            'donor_id' => $donor_id,
            'donor_user_id' => $session['donor_user_id'],
            'charity_id' => $charity_id,
            'charity_name' => $session['charity_name'],
            'amount' => $amount,
            'session_id' => $session_id,
            'module_id' => $module_id,
            'session_total' => $updated_session['total_amount'],
            'timestamp' => date('Y-m-d H:i:s')
        ], "user_" . $session['donor_user_id']);
        
        $response = [
            'success' => true, 
            'message' => 'Don enregistré avec succès',
            'donation_id' => $donation_id,
            'donor_id' => $session['donor_user_id'],
            'charity_id' => $charity_id,
            'charity_name' => $session['charity_name'],
            'amount' => $amount,
            'module_id' => $module_id,
            'session_id' => $session_id,
            'session_total' => $updated_session['total_amount'],
            'session_coins' => $updated_session['total_coins']
        ];
        
        error_log("Envoi de la réponse de succès : " . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Exception d'enregistrement de don : " . $e->getMessage());
        error_log("Trace de l'exception : " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Erreur du serveur : ' . $e->getMessage()]);
    }
}

/**
 * Obtenir les statistiques de dons pour un donateur
 */
function getDonationStats($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de donateur requis']);
        return;
    }
    
    $query = "SELECT 
                SUM(amount) as total_donated, 
                COUNT(*) as donation_count,
                AVG(amount) as average_donation,
                MAX(amount) as largest_donation,
                MIN(amount) as smallest_donation,
                MAX(created_at) as last_donation_date
              FROM donations 
              WHERE donor_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtenir la distribution par organisme (basée sur les dons réels, pas les préférences)
    $query = "SELECT 
                c.name as charity_name,
                c.id as charity_id,
                SUM(d.amount) as total_donated,
                COUNT(*) as donation_count
              FROM donations d
              JOIN charities c ON d.charity_id = c.id
              WHERE d.donor_id = ?
              GROUP BY d.charity_id
              ORDER BY total_donated DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $charity_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'stats' => $stats ?: [
            'total_donated' => 0,
            'donation_count' => 0,
            'average_donation' => 0,
            'largest_donation' => 0,
            'smallest_donation' => 0,
            'last_donation_date' => null
        ],
        'charity_distribution' => $charity_distribution
    ]);
}

/**
 * Obtenir l'historique des dons pour un donateur
 */
function getDonationHistory($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de donateur requis']);
        return;
    }
    
    try {
        $query = "SELECT 
                    d.id,
                    d.amount,
                    d.created_at,
                    d.module_id,
                    d.session_id,
                    c.name as charity_name,
                    c.id as charity_id,
                    ds.started_at as session_started
                  FROM donations d
                  JOIN charities c ON d.charity_id = c.id
                  JOIN donation_sessions ds ON d.session_id = ds.id
                  WHERE d.donor_id = ?
                  ORDER BY d.created_at DESC
                  LIMIT 50";
        $stmt = $db->prepare($query);
        $stmt->execute([$donor_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'history' => $history,
            'count' => count($history)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Erreur d'historique des dons : " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Échec du chargement de l\'historique des dons : ' . $e->getMessage()]);
    }
}

/**
 * Obtenir les dons pour une session spécifique
 */
function getSessionDonations($db, $data) {
    $session_id = $data['session_id'] ?? '';
    $donor_id = $data['donor_id'] ?? '';
    
    if (empty($session_id) || empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de session et ID de donateur requis']);
        return;
    }
    
    try {
        $query = "SELECT 
                    d.id,
                    d.amount,
                    d.coin_count,
                    d.created_at,
                    c.name as charity_name,
                    m.name as module_name
                  FROM donations d
                  JOIN charities c ON d.charity_id = c.id
                  JOIN modules m ON d.module_id = m.module_id
                  WHERE d.session_id = ? AND d.donor_id = ?
                  ORDER BY d.created_at ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([$session_id, $donor_id]);
        $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtenir les informations de session
        $session_query = "SELECT ds.*, c.name as charity_name 
                         FROM donation_sessions ds
                         JOIN charities c ON ds.charity_id = c.id
                         WHERE ds.id = ? AND ds.donor_id = ?";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$session_id, $donor_id]);
        $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'donations' => $donations,
            'session' => $session,
            'count' => count($donations)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Erreur des dons de session : " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Échec du chargement des dons de session : ' . $e->getMessage()]);
    }
}

// ... (conserver les autres fonctions existantes comme getTaxReceipts, getCharityDonations, getRecentDonations inchangées)
// Ces fonctions ne nécessitent pas de modifications pour la sélection d'organisme par session

/**
 * Obtenir les données de reçu fiscal pour un donateur
 */
function getTaxReceipts($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    $year = $data['year'] ?? date('Y');
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de donateur requis']);
        return;
    }
    
    $receipt_data = generate_tax_receipt_data($donor_id, $year, $db);
    
    echo json_encode([
        'success' => true, 
        'receipt' => $receipt_data,
        'donor_info' => getDonorInfo($donor_id, $db)
    ]);
}

/**
 * Obtenir les dons pour un organisme
 */
function getCharityDonations($db, $data) {
    $charity_id = $data['charity_id'] ?? '';
    $limit = $data['limit'] ?? 50;
    $offset = $data['offset'] ?? 0;
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID d\'organisme requis']);
        return;
    }
    
    $query = "SELECT 
                d.id,
                d.amount,
                d.created_at,
                d.module_id,
                do.user_id as donor_id,
                ds.id as session_id
              FROM donations d
              JOIN donors do ON d.donor_id = do.id
              JOIN donation_sessions ds ON d.session_id = ds.id
              WHERE d.charity_id = ?
              ORDER BY d.created_at DESC
              LIMIT ? OFFSET ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id, $limit, $offset]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtenir le total et le montant pour la pagination
    $query = "SELECT COUNT(*) as total, SUM(amount) as total_amount 
              FROM donations 
              WHERE charity_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'donations' => $donations,
        'pagination' => [
            'total' => $totals['total'],
            'total_amount' => $totals['total_amount'],
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
}

/**
 * Obtenir les dons récents dans tout le système
 */
function getRecentDonations($db, $data) {
    $limit = $data['limit'] ?? 10;
    
    $query = "SELECT 
                d.id,
                d.amount,
                d.created_at,
                do.user_id as donor_id,
                c.name as charity_name,
                ds.id as session_id
              FROM donations d
              JOIN donors do ON d.donor_id = do.id
              JOIN charities c ON d.charity_id = c.id
              JOIN donation_sessions ds ON d.session_id = ds.id
              ORDER BY d.created_at DESC
              LIMIT ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'recent_donations' => $donations
    ]);
}

/**
 * Fonction d'aide pour obtenir les informations du donateur
 */
function getDonorInfo($donor_id, $db) {
    $query = "SELECT user_id, email, created_at FROM donors WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>