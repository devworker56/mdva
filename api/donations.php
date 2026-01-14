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
                recordDonation($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'record_anonymous':
            if ($method == 'POST') {
                recordAnonymousDonation($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'stats':
            if ($method == 'GET') {
                getDonationStats($db, $input);
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
            
        case 'history':
            if ($method == 'GET') {
                getDonationHistory($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'tax_receipts':
            if ($method == 'GET') {
                getTaxReceipts($db, $input);
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
            
        case 'recent_anonymous':
            if ($method == 'GET') {
                getRecentAnonymousDonations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'session_donations':
            if ($method == 'GET') {
                getSessionDonations($db, $input);
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
 * Record a donation from Module MDVA - WITH SESSION VALIDATION
 */
function recordDonation($db, $data) {
    error_log("Recording donation: " . json_encode($data));
    
    $module_id = $data['module_id'] ?? '';
    $amount = $data['amount'] ?? 0;
    $coin_count = $data['coin_count'] ?? 0;
    $session_id = $data['session_id'] ?? '';
    $session_token = $data['session_token'] ?? '';
    
    if (empty($module_id) || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: module_id and amount > 0 required']);
        return;
    }
    
    try {
        // Check that an active session exists for this module
        $session_query = "SELECT ds.*, d.id as donor_id, d.user_id as donor_user_id, 
                                 c.id as charity_id, c.name as charity_name
                          FROM donation_sessions ds
                          JOIN donors d ON ds.donor_id = d.id
                          JOIN charities c ON ds.charity_id = c.id
                          WHERE ds.module_id = ? AND ds.status = 'active' 
                          AND ds.expires_at > NOW()
                          ORDER BY ds.started_at DESC LIMIT 1";
        
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$module_id]);
        $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No active donation session for this module']);
            return;
        }
        
        $donor_id = $session['donor_id'];
        $charity_id = $session['charity_id'];
        $session_id = $session['id'];
        
        error_log("Active session found: donor_id=$donor_id, charity_id=$charity_id, session_id=$session_id");
        
        // Use stored procedure to record donation with proper validation
        $stmt = $db->prepare("CALL RecordDonationWithStats(?, ?, ?, ?, ?, ?)");
        $stmt->execute([$donor_id, $charity_id, $amount, $coin_count, $module_id, $session_id]);
        
        $donation_id = $db->lastInsertId();
        error_log("Donation recorded successfully with ID: " . $donation_id);
        
        // Get updated session information
        $session_query = "SELECT total_amount, total_coins FROM donation_sessions WHERE id = ?";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$session_id]);
        $updated_session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notify via Pusher for real-time updates
        // 1. Notify donor user
        notify_pusher('donation_received', [
            'donation_id' => $donation_id,
            'donor_id' => $donor_id,
            'donor_user_id' => $session['donor_user_id'],
            'charity_id' => $charity_id,
            'charity_name' => $session['charity_name'],
            'amount' => $amount,
            'session_id' => $session_id,
            'module_id' => $module_id,
            'session_total' => $updated_session['total_amount'],
            'timestamp' => date('Y-m-d H:i:s')
        ], "user_" . $session['donor_user_id']);
        
        // 2. Notify charity dashboard
        notify_pusher('new_donation', [
            'donation_id' => $donation_id,
            'donor_id' => $session['donor_user_id'], // Use donor_user_id, not internal ID
            'charity_id' => $charity_id,
            'charity_name' => $session['charity_name'],
            'amount' => $amount,
            'coin_count' => $coin_count,
            'session_id' => $session_id,
            'module_id' => $module_id,
            'location' => $module_id, // You might want to get actual location
            'timestamp' => date('Y-m-d H:i:s')
        ], "charity_" . $charity_id);
        
        $response = [
            'success' => true, 
            'message' => 'Donation recorded successfully',
            'donation_id' => $donation_id,
            'donor_id' => $session['donor_user_id'],
            'charity_id' => $charity_id,
            'charity_name' => $session['charity_name'],
            'amount' => $amount,
            'module_id' => $module_id,
            'session_id' => $session_id,
            'session_total' => $updated_session['total_amount'],
            'session_coins' => $updated_session['total_coins']
        ];
        
        error_log("Sending success response: " . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Donation recording exception: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
}

/**
 * Record an anonymous donation from ESP32
 */
function recordAnonymousDonation($db, $data) {
    error_log("Recording anonymous donation: " . json_encode($data));
    
    $module_id = $data['module_id'] ?? '';
    $amount = floatval($data['amount'] ?? 0);
    $coin_count = intval($data['coin_count'] ?? 0);
    $session_id = $data['session_id'] ?? '';
    $donation_session_id = $data['donation_session_id'] ?? '';
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
        
        // Get session info
        $session_query = "SELECT ds.*, c.name as charity_name
                          FROM donation_sessions ds
                          JOIN charities c ON ds.charity_id = c.id
                          WHERE (ds.id = ? OR ds.anonymous_session_id = ?) 
                          AND ds.status = 'active' 
                          AND ds.expires_at > NOW()
                          ORDER BY ds.started_at DESC LIMIT 1";
        
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$donation_session_id, $session_id]);
        $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            // Create a new session if none exists
            $create_session_query = "INSERT INTO donation_sessions 
                                    (anonymous_session_id, charity_id, module_id, status, started_at, expires_at) 
                                    VALUES (?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))";
            $create_session_stmt = $db->prepare($create_session_query);
            $create_session_stmt->execute([$session_id, $charity_id, $module_id]);
            $donation_session_id = $db->lastInsertId();
            
            // Create anonymous session record
            $anonymous_session_query = "INSERT INTO anonymous_sessions 
                                      (session_id, started_at, active) 
                                      VALUES (?, NOW(), 1) 
                                      ON DUPLICATE KEY UPDATE last_donation_at = NOW()";
            $anonymous_session_stmt = $db->prepare($anonymous_session_query);
            $anonymous_session_stmt->execute([$session_id]);
            
            $session = [
                'id' => $donation_session_id,
                'anonymous_session_id' => $session_id,
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'module_id' => $module_id,
                'total_amount' => 0,
                'total_coins' => 0
            ];
        } else {
            $donation_session_id = $session['id'];
            $anonymous_session_id = $session['anonymous_session_id'] ?: $session_id;
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
        
        $db->beginTransaction();
        
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
        
        // Also record in main donations table for compatibility
        $main_donation_query = "INSERT INTO donations 
                               (charity_id, amount, coin_count, module_id, session_id, 
                                anonymous_session_id, donation_code, status, donation_type, location)
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', 'cash', ?)";
        $main_donation_stmt = $db->prepare($main_donation_query);
        $main_donation_stmt->execute([
            $charity_id, 
            $amount, 
            $coin_count, 
            $module_id, 
            $donation_session_id, 
            $session_id, 
            $donation_code,
            $location
        ]);
        
        $donation_id = $db->lastInsertId();
        
        // Update donation session totals
        $update_session_query = "UPDATE donation_sessions 
                                SET total_amount = total_amount + ?, 
                                    total_coins = total_coins + ?,
                                    updated_at = NOW()
                                WHERE id = ?";
        $update_session_stmt = $db->prepare($update_session_query);
        $update_session_stmt->execute([$amount, $coin_count, $donation_session_id]);
        
        // Update anonymous session
        $update_anonymous_query = "UPDATE anonymous_sessions 
                                  SET total_donated = total_donated + ?,
                                      donation_count = donation_count + 1,
                                      last_donation_at = NOW(),
                                      updated_at = NOW()
                                  WHERE session_id = ?";
        $update_anonymous_stmt = $db->prepare($update_anonymous_query);
        $update_anonymous_stmt->execute([$amount, $session_id]);
        
        // Get updated session info
        $session_query = "SELECT total_amount, total_coins FROM donation_sessions WHERE id = ?";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$donation_session_id]);
        $updated_session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get anonymous session totals
        $anonymous_query = "SELECT total_donated, donation_count FROM anonymous_sessions WHERE session_id = ?";
        $anonymous_stmt = $db->prepare($anonymous_query);
        $anonymous_stmt->execute([$session_id]);
        $anonymous_session = $anonymous_stmt->fetch(PDO::FETCH_ASSOC);
        
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
            'donation_session_id' => $donation_session_id,
            'session_total' => $updated_session['total_amount'],
            'session_coins' => $updated_session['total_coins'],
            'anonymous_session_total' => $anonymous_session['total_donated'],
            'anonymous_donation_count' => $anonymous_session['donation_count'],
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
                'donation_session_id' => $donation_session_id,
                'session_total' => $updated_session['total_amount'],
                'session_coins' => $updated_session['total_coins'],
                'anonymous_session_total' => $anonymous_session['total_donated'],
                'anonymous_donation_count' => $anonymous_session['donation_count'],
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
 * Get donation statistics for a donor
 */
function getDonationStats($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Donor ID required']);
        return;
    }
    
    $query = "SELECT 
                SUM(amount) as total_donated, 
                COUNT(*) as donation_count,
                AVG(amount) as average_donation,
                MAX(amount) as largest_donation,
                MIN(amount) as smallest_donation,
                MAX(created_at) as last_donation_date
              FROM donations 
              WHERE donor_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get distribution by charity (based on actual donations, not preferences)
    $query = "SELECT 
                c.name as charity_name,
                c.id as charity_id,
                SUM(d.amount) as total_donated,
                COUNT(*) as donation_count
              FROM donations d
              JOIN charities c ON d.charity_id = c.id
              WHERE d.donor_id = ?
              GROUP BY d.charity_id
              ORDER BY total_donated DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $charity_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'stats' => $stats ?: [
            'total_donated' => 0,
            'donation_count' => 0,
            'average_donation' => 0,
            'largest_donation' => 0,
            'smallest_donation' => 0,
            'last_donation_date' => null
        ],
        'charity_distribution' => $charity_distribution
    ]);
}

/**
 * Get global donation statistics (for anonymous mobile app)
 */
function getGlobalDonationStats($db, $data) {
    // Combined stats from both registered and anonymous donations
    $query = "SELECT 
                (SELECT COALESCE(SUM(amount), 0) FROM donations WHERE status = 'completed') +
                (SELECT COALESCE(SUM(amount), 0) FROM anonymous_donations WHERE status = 'completed') as total_donated,
                
                (SELECT COUNT(*) FROM donations WHERE status = 'completed') +
                (SELECT COUNT(*) FROM anonymous_donations WHERE status = 'completed') as donation_count,
                
                (SELECT COALESCE(SUM(amount), 0) FROM donations WHERE DATE(created_at) = CURDATE() AND status = 'completed') +
                (SELECT COALESCE(SUM(amount), 0) FROM anonymous_donations WHERE DATE(created_at) = CURDATE() AND status = 'completed') as today_donated,
                
                (SELECT COUNT(*) FROM donations WHERE DATE(created_at) = CURDATE() AND status = 'completed') +
                (SELECT COUNT(*) FROM anonymous_donations WHERE DATE(created_at) = CURDATE() AND status = 'completed') as today_count,
                
                (SELECT COUNT(DISTINCT charity_id) FROM donations WHERE status = 'completed') +
                (SELECT COUNT(DISTINCT charity_id) FROM anonymous_donations WHERE status = 'completed') as charity_count";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get last donation (from either table)
    $last_query = "SELECT 
                    amount, 
                    created_at, 
                    c.name as charity_name,
                    'registered' as donor_type,
                    d.module_id,
                    d.location
                   FROM donations d
                   JOIN charities c ON d.charity_id = c.id
                   WHERE d.status = 'completed'
                   ORDER BY created_at DESC LIMIT 1
                   
                   UNION ALL
                   
                   SELECT 
                    amount, 
                    created_at, 
                    c.name as charity_name,
                    'anonymous' as donor_type,
                    ad.module_id,
                    ad.location
                   FROM anonymous_donations ad
                   JOIN charities c ON ad.charity_id = c.id
                   WHERE ad.status = 'completed'
                   ORDER BY created_at DESC LIMIT 1
                   
                   ORDER BY created_at DESC LIMIT 1";
    
    $last_stmt = $db->prepare($last_query);
    $last_stmt->execute();
    $last_donation = $last_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get top charities
    $top_charities_query = "SELECT 
                              c.id,
                              c.name,
                              COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0) as total_donated,
                              COUNT(d.id) + COUNT(ad.id) as donation_count
                            FROM charities c
                            LEFT JOIN donations d ON c.id = d.charity_id AND d.status = 'completed'
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
 * Get donation history for a donor
 */
function getDonationHistory($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Donor ID required']);
        return;
    }
    
    try {
        $query = "SELECT 
                    d.id,
                    d.amount,
                    d.created_at,
                    d.module_id,
                    d.session_id,
                    c.name as charity_name,
                    c.id as charity_id,
                    ds.started_at as session_started
                  FROM donations d
                  JOIN charities c ON d.charity_id = c.id
                  JOIN donation_sessions ds ON d.session_id = ds.id
                  WHERE d.donor_id = ?
                  ORDER BY d.created_at DESC
                  LIMIT 50";
        $stmt = $db->prepare($query);
        $stmt->execute([$donor_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'history' => $history,
            'count' => count($history)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Donation history error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load donation history: ' . $e->getMessage()]);
    }
}

/**
 * Get donations for a specific session
 */
function getSessionDonations($db, $data) {
    $session_id = $data['session_id'] ?? '';
    $donor_id = $data['donor_id'] ?? '';
    
    if (empty($session_id) || empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session ID and donor ID required']);
        return;
    }
    
    try {
        $query = "SELECT 
                    d.id,
                    d.amount,
                    d.coin_count,
                    d.created_at,
                    c.name as charity_name,
                    m.name as module_name
                  FROM donations d
                  JOIN charities c ON d.charity_id = c.id
                  JOIN modules m ON d.module_id = m.module_id
                  WHERE d.session_id = ? AND d.donor_id = ?
                  ORDER BY d.created_at ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([$session_id, $donor_id]);
        $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get session information
        $session_query = "SELECT ds.*, c.name as charity_name 
                         FROM donation_sessions ds
                         JOIN charities c ON ds.charity_id = c.id
                         WHERE ds.id = ? AND ds.donor_id = ?";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$session_id, $donor_id]);
        $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'donations' => $donations,
            'session' => $session,
            'count' => count($donations)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Session donations error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load session donations: ' . $e->getMessage()]);
    }
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
                    m.name as module_name,
                    ds.started_at as session_started,
                    ds.total_amount as session_total,
                    ds.total_coins as session_coins
                  FROM anonymous_donations ad
                  JOIN charities c ON ad.charity_id = c.id
                  LEFT JOIN modules m ON ad.module_id = m.module_id
                  LEFT JOIN donation_sessions ds ON ad.anonymous_session_id = ds.anonymous_session_id
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

/**
 * Get tax receipt data for a donor
 */
function getTaxReceipts($db, $data) {
    $donor_id = $data['donor_id'] ?? '';
    $year = $data['year'] ?? date('Y');
    
    if (empty($donor_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Donor ID required']);
        return;
    }
    
    $receipt_data = generate_tax_receipt_data($donor_id, $year, $db);
    
    echo json_encode([
        'success' => true, 
        'receipt' => $receipt_data,
        'donor_info' => getDonorInfo($donor_id, $db)
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
    
    // Get both registered and anonymous donations
    $query = "SELECT 
                d.id,
                d.amount,
                d.created_at,
                d.module_id,
                do.user_id as donor_id,
                ds.id as session_id,
                'registered' as donor_type,
                m.name as module_name,
                d.location
              FROM donations d
              JOIN donors do ON d.donor_id = do.id
              JOIN donation_sessions ds ON d.session_id = ds.id
              LEFT JOIN modules m ON d.module_id = m.module_id
              WHERE d.charity_id = ? AND d.donor_id IS NOT NULL
              
              UNION ALL
              
              SELECT 
                ad.id + 1000000 as id,
                ad.amount,
                ad.created_at,
                ad.module_id,
                'anonymous' as donor_id,
                NULL as session_id,
                'anonymous' as donor_type,
                m.name as module_name,
                ad.location
              FROM anonymous_donations ad
              LEFT JOIN modules m ON ad.module_id = m.module_id
              WHERE ad.charity_id = ?
              
              ORDER BY created_at DESC
              LIMIT ? OFFSET ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id, $charity_id, $limit, $offset]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total and amount for pagination
    $query = "SELECT 
                (SELECT COUNT(*) FROM donations WHERE charity_id = ? AND donor_id IS NOT NULL) +
                (SELECT COUNT(*) FROM anonymous_donations WHERE charity_id = ?) as total,
                
                (SELECT COALESCE(SUM(amount), 0) FROM donations WHERE charity_id = ? AND donor_id IS NOT NULL) +
                (SELECT COALESCE(SUM(amount), 0) FROM anonymous_donations WHERE charity_id = ?) as total_amount";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id, $charity_id, $charity_id, $charity_id]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'donations' => $donations,
        'pagination' => [
            'total' => $totals['total'],
            'total_amount' => $totals['total_amount'],
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
                d.id,
                d.amount,
                d.created_at,
                do.user_id as donor_id,
                c.name as charity_name,
                ds.id as session_id,
                'registered' as donor_type
              FROM donations d
              JOIN donors do ON d.donor_id = do.id
              JOIN charities c ON d.charity_id = c.id
              JOIN donation_sessions ds ON d.session_id = ds.id
              WHERE d.donor_id IS NOT NULL
              ORDER BY d.created_at DESC
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
 * Get recent anonymous donations
 */
function getRecentAnonymousDonations($db, $data) {
    $limit = intval($data['limit'] ?? 20);
    
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
                m.name as module_name,
                'anonymous' as donor_type
              FROM anonymous_donations ad
              JOIN charities c ON ad.charity_id = c.id
              LEFT JOIN modules m ON ad.module_id = m.module_id
              ORDER BY ad.created_at DESC
              LIMIT ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'donations' => $donations
    ]);
}

/**
 * Helper function to get donor information
 */
function getDonorInfo($donor_id, $db) {
    $query = "SELECT user_id, email, created_at FROM donors WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>