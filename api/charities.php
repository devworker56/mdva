<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

// GESTION AMÉLIORÉE DES ENTRÉES - Support à la fois GET et POST
$input = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $input = $_POST;
    }
} else {
    $input = $_GET; // Accepte également les paramètres GET
}

// Activer CORS pour l'application mobile
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer la requête OPTIONS de pré-vérification
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch($action) {
        case 'get_approved':
            getApprovedCharities($db);
            break;
            
        case 'approve':
            // Permettre à la fois GET et POST pour la compatibilité avec le tableau de bord admin
            approveCharity($db, $input);
            break;
            
        case 'reject':
            // Permettre à la fois GET et POST pour la compatibilité avec le tableau de bord admin
            rejectCharity($db, $input);
            break;
            
        case 'revoke':
            // Permettre à la fois GET et POST pour la compatibilité avec le tableau de bord admin
            revokeCharity($db, $input);
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
 * Obtenir tous les organismes approuvés pour l'application mobile
 */
function getApprovedCharities($db) {
    $query = "SELECT id, name, description, created_at, logo_url, website 
              FROM charities 
              WHERE approved = 1 
              ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $charities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'charities' => $charities
    ]);
}

/**
 * Approuver un organisme et notifier les applications mobiles
 */
function approveCharity($db, $data) {
    // CORRIGÉ : Support à la fois GET (paramètre id) et POST (id dans data)
    $charity_id = $data['id'] ?? $data['charity_id'] ?? $_GET['id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID d\'organisme requis']);
        return;
    }
    
    // Valider que l'organisme existe
    $query = "SELECT id, name, description FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organisme non trouvé']);
        return;
    }
    
    $query = "UPDATE charities SET approved = 1, updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$charity_id])) {
        // Obtenir les informations mises à jour de l'organisme pour la notification
        $query = "SELECT id, name, description FROM charities WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$charity_id]);
        $charity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notifier le serveur WebSocket du nouvel organisme approuvé (si la fonction existe)
        if (function_exists('notify_websocket')) {
            notify_websocket('new_charity', [
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'charity_description' => $charity['description'],
                'action' => 'approved',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Enregistrer l'activité (si la fonction existe)
        if (function_exists('log_activity')) {
            log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_approved', 
                "Organisme '{$charity['name']}' (ID : $charity_id) approuvé");
        }
        
        echo json_encode(['success' => true, 'message' => 'Organisme approuvé avec succès']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Échec de l\'approbation de l\'organisme']);
    }
}

/**
 * Rejeter une demande d'organisme
 */
function rejectCharity($db, $data) {
    // CORRIGÉ : Support à la fois GET (paramètre id) et POST (id dans data)
    $charity_id = $data['id'] ?? $data['charity_id'] ?? $_GET['id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID d\'organisme requis']);
        return;
    }
    
    // Obtenir le nom de l'organisme avant la suppression pour la journalisation
    $query = "SELECT name FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organisme non trouvé']);
        return;
    }
    
    $query = "DELETE FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$charity_id])) {
        // Enregistrer l'activité (si la fonction existe)
        if (function_exists('log_activity')) {
            log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_rejected', 
                "Organisme '{$charity['name']}' (ID : $charity_id) rejeté et supprimé");
        }
            
        echo json_encode(['success' => true, 'message' => 'Organisme rejeté avec succès']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Échec du rejet de l\'organisme']);
    }
}

/**
 * Révoquer l'approbation d'un organisme
 */
function revokeCharity($db, $data) {
    // CORRIGÉ : Support à la fois GET (paramètre id) et POST (id dans data)
    $charity_id = $data['id'] ?? $data['charity_id'] ?? $_GET['id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID d\'organisme requis']);
        return;
    }
    
    // Valider que l'organisme existe et est approuvé
    $query = "SELECT id, name FROM charities WHERE id = ? AND approved = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organisme approuvé non trouvé']);
        return;
    }
    
    $query = "UPDATE charities SET approved = 0, updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$charity_id])) {
        // Obtenir les informations de l'organisme pour la notification
        $query = "SELECT name FROM charities WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$charity_id]);
        $charity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notifier le serveur WebSocket de la révocation (si la fonction existe)
        if (function_exists('notify_websocket')) {
            notify_websocket('charity_update', [
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'action' => 'revoked',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Enregistrer l'activité (si la fonction existe)
        if (function_exists('log_activity')) {
            log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_revoked', 
                "Approbation de l'organisme '{$charity['name']}' (ID : $charity_id) révoquée");
        }
            
        echo json_encode(['success' => true, 'message' => 'Approbation de l\'organisme révoquée']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Échec de la révocation de l\'organisme']);
    }
}
?>