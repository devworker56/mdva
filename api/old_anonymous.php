<?php
// anonymous.php - API endpoints for anonymous donations
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
        case 'start_session':
            if ($method == 'POST') {
                startAnonymousSession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'record_donation':
            if ($method == 'POST') {
                recordAnonymousDonation($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'get_session':
            if ($method == 'GET') {
                getAnonymousSession($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'get_donations':
            if ($method == 'GET') {
                getAnonymousDonations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'generate_receipt_code':
            if ($method == 'POST') {
                generateReceiptCode($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'claim_receipt':
            if ($method == 'POST') {
                claimTaxReceipt($db, $input);
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
    $location = $data['location'] ?? '';
    $device_id = $data['device_id'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
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
        
        // Create anonymous session
        $session_query = "INSERT INTO anonymous_sessions 
                          (session_id, device_id, ip_address, user_agent, started_at, active) 
                          VALUES (?, ?, ?, ?, NOW(), 1)";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$session_id, $device_id, $ip_address, $user_agent]);
        
        // Create donation session entry for compatibility
        $donation_session_query = "INSERT INTO donation_sessions 
                                   (anonymous_session_id, charity_id, module_id, status, started_at, expires_at) 
                                   VALUES (?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))";
        $donation_session_stmt = $db->prepare($donation_session_query);
        $donation_session_stmt->execute([$session_id, $charity_id, $module_id]);
        
        $donation_session_id = $db->lastInsertId();
        
        // Notify module via Pusher
        $pusher_data = [
            'session_id' => $session_id,
            'donation_session_id' => $donation_session_id,
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'module_id' => $module_id,
            'action' => 'session_started',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $pusher_result = notify_pusher('session_started', $pusher_data, "private-module_" . $module_id);
        
        // Log activity
        log_activity($db, 'system', 0, 'anonymous_session_started', 
            "Anonymous session started: $session_id for charity '{$charity['name']}' via module $module_id");
        
        $db->commit();
        
        $response = [
            'success' => true,
            'message' => 'Anonymous session started successfully',
            'session' => [
                'session_id' => $session_id,
                'donation_session_id' => $donation_session_id,
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'module_id' => $module_id,
                'module_name' => $module['name'],
                'location' => $location,
                'started_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + 1800) // 30 minutes
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
 * Record an anonymous donation from ESP32
 */
function recordAnonymousDonation($db, $data) {
    error_log("Recording anonymous donation: " . json_encode($data));
    
    $module_id = $data['module_id'] ?? '';
    $amount = floatval($data['amount'] ?? 0);
    $coin_count = intval($data['coin_count'] ?? 0);
    $session_id = $data['session_id'] ?? '';
    $donation_session_id = $data['donation_session_id'] ?? '';
    
    if (empty($module_id) || $amount <= 0 || empty($session_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: module_id, amount > 0, and session_id are required']);
        return;
    }
    
    try {
        // Get session and charity info
        $session_query = "SELECT ds.charity_id, ds.anonymous_session_id, c.name as charity_name
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
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No active donation session found']);
            return;
        }
        
        $charity_id = $session['charity_id'];
        $anonymous_session_id = $session['anonymous_session_id'] ?: $session_id;
        
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
        
        // Generate donation code if needed
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
                          VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY))";
            $code_stmt = $db->prepare($code_query);
            $code_stmt->execute([$donation_code, $anonymous_session_id, $charity_id, $amount]);
        }
        
        // Record in anonymous_donations table
        $donation_query = "INSERT INTO anonymous_donations 
                          (anonymous_session_id, charity_id, amount, coin_count, module_id, location, donation_code, status)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')";
        $donation_stmt = $db->prepare($donation_query);
        $donation_stmt->execute([
            $anonymous_session_id, 
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
            $anonymous_session_id, 
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
        
        // Get updated session info
        $session_query = "SELECT total_amount, total_coins FROM donation_sessions WHERE id = ?";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->execute([$donation_session_id]);
        $updated_session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        
        $db->commit();
        
        // Notify via Pusher
        $pusher_data = [
            'donation_id' => $donation_id,
            'anonymous_donation_id' => $anonymous_donation_id,
            'charity_id' => $charity_id,
            'charity_name' => $session['charity_name'],
            'amount' => $amount,
            'coin_count' => $coin_count,
            'module_id' => $module_id,
            'location' => $location,
            'session_id' => $anonymous_session_id,
            'donation_session_id' => $donation_session_id,
            'session_total' => $updated_session['total_amount'],
            'session_coins' => $updated_session['total_coins'],
            'donation_code' => $donation_code,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Notify charity dashboard
        notify_pusher('new_donation', $pusher_data, "charity_" . $charity_id);
        
        // Notify public donations channel
        notify_pusher('donation_recorded', $pusher_data, "public-donations");
        
        // Notify session channel for mobile app
        notify_pusher('donation_received', $pusher_data, "session_" . $anonymous_session_id);
        
        $response = [
            'success' => true,
            'message' => 'Donation recorded successfully',
            'donation' => [
                'id' => $donation_id,
                'anonymous_id' => $anonymous_donation_id,
                'amount' => $amount,
                'coin_count' => $coin_count,
                'charity_id' => $charity_id,
                'charity_name' => $session['charity_name'],
                'module_id' => $module_id,
                'session_id' => $anonymous_session_id,
                'donation_session_id' => $donation_session_id,
                'session_total' => $updated_session['total_amount'],
                'session_coins' => $updated_session['total_coins'],
                'donation_code' => $donation_code,
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
        echo json_encode(['success' => false, 'message' => 'Failed to record donation: ' . $e->getMessage()]);
    }
}

/**
 * Get anonymous session information
 */
function getAnonymousSession($db, $data) {
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
                              amount, coin_count, created_at, donation_code, location
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

/**
 * Get anonymous donations for a charity
 */
function getAnonymousDonations($db, $data) {
    $charity_id = $data['charity_id'] ?? '';
    $limit = intval($data['limit'] ?? 50);
    $offset = intval($data['offset'] ?? 0);
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'charity_id is required']);
        return;
    }
    
    // Get both registered and anonymous donations
    $query = "SELECT 
                'anonymous' as donor_type,
                ad.amount,
                ad.coin_count,
                ad.created_at,
                ad.module_id,
                ad.location,
                ad.donation_code,
                m.name as module_name,
                s.started_at as session_started
              FROM anonymous_donations ad
              JOIN anonymous_sessions s ON ad.anonymous_session_id = s.session_id
              LEFT JOIN modules m ON ad.module_id = m.module_id
              WHERE ad.charity_id = ?
              
              UNION ALL
              
              SELECT 
                'registered' as donor_type,
                d.amount,
                d.coin_count,
                d.created_at,
                d.module_id,
                d.location,
                NULL as donation_code,
                m.name as module_name,
                ds.started_at as session_started
              FROM donations d
              JOIN donation_sessions ds ON d.session_id = ds.id
              LEFT JOIN modules m ON d.module_id = m.module_id
              WHERE d.charity_id = ? AND d.donor_id IS NOT NULL
              
              ORDER BY created_at DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id, $charity_id, $limit, $offset]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get totals
    $totals_query = "SELECT 
                       COUNT(*) as total_count,
                       SUM(amount) as total_amount
                     FROM (
                       SELECT amount FROM anonymous_donations WHERE charity_id = ?
                       UNION ALL
                       SELECT amount FROM donations WHERE charity_id = ? AND donor_id IS NOT NULL
                     ) as all_donations";
    
    $totals_stmt = $db->prepare($totals_query);
    $totals_stmt->execute([$charity_id, $charity_id]);
    $totals = $totals_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'donations' => $donations,
        'pagination' => [
            'total' => $totals['total_count'] ?? 0,
            'total_amount' => $totals['total_amount'] ?? 0,
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
}

/**
 * Generate a tax receipt code for anonymous donations
 */
function generateReceiptCode($db, $data) {
    $session_id = $data['session_id'] ?? '';
    $charity_id = $data['charity_id'] ?? '';
    $email = $data['email'] ?? '';
    
    if (empty($session_id) || empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'session_id and charity_id are required']);
        return;
    }
    
    // Get total donations for this session and charity
    $donations_query = "SELECT SUM(amount) as total_amount, COUNT(*) as donation_count
                        FROM anonymous_donations 
                        WHERE anonymous_session_id = ? AND charity_id = ?";
    $donations_stmt = $db->prepare($donations_query);
    $donations_stmt->execute([$session_id, $charity_id]);
    $donations = $donations_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donations || $donations['total_amount'] <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No donations found for this session and charity']);
        return;
    }
    
    // Generate receipt code
    $receipt_code = 'RCPT-' . date('Y') . '-' . strtoupper(substr($session_id, -8)) . '-' . time();
    
    try {
        $db->beginTransaction();
        
        // Update donation codes if they exist
        $update_query = "UPDATE donation_codes 
                        SET amount = ?, claimed_at = NOW()
                        WHERE anonymous_session_id = ? AND charity_id = ? AND claimed_at IS NULL";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$donations['total_amount'], $session_id, $charity_id]);
        
        // Also update donations table
        $update_donations_query = "UPDATE donations 
                                  SET donation_code = ?
                                  WHERE anonymous_session_id = ? AND charity_id = ?";
        $update_donations_stmt = $db->prepare($update_donations_query);
        $update_donations_stmt->execute([$receipt_code, $session_id, $charity_id]);
        
        $db->commit();
        
        // Get charity info
        $charity_query = "SELECT name FROM charities WHERE id = ?";
        $charity_stmt = $db->prepare($charity_query);
        $charity_stmt->execute([$charity_id]);
        $charity = $charity_stmt->fetch(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'message' => 'Tax receipt code generated successfully',
            'receipt' => [
                'receipt_code' => $receipt_code,
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'] ?? '',
                'total_amount' => $donations['total_amount'],
                'donation_count' => $donations['donation_count'],
                'session_id' => $session_id,
                'generated_at' => date('Y-m-d H:i:s'),
                'year' => date('Y')
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        error_log("Error generating receipt code: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to generate receipt code: ' . $e->getMessage()]);
    }
}

/**
 * Claim a tax receipt with email
 */
function claimTaxReceipt($db, $data) {
    $receipt_code = $data['receipt_code'] ?? '';
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? '';
    
    if (empty($receipt_code) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'receipt_code and email are required']);
        return;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        return;
    }
    
    // Check if receipt code exists and is not expired
    $receipt_query = "SELECT 
                        dc.*,
                        c.name as charity_name,
                        ad.anonymous_session_id,
                        ad.amount,
                        ad.created_at
                      FROM donation_codes dc
                      JOIN charities c ON dc.charity_id = c.id
                      LEFT JOIN anonymous_donations ad ON dc.donation_code = ad.donation_code
                      WHERE dc.donation_code = ? 
                      AND dc.expires_at > NOW()
                      AND dc.claimed_at IS NULL";
    
    $receipt_stmt = $db->prepare($receipt_query);
    $receipt_stmt->execute([$receipt_code]);
    $receipt = $receipt_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receipt) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Receipt code not found, expired, or already claimed']);
        return;
    }
    
    try {
        // Mark as claimed
        $update_query = "UPDATE donation_codes 
                        SET claimed_at = NOW()
                        WHERE donation_code = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$receipt_code]);
        
        // Also update anonymous_donations
        $update_anonymous_query = "UPDATE anonymous_donations 
                                  SET claimed_at = NOW()
                                  WHERE donation_code = ?";
        $update_anonymous_stmt = $db->prepare($update_anonymous_query);
        $update_anonymous_stmt->execute([$receipt_code]);
        
        // Generate tax receipt
        $tax_receipt_number = 'TAX-' . date('Y') . '-' . strtoupper(substr($receipt_code, -12));
        $year = date('Y');
        
        // TODO: Send email with receipt PDF
        // For now, just return the receipt data
        
        $response = [
            'success' => true,
            'message' => 'Tax receipt claimed successfully. Receipt will be emailed to you.',
            'receipt' => [
                'receipt_number' => $tax_receipt_number,
                'original_code' => $receipt_code,
                'charity_name' => $receipt['charity_name'],
                'charity_id' => $receipt['charity_id'],
                'amount' => $receipt['amount'],
                'year' => $year,
                'claimed_at' => date('Y-m-d H:i:s'),
                'claimed_by_email' => $email,
                'claimed_by_name' => $name
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Error claiming tax receipt: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to claim tax receipt: ' . $e->getMessage()]);
    }
}
?>