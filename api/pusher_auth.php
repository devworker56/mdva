<?php
// pusher_auth.php - VERSION SIMPLIFIÉE ET FONCTIONNELLE
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Activer la journalisation détaillée
error_log("=== POINT D'ACCÈS D'AUTHENTIFICATION PUSHER ATTEINT ===");
error_log("Méthode de requête : " . $_SERVER['REQUEST_METHOD']);
error_log("Paramètres GET : " . json_encode($_GET));

// Définir les identifiants Pusher directement (depuis votre config)
define('PUSHER_APP_ID', '2065620');
define('PUSHER_KEY', 'fe6f264f2fba2f7bc4a2'); 
define('PUSHER_SECRET', '7cf64dce7ff9a89e0450');
define('PUSHER_CLUSTER', 'us2');

// Vérifier si les fichiers requis existent
$config_file = '../config/database.php';

error_log("Le fichier de config de la base de données existe : " . (file_exists($config_file) ? 'OUI' : 'NON'));

if (!file_exists($config_file)) {
    error_log("❌ Fichier de configuration de la base de données manquant");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration de la base de données manquante']);
    exit;
}

require_once $config_file;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $channel = $_GET['channel'] ?? '';
    $socket_id = $_GET['socket_id'] ?? '';

    error_log("Demande d'authentification - Canal : $channel, ID de socket : $socket_id");

    if (empty($channel) || empty($socket_id)) {
        error_log("❌ Canal ou socket_id manquant");
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Canal ou socket_id manquant'
        ]);
        exit;
    }

    // Vérifier qu'il s'agit d'un canal de module valide
    if (strpos($channel, 'private-module_') === 0) {
        $moduleId = str_replace('private-module_', '', $channel);
        
        error_log("🔍 Validation du module : " . $moduleId);
        
        // Vérifier que le module existe dans la base de données et est actif
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, module_id, name, status FROM modules WHERE module_id = ? AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($module) {
            error_log("✅ Module trouvé et actif : " . $module['name']);
            
            // AUTHENTIFICATION MANUELLE (garantie de fonctionner)
            $string_to_sign = $socket_id . ':' . $channel;
            $auth_signature = hash_hmac('sha256', $string_to_sign, PUSHER_SECRET, false);
            $auth = PUSHER_KEY . ':' . $auth_signature;
            
            error_log("✅ Authentification manuelle générée avec succès");
            error_log("✅ Chaîne à signer : " . $string_to_sign);
            error_log("✅ Signature d'authentification : " . $auth_signature);
            error_log("✅ Authentification finale : " . $auth);
            
            echo json_encode([
                'success' => true,
                'auth' => $auth,
                'module' => [
                    'id' => $module['id'],
                    'module_id' => $module['module_id'],
                    'name' => $module['name']
                ]
            ]);
            
        } else {
            error_log("❌ Module non trouvé ou inactif : " . $moduleId);
            http_response_code(404);
            echo json_encode([
                'success' => false, 
                'message' => 'Module non trouvé ou inactif : ' . $moduleId
            ]);
        }
        
    } else {
        error_log("❌ Format de canal invalide : " . $channel);
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Format de canal invalide. Doit commencer par private-module_'
        ]);
    }

} catch (Exception $e) {
    error_log("❌ Exception d'authentification Pusher : " . $e->getMessage());
    error_log("❌ Trace de l'exception : " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur du serveur : ' . $e->getMessage()
    ]);
}
?>