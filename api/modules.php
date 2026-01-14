<?php
// modules.php - Module registration, status, and management
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
        case 'register':
            if ($method == 'POST') {
                registerModule($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'status':
            if ($method == 'GET') {
                getModuleStatus($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'heartbeat':
            if ($method == 'POST') {
                updateHeartbeat($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'get_all':
            if ($method == 'GET') {
                getAllModules($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'get_locations':
            if ($method == 'GET') {
                getModuleLocations($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'update_location':
            if ($method == 'POST') {
                updateModuleLocation($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'generate_qr':
            if ($method == 'POST') {
                generateModuleQR($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'stats':
            if ($method == 'GET') {
                getModuleStats($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'check_status':
            if ($method == 'GET') {
                checkModuleStatus($db, $input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'update':
            if ($method == 'POST') {
                updateModule($db, $input);
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
 * Register a new module
 */
function registerModule($db, $data) {
    error_log("Registering module: " . json_encode($data));
    
    $mac_address = $data['mac_address'] ?? '';
    $firmware_version = $data['firmware_version'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (empty($mac_address)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'MAC address is required']);
        return;
    }
    
    // Check if module is already registered
    $check_query = "SELECT 
                      m.id, 
                      m.module_id, 
                      m.name, 
                      m.status,
                      mr.registration_token
                    FROM modules m
                    LEFT JOIN module_registration mr ON m.mac_address = mr.mac_address
                    WHERE m.mac_address = ? OR mr.mac_address = ?";
    
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$mac_address, $mac_address]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Module exists, update last seen and return info
        $update_query = "UPDATE modules 
                        SET last_seen = NOW(), 
                            last_heartbeat = NOW(),
                            firmware_version = ?,
                            ip_address = ?,
                            updated_at = NOW()
                        WHERE mac_address = ?";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$firmware_version, $ip_address, $mac_address]);
        
        // Update registration table
        $update_reg_query = "UPDATE module_registration 
                            SET last_seen = NOW(),
                                ip_address = ?,
                                firmware_version = ?,
                                updated_at = NOW()
                            WHERE mac_address = ?";
        
        $update_reg_stmt = $db->prepare($update_reg_query);
        $update_reg_stmt->execute([$ip_address, $firmware_version, $mac_address]);
        
        $response = [
            'success' => true,
            'message' => 'Module already registered',
            'module' => [
                'id' => $existing['id'],
                'module_id' => $existing['module_id'],
                'name' => $existing['name'],
                'status' => $existing['status'],
                'registration_token' => $existing['registration_token'],
                'already_registered' => true
            ]
        ];
        
        echo json_encode($response);
        return;
    }
    
    // Generate module ID
    $module_id = 'MDVA_' . strtoupper(substr($mac_address, -8));
    $registration_token = 'REG_' . bin2hex(random_bytes(16));
    
    try {
        $db->beginTransaction();
        
        // Insert into registration table
        $reg_query = "INSERT INTO module_registration 
                     (mac_address, firmware_version, ip_address, registration_token, status, last_seen)
                     VALUES (?, ?, ?, ?, 'pending', NOW())";
        
        $reg_stmt = $db->prepare($reg_query);
        $reg_stmt->execute([$mac_address, $firmware_version, $ip_address, $registration_token]);
        
        // Insert into modules table
        $module_query = "INSERT INTO modules 
                        (module_id, name, mac_address, firmware_version, ip_address, status, last_seen, last_heartbeat)
                        VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())";
        
        $default_name = "Module " . substr($mac_address, -6);
        $module_stmt = $db->prepare($module_query);
        $module_stmt->execute([$module_id, $default_name, $mac_address, $firmware_version, $ip_address]);
        
        $module_db_id = $db->lastInsertId();
        
        // Generate QR code data
        $qr_data = json_encode([
            'module_id' => $module_id,
            'mac_address' => $mac_address,
            'registration_token' => $registration_token,
            'type' => 'donation_module',
            'system' => 'MDVA',
            'timestamp' => time()
        ]);
        
        // Update module with QR data
        $qr_query = "UPDATE modules SET qr_code_data = ? WHERE id = ?";
        $qr_stmt = $db->prepare($qr_query);
        $qr_stmt->execute([$qr_data, $module_db_id]);
        
        // Update registration table with module_id
        $update_reg_query = "UPDATE module_registration 
                            SET module_id = ?, 
                                status = 'registered',
                                registered_at = NOW()
                            WHERE mac_address = ?";
        
        $update_reg_stmt = $db->prepare($update_reg_query);
        $update_reg_stmt->execute([$module_id, $mac_address]);
        
        // Log activity
        log_activity($db, 'module', $module_db_id, 'module_registered', 
            "Module registered: $module_id (MAC: $mac_address)");
        
        $db->commit();
        
        // Generate QR code file
        $qr_dir = "../qr_codes/";
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        
        $qr_file = "mdva_module_" . $module_id . ".png";
        $qr_path = $qr_dir . $qr_file;
        
        if (function_exists('generateModuleQRCode')) {
            generateModuleQRCode($module_id, $default_name, '', $qr_path);
        }
        
        $response = [
            'success' => true,
            'message' => 'Module registered successfully',
            'module' => [
                'id' => $module_db_id,
                'module_id' => $module_id,
                'name' => $default_name,
                'mac_address' => $mac_address,
                'registration_token' => $registration_token,
                'firmware_version' => $firmware_version,
                'ip_address' => $ip_address,
                'status' => 'active',
                'qr_code_data' => $qr_data,
                'qr_file' => $qr_file,
                'registered_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        error_log("Error registering module: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to register module: ' . $e->getMessage()]);
    }
}

/**
 * Get module status and information
 */
function getModuleStatus($db, $data) {
    $module_id = $data['module_id'] ?? '';
    $mac_address = $data['mac_address'] ?? '';
    
    if (empty($module_id) && empty($mac_address)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'module_id or mac_address is required']);
        return;
    }
    
    $where = "1=1";
    $params = [];
    
    if (!empty($module_id)) {
        $where .= " AND m.module_id = ?";
        $params[] = $module_id;
    }
    
    if (!empty($mac_address)) {
        $where .= " AND m.mac_address = ?";
        $params[] = $mac_address;
    }
    
    $query = "SELECT 
                m.id,
                m.module_id,
                m.name,
                m.mac_address,
                m.status,
                m.last_seen,
                m.last_heartbeat,
                m.firmware_version,
                m.ip_address,
                m.total_donations,
                m.total_transactions,
                m.coin_value,
                m.qr_code_data,
                l.name as location_name,
                l.address,
                l.city,
                l.province,
                l.postal_code,
                TIMESTAMPDIFF(MINUTE, m.last_heartbeat, NOW()) as minutes_since_heartbeat,
                CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, m.last_heartbeat, NOW()) > 5 THEN 'offline'
                    WHEN m.status = 'maintenance' THEN 'maintenance'
                    WHEN m.status = 'inactive' THEN 'inactive'
                    ELSE 'online'
                END as connection_status
              FROM modules m
              LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
              LEFT JOIN locations l ON ml.location_id = l.id
              WHERE $where
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($module) {
        // Get recent donations for this module
        $donations_query = "SELECT 
                              'registered' as type,
                              d.amount,
                              d.created_at,
                              c.name as charity_name,
                              d.session_id
                            FROM donations d
                            JOIN charities c ON d.charity_id = c.id
                            WHERE d.module_id = ?
                              AND d.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                            
                            UNION ALL
                            
                            SELECT 
                              'anonymous' as type,
                              ad.amount,
                              ad.created_at,
                              c.name as charity_name,
                              NULL as session_id
                            FROM anonymous_donations ad
                            JOIN charities c ON ad.charity_id = c.id
                            WHERE ad.module_id = ?
                              AND ad.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                            
                            ORDER BY created_at DESC
                            LIMIT 10";
        
        $donations_stmt = $db->prepare($donations_query);
        $donations_stmt->execute([$module['module_id'], $module['module_id']]);
        $recent_donations = $donations_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate today's donations
        $today_query = "SELECT 
                          COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0) as today_total,
                          COUNT(d.id) + COUNT(ad.id) as today_count
                        FROM donations d
                        LEFT JOIN anonymous_donations ad ON d.module_id = ad.module_id 
                          AND DATE(ad.created_at) = CURDATE()
                        WHERE (d.module_id = ? OR ad.module_id = ?)
                          AND DATE(d.created_at) = CURDATE()";
        
        $today_stmt = $db->prepare($today_query);
        $today_stmt->execute([$module['module_id'], $module['module_id']]);
        $today_stats = $today_stmt->fetch(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'module' => $module,
            'stats' => [
                'total_donations' => floatval($module['total_donations'] ?? 0),
                'total_transactions' => intval($module['total_transactions'] ?? 0),
                'today_total' => floatval($today_stats['today_total'] ?? 0),
                'today_count' => intval($today_stats['today_count'] ?? 0),
                'recent_donations' => $recent_donations
            ]
        ];
        
        echo json_encode($response);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Module not found']);
    }
}

/**
 * Update module heartbeat (called by ESP32 periodically)
 */
function updateHeartbeat($db, $data) {
    $module_id = $data['module_id'] ?? '';
    $mac_address = $data['mac_address'] ?? '';
    $firmware_version = $data['firmware_version'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (empty($module_id) && empty($mac_address)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'module_id or mac_address is required']);
        return;
    }
    
    $where = "1=1";
    $params = [];
    
    if (!empty($module_id)) {
        $where .= " AND module_id = ?";
        $params[] = $module_id;
    }
    
    if (!empty($mac_address)) {
        $where .= " AND mac_address = ?";
        $params[] = $mac_address;
    }
    
    $query = "UPDATE modules 
              SET last_heartbeat = NOW(),
                  last_seen = NOW(),
                  firmware_version = COALESCE(?, firmware_version),
                  ip_address = ?,
                  updated_at = NOW()
              WHERE $where";
    
    $params = array_merge([$firmware_version, $ip_address], $params);
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            // Get updated module info
            $module_query = "SELECT module_id, name, status FROM modules WHERE $where";
            $module_stmt = $db->prepare($module_query);
            
            if (!empty($module_id)) {
                $module_stmt->execute([$module_id]);
            } else {
                $module_stmt->execute([$mac_address]);
            }
            
            $module = $module_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Notify via Pusher if module was offline and is now online
            $status_query = "SELECT TIMESTAMPDIFF(MINUTE, last_heartbeat, NOW()) as minutes_offline 
                            FROM modules WHERE $where";
            $status_stmt = $db->prepare($status_query);
            
            if (!empty($module_id)) {
                $status_stmt->execute([$module_id]);
            } else {
                $status_stmt->execute([$mac_address]);
            }
            
            $status = $status_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($status['minutes_offline'] > 5) {
                // Module was offline for more than 5 minutes, send reconnection notification
                $pusher_data = [
                    'module_id' => $module['module_id'],
                    'module_name' => $module['name'],
                    'status' => 'online',
                    'was_offline_minutes' => $status['minutes_offline'],
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                notify_pusher('module_reconnected', $pusher_data, "modules_channel");
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Heartbeat updated',
                'timestamp' => date('Y-m-d H:i:s'),
                'module' => $module
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Module not found']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Error updating heartbeat: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update heartbeat: ' . $e->getMessage()]);
    }
}

/**
 * Get all modules with optional filtering
 */
function getAllModules($db, $data) {
    $status = $data['status'] ?? 'all'; // all, active, inactive, maintenance, offline
    $limit = intval($data['limit'] ?? 100);
    $offset = intval($data['offset'] ?? 0);
    $with_stats = filter_var($data['with_stats'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    $where = "1=1";
    $params = [];
    
    if ($status !== 'all') {
        if ($status === 'offline') {
            $where .= " AND (TIMESTAMPDIFF(MINUTE, last_heartbeat, NOW()) > 5 OR status = 'offline')";
        } else {
            $where .= " AND status = ?";
            $params[] = $status;
        }
    }
    
    $query = "SELECT 
                m.id,
                m.module_id,
                m.name,
                m.mac_address,
                m.status,
                m.last_seen,
                m.last_heartbeat,
                m.firmware_version,
                m.ip_address,
                m.total_donations,
                m.total_transactions,
                m.coin_value,
                l.name as location_name,
                l.city,
                l.province,
                TIMESTAMPDIFF(MINUTE, m.last_heartbeat, NOW()) as minutes_since_heartbeat,
                CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, m.last_heartbeat, NOW()) > 5 THEN 'offline'
                    WHEN m.status = 'maintenance' THEN 'maintenance'
                    WHEN m.status = 'inactive' THEN 'inactive'
                    ELSE 'online'
                END as connection_status
              FROM modules m
              LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
              LEFT JOIN locations l ON ml.location_id = l.id
              WHERE $where
              ORDER BY m.name
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM modules WHERE $where";
    $count_stmt = $db->prepare($count_query);
    
    if ($status !== 'all' && $status !== 'offline') {
        $count_stmt->execute([$status]);
    } else {
        $count_stmt->execute();
    }
    
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($with_stats) {
        // Get additional statistics for each module
        foreach ($modules as &$module) {
            // Today's donations
            $today_query = "SELECT 
                              COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0) as today_total,
                              COUNT(d.id) + COUNT(ad.id) as today_count
                            FROM donations d
                            LEFT JOIN anonymous_donations ad ON d.module_id = ad.module_id 
                              AND DATE(ad.created_at) = CURDATE()
                            WHERE (d.module_id = ? OR ad.module_id = ?)
                              AND DATE(d.created_at) = CURDATE()";
            
            $today_stmt = $db->prepare($today_query);
            $today_stmt->execute([$module['module_id'], $module['module_id']]);
            $today_stats = $today_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Last 7 days donations
            $weekly_query = "SELECT 
                                DATE(COALESCE(d.created_at, ad.created_at)) as date,
                                COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0) as daily_total
                              FROM donations d
                              LEFT JOIN anonymous_donations ad ON d.module_id = ad.module_id 
                                AND DATE(ad.created_at) = DATE(d.created_at)
                              WHERE (d.module_id = ? OR ad.module_id = ?)
                                AND COALESCE(d.created_at, ad.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                              GROUP BY DATE(COALESCE(d.created_at, ad.created_at))
                              ORDER BY date DESC";
            
            $weekly_stmt = $db->prepare($weekly_query);
            $weekly_stmt->execute([$module['module_id'], $module['module_id']]);
            $weekly_stats = $weekly_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $module['stats'] = [
                'today_total' => floatval($today_stats['today_total'] ?? 0),
                'today_count' => intval($today_stats['today_count'] ?? 0),
                'weekly_stats' => $weekly_stats
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'modules' => $modules,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + count($modules)) < $total
        ]
    ]);
}

/**
 * Get all module locations
 */
function getModuleLocations($db, $data) {
    $query = "SELECT 
                l.id,
                l.name,
                l.address,
                l.city,
                l.province,
                l.country,
                l.postal_code,
                l.phone,
                l.contact_email,
                l.active,
                l.module_count,
                l.total_donations,
                COUNT(m.id) as active_modules_count,
                COALESCE(SUM(m.total_donations), 0) as location_total_donations
              FROM locations l
              LEFT JOIN module_locations ml ON l.id = ml.location_id AND ml.status = 'active'
              LEFT JOIN modules m ON ml.module_id = m.id AND m.status = 'active'
              WHERE l.active = 1
              GROUP BY l.id
              ORDER BY l.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'locations' => $locations,
        'count' => count($locations)
    ]);
}

/**
 * Update module location
 */
function updateModuleLocation($db, $data) {
    $module_id = $data['module_id'] ?? '';
    $location_id = $data['location_id'] ?? '';
    $action = $data['action'] ?? 'assign'; // assign, remove
    
    if (empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'module_id is required']);
        return;
    }
    
    // Verify module exists
    $module_query = "SELECT id FROM modules WHERE module_id = ?";
    $module_stmt = $db->prepare($module_query);
    $module_stmt->execute([$module_id]);
    $module = $module_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$module) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Module not found']);
        return;
    }
    
    $module_db_id = $module['id'];
    
    if ($action === 'assign' && empty($location_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'location_id is required for assign action']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        if ($action === 'assign') {
            // Verify location exists
            $location_query = "SELECT id FROM locations WHERE id = ? AND active = 1";
            $location_stmt = $db->prepare($location_query);
            $location_stmt->execute([$location_id]);
            $location = $location_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$location) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Location not found or inactive']);
                $db->rollBack();
                return;
            }
            
            // Remove any existing active assignment for this module
            $remove_query = "UPDATE module_locations 
                            SET status = 'removed', removed_at = NOW() 
                            WHERE module_id = ? AND status = 'active'";
            $remove_stmt = $db->prepare($remove_query);
            $remove_stmt->execute([$module_db_id]);
            
            // Check if assignment already exists (inactive)
            $check_query = "SELECT id FROM module_locations 
                           WHERE module_id = ? AND location_id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$module_db_id, $location_id]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Reactivate existing assignment
                $reactivate_query = "UPDATE module_locations 
                                    SET status = 'active', 
                                        installed_at = NOW(),
                                        removed_at = NULL,
                                        notes = ?
                                    WHERE id = ?";
                $reactivate_stmt = $db->prepare($reactivate_query);
                $reactivate_stmt->execute([
                    $data['notes'] ?? 'Reassigned to location',
                    $existing['id']
                ]);
            } else {
                // Create new assignment
                $assign_query = "INSERT INTO module_locations 
                                (module_id, location_id, installed_at, status, notes)
                                VALUES (?, ?, NOW(), 'active', ?)";
                $assign_stmt = $db->prepare($assign_query);
                $assign_stmt->execute([
                    $module_db_id,
                    $location_id,
                    $data['notes'] ?? 'Assigned to location'
                ]);
            }
            
            // Update location module count
            $update_location_query = "UPDATE locations 
                                     SET module_count = (
                                       SELECT COUNT(*) 
                                       FROM module_locations 
                                       WHERE location_id = ? AND status = 'active'
                                     )
                                     WHERE id = ?";
            $update_location_stmt = $db->prepare($update_location_query);
            $update_location_stmt->execute([$location_id, $location_id]);
            
            $message = 'Module assigned to location successfully';
            
        } elseif ($action === 'remove') {
            // Remove active assignment
            $remove_query = "UPDATE module_locations 
                            SET status = 'removed', 
                                removed_at = NOW(),
                                notes = CONCAT(COALESCE(notes, ''), '; ', ?)
                            WHERE module_id = ? AND status = 'active'";
            $remove_stmt = $db->prepare($remove_query);
            $remove_stmt->execute([
                $data['notes'] ?? 'Manually removed from location',
                $module_db_id
            ]);
            
            $message = 'Module removed from location successfully';
        }
        
        // Log activity
        log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'module_location_' . $action, 
            "Module $module_id " . ($action === 'assign' ? 'assigned to' : 'removed from') . " location $location_id");
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'module_id' => $module_id,
            'action' => $action
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        error_log("Error updating module location: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update module location: ' . $e->getMessage()]);
    }
}

/**
 * Generate QR code for module
 */
function generateModuleQR($db, $data) {
    $module_id = $data['module_id'] ?? '';
    
    if (empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'module_id is required']);
        return;
    }
    
    // Get module information
    $module_query = "SELECT 
                      m.id,
                      m.module_id,
                      m.name,
                      m.mac_address,
                      l.name as location_name,
                      l.address,
                      l.city,
                      l.province
                    FROM modules m
                    LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
                    LEFT JOIN locations l ON ml.location_id = l.id
                    WHERE m.module_id = ?";
    
    $module_stmt = $db->prepare($module_query);
    $module_stmt->execute([$module_id]);
    $module = $module_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$module) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Module not found']);
        return;
    }
    
    // Build location string
    $location = '';
    if ($module['location_name']) {
        $location = $module['location_name'];
        if ($module['address']) $location .= ', ' . $module['address'];
        if ($module['city']) $location .= ', ' . $module['city'];
        if ($module['province']) $location .= ', ' . $module['province'];
    }
    
    // Generate QR code
    $qr_dir = "../qr_codes/";
    if (!file_exists($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    
    $qr_file = "mdva_module_" . $module_id . ".png";
    $qr_path = $qr_dir . $qr_file;
    
    try {
        if (function_exists('generateModuleQRCode')) {
            generateModuleQRCode($module_id, $module['name'], $location, $qr_path);
        } else {
            // Fallback: Create simple QR data
            $qr_data = json_encode([
                'module_id' => $module_id,
                'name' => $module['name'],
                'location' => $location,
                'type' => 'donation_module',
                'system' => 'MDVA',
                'timestamp' => time()
            ]);
            
            // Update module with QR data
            $update_query = "UPDATE modules SET qr_code_data = ? WHERE module_id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$qr_data, $module_id]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'QR code generated successfully',
            'qr_file' => $qr_file,
            'qr_path' => $qr_path,
            'module' => [
                'module_id' => $module_id,
                'name' => $module['name'],
                'location' => $location
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Error generating QR code: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to generate QR code: ' . $e->getMessage()]);
    }
}

/**
 * Get module statistics
 */
function getModuleStats($db, $data) {
    $module_id = $data['module_id'] ?? '';
    $timeframe = $data['timeframe'] ?? 'today'; // today, week, month, year, all
    
    if (empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'module_id is required']);
        return;
    }
    
    // Build timeframe condition
    $time_condition = "";
    switch ($timeframe) {
        case 'today':
            $time_condition = "AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $time_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $time_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $time_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            break;
        case 'all':
        default:
            $time_condition = "";
            break;
    }
    
    // Get combined statistics from both tables
    $stats_query = "SELECT 
                      'registered' as type,
                      COALESCE(SUM(amount), 0) as total_amount,
                      COUNT(*) as donation_count,
                      AVG(amount) as avg_amount,
                      MIN(amount) as min_amount,
                      MAX(amount) as max_amount
                    FROM donations 
                    WHERE module_id = ?
                      AND status = 'completed'
                      $time_condition
                    
                    UNION ALL
                    
                    SELECT 
                      'anonymous' as type,
                      COALESCE(SUM(amount), 0) as total_amount,
                      COUNT(*) as donation_count,
                      AVG(amount) as avg_amount,
                      MIN(amount) as min_amount,
                      MAX(amount) as max_amount
                    FROM anonymous_donations 
                    WHERE module_id = ?
                      AND status = 'completed'
                      $time_condition";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$module_id, $module_id]);
    $registered_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    $anonymous_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    $combined_stats = [
        'total_amount' => ($registered_stats['total_amount'] ?? 0) + ($anonymous_stats['total_amount'] ?? 0),
        'donation_count' => ($registered_stats['donation_count'] ?? 0) + ($anonymous_stats['donation_count'] ?? 0),
        'avg_amount' => (($registered_stats['total_amount'] ?? 0) + ($anonymous_stats['total_amount'] ?? 0)) / 
                       max(1, ($registered_stats['donation_count'] ?? 0) + ($anonymous_stats['donation_count'] ?? 0)),
        'min_amount' => min($registered_stats['min_amount'] ?? PHP_FLOAT_MAX, $anonymous_stats['min_amount'] ?? PHP_FLOAT_MAX),
        'max_amount' => max($registered_stats['max_amount'] ?? 0, $anonymous_stats['max_amount'] ?? 0),
        'registered' => $registered_stats,
        'anonymous' => $anonymous_stats
    ];
    
    // Get charity distribution
    $charity_query = "SELECT 
                        c.id,
                        c.name,
                        COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0) as total_donated,
                        COUNT(d.id) + COUNT(ad.id) as donation_count
                      FROM charities c
                      LEFT JOIN donations d ON c.id = d.charity_id AND d.module_id = ? $time_condition
                      LEFT JOIN anonymous_donations ad ON c.id = ad.charity_id AND ad.module_id = ? $time_condition
                      GROUP BY c.id
                      HAVING total_donated > 0
                      ORDER BY total_donated DESC";
    
    $charity_stmt = $db->prepare($charity_query);
    $charity_stmt->execute([$module_id, $module_id]);
    $charity_distribution = $charity_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get hourly distribution for today
    $hourly_query = "SELECT 
                        HOUR(created_at) as hour,
                        COALESCE(SUM(amount), 0) as hourly_amount,
                        COUNT(*) as hourly_count
                      FROM donations 
                      WHERE module_id = ?
                        AND DATE(created_at) = CURDATE()
                      GROUP BY HOUR(created_at)
                      
                      UNION ALL
                      
                      SELECT 
                        HOUR(created_at) as hour,
                        COALESCE(SUM(amount), 0) as hourly_amount,
                        COUNT(*) as hourly_count
                      FROM anonymous_donations 
                      WHERE module_id = ?
                        AND DATE(created_at) = CURDATE()
                      GROUP BY HOUR(created_at)
                      
                      ORDER BY hour";
    
    $hourly_stmt = $db->prepare($hourly_query);
    $hourly_stmt->execute([$module_id, $module_id]);
    $hourly_data = $hourly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine hourly data
    $hourly_summary = array_fill(0, 24, ['hour' => 0, 'amount' => 0, 'count' => 0]);
    foreach ($hourly_data as $row) {
        $hour = intval($row['hour']);
        $hourly_summary[$hour]['hour'] = $hour;
        $hourly_summary[$hour]['amount'] += $row['hourly_amount'];
        $hourly_summary[$hour]['count'] += $row['hourly_count'];
    }
    
    echo json_encode([
        'success' => true,
        'module_id' => $module_id,
        'timeframe' => $timeframe,
        'stats' => $combined_stats,
        'charity_distribution' => $charity_distribution,
        'hourly_distribution' => $hourly_summary
    ]);
}

/**
 * Check if a module is active and online
 */
function checkModuleStatus($db, $data) {
    $module_id = $data['module_id'] ?? '';
    
    if (empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'module_id is required']);
        return;
    }
    
    $query = "SELECT 
                module_id,
                name,
                status,
                last_heartbeat,
                TIMESTAMPDIFF(MINUTE, last_heartbeat, NOW()) as minutes_since_heartbeat,
                CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, last_heartbeat, NOW()) > 5 THEN 'offline'
                    WHEN status = 'maintenance' THEN 'maintenance'
                    WHEN status = 'inactive' THEN 'inactive'
                    ELSE 'online'
                END as connection_status
              FROM modules 
              WHERE module_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$module_id]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($module) {
        $is_active = ($module['connection_status'] === 'online' && $module['status'] === 'active');
        
        echo json_encode([
            'success' => true,
            'active' => $is_active,
            'module' => $module,
            'message' => $is_active ? 'Module is active and online' : 'Module is not active'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Module not found']);
    }
}

/**
 * Update module information
 */
function updateModule($db, $data) {
    $module_id = $data['module_id'] ?? '';
    
    if (empty($module_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'module_id is required']);
        return;
    }
    
    // Verify module exists
    $check_query = "SELECT id FROM modules WHERE module_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$module_id]);
    
    if($check_stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Module not found']);
        return;
    }
    
    // Build update query based on provided fields
    $update_fields = [];
    $update_values = [];
    
    $allowed_fields = ['name', 'status', 'coin_value', 'firmware_version'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $update_values[] = $data[$field];
        }
    }
    
    if (empty($update_fields)) {
        echo json_encode(['success' => true, 'message' => 'No changes to update']);
        return;
    }
    
    $update_fields[] = "updated_at = NOW()";
    $update_values[] = $module_id;
    
    $update_query = "UPDATE modules SET " . implode(', ', $update_fields) . " WHERE module_id = ?";
    
    try {
        $stmt = $db->prepare($update_query);
        $stmt->execute($update_values);
        
        // Get updated module info
        $module_query = "SELECT module_id, name, status FROM modules WHERE module_id = ?";
        $module_stmt = $db->prepare($module_query);
        $module_stmt->execute([$module_id]);
        $module = $module_stmt->fetch(PDO::FETCH_ASSOC);
        
        // If status changed, notify via Pusher
        if (isset($data['status']) && $module) {
            $pusher_data = [
                'module_id' => $module_id,
                'module_name' => $module['name'],
                'old_status' => '', // We don't have old status here
                'new_status' => $data['status'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            notify_pusher('module_status_changed', $pusher_data, "modules_channel");
        }
        
        // Log activity
        log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'module_updated', 
            "Module $module_id updated");
        
        echo json_encode([
            'success' => true,
            'message' => 'Module updated successfully',
            'module' => $module
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Error updating module: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update module: ' . $e->getMessage()]);
    }
}
?>