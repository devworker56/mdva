<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

// GESTION AMÉLIORÉE DES ENTRÉES
$input = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

// AJOUT DES EN-TÊTES CORS POUR L'APPLICATION MOBILE
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer la requête OPTIONS de pré-vérification
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// AJOUT DE LA GESTION DES ERREURS POUR LA BASE DE DONNÉES
try {
    switch($action) {
        case 'register':
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email et mot de passe sont requis']);
                break;
            }
            
            // Générer un ID utilisateur unique
            $user_id = 'DONOR_' . uniqid();
            
            // Vérifier si l'email existe déjà
            $query = "SELECT id FROM donors WHERE email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email]);
            
            if($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Email déjà enregistré']);
                break;
            }
            
            // Insérer le donateur (SANS selected_charity_id)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO donors (user_id, email, password) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if($stmt->execute([$user_id, $email, $hashed_password])) {
                // Obtenir l'ID du nouveau donateur
                $new_donor_id = $db->lastInsertId();
                
                // Créer la transaction vérifiable initiale pour ce donateur
                create_initial_verifiable_transaction($new_donor_id, $user_id, $db);
                
                echo json_encode(['success' => true, 'message' => 'Inscription réussie']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Échec de l\'inscription']);
            }
            break;
            
        case 'login':
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email et mot de passe sont requis']);
                break;
            }
            
            $query = "SELECT * FROM donors WHERE email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email]);
            $donor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($donor && password_verify($password, $donor['password'])) {
                echo json_encode([
                    'success' => true,
                    'token' => bin2hex(random_bytes(32)),
                    'user' => [
                        'id' => $donor['id'],
                        'user_id' => $donor['user_id'],
                        'email' => $donor['email']
                        // PAS de selected_charity_id dans la réponse
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Identifiants invalides']);
            }
            break;
            
        // SUPPRIMÉ : action select_charity - la sélection d'organisme est maintenant uniquement par session
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action invalide']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception PHP dans donors.php : " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Une erreur du serveur est survenue',
        'error' => $e->getMessage()
    ]);
}
?>