<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

// Get input
if ($method == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($method == 'POST') {
        recordDirectDonation($db, $input);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function recordDirectDonation($db, $data) {
    error_log("=== DIRECT DONATION ===");
    error_log("Data: " . json_encode($data));
    
    $module_id = $data['module_id'] ?? 'station-001'; // Default to station-001
    $amount = $data['amount'] ?? 0;
    $coin_count = $data['coin_count'] ?? 0;
    $donor_id = $data['donor_id'] ?? ''; // Mobile app sends donor's user_id (e.g., DONOR_68f55cd2be9b6)
    $charity_id = $data['charity_id'] ?? '';
    
    if (empty($donor_id) || empty($charity_id) || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: donor_id, charity_id, and amount > 0 required']);
        return;
    }
    
    try {
        // Get donor's internal ID from user_id
        $donor_query = "SELECT id FROM donors WHERE user_id = ?";
        $donor_stmt = $db->prepare($donor_query);
        $donor_stmt->execute([$donor_id]);
        $donor = $donor_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$donor) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Donor not found']);
            return;
        }
        
        $internal_donor_id = $donor['id'];
        
        // Verify charity exists and is approved
        $charity_query = "SELECT id, name FROM charities WHERE id = ? AND approved = 1";
        $charity_stmt = $db->prepare($charity_query);
        $charity_stmt->execute([$charity_id]);
        $charity = $charity_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$charity) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Charity not found or not approved']);
            return;
        }
        
        // Insert donation directly (no session required)
        $query = "INSERT INTO donations (donor_id, charity_id, amount, coin_count, module_id, status) 
                  VALUES (?, ?, ?, ?, ?, 'completed')";
        $stmt = $db->prepare($query);
        $stmt->execute([$internal_donor_id, $charity_id, $amount, $coin_count, $module_id]);
        
        $donation_id = $db->lastInsertId();
        
        error_log("✅ Direct donation recorded: ID $donation_id, Donor $donor_id, Charity $charity_id, Amount $$amount");
        
        // Send Pusher notifications
        // 1. To charity dashboard
        notify_pusher('new_donation', [
            'donation_id' => $donation_id,
            'donor_id' => $donor_id,
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'amount' => $amount,
            'coin_count' => $coin_count,
            'module_id' => $module_id,
            'timestamp' => date('Y-m-d H:i:s')
        ], "charity_" . $charity_id);
        
        // 2. To donor's mobile app
        notify_pusher('donation_received', [
            'donation_id' => $donation_id,
            'donor_id' => $donor_id,
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'amount' => $amount,
            'coin_count' => $coin_count,
            'module_id' => $module_id,
            'timestamp' => date('Y-m-d H:i:s')
        ], "user_" . $donor_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Donation recorded successfully',
            'donation_id' => $donation_id,
            'donor_id' => $donor_id,
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'amount' => $amount
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("❌ Direct donation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error recording donation: ' . $e->getMessage()]);
    }
}
?>