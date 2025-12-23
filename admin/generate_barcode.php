<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$data = $_POST['data'] ?? '';
$type = $_POST['type'] ?? 'QRCODE'; // Par défaut QRCODE puisque c'est ce que vous avez
$width = $_POST['width'] ?? 2;
$height = $_POST['height'] ?? 1;

header('Content-Type: application/json');

if (empty($data)) {
    echo json_encode(['success' => false, 'message' => 'Les données sont requises']);
    exit;
}

try {
    // Utilisez UNIQUEMENT la bibliothèque phpqrcode que vous avez réellement
    require_once '../Lib/phpqrcode/qrlib.php';
    
    $barcodeDir = "../barcodes/";
    if (!file_exists($barcodeDir)) {
        mkdir($barcodeDir, 0755, true);
    }
    
    $filename = 'code_' . md5($data . $type . time()) . '.png';
    $filepath = $barcodeDir . $filename;
    
    // Toujours générer des codes QR pour l'instant (puisque c'est la bibliothèque que vous avez)
    QRcode::png($data, $filepath, QR_ECLEVEL_L, 10, 2);
    
    echo json_encode([
        'success' => true,
        'barcode_url' => $filepath,
        'code_data' => $data,
        'type' => 'QR Code',
        'html' => '
            <div class="text-center">
                <img src="' . $filepath . '" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                <div class="mt-2">
                    <strong>Données :</strong> ' . htmlspecialchars($data) . '<br>
                    <strong>Type :</strong> QR Code
                </div>
            </div>
        '
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la génération du code QR : ' . $e->getMessage()]);
}
?>