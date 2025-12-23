<?php
// Activer le rapport d'erreurs maximum
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Journaliser chaque requête
error_log("=== SESSIONS.PHP ATTEINT ===");
error_log("Méthode de requête : " . $_SERVER['REQUEST_METHOD']);
error_log("URI de requête : " . $_SERVER['REQUEST_URI']);
error_log("Chaîne de requête : " . ($_SERVER['QUERY_STRING'] ?? 'Aucune'));
error_log("Type de contenu : " . ($_SERVER['CONTENT_TYPE'] ?? 'Aucun'));
error_log("Données d'entrée : " . file_get_contents('php://input'));

// Vérifier si les fichiers requis existent
$config_file = '../config/database.php';
$functions_file = '../includes/functions.php';

error_log("Le fichier de config existe : " . (file_exists($config_file) ? 'OUI' : 'NON'));
error_log("Le fichier de fonctions existe : " . (file_exists($functions_file) ? 'OUI' : 'NON'));

// Continuer avec votre code existant...
require_once $config_file;
require_once $functions_file;

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

// Journaliser la requête pour le débogage
error_log("Requête API Sessions : action=$action, input=" . json_encode($input));

// En-têtes CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch($action) {
        // AJOUTER LE CAS DE TEST ICI - au début
        case 'test_simple':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                error_log("Point de terminaison TEST_SIMPLE atteint");
                echo json_encode([
                    'success' => true,
                    'message' => 'Le test POST simple fonctionne !',
                    'data_received' => json_decode(file_get_contents('php://input'), true)
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST requis pour le test']);
            }
            break;
            
        // VOS CAS EXISTANTS SUIVENT...
        case 'start_session':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                startDonationSession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        case 'verify_session':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                verifySession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        case 'end_session':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                endDonationSession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        case 'get_active_session':
            if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                getActiveSession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action invalide : ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception de l'API Sessions : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur : ' . $e->getMessage()]);
}

function startDonationSession($db, $data) {
    error_log("startDonationSession appelé avec : " . json_encode($data));
    
    $donor_id = $data['donor_id'] ?? '';
    $charity_id = $data['charity_id'] ?? '';
    $module_id = $data['module_id'] ?? '';
    
    if (empty($donor_id) || empty($charity_id) || empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champs requis manquants : donor_id, charity_id et module_id sont requis']);
        return;
    }
    
    // Vérifier que le donateur existe
    $query = "SELECT id, user_id FROM donors WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donateur non trouvé']);
        return;
    }
    
    // Vérifier que l'organisme existe et est approuvé
    $query = "SELECT id, name FROM charities WHERE id = ? AND approved = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organisme non trouvé ou non approuvé']);
        return;
    }
    
    // Vérifier que le module existe et est actif
    $query = "SELECT id, name FROM modules WHERE module_id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$module_id]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$module) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Module non trouvé ou non actif']);
        return;
    }
    
    error_log("Donateur, organisme et module vérifiés avec succès");
    
    try {
        $db->beginTransaction();
        
        // Générer un jeton de session unique
        $session_token = 'sess_' . bin2hex(random_bytes(16));
        
        // Créer l'enregistrement de session de don
        $query = "INSERT INTO donation_sessions (donor_id, charity_id, module_id, session_token, status) 
                  VALUES (?, ?, ?, ?, 'active')";
        $stmt = $db->prepare($query);
        $stmt->execute([$donor_id, $charity_id, $module_id, $session_token]);
        
        $session_id = $db->lastInsertId();
        
        // Créer l'enregistrement vérifiable de session
        $transaction_hash = create_verifiable_donation_session(
            $donor_id,
            $donor['user_id'],
            $charity_id,
            $module_id,
            $session_id,
            $db
        );
        
        // Journaliser l'activité
        log_activity($db, 'donor', $donor_id, 'donation_session_started', 
            "Le donateur {$donor['user_id']} a démarré une session de don pour l'organisme '{$charity['name']}' via le module $module_id");
        
        $db->commit();
        
        // Notifier via Pusher du début de session
        $pusher_result = notify_pusher('session_started', [
            'session_id' => $session_id,
            'donor_id' => $donor_id,
            'charity_id' => $charity_id,
            'module_id' => $module_id,
            'charity_name' => $charity['name'],
            'timestamp' => date('Y-m-d H:i:s')
        ], "private-module_$module_id");

        error_log("Résultat de la notification Pusher : " . ($pusher_result ? "SUCCÈS" : "ÉCHEC"));
        
        $response = [
            'success' => true, 
            'message' => 'Session de don démarrée avec succès',
            'session' => [
                'session_id' => $session_id,
                'session_token' => $session_token,
                'donor_id' => $donor_id,
                'donor_user_id' => $donor['user_id'],
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'module_id' => $module_id,
                'module_name' => $module['name'],
                'transaction_hash' => $transaction_hash,
                'started_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + 900), // 15 minutes
                'verifiable' => true
            ]
        ];
        
        error_log("Session créée avec succès : " . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        error_log("Erreur de session de don : " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Échec du démarrage de la session de don',
            'error' => $e->getMessage()
        ]);
    }
}

function verifySession($db, $data) {
    $session_id = $data['session_id'] ?? '';
    $session_token = $data['session_token'] ?? '';
    
    if (empty($session_id) && empty($session_token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de session ou jeton requis']);
        return;
    }
    
    $query = "SELECT ds.*, d.user_id as donor_user_id, c.name as charity_name, m.name as module_name
              FROM donation_sessions ds
              JOIN donors d ON ds.donor_id = d.id
              JOIN charities c ON ds.charity_id = c.id
              JOIN modules m ON ds.module_id = m.module_id
              WHERE (ds.id = ? OR ds.session_token = ?) AND ds.status = 'active' 
              AND ds.expires_at > NOW()";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id, $session_token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo json_encode([
            'success' => true,
            'active' => true,
            'session' => $session
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'active' => false,
            'message' => 'Session non trouvée ou expirée'
        ]);
    }
}

function endDonationSession($db, $data) {
    $session_id = $data['session_id'] ?? '';
    $session_token = $data['session_token'] ?? '';
    
    if (empty($session_id) && empty($session_token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de session ou jeton requis']);
        return;
    }
    
    $query = "UPDATE donation_sessions 
              SET status = 'completed', ended_at = NOW() 
              WHERE (id = ? OR session_token = ?) AND status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id, $session_token]);
    
    if ($stmt->rowCount() > 0) {
        // Obtenir les détails de la session pour la journalisation
        $query = "SELECT ds.*, d.user_id, c.name as charity_name 
                  FROM donation_sessions ds
                  JOIN donors d ON ds.donor_id = d.id
                  JOIN charities c ON ds.charity_id = c.id
                  WHERE ds.id = ? OR ds.session_token = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$session_id, $session_token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Journaliser l'activité
        log_activity($db, 'donor', $session['donor_id'], 'donation_session_ended', 
            "Le donateur {$session['user_id']} a terminé la session de don pour l'organisme '{$session['charity_name']}'");
        
        echo json_encode([
            'success' => true,
            'message' => 'Session terminée avec succès'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Session non trouvée ou déjà terminée'
        ]);
    }
}

function getActiveSession($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    $module_id = $data['module_id'] ?? '';
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de donateur requis']);
        return;
    }
    
    $query = "SELECT ds.*, d.user_id as donor_user_id, c.name as charity_name, c.id as charity_id,
                     m.name as module_name
              FROM donation_sessions ds
              JOIN donors d ON ds.donor_id = d.id
              JOIN charities c ON ds.charity_id = c.id
              JOIN modules m ON ds.module_id = m.module_id
              WHERE ds.donor_id = ? AND ds.status = 'active' 
              AND ds.expires_at > NOW()";
    
    $params = [$donor_id];
    
    if (!empty($module_id)) {
        $query .= " AND ds.module_id = ?";
        $params[] = $module_id;
    }
    
    $query .= " ORDER BY ds.started_at DESC LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo json_encode([
            'success' => true,
            'session' => $session
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'session' => null,
            'message' => 'Aucune session active trouvée'
        ]);
    }
}
?>