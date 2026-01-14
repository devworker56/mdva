<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

// ENHANCED INPUT HANDLING - Support both GET and POST
$input = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $input = $_POST;
    }
} else {
    $input = $_GET; // Also accept GET parameters
}

// Enable CORS for mobile application
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS pre-flight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch($action) {
        case 'get_approved':
            getApprovedCharities($db);
            break;
            
        case 'get_all':
            getAllCharities($db, $input);
            break;
            
        case 'stats':
            getCharityStats($db, $input);
            break;
            
        case 'detail':
            getCharityDetail($db, $input);
            break;
            
        case 'approve':
            // Allow both GET and POST for compatibility with admin dashboard
            approveCharity($db, $input);
            break;
            
        case 'reject':
            // Allow both GET and POST for compatibility with admin dashboard
            rejectCharity($db, $input);
            break;
            
        case 'revoke':
            // Allow both GET and POST for compatibility with admin dashboard
            revokeCharity($db, $input);
            break;
            
        case 'create':
            createCharity($db, $input);
            break;
            
        case 'update':
            updateCharity($db, $input);
            break;
            
        case 'search':
            searchCharities($db, $input);
            break;
            
        case 'dashboard_stats':
            getCharityDashboardStats($db, $input);
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
 * Get all approved charities for mobile app
 */
function getApprovedCharities($db) {
    $query = "SELECT 
                id, 
                name, 
                description, 
                created_at, 
                logo_url, 
                website,
                charity_number,
                total_donations,
                donation_count
              FROM charities 
              WHERE approved = 1 
              AND active = 1
              ORDER BY name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $charities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics for each charity
    foreach ($charities as &$charity) {
        $stats_query = "SELECT 
                          total_donations,
                          donation_count,
                          total_donors,
                          avg_donation,
                          last_donation_at
                        FROM charity_stats_cache 
                        WHERE charity_id = ?";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->execute([$charity['id']]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats) {
            $charity['total_donations'] = floatval($stats['total_donations'] ?? 0);
            $charity['donation_count'] = intval($stats['donation_count'] ?? 0);
            $charity['total_donors'] = intval($stats['total_donors'] ?? 0);
            $charity['avg_donation'] = floatval($stats['avg_donation'] ?? 0);
            $charity['last_donation_at'] = $stats['last_donation_at'] ?? null;
        } else {
            $charity['total_donations'] = 0;
            $charity['donation_count'] = 0;
            $charity['total_donors'] = 0;
            $charity['avg_donation'] = 0;
            $charity['last_donation_at'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'charities' => $charities,
        'count' => count($charities)
    ]);
}

/**
 * Get all charities (including unapproved - for admin)
 */
function getAllCharities($db, $data) {
    $limit = intval($data['limit'] ?? 100);
    $offset = intval($data['offset'] ?? 0);
    $status = $data['status'] ?? 'all'; // all, approved, pending, inactive
    
    $where = "1=1";
    $params = [];
    
    if ($status === 'approved') {
        $where .= " AND approved = 1 AND active = 1";
    } elseif ($status === 'pending') {
        $where .= " AND approved = 0";
    } elseif ($status === 'inactive') {
        $where .= " AND active = 0";
    }
    
    $query = "SELECT 
                id, 
                name, 
                description, 
                email,
                website,
                logo_url,
                address,
                city,
                province,
                postal_code,
                phone,
                contact_person,
                charity_number,
                approved,
                active,
                created_at,
                updated_at
              FROM charities 
              WHERE $where
              ORDER BY name
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $charities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM charities WHERE $where";
    $count_stmt = $db->prepare($count_query);
    
    if ($status === 'approved') {
        $count_stmt->execute();
    } elseif ($status === 'pending') {
        $count_stmt->execute();
    } elseif ($status === 'inactive') {
        $count_stmt->execute();
    } else {
        $count_stmt->execute();
    }
    
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'charities' => $charities,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + count($charities)) < $total
        ]
    ]);
}

/**
 * Get detailed charity statistics
 */
function getCharityStats($db, $data) {
    $charity_id = $data['charity_id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'charity_id is required']);
        return;
    }
    
    // Verify charity exists
    $charity_query = "SELECT id, name FROM charities WHERE id = ?";
    $charity_stmt = $db->prepare($charity_query);
    $charity_stmt->execute([$charity_id]);
    $charity = $charity_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Charity not found']);
        return;
    }
    
    // Get cached stats
    $stats_query = "SELECT 
                      total_donations,
                      total_donors,
                      donation_count,
                      avg_donation,
                      last_donation_at,
                      calculated_at
                    FROM charity_stats_cache 
                    WHERE charity_id = ?";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$charity_id]);
    $cached_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no cached stats or stats are old (> 1 hour), calculate fresh
    if (!$cached_stats || strtotime($cached_stats['calculated_at']) < (time() - 3600)) {
        // Calculate combined stats from donations and anonymous_donations
        $calculate_query = "SELECT 
                              COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0) as total_donations,
                              COUNT(DISTINCT CASE 
                                WHEN d.donor_id IS NOT NULL THEN CONCAT('R_', d.donor_id)
                                WHEN d.anonymous_session_id IS NOT NULL THEN CONCAT('A_', d.anonymous_session_id)
                              END) as total_donors,
                              COUNT(d.id) + COUNT(ad.id) as donation_count,
                              (COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0)) / 
                              NULLIF(COUNT(d.id) + COUNT(ad.id), 0) as avg_donation,
                              GREATEST(MAX(d.created_at), MAX(ad.created_at)) as last_donation_at
                            FROM donations d
                            LEFT JOIN anonymous_donations ad ON d.charity_id = ad.charity_id
                            WHERE d.charity_id = ? OR ad.charity_id = ?";
        
        $calculate_stmt = $db->prepare($calculate_query);
        $calculate_stmt->execute([$charity_id, $charity_id]);
        $calculated_stats = $calculate_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update cache
        $update_cache_query = "INSERT INTO charity_stats_cache 
                              (charity_id, total_donations, total_donors, donation_count, avg_donation, last_donation_at, calculated_at)
                              VALUES (?, ?, ?, ?, ?, ?, NOW())
                              ON DUPLICATE KEY UPDATE
                              total_donations = VALUES(total_donations),
                              total_donors = VALUES(total_donors),
                              donation_count = VALUES(donation_count),
                              avg_donation = VALUES(avg_donation),
                              last_donation_at = VALUES(last_donation_at),
                              calculated_at = NOW()";
        
        $update_cache_stmt = $db->prepare($update_cache_query);
        $update_cache_stmt->execute([
            $charity_id,
            $calculated_stats['total_donations'] ?? 0,
            $calculated_stats['total_donors'] ?? 0,
            $calculated_stats['donation_count'] ?? 0,
            $calculated_stats['avg_donation'] ?? 0,
            $calculated_stats['last_donation_at'] ?? null
        ]);
        
        $stats = $calculated_stats;
    } else {
        $stats = $cached_stats;
    }
    
    // Get today's donations
    $today_query = "SELECT 
                      COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0) as today_total,
                      COUNT(d.id) + COUNT(ad.id) as today_count
                    FROM donations d
                    LEFT JOIN anonymous_donations ad ON d.charity_id = ad.charity_id 
                      AND DATE(ad.created_at) = CURDATE()
                    WHERE (d.charity_id = ? OR ad.charity_id = ?)
                      AND DATE(d.created_at) = CURDATE()";
    
    $today_stmt = $db->prepare($today_query);
    $today_stmt->execute([$charity_id, $charity_id]);
    $today_stats = $today_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get monthly breakdown for the current year
    $monthly_query = "SELECT 
                        DATE_FORMAT(COALESCE(d.created_at, ad.created_at), '%Y-%m') as month,
                        COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0) as monthly_total,
                        COUNT(d.id) + COUNT(ad.id) as monthly_count
                      FROM donations d
                      LEFT JOIN anonymous_donations ad ON d.charity_id = ad.charity_id 
                        AND DATE_FORMAT(ad.created_at, '%Y-%m') = DATE_FORMAT(d.created_at, '%Y-%m')
                      WHERE (d.charity_id = ? OR ad.charity_id = ?)
                        AND YEAR(COALESCE(d.created_at, ad.created_at)) = YEAR(CURDATE())
                      GROUP BY DATE_FORMAT(COALESCE(d.created_at, ad.created_at), '%Y-%m')
                      ORDER BY month DESC";
    
    $monthly_stmt = $db->prepare($monthly_query);
    $monthly_stmt->execute([$charity_id, $charity_id]);
    $monthly_stats = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top modules for this charity
    $modules_query = "SELECT 
                        m.module_id,
                        m.name as module_name,
                        COALESCE(SUM(d.amount), 0) + COALESCE(SUM(ad.amount), 0) as total_donated,
                        COUNT(d.id) + COUNT(ad.id) as donation_count,
                        l.name as location_name,
                        l.city,
                        l.province
                      FROM modules m
                      LEFT JOIN donations d ON m.module_id = d.module_id AND d.charity_id = ?
                      LEFT JOIN anonymous_donations ad ON m.module_id = ad.module_id AND ad.charity_id = ?
                      LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
                      LEFT JOIN locations l ON ml.location_id = l.id
                      WHERE m.status = 'active'
                      GROUP BY m.id
                      HAVING total_donated > 0
                      ORDER BY total_donated DESC
                      LIMIT 10";
    
    $modules_stmt = $db->prepare($modules_query);
    $modules_stmt->execute([$charity_id, $charity_id]);
    $top_modules = $modules_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'charity' => $charity,
        'stats' => [
            'total_donations' => floatval($stats['total_donations'] ?? 0),
            'total_donors' => intval($stats['total_donors'] ?? 0),
            'donation_count' => intval($stats['donation_count'] ?? 0),
            'avg_donation' => floatval($stats['avg_donation'] ?? 0),
            'last_donation_at' => $stats['last_donation_at'],
            'today_total' => floatval($today_stats['today_total'] ?? 0),
            'today_count' => intval($today_stats['today_count'] ?? 0),
            'monthly_stats' => $monthly_stats,
            'top_modules' => $top_modules
        ]
    ]);
}

/**
 * Get charity detail information
 */
function getCharityDetail($db, $data) {
    $charity_id = $data['charity_id'] ?? '';
    $include_stats = filter_var($data['include_stats'] ?? true, FILTER_VALIDATE_BOOLEAN);
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'charity_id is required']);
        return;
    }
    
    $query = "SELECT 
                id, 
                name, 
                description, 
                email,
                website,
                logo_url,
                address,
                city,
                province,
                postal_code,
                phone,
                contact_person,
                charity_number,
                approved,
                active,
                created_at,
                updated_at
              FROM charities 
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Charity not found']);
        return;
    }
    
    $response = [
        'success' => true,
        'charity' => $charity
    ];
    
    if ($include_stats) {
        // Get basic stats
        $stats_query = "SELECT 
                          total_donations,
                          donation_count,
                          total_donors,
                          avg_donation
                        FROM charity_stats_cache 
                        WHERE charity_id = ?";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->execute([$charity_id]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats) {
            $response['stats'] = [
                'total_donations' => floatval($stats['total_donations']),
                'donation_count' => intval($stats['donation_count']),
                'total_donors' => intval($stats['total_donors']),
                'avg_donation' => floatval($stats['avg_donation'])
            ];
        }
    }
    
    echo json_encode($response);
}

/**
 * Approve a charity and notify mobile apps
 */
function approveCharity($db, $data) {
    // FIXED: Support both GET (parameter id) and POST (id in data)
    $charity_id = $data['id'] ?? $data['charity_id'] ?? $_GET['id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Charity ID required']);
        return;
    }
    
    // Verify charity exists
    $query = "SELECT id, name, description FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Charity not found']);
        return;
    }
    
    $query = "UPDATE charities SET approved = 1, updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$charity_id])) {
        // Get updated charity information for notification
        $query = "SELECT id, name, description FROM charities WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$charity_id]);
        $charity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notify WebSocket server of new approved charity (if function exists)
        if (function_exists('notify_websocket')) {
            notify_websocket('new_charity', [
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'charity_description' => $charity['description'],
                'action' => 'approved',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Notify via Pusher for mobile apps
        $pusher_data = [
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'action' => 'approved',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        notify_pusher('charity_approved', $pusher_data, "charities_channel");
        
        // Log activity (if function exists)
        if (function_exists('log_activity')) {
            log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_approved', 
                "Charity '{$charity['name']}' (ID: $charity_id) approved");
        }
        
        echo json_encode(['success' => true, 'message' => 'Charity approved successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to approve charity']);
    }
}

/**
 * Reject a charity application
 */
function rejectCharity($db, $data) {
    // FIXED: Support both GET (parameter id) and POST (id in data)
    $charity_id = $data['id'] ?? $data['charity_id'] ?? $_GET['id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Charity ID required']);
        return;
    }
    
    // Get charity name before deletion for logging
    $query = "SELECT name FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Charity not found']);
        return;
    }
    
    $query = "DELETE FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$charity_id])) {
        // Log activity (if function exists)
        if (function_exists('log_activity')) {
            log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_rejected', 
                "Charity '{$charity['name']}' (ID: $charity_id) rejected and deleted");
        }
            
        echo json_encode(['success' => true, 'message' => 'Charity rejected successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to reject charity']);
    }
}

/**
 * Revoke charity approval
 */
function revokeCharity($db, $data) {
    // FIXED: Support both GET (parameter id) and POST (id in data)
    $charity_id = $data['id'] ?? $data['charity_id'] ?? $_GET['id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Charity ID required']);
        return;
    }
    
    // Verify charity exists and is approved
    $query = "SELECT id, name FROM charities WHERE id = ? AND approved = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$charity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Approved charity not found']);
        return;
    }
    
    $query = "UPDATE charities SET approved = 0, updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$charity_id])) {
        // Get charity information for notification
        $query = "SELECT name FROM charities WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$charity_id]);
        $charity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notify WebSocket server of revocation (if function exists)
        if (function_exists('notify_websocket')) {
            notify_websocket('charity_update', [
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'action' => 'revoked',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Notify via Pusher for mobile apps
        $pusher_data = [
            'charity_id' => $charity_id,
            'charity_name' => $charity['name'],
            'action' => 'revoked',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        notify_pusher('charity_revoked', $pusher_data, "charities_channel");
        
        // Log activity (if function exists)
        if (function_exists('log_activity')) {
            log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_revoked', 
                "Charity '{$charity['name']}' (ID: $charity_id) approval revoked");
        }
            
        echo json_encode(['success' => true, 'message' => 'Charity approval revoked']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to revoke charity']);
    }
}

/**
 * Create a new charity (admin only)
 */
function createCharity($db, $data) {
    // Verify required fields
    $required = ['name', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Check if email already exists
    $check_query = "SELECT id FROM charities WHERE email = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$data['email']]);
    
    if($check_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        return;
    }
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Prepare insert query
    $insert_query = "INSERT INTO charities 
                    (name, description, email, password, website, logo_url, 
                     address, city, province, postal_code, phone, contact_person, 
                     charity_number, approved, active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $db->prepare($insert_query);
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['email'],
            $hashed_password,
            $data['website'] ?? '',
            $data['logo_url'] ?? '',
            $data['address'] ?? '',
            $data['city'] ?? '',
            $data['province'] ?? '',
            $data['postal_code'] ?? '',
            $data['phone'] ?? '',
            $data['contact_person'] ?? '',
            $data['charity_number'] ?? '',
            $data['approved'] ?? 0,
            $data['active'] ?? 1
        ]);
        
        $new_charity_id = $db->lastInsertId();
        
        // Create initial stats cache entry
        $stats_query = "INSERT INTO charity_stats_cache 
                       (charity_id, total_donations, total_donors, donation_count, avg_donation, calculated_at)
                       VALUES (?, 0, 0, 0, 0, NOW())";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->execute([$new_charity_id]);
        
        // Log activity
        if (function_exists('log_activity')) {
            log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_created', 
                "Charity '{$data['name']}' created with ID: $new_charity_id");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Charity created successfully',
            'charity_id' => $new_charity_id
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create charity: ' . $e->getMessage()]);
    }
}

/**
 * Update charity information
 */
function updateCharity($db, $data) {
    $charity_id = $data['id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Charity ID required']);
        return;
    }
    
    // Verify charity exists
    $check_query = "SELECT id FROM charities WHERE id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$charity_id]);
    
    if($check_stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Charity not found']);
        return;
    }
    
    // Build update query based on provided fields
    $update_fields = [];
    $update_values = [];
    
    $allowed_fields = [
        'name', 'description', 'website', 'logo_url', 'address', 'city',
        'province', 'postal_code', 'phone', 'contact_person', 'charity_number',
        'approved', 'active'
    ];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $update_values[] = $data[$field];
        }
    }
    
    // Handle password update separately
    if (!empty($data['password'])) {
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $update_fields[] = "password = ?";
        $update_values[] = $hashed_password;
    }
    
    if (empty($update_fields)) {
        echo json_encode(['success' => true, 'message' => 'No changes to update']);
        return;
    }
    
    $update_fields[] = "updated_at = NOW()";
    $update_values[] = $charity_id;
    
    $update_query = "UPDATE charities SET " . implode(', ', $update_fields) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($update_query);
        $stmt->execute($update_values);
        
        // Get updated charity info for notification
        $charity_query = "SELECT name, approved FROM charities WHERE id = ?";
        $charity_stmt = $db->prepare($charity_query);
        $charity_stmt->execute([$charity_id]);
        $charity = $charity_stmt->fetch(PDO::FETCH_ASSOC);
        
        // If approval status changed, notify
        if (isset($data['approved']) && $charity) {
            $action = $data['approved'] ? 'approved' : 'revoked';
            $pusher_data = [
                'charity_id' => $charity_id,
                'charity_name' => $charity['name'],
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            notify_pusher('charity_' . $action, $pusher_data, "charities_channel");
        }
        
        // Log activity
        if (function_exists('log_activity')) {
            log_activity($db, 'admin', $_SESSION['user_id'] ?? 0, 'charity_updated', 
                "Charity ID: $charity_id updated");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Charity updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update charity: ' . $e->getMessage()]);
    }
}

/**
 * Search charities by name or description
 */
function searchCharities($db, $data) {
    $search_term = $data['q'] ?? '';
    $limit = intval($data['limit'] ?? 20);
    $approved_only = filter_var($data['approved_only'] ?? true, FILTER_VALIDATE_BOOLEAN);
    
    if (empty($search_term) || strlen($search_term) < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Search term must be at least 2 characters']);
        return;
    }
    
    $where = "1=1";
    $params = [];
    
    if ($approved_only) {
        $where .= " AND approved = 1 AND active = 1";
    }
    
    $search_like = "%" . $search_term . "%";
    $where .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = $search_like;
    $params[] = $search_like;
    
    $query = "SELECT 
                id, 
                name, 
                description, 
                website,
                logo_url,
                charity_number,
                approved,
                active
              FROM charities 
              WHERE $where
              ORDER BY name
              LIMIT ?";
    
    $params[] = $limit;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $charities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'charities' => $charities,
        'count' => count($charities),
        'search_term' => $search_term
    ]);
}

/**
 * Get dashboard statistics for a charity
 */
function getCharityDashboardStats($db, $data) {
    $charity_id = $data['charity_id'] ?? '';
    
    if (empty($charity_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'charity_id is required']);
        return;
    }
    
    // Get basic stats from cache
    $stats_query = "SELECT 
                      total_donations,
                      donation_count,
                      total_donors,
                      avg_donation,
                      last_donation_at
                    FROM charity_stats_cache 
                    WHERE charity_id = ?";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$charity_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stats) {
        $stats = [
            'total_donations' => 0,
            'donation_count' => 0,
            'total_donors' => 0,
            'avg_donation' => 0,
            'last_donation_at' => null
        ];
    }
    
    // Get today's donations
    $today_query = "SELECT 
                      COALESCE(SUM(amount), 0) as today_total,
                      COUNT(*) as today_count
                    FROM donations 
                    WHERE charity_id = ? 
                      AND DATE(created_at) = CURDATE()
                      AND status = 'completed'
                    
                    UNION ALL
                    
                    SELECT 
                      COALESCE(SUM(amount), 0) as today_total,
                      COUNT(*) as today_count
                    FROM anonymous_donations 
                    WHERE charity_id = ? 
                      AND DATE(created_at) = CURDATE()
                      AND status = 'completed'";
    
    $today_stmt = $db->prepare($today_query);
    $today_stmt->execute([$charity_id, $charity_id]);
    $today_registered = $today_stmt->fetch(PDO::FETCH_ASSOC);
    $today_anonymous = $today_stmt->fetch(PDO::FETCH_ASSOC);
    
    $today_total = ($today_registered['today_total'] ?? 0) + ($today_anonymous['today_total'] ?? 0);
    $today_count = ($today_registered['today_count'] ?? 0) + ($today_anonymous['today_count'] ?? 0);
    
    // Get weekly trend (last 7 days)
    $weekly_query = "SELECT 
                        DATE(created_at) as date,
                        COALESCE(SUM(amount), 0) as daily_total,
                        COUNT(*) as daily_count
                      FROM donations 
                      WHERE charity_id = ? 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        AND status = 'completed'
                      GROUP BY DATE(created_at)
                      
                      UNION ALL
                      
                      SELECT 
                        DATE(created_at) as date,
                        COALESCE(SUM(amount), 0) as daily_total,
                        COUNT(*) as daily_count
                      FROM anonymous_donations 
                      WHERE charity_id = ? 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        AND status = 'completed'
                      GROUP BY DATE(created_at)
                      
                      ORDER BY date DESC";
    
    $weekly_stmt = $db->prepare($weekly_query);
    $weekly_stmt->execute([$charity_id, $charity_id]);
    $weekly_data = $weekly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine daily totals
    $weekly_summary = [];
    foreach ($weekly_data as $row) {
        $date = $row['date'];
        if (!isset($weekly_summary[$date])) {
            $weekly_summary[$date] = [
                'date' => $date,
                'total' => 0,
                'count' => 0
            ];
        }
        $weekly_summary[$date]['total'] += $row['daily_total'];
        $weekly_summary[$date]['count'] += $row['daily_count'];
    }
    
    $weekly_summary = array_values($weekly_summary);
    
    // Get donor types breakdown
    $donor_types_query = "SELECT 
                            'registered' as donor_type,
                            COUNT(DISTINCT donor_id) as donor_count,
                            COALESCE(SUM(amount), 0) as total_amount
                          FROM donations 
                          WHERE charity_id = ? 
                            AND donor_id IS NOT NULL
                            AND status = 'completed'
                          
                          UNION ALL
                          
                          SELECT 
                            'anonymous' as donor_type,
                            COUNT(DISTINCT anonymous_session_id) as donor_count,
                            COALESCE(SUM(amount), 0) as total_amount
                          FROM anonymous_donations 
                          WHERE charity_id = ? 
                            AND status = 'completed'";
    
    $donor_types_stmt = $db->prepare($donor_types_query);
    $donor_types_stmt->execute([$charity_id, $charity_id]);
    $donor_types = $donor_types_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_donations' => floatval($stats['total_donations']),
            'donation_count' => intval($stats['donation_count']),
            'total_donors' => intval($stats['total_donors']),
            'avg_donation' => floatval($stats['avg_donation']),
            'last_donation_at' => $stats['last_donation_at'],
            'today_total' => floatval($today_total),
            'today_count' => intval($today_count),
            'weekly_trend' => $weekly_summary,
            'donor_types' => $donor_types
        ]
    ]);
}
?>