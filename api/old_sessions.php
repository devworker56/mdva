<?php
// sessions.php - API endpoint for session management
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get input data
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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch($action) {
        case 'start_anonymous':
            if ($method == 'POST') {
                startAnonymousSession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'end_anonymous':
            if ($method == 'POST') {
                endAnonymousSession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'get_session':
            if ($method == 'GET') {
                getSession($db, $input);
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
 * Start an anonymous donation session
 */
function startAnonymousSession($db, $data) {
    error_log("Starting anonymous session: " . json_encode($data));
    
    $session_id = $data['session_id'] ?? '';
    $charity_id = $data['charity_id'] ?? '';
    $module_id = $data['module_id'] ?? '';
    $mobile_app_id = $data['mobile_app_id'] ?? '';
    
    if (empty($session_id) || empty($charity_id) || empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: session_id, charity_id, and module_id are required']);
        return;
    }
    
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
    
    try {
        $db->beginTransaction();
        
        // Check if session already exists
        $existing_session_query = "SELECT id FROM anonymous_sessions WHERE session_id = ? AND status = 'active'";
        $existing_session_stmt = $db->prepare($existing_session_query);
        $existing_session_stmt->execute([$session_id]);
        $existing_session = $existing_session_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_session) {
            // Update existing session
            $update_session_query = "UPDATE anonymous_sessions 
                                    SET charity_id = ?, 
                                        module_id = ?,
                                        mobile_app_id = ?,
                                        last_activity = NOW(),
                                        status = 'active'
                                    WHERE session_id = ?";
            $update_session_stmt = $db->prepare($update_session_query);
            $update_session_stmt->execute([$charity_id, $module_id, $mobile_app_id, $session_id]);
            
            $anonymous_session_id = $existing_session['id'];
        } else {
            // Create new anonymous session
            $session_query = "INSERT INTO anonymous_sessions 
                            (session_id, mobile_app_id, module_id, charity_id, status, started_at, last_activity) 
                            VALUES (?, ?, ?, ?, 'active', NOW(), NOW())";
            $session_stmt = $db->prepare($session_query);
            $session_stmt->execute([$session_id, $mobile_app_id, $module_id, $charity_id]);
            
            $anonymous_session_id = $db->lastInsertId();
        }
        
        // Create or update donation session
        $donation_session_query = "INSERT INTO donation_sessions 
                                  (anonymous_session_id, charity_id, module_id, status, started_at, expires_at) 
                                  VALUES (?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE))
                                  ON DUPLICATE KEY UPDATE
                                  charity_id = VALUES(charity_id),
                                  status = VALUES(status),
                                  expires_at = VALUES(expires_at)";
        $donation_session_stmt = $db->prepare($donation_session_query);
        $donation_session_stmt->execute([$session_id, $charity_id, $module_id]);
        
        $donation_session_id = $db->lastInsertId();
        
        // Get module location for response
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
        
        // Notify ESP32 via Pusher - Using PUBLIC channel
        $pusher_data = [
            'session_id' => $session_id,
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'module_id' => $module_id,
            'donation_session_id' => $donation_session_id,
            'expires_at' => date('Y-m-d H:i:s', time() + 900), // 15 minutes
            'action' => 'session_started',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $pusher_result = notify_pusher('start_session', $pusher_data, "module_" . $module_id);
        
        // Log activity
        log_activity($db, 'mobile_app', 0, 'anonymous_session_started', 
            "Mobile app started anonymous session: $session_id for charity '{$charity['name']}' via module $module_id");
        
        $db->commit();
        
        $response = [
            'success' => true,
            'message' => 'Session started successfully. ESP32 notified.',
            'session' => [
                'session_id' => $session_id,
                'anonymous_session_id' => $anonymous_session_id,
                'donation_session_id' => $donation_session_id,
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'module_id' => $module_id,
                'module_name' => $module['name'],
                'location' => $location,
                'started_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + 900), // 15 minutes
                'pusher_notified' => $pusher_result
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        error_log("Error starting anonymous session: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to start session: ' . $e->getMessage()]);
    }
}

/**
 * End an anonymous donation session
 */
function endAnonymousSession($db, $data) {
    $session_id = $data['session_id'] ?? '';
    $module_id = $data['module_id'] ?? '';
    
    if (empty($session_id) || empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'session_id and module_id are required']);
        return;
    }
    
    try {
        // Update anonymous session
        $update_session_query = "UPDATE anonymous_sessions 
                                SET status = 'completed', 
                                    last_activity = NOW() 
                                WHERE session_id = ? AND module_id = ?";
        $update_session_stmt = $db->prepare($update_session_query);
        $update_session_stmt->execute([$session_id, $module_id]);
        
        // Update donation session
        $update_donation_session_query = "UPDATE donation_sessions 
                                         SET status = 'completed', 
                                             ended_at = NOW() 
                                         WHERE anonymous_session_id = ? AND module_id = ?";
        $update_donation_session_stmt = $db->prepare($update_donation_session_query);
        $update_donation_session_stmt->execute([$session_id, $module_id]);
        
        // Notify ESP32 via Pusher - Using PUBLIC channel
        $pusher_data = [
            'session_id' => $session_id,
            'module_id' => $module_id,
            'action' => 'session_ended',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        notify_pusher('end_session', $pusher_data, "module_" . $module_id);
        
        // Get session summary
        $summary_query = "SELECT 
                           SUM(ad.amount) as total_amount,
                           COUNT(ad.id) as donation_count,
                           c.name as charity_name
                         FROM anonymous_donations ad
                         JOIN charities c ON ad.charity_id = c.id
                         WHERE ad.anonymous_session_id = ?
                         GROUP BY ad.charity_id";
        $summary_stmt = $db->prepare($summary_query);
        $summary_stmt->execute([$session_id]);
        $summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Session ended successfully',
            'session_id' => $session_id,
            'summary' => $summary
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Error ending session: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to end session: ' . $e->getMessage()]);
    }
}

/**
 * Get session information
 */
function getSession($db, $data) {
    $session_id = $data['session_id'] ?? '';
    
    if (empty($session_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'session_id is required']);
        return;
    }
    
    $query = "SELECT 
                s.*,
                ds.id as donation_session_id,
                ds.charity_id,
                ds.module_id,
                ds.total_amount,
                ds.total_coins,
                ds.status as session_status,
                ds.expires_at,
                c.name as charity_name,
                m.name as module_name
              FROM anonymous_sessions s
              LEFT JOIN donation_sessions ds ON s.session_id = ds.anonymous_session_id
              LEFT JOIN charities c ON ds.charity_id = c.id
              LEFT JOIN modules m ON ds.module_id = m.module_id
              WHERE s.session_id = ?
              ORDER BY ds.started_at DESC LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        // Get donations for this session
        $donations_query = "SELECT 
                              amount, coin_count, created_at, donation_code, location, module_id
                            FROM anonymous_donations 
                            WHERE anonymous_session_id = ?
                            ORDER BY created_at DESC";
        $donations_stmt = $db->prepare($donations_query);
        $donations_stmt->execute([$session_id]);
        $donations = $donations_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'session' => $session,
            'donations' => $donations,
            'donation_count' => count($donations),
            'total_amount' => array_sum(array_column($donations, 'amount'))
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => true,
            'session' => null,
            'message' => 'Session not found'
        ]);
    }
}
?>