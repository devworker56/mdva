<?php
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Key');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
} else {
    $input = $_GET;
}

try {
    switch($action) {
        case 'record_anonymous':
        case 'record_donation':
            if ($method == 'POST') recordAnonymousDonation($db, $input);
            else throw new Exception("Method not allowed", 405);
            break;
            
        case 'global_stats':
            getGlobalDonationStats($db, $input);
            break;
            
        case 'charity_donations':
            getCharityDonations($db, $input);
            break;
            
        case 'recent_donations':
            getRecentDonations($db, $input);
            break;
            
        case 'anonymous_session_donations':
            getAnonymousSessionDonations($db, $input);
            break;
            
        default:
            throw new Exception("Invalid action", 400);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function recordAnonymousDonation($db, $data) {
    $module_id = $data['module_id'] ?? '';
    $amount = floatval($data['amount'] ?? 0);
    $coin_count = intval($data['coin_count'] ?? 0);
    $session_id = $data['session_id'] ?? '';
    $charity_id = $data['charity_id'] ?? '';

    if (empty($module_id) || $amount <= 0 || empty($session_id) || empty($charity_id)) {
        throw new Exception("Missing required fields", 400);
    }

    try {
        $db->beginTransaction();

        // 1. Verify Charity & Module
        $c_stmt = $db->prepare("SELECT name FROM charities WHERE id = ? AND approved = 1");
        $c_stmt->execute([$charity_id]);
        $charity = $c_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$charity) throw new Exception("Charity not found or inactive");

        // 2. Insert into main donations table (Dashboard compatibility)
        $d_sql = "INSERT INTO donations (charity_id, amount, coin_count, module_id, session_id, donation_type, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, 'cash', 'completed', NOW())";
        $db->prepare($d_sql)->execute([$charity_id, $amount, $coin_count, $module_id, $session_id]);
        $donation_id = $db->lastInsertId();

        // 3. Update Anonymous Session
        $s_sql = "INSERT INTO anonymous_sessions (session_id, charity_id, module_id, total_amount, total_coins, status, started_at, last_activity) 
                  VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW()) 
                  ON DUPLICATE KEY UPDATE total_amount = total_amount + VALUES(total_amount), total_coins = total_coins + VALUES(total_coins), last_activity = NOW()";
        $db->prepare($s_sql)->execute([$session_id, $charity_id, $module_id, $amount, $coin_count]);

        // 4. Notify Pusher
        notify_pusher('donation_received', ['amount' => $amount, 'session_id' => $session_id], "session_" . $session_id);
        notify_pusher('new_donation', ['amount' => $amount, 'charity_name' => $charity['name']], "charity_" . $charity_id);

        $db->commit();
        echo json_encode(['success' => true, 'donation_id' => $donation_id]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// RESTORED: All your original stats functions
function getGlobalDonationStats($db, $data) {
    $query = "SELECT COALESCE(SUM(amount), 0) as total_donated, COUNT(*) as donation_count, 
              COUNT(DISTINCT charity_id) as charity_count FROM donations WHERE status = 'completed'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    echo json_encode(['success' => true, 'stats' => $stmt->fetch(PDO::FETCH_ASSOC)]);
}

function getCharityDonations($db, $data) {
    $charity_id = $data['charity_id'] ?? '';
    $limit = intval($data['limit'] ?? 50);
    $stmt = $db->prepare("SELECT * FROM donations WHERE charity_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$charity_id, $limit]);
    echo json_encode(['success' => true, 'donations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getRecentDonations($db, $data) {
    $limit = intval($data['limit'] ?? 10);
    $stmt = $db->prepare("SELECT d.*, c.name as charity_name FROM donations d JOIN charities c ON d.charity_id = c.id ORDER BY d.created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    echo json_encode(['success' => true, 'recent_donations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getAnonymousSessionDonations($db, $data) {
    $s_id = $data['session_id'] ?? '';
    $stmt = $db->prepare("SELECT * FROM donations WHERE session_id = ?");
    $stmt->execute([$s_id]);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'donations' => $res, 'total_amount' => array_sum(array_column($res, 'amount'))]);
}
ob_end_flush();