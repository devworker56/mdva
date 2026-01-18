<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// CORRECTED PATH - Include phpqrcode.php NOT qrlib.php
require_once __DIR__ . '/../qrlib/phpqrcode/phpqrcode.php';

$database = new Database();
$db = $database->getConnection();

// Rest of your code remains the same...
// Gérer les soumissions de formulaire
if (isset($_POST['generate_single'])) {
    $module_id = $_POST['module_id'] ?? '';
    $barcode_data = $_POST['barcode_data'] ?? '';
    $type = $_POST['type'] ?? 'QRCODE';
    
    if (!empty($module_id)) {
        // Générer le code QR pour le module dans le format ESP32
        $module = getModuleWithLocation($module_id, $db);
        if ($module) {
            // Build location string in ESP32 format
            $location_string = $module['location_name'] ?: '';
            if ($module['address']) $location_string .= ', ' . $module['address'];
            if ($module['city']) $location_string .= ', ' . $module['city'];
            if ($module['province']) $location_string .= ', ' . $module['province'];
            if ($module['postal_code']) $location_string .= ' ' . $module['postal_code'];
            
            // Create QR data in ESP32 format
            $qr_data = [
                'module_id' => $module['module_id'],
                'module_name' => $module['name'],
                'location' => $location_string,
                'system' => 'MDVA',
                'type' => 'donation_module',
                'version' => '1.0',
                'timestamp' => time(),
                'url' => "https://systeme-mdva.com/module/" . urlencode($module['module_id'])
            ];
            
            $qr_data_json = json_encode($qr_data);
            
            $qr_dir = "../qr_codes/";
            if (!file_exists($qr_dir)) {
                mkdir($qr_dir, 0755, true);
            }
            
            $qr_filename = "mdva_module_" . $module['module_id'] . ".png";
            $qr_path = $qr_dir . $qr_filename;
            
            // Generate QR code with ESP32 format
            QRcode::png($qr_data_json, $qr_path, QR_ECLEVEL_L, 10, 2);
            
            $success_message = "Code QR ESP32 généré pour le module : " . htmlspecialchars($module_id);
            $preview_file = $qr_path;
            $preview_data = $module_id;
            $qr_data_preview = $qr_data;
        } else {
            $error_message = "Module non trouvé : " . htmlspecialchars($module_id);
        }
    } elseif (!empty($barcode_data)) {
        // Generate custom QR code
        $custom_data = $barcode_data;
        
        $custom_qr_dir = "../custom_qr_codes/";
        if (!file_exists($custom_qr_dir)) {
            mkdir($custom_qr_dir, 0755, true);
        }
        
        $qr_file = $custom_qr_dir . 'custom_' . md5($custom_data . time()) . '.png';
        
        QRcode::png($custom_data, $qr_file, QR_ECLEVEL_L, 10, 2);
        
        $success_message = "Code généré pour : " . htmlspecialchars($barcode_data);
        $preview_file = $qr_file;
        $preview_data = $barcode_data;
        $qr_data_preview = ['custom_data' => $barcode_data];
    }
}

if (isset($_POST['generate_all'])) {
    $generated = generateAllModuleQRCodes($db);
    $success_message = count($generated) . " codes QR générés pour tous les modules actifs";
    
    // Store generated data for preview
    if (!empty($generated)) {
        $first_module = $generated[0];
        $preview_file = "../qr_codes/" . $first_module['qr_file'];
        $preview_data = $first_module['module_id'];
        $qr_data_preview = $first_module['qr_data'];
    }
}

// Obtenir tous les modules pour le menu déroulant
function getActiveModules($db) {
    $query = "SELECT module_id, name FROM modules WHERE status = 'active' ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$modules = getActiveModules($db);

include '../includes/header.php';
?>