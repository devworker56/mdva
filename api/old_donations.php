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
        case 'record_anonymous':  // CHANGED: This matches ESP32 call
            if ($method == 'POST') {
                recordAnonymousDonation($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'record_donation':  // Keep for backward compatibility
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
 * UPDATED: Matches ESP32 payload exactly
 */
function recordAnonymousDonation($db, $data) {
    error_log("🎯 [DEBUG] ESP32 donation recording started: " . json_encode($data));
    
    // ESP32 sends: module_id, amount, coin_count, session_id, charity_id, donation_session_id, location
    $module_id = $data['module_id'] ?? '';
    $amount = floatval($data['amount'] ?? 0);
    $coin_count = intval($data['coin_count'] ?? 0);
    $session_id = $data['session_id'] ?? '';
    $charity_id = $data['charity_id'] ?? '';
    $donation_session_id = $data['donation_session_id'] ?? ''; // Optional, for backward compatibility
    $location = $data['location'] ?? ''; // Optional from ESP32
    
    error_log("🎯 [DEBUG] Parsed values:");
    error_log("🎯 module_id: " . $module_id);
    error_log("🎯 amount: " . $amount);
    error_log("🎯 coin_count: " . $coin_count);
    error_log("🎯 session_id: " . $session_id);
    error_log("🎯 charity_id: " . $charity_id);
    error_log("🎯 donation_session_id: " . $donation_session_id);
    
    // Validation - match ESP32 validation
    if (empty($module_id) || $amount <= 0 || empty($session_id) || empty($charity_id)) {
        http_response_code(400);
        $error_msg = 'Missing required fields: ';
        $error_msg .= empty($module_id) ? 'module_id, ' : '';
        $error_msg .= ($amount <= 0) ? 'amount > 0, ' : '';
        $error_msg .= empty($session_id) ? 'session_id, ' : '';
        $error_msg .= empty($charity_id) ? 'charity_id' : '';
        error_log("❌ [ERROR] Validation failed: " . $error_msg);
        echo json_encode(['success' => false, 'message' => $error_msg]);
        return;
    }
    
    try {
        // 1. VERIFY CHARITY
        $charity_query = "SELECT id, name FROM charities WHERE id = ? AND approved = 1 AND active = 1";
        $charity_stmt = $db->prepare($charity_query);
        $charity_stmt->execute([$charity_id]);
        $charity = $charity_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$charity) {
            http_response_code(404);
            error_log("❌ [ERROR] Charity not found or not approved: " . $charity_id);
            echo json_encode(['success' => false, 'message' => 'Charity not found or not approved']);
            return;
        }
        error_log("✅ Charity verified: " . $charity['name']);
        
        // 2. VERIFY MODULE
        $module_query = "SELECT id, name FROM modules WHERE module_id = ? AND status = 'active'";
        $module_stmt = $db->prepare($module_query);
        $module_stmt->execute([$module_id]);
        $module = $module_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$module) {
            http_response_code(404);
            error_log("❌ [ERROR] Module not found or not active: " . $module_id);
            echo json_encode(['success' => false, 'message' => 'Module not found or not active']);
            return;
        }
        error_log("✅ Module verified: " . $module['name']);
        
        $db->beginTransaction();
        
        // 3. GET LOCATION (if not provided by ESP32)
        if (empty($location)) {
            $location_query = "SELECT 
                                l.name as location_name, 
                                l.city, 
                                l.province 
                              FROM modules m
                              LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
                              LEFT JOIN locations l ON ml.location_id = l.id
                              WHERE m.module_id = ?";
            $location_stmt = $db->prepare($location_query);
            $location_stmt->execute([$module_id]);
            $location_data = $location_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($location_data && $location_data['location_name']) {
                $location = $location_data['location_name'] . ', ' . 
                           $location_data['city'] . ', ' . 
                           $location_data['province'];
            }
        }
        error_log("📍 Location: " . $location);
        
        // 4. GENERATE DONATION CODE (optional)
        $donation_code = null;
        $settings_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'generate_receipt_codes_for_anonymous'";
        $settings_stmt = $db->prepare($settings_query);
        $settings_stmt->execute();
        $generate_codes = $settings_stmt->fetchColumn();
        
        if ($generate_codes === 'true') {
            $donation_code = 'DON' . strtoupper(uniqid()) . substr($session_id, -6);
            error_log("🎫 Generated donation code: " . $donation_code);
        }
        
        // 5. INSERT INTO MAIN DONATIONS TABLE (this is what dashboard reads)
        error_log("📝 Inserting into donations table...");
        $donation_query = "INSERT INTO donations 
                          (charity_id, amount, coin_count, module_id, session_id, 
                           donation_type, location, status, created_at)
                          VALUES (?, ?, ?, ?, ?, 'cash', ?, 'completed', NOW())";
        
        $donation_stmt = $db->prepare($donation_query);
        $donation_stmt->execute([
            $charity_id, 
            $amount, 
            $coin_count, 
            $module_id, 
            $session_id, 
            $location
        ]);
        
        $donation_id = $db->lastInsertId();
        error_log("✅ Inserted into donations table with ID: " . $donation_id);
        
        // 6. INSERT INTO ANONYMOUS DONATIONS (for compatibility)
        error_log("📝 Inserting into anonymous_donations table...");
        $anonymous_query = "INSERT INTO anonymous_donations 
                          (anonymous_session_id, charity_id, amount, coin_count, 
                           module_id, location, donation_code, status, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())";
        
        $anonymous_stmt = $db->prepare($anonymous_query);
        $anonymous_stmt->execute([
            $session_id,
            $charity_id,
            $amount,
            $coin_count,
            $module_id,
            $location,
            $donation_code
        ]);
        
        $anonymous_donation_id = $db->lastInsertId();
        error_log("✅ Inserted into anonymous_donations with ID: " . $anonymous_donation_id);
        
        // 7. UPDATE/CREATE ANONYMOUS SESSION
        error_log("📝 Updating anonymous_sessions...");
        $update_session_query = "INSERT INTO anonymous_sessions 
                                (session_id, charity_id, module_id, total_amount, 
                                 total_coins, started_at, last_activity, status)
                                VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 'active')
                                ON DUPLICATE KEY UPDATE
                                total_amount = total_amount + VALUES(total_amount),
                                total_coins = total_coins + VALUES(total_coins),
                                last_activity = NOW(),
                                status = 'active'";
        
        $update_stmt = $db->prepare($update_session_query);
        $update_stmt->execute([$session_id, $charity_id, $module_id, $amount, $coin_count]);
        error_log("✅ Updated anonymous_sessions");
        
        // 8. GET UPDATED TOTALS
        $totals_query = "SELECT total_amount, total_coins 
                        FROM anonymous_sessions 
                        WHERE session_id = ?";
        $totals_stmt = $db->prepare($totals_query);
        $totals_stmt->execute([$session_id]);
        $totals = $totals_stmt->fetch(PDO::FETCH_ASSOC);
        
        $db->commit();
        error_log("✅ Transaction committed successfully!");
        
        // 9. PREPARE PUSHER DATA
        $pusher_data = [
            'donation_id' => $donation_id,
            'anonymous_donation_id' => $anonymous_donation_id,
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'amount' => $amount,
            'coin_count' => $coin_count,
            'module_id' => $module_id,
            'module_name' => $module['name'],
            'location' => $location,
            'session_id' => $session_id,
            'donation_session_id' => $donation_session_id, // Include for backward compatibility
            'session_total' => $totals['total_amount'] ?? $amount,
            'session_coins' => $totals['total_coins'] ?? $coin_count,
            'donation_code' => $donation_code,
            'timestamp' => date('Y-m-d H:i:s'),
            'donor_type' => 'anonymous'
        ];
        
        error_log("📤 Preparing Pusher notifications...");
        
        // 10. SEND PUSHER NOTIFICATIONS
        // Notify charity dashboard
        $charity_result = notify_pusher('new_donation', $pusher_data, "charity_" . $charity_id);
        error_log("📡 Charity notification result: " . ($charity_result ? 'Success' : 'Failed'));
        
        // Notify public donations channel
        $public_result = notify_pusher('donation_recorded', $pusher_data, "public-donations");
        
        // Notify session channel for mobile app
        $session_result = notify_pusher('donation_received', $pusher_data, "session_" . $session_id);
        
        // Notify module channel
        $module_result = notify_pusher('donation_processed', $pusher_data, "private-module_" . $module_id);
        
        // 11. UPDATE CHARITY STATS CACHE
        error_log("📊 Updating charity stats cache...");
        try {
            $update_stats_query = "CALL UpdateCharityStats(?)";
            $update_stats_stmt = $db->prepare($update_stats_query);
            $update_stats_stmt->execute([$charity_id]);
            error_log("✅ Charity stats cache updated");
        } catch (Exception $e) {
            error_log("⚠️ Could not update charity stats: " . $e->getMessage());
        }
        
        // 12. PREPARE RESPONSE
        $response = [
            'success' => true,
            'message' => 'Donation recorded successfully',
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
                'session_total' => $totals['total_amount'] ?? $amount,
                'session_coins' => $totals['total_coins'] ?? $coin_count,
                'donation_code' => $donation_code,
                'location' => $location,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        error_log("✅🎉 Donation recording COMPLETE!");
        error_log("Response: " . json_encode($response));
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
            error_log("❌ Transaction rolled back due to error");
        }
        http_response_code(500);
        error_log("❌❌❌ CRITICAL ERROR in recordAnonymousDonation: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to record donation: ' . $e->getMessage(),
            'debug_info' => [
                'module_id' => $module_id,
                'amount' => $amount,
                'session_id' => $session_id,
                'charity_id' => $charity_id
            ]
        ]);
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
    
    // Query from main donations table (which includes anonymous donations)
    $query = "SELECT 
                d.id,
                d.amount,
                d.created_at,
                'anonymous' as donor_id,
                c.name as charity_name,
                'anonymous' as donor_type,
                d.module_id,
                d.location,
                d.session_id
              FROM donations d
              JOIN charities c ON d.charity_id = c.id
              WHERE d.donation_type = 'cash'  -- Anonymous donations are cash
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