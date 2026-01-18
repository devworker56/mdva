<?php
// get_qr_as_base64.php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['module_id'])) {
    echo json_encode(['success' => false, 'message' => 'Module ID requis']);
    exit();
}

$module_id = $data['module_id'];

// 1. Générer le QR code via generate_all_qr_codes.php
require_once __DIR__ . '/qrlib/phpqrcode/phpqrcode.php';

$qr_data = [
    'module_id' => $module_id,
    'module_name' => $data['module_name'] ?? $module_id,
    'location' => $data['location_name'] ?? '',
    'system' => 'MDVA',
    'type' => 'donation_module',
    'timestamp' => time()
];

$qr_dir = __DIR__ . '/qr_codes/';
if (!file_exists($qr_dir)) {
    mkdir($qr_dir, 0755, true);
}

$qr_file = $qr_dir . 'mdva_module_' . $module_id . '.png';
QRcode::png(json_encode($qr_data), $qr_file, QR_ECLEVEL_L, 10, 2);

if (file_exists($qr_file)) {
    // Convertir en base64
    $image_data = file_get_contents($qr_file);
    $base64 = 'data:image/png;base64,' . base64_encode($image_data);
    
    echo json_encode([
        'success' => true,
        'base64' => $base64,
        'message' => 'QR code généré et converti en Base64',
        'file_size' => filesize($qr_file)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Échec génération QR'
    ]);
}
?>