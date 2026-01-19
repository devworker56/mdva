<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get input data based on method
if ($method == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST; // Fallback to form data
    }
} else {
    $input = $_GET;
}

// Enable CORS for React Native app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS pre-flight request
if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch($action) {
        case 'record_donation':
            if ($method == 'POST') {
                recordAnonymousDonation($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'global_stats':
            if ($method == 'GET') {
                getGlobalDonationStats($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'charity_donations':
            if ($method == 'GET') {
                getCharityDonations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'recent_donations':
            if ($method == 'GET') {
                getRecentDonations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'anonymous_session_donations':
            if ($method == 'GET') {
                getAnonymousSessionDonations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Record an anonymous donation from ESP32
 * Note: This is the ONLY donation recording function now
 */
function recordAnonymousDonation($db, $data) {
    error_log("Recording anonymous donation: " . json_encode($data));
    
    $module_id = $data['module_id'] ?? '';
    $amount = floatval($data['amount'] ?? 0);
    $coin_count = intval($data['coin_count'] ?? 0);
    $session_id = $data['session_id'] ?? '';
    $charity_id = $data['charity_id'] ?? '';
    
    if (empty($module_id) || $amount <= 0 || empty($session_id) || empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: module_id, amount > 0, session_id, and charity_id are required']);
        return;
    }
    
    try {
        // Verify charity exists and is approved
        $charity_query = "SELECT id, name FROM charities WHERE id = ? AND approved = 1 AND active = 1";
        $charity_stmt = $db->prepare($charity_query);
        $charity_stmt->execute([$charity_id]);
        $charity = $charity_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$charity) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Charity not found or not approved']);
            return;
        }
        
        // Verify module exists and is active
        $module_query = "SELECT id, name FROM modules WHERE module_id = ? AND status = 'active'";
        $module_stmt = $db->prepare($module_query);
        $module_stmt->execute([$module_id]);
        $module = $module_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$module) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Module not found or not active']);
            return;
        }
        
        // Check if anonymous session exists, create if not
        $session_query = "SELECT id, total_donated, donation_count FROM anonymous_sessions WHERE session_id = ?";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$session_id]);
        $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        $db->beginTransaction();
        
        if (!$session) {
            // Create anonymous session if it doesn't exist
            $create_session_query = "INSERT INTO anonymous_sessions 
                                    (session_id, charity_id, module_id, started_at, last_donation_at, status) 
                                    VALUES (?, ?, ?, NOW(), NOW(), 'active')";
            $create_session_stmt = $db->prepare($create_session_query);
            $create_session_stmt->execute([$session_id, $charity_id, $module_id]);
            
            $session = [
                'id' => $db->lastInsertId(),
                'total_donated' => 0,
                'donation_count' => 0
            ];
        }
        
        // Get module location if available
        $location_query = "SELECT l.name as location_name, l.city, l.province 
                          FROM modules m
                          LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
                          LEFT JOIN locations l ON ml.location_id = l.id
                          WHERE m.module_id = ?";
        $location_stmt = $db->prepare($location_query);
        $location_stmt->execute([$module_id]);
        $location_data = $location_stmt->fetch(PDO::FETCH_ASSOC);
        
        $location = '';
        if ($location_data && $location_data['location_name']) {
            $location = $location_data['location_name'] . ', ' . 
                       $location_data['city'] . ', ' . 
                       $location_data['province'];
        }
        
        // Generate donation code
        $donation_code = null;
        $settings_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'generate_receipt_codes_for_anonymous'";
        $settings_stmt = $db->prepare($settings_query);
        $settings_stmt->execute();
        $generate_codes = $settings_stmt->fetchColumn();
        
        if ($generate_codes === 'true') {
            $donation_code = 'DON' . strtoupper(uniqid()) . substr($session_id, -6);
            
            // Insert into donation_codes table
            $code_query = "INSERT INTO donation_codes 
                          (donation_code, anonymous_session_id, charity_id, amount, generated_at, expires_at)
                          VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY))
                          ON DUPLICATE KEY UPDATE amount = amount + ?";
            $code_stmt = $db->prepare($code_query);
            $code_stmt->execute([$donation_code, $session_id, $charity_id, $amount, $amount]);
        }
        
        // Record in anonymous_donations table
        $donation_query = "INSERT INTO anonymous_donations 
                          (anonymous_session_id, charity_id, amount, coin_count, module_id, location, donation_code, status)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')";
        $donation_stmt = $db->prepare($donation_query);
        $donation_stmt->execute([
            $session_id, 
            $charity_id, 
            $amount, 
            $coin_count, 
            $module_id, 
            $location, 
            $donation_code
        ]);
        
        $anonymous_donation_id = $db->lastInsertId();
        
        // Also record in main donations table for compatibility (without donor_id)
        $main_donation_query = "INSERT INTO donations 
                               (charity_id, amount, coin_count, module_id, anonymous_session_id, donation_code, status, donation_type, location)
                               VALUES (?, ?, ?, ?, ?, ?, 'completed', 'cash', ?)";
        $main_donation_stmt = $db->prepare($main_donation_query);
        $main_donation_stmt->execute([
            $charity_id, 
            $amount, 
            $coin_count, 
            $module_id, 
            $session_id, 
            $donation_code,
            $location
        ]);
        
        $donation_id = $db->lastInsertId();
        
        // Update anonymous session totals
        $update_session_query = "UPDATE anonymous_sessions 
                                SET total_donated = total_donated + ?,
                                    donation_count = donation_count + 1,
                                    last_donation_at = NOW(),
                                    updated_at = NOW(),
                                    charity_id = ?,
                                    module_id = ?
                                WHERE session_id = ?";
        $update_session_stmt = $db->prepare($update_session_query);
        $update_session_stmt->execute([$amount, $charity_id, $module_id, $session_id]);
        
        // Get updated session totals
        $updated_session_query = "SELECT total_donated, donation_count FROM anonymous_sessions WHERE session_id = ?";
        $updated_session_stmt = $db->prepare($updated_session_query);
        $updated_session_stmt->execute([$session_id]);
        $updated_session = $updated_session_stmt->fetch(PDO::FETCH_ASSOC);
        
        $db->commit();
        
        // Notify via Pusher
        $pusher_data = [
            'donation_id' => $donation_id,
            'anonymous_donation_id' => $anonymous_donation_id,
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'amount' => $amount,
            'coin_count' => $coin_count,
            'module_id' => $module_id,
            'location' => $location,
            'session_id' => $session_id,
            'session_total' => $updated_session['total_donated'],
            'session_donation_count' => $updated_session['donation_count'],
            'donation_code' => $donation_code,
            'timestamp' => date('Y-m-d H:i:s'),
            'donor_type' => 'anonymous'
        ];
        
        // Notify charity dashboard
        notify_pusher('new_donation', $pusher_data, "charity_" . $charity_id);
        
        // Notify public donations channel
        notify_pusher('donation_recorded', $pusher_data, "public-donations");
        
        // Notify session channel for mobile app
        notify_pusher('donation_received', $pusher_data, "session_" . $session_id);
        
        // Notify module channel
        notify_pusher('donation_processed', $pusher_data, "private-module_" . $module_id);
        
        // Update charity stats cache
        $update_stats_query = "CALL UpdateCharityStats(?)";
        $update_stats_stmt = $db->prepare($update_stats_query);
        $update_stats_stmt->execute([$charity_id]);
        
        $response = [
            'success' => true,
            'message' => 'Anonymous donation recorded successfully',
            'donation' => [
                'id' => $donation_id,
                'anonymous_id' => $anonymous_donation_id,
                'amount' => $amount,
                'coin_count' => $coin_count,
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'module_id' => $module_id,
                'session_id' => $session_id,
                'session_total' => $updated_session['total_donated'],
                'session_donation_count' => $updated_session['donation_count'],
                'donation_code' => $donation_code,
                'location' => $location,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        error_log("Error recording anonymous donation: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Failed to record donation: ' . $e->getMessage()]);
    }
}

/**
 * Get global donation statistics (for anonymous mobile app)
 */
function getGlobalDonationStats($db, $data) {
    // Stats from anonymous donations only
    $query = "SELECT 
                COALESCE(SUM(amount), 0) as total_donated,
                COUNT(*) as donation_count,
                COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN amount ELSE 0 END), 0) as today_donated,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_count,
                COUNT(DISTINCT charity_id) as charity_count
              FROM anonymous_donations 
              WHERE status = 'completed'";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get last donation
    $last_query = "SELECT 
                    amount, 
                    created_at, 
                    c.name as charity_name,
                    'anonymous' as donor_type,
                    ad.module_id,
                    ad.location
                   FROM anonymous_donations ad
                   JOIN charities c ON ad.charity_id = c.id
                   WHERE ad.status = 'completed'
                   ORDER BY created_at DESC LIMIT 1";
    
    $last_stmt = $db->prepare($last_query);
    $last_stmt->execute();
    $last_donation = $last_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get top charities
    $top_charities_query = "SELECT 
                              c.id,
                              c.name,
                              COALESCE(SUM(ad.amount), 0) as total_donated,
                              COUNT(ad.id) as donation_count
                            FROM charities c
                            LEFT JOIN anonymous_donations ad ON c.id = ad.charity_id AND ad.status = 'completed'
                            WHERE c.approved = 1 AND c.active = 1
                            GROUP BY c.id
                            HAVING total_donated > 0
                            ORDER BY total_donated DESC
                            LIMIT 5";
    
    $top_charities_stmt = $db->prepare($top_charities_query);
    $top_charities_stmt->execute();
    $top_charities = $top_charities_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_donated' => floatval($stats['total_donated'] ?? 0),
            'donation_count' => intval($stats['donation_count'] ?? 0),
            'today_donated' => floatval($stats['today_donated'] ?? 0),
            'today_count' => intval($stats['today_count'] ?? 0),
            'charity_count' => intval($stats['charity_count'] ?? 0),
            'last_donation' => $last_donation,
            'top_charities' => $top_charities
        ]
    ]);
}

/**
 * Get donations for a charity
 */
function getCharityDonations($db, $data) {
    $charity_id = $data['charity_id'] ?? '';
    $limit = $data['limit'] ?? 50;
    $offset = $data['offset'] ?? 0;
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Charity ID required']);
        return;
    }
    
    // Get anonymous donations only
    $query = "SELECT 
                ad.id,
                ad.amount,
                ad.created_at,
                ad.module_id,
                'anonymous' as donor_id,
                'anonymous' as donor_type,
                m.name as module_name,
                ad.location,
                ad.donation_code,
                ad.coin_count
              FROM anonymous_donations ad
              LEFT JOIN modules m ON ad.module_id = m.module_id
              WHERE ad.charity_id = ?
              ORDER BY ad.created_at DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id, $limit, $offset]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total and amount for pagination
    $query = "SELECT 
                COUNT(*) as total,
                COALESCE(SUM(amount), 0) as total_amount
              FROM anonymous_donations 
              WHERE charity_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'donations' => $donations,
        'pagination' => [
            'total' => $totals['total'] ?? 0,
            'total_amount' => $totals['total_amount'] ?? 0,
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
}

/**
 * Get recent donations in the entire system
 */
function getRecentDonations($db, $data) {
    $limit = $data['limit'] ?? 10;
    
    $query = "SELECT 
                ad.id,
                ad.amount,
                ad.created_at,
                'anonymous' as donor_id,
                c.name as charity_name,
                'anonymous' as donor_type,
                ad.module_id,
                ad.location
              FROM anonymous_donations ad
              JOIN charities c ON ad.charity_id = c.id
              ORDER BY ad.created_at DESC
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
 * Get anonymous session donations
 */
function getAnonymousSessionDonations($db, $data) {
    $session_id = $data['session_id'] ?? '';
    
    if (empty($session_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session ID required']);
        return;
    }
    
    try {
        $query = "SELECT 
                    ad.id,
                    ad.amount,
                    ad.coin_count,
                    ad.created_at,
                    ad.module_id,
                    ad.location,
                    ad.donation_code,
                    c.name as charity_name,
                    c.id as charity_id,
                    m.name as module_name
                  FROM anonymous_donations ad
                  JOIN charities c ON ad.charity_id = c.id
                  LEFT JOIN modules m ON ad.module_id = m.module_id
                  WHERE ad.anonymous_session_id = ?
                  ORDER BY ad.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$session_id]);
        $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get anonymous session info
        $session_query = "SELECT * FROM anonymous_sessions WHERE session_id = ?";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$session_id]);
        $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'donations' => $donations,
            'session' => $session,
            'count' => count($donations),
            'total_amount' => array_sum(array_column($donations, 'amount'))
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Anonymous session donations error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load session donations: ' . $e->getMessage()]);
    }
}
?>