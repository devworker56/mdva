<?php
/**
 * Fonctions utilitaires pour le système MDVA
 */

/**
 * Assainir les données d'entrée
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Créer un enregistrement vérifiable de session de don
 * Utilisé lors du démarrage d'une session de don avec sélection d'organisme par don
 */
function create_verifiable_donation_session($donor_id, $user_id, $charity_id, $module_id, $session_id, $db) {
    $timestamp = time();
    
    // Obtenir le hash de la transaction précédente pour maintenir la chaîne
    $previous_hash = get_last_transaction_hash($donor_id, $db);
    
    // Obtenir le nom de l'organisme
    $charity_name = get_charity_name($charity_id, $db);
    
    // Créer les données de transaction pour le hachage cryptographique
    $transaction_data = [
        'donor_id' => $user_id,
        'charity_id' => $charity_id,
        'charity_name' => $charity_name,
        'module_id' => $module_id,
        'session_id' => $session_id,
        'action' => 'donation_session_start',
        'timestamp' => $timestamp,
        'previous_hash' => $previous_hash
    ];
    
    // Générer le hash cryptographique
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    // Stocker dans la table des transactions vérifiables
    $query = "INSERT INTO verifiable_transactions 
              (donor_id, transaction_type, transaction_data, transaction_hash, previous_hash, timestamp, session_id) 
              VALUES (?, 'donation_session', ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $donor_id, 
        $transaction_data_json, 
        $transaction_hash,
        $previous_hash,
        date('Y-m-d H:i:s'),
        $session_id
    ]);
    
    error_log("MDVA Session de Don : Donateur $user_id démarrant une session pour $charity_name via le module $module_id. Session : $session_id, Hash de Transaction : $transaction_hash");
    
    return $transaction_hash;
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

/**
 * Vérifier si l'utilisateur est administrateur
 */
function is_admin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
}

/**
 * Vérifier si l'utilisateur est organisme
 */
function is_charity() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'charity';
}

/**
 * Rediriger vers la page spécifiée
 */
function redirect($page) {
    header("Location: " . $page);
    exit();
}

/**
 * Générer une chaîne aléatoire
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Formater la devise
 */
function format_currency($amount) {
    return number_format($amount, 2) . ' $';
}

/**
 * Obtenir le nom de l'organisme par ID
 */
function get_charity_name($charity_id, $db) {
    $query = "SELECT name FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    return $charity ? $charity['name'] : 'Organisme Inconnu';
}

/**
 * Obtenir l'user_id du donateur par ID
 */
function get_donor_user_id($donor_id, $db) {
    $query = "SELECT user_id FROM donors WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    return $donor ? $donor['user_id'] : 'Donateur Inconnu';
}

/**
 * Journaliser l'activité
 */
function log_activity($db, $user_type, $user_id, $action, $description = '') {
    try {
        $query = "INSERT INTO activity_logs (user_type, user_id, action, description) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_type, $user_id, $action, $description]);
        return true;
    } catch (Exception $e) {
        error_log("Échec de l'insertion du journal d'activité : " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier via Pusher en utilisant le SDK PHP
 */
function notify_pusher($event, $data, $channel) {
    try {
        // Inclure votre configuration Pusher
        require_once __DIR__ . '/../config/pusher.php';
        
        $pusher = getPusher();
        if (!$pusher) {
            error_log("❌ Pusher non initialisé");
            return false;
        }
        
        $result = $pusher->trigger($channel, $event, $data);
        error_log("✅ SDK Pusher : $event vers $channel - SUCCÈS");
        return true;
        
    } catch (Exception $e) {
        error_log("❌ Erreur du SDK Pusher : " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les statistiques de dons pour un organisme
 */
function get_charity_stats($charity_id, $db) {
    $query = "SELECT 
                SUM(amount) as total_donations,
                COUNT(*) as donation_count,
                AVG(amount) as average_donation,
                MAX(amount) as largest_donation,
                MIN(amount) as smallest_donation
              FROM donations 
              WHERE charity_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtenir les données de dons mensuelles pour les graphiques
 */
function get_monthly_donation_data($charity_id, $db, $year = null) {
    if ($year === null) {
        $year = date('Y');
    }
    
    $query = "SELECT 
                MONTH(created_at) as month,
                SUM(amount) as total_amount,
                COUNT(*) as donation_count
              FROM donations 
              WHERE charity_id = ? AND YEAR(created_at) = ?
              GROUP BY MONTH(created_at)
              ORDER BY month";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id, $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Générer les données de reçu fiscal pour un donateur
 */
function generate_tax_receipt_data($donor_id, $year, $db) {
    $query = "SELECT 
                d.amount,
                d.created_at,
                c.name as charity_name,
                c.id as charity_id
              FROM donations d
              JOIN charities c ON d.charity_id = c.id
              WHERE d.donor_id = ? AND YEAR(d.created_at) = ?
              ORDER BY d.created_at";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id, $year]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_amount = 0;
    foreach ($donations as $donation) {
        $total_amount += $donation['amount'];
    }
    
    return [
        'donations' => $donations,
        'total_amount' => $total_amount,
        'donation_count' => count($donations),
        'year' => $year
    ];
}

/**
 * Valider le format d'email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Vérifier la force du mot de passe
 */
function check_password_strength($password) {
    $strength = 0;
    
    // Vérification de la longueur
    if (strlen($password) >= 8) $strength++;
    
    // Contient des minuscules
    if (preg_match('/[a-z]/', $password)) $strength++;
    
    // Contient des majuscules
    if (preg_match('/[A-Z]/', $password)) $strength++;
    
    // Contient des chiffres
    if (preg_match('/[0-9]/', $password)) $strength++;
    
    // Contient des caractères spéciaux
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $strength++;
    
    return $strength >= 4; // Au moins 4 critères sur 5
}

// =============================================================================
// FONCTIONS POUR LA GÉNÉRATION DE CODES QR ESP32
// =============================================================================

/**
 * Générer les données de code QR pour le Module MDVA (ESP32 format)
 */
function generateModuleQRData($module_id, $module_name = '', $location = '') {
    $qr_data = [
        'module_id' => $module_id,
        'module_name' => $module_name,
        'location' => $location,
        'system' => 'MDVA',
        'type' => 'donation_module',
        'version' => '1.0',
        'timestamp' => time(),
        'url' => "https://systeme-mdva.com/module/" . urlencode($module_id)
    ];
    
    return json_encode($qr_data);
}

/**
 * Générer un code QR unique pour un module (ESP32 format)
 */
function generateModuleQRCode($module_id, $module_name = '', $location = '', $save_path = null) {
    require_once __DIR__ . '/../qrlib/phpqrcode/phpqrcode.php'; // FIXED PATH
    
    $qr_data = generateModuleQRData($module_id, $module_name, $location);
    
    if ($save_path === null) {
        $qr_dir = "../qr_codes/";
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        $save_path = $qr_dir . "mdva_module_" . $module_id . ".png";
    }
    
    QRcode::png($qr_data, $save_path, QR_ECLEVEL_L, 10, 2);
    
    return $save_path;
}

/**
 * Générer plusieurs codes QR pour tous les modules (ESP32 format)
 */
function generateAllModuleQRCodes($db) {
    require_once __DIR__ . '/../qrlib/phpqrcode/phpqrcode.php'; // FIXED PATH
    
    $qr_dir = "../qr_codes/";
    if (!file_exists($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    
    // UPDATED QUERY: Get modules with complete location information
    $query = "SELECT 
                m.module_id,
                m.name as module_name,
                COALESCE(l.name, m.location) as location_name,
                l.address,
                l.city,
                l.province,
                l.postal_code
              FROM modules m
              LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
              LEFT JOIN locations l ON ml.location_id = l.id
              WHERE m.status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $generated = [];
    
    foreach ($modules as $module) {
        // Build location string in ESP32 format
        $location_string = $module['location_name'] ?: '';
        if ($module['address']) $location_string .= ', ' . $module['address'];
        if ($module['city']) $location_string .= ', ' . $module['city'];
        if ($module['province']) $location_string .= ', ' . $module['province'];
        if ($module['postal_code']) $location_string .= ' ' . $module['postal_code'];
        
        // Create QR data in ESP32 format
        $qr_data = [
            'module_id' => $module['module_id'],
            'module_name' => $module['module_name'],
            'location' => $location_string,
            'system' => 'MDVA',
            'type' => 'donation_module',
            'version' => '1.0',
            'timestamp' => time(),
            'url' => "https://systeme-mdva.com/module/" . urlencode($module['module_id'])
        ];
        
        $qr_data_json = json_encode($qr_data);
        
        $filename = "mdva_module_" . $module['module_id'] . ".png";
        $filepath = $qr_dir . $filename;
        
        // Generate QR code with ESP32 format data
        QRcode::png($qr_data_json, $filepath, QR_ECLEVEL_L, 10, 2);
        
        $generated[] = [
            'module_id' => $module['module_id'],
            'module_name' => $module['module_name'],
            'qr_file' => $filename,
            'qr_data' => $qr_data
        ];
    }
    
    return $generated;
}

/**
 * Sauvegarder un nouveau module dans la base de données et générer le QR code
 */
function saveModuleToDatabaseAndGenerateQR($module_data, $db) {
    try {
        $db->beginTransaction();
        
        // Extract form data
        $module_id = $module_data['module_id'] ?? '';
        $module_name = $module_data['module_name'] ?? '';
        $mac_address = $module_data['mac_address'] ?? '';
        $location_name = $module_data['location_name'] ?? '';
        $address = $module_data['address'] ?? '';
        $city = $module_data['city'] ?? '';
        $province = $module_data['province'] ?? '';
        $postal_code = $module_data['postal_code'] ?? '';
        
        // ========== 1. Save/Update Location ==========
        $location_id = null;
        
        // Check if location already exists
        $location_query = "SELECT id FROM locations WHERE name = ?";
        $location_stmt = $db->prepare($location_query);
        $location_stmt->execute([$location_name]);
        $existing_location = $location_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_location) {
            $location_id = $existing_location['id'];
            
            // Update existing location
            $update_location = "UPDATE locations 
                               SET address = ?, city = ?, province = ?, postal_code = ?, updated_at = NOW()
                               WHERE id = ?";
            $update_stmt = $db->prepare($update_location);
            $update_stmt->execute([$address, $city, $province, $postal_code, $location_id]);
        } else {
            // Insert new location
            $insert_location = "INSERT INTO locations 
                               (name, address, city, province, country, postal_code, active, created_at) 
                               VALUES (?, ?, ?, ?, 'Canada', ?, 1, NOW())";
            $insert_stmt = $db->prepare($insert_location);
            $insert_stmt->execute([$location_name, $address, $city, $province, $postal_code]);
            $location_id = $db->lastInsertId();
        }
        
        // ========== 2. Save/Update Module ==========
        $module_db_id = null;
        
        // Check if module already exists
        $module_query = "SELECT id FROM modules WHERE module_id = ?";
        $module_stmt = $db->prepare($module_query);
        $module_stmt->execute([$module_id]);
        $existing_module = $module_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_module) {
            $module_db_id = $existing_module['id'];
            
            // Update existing module
            $update_module = "UPDATE modules 
                             SET name = ?, location = ?, mac_address = ?, firmware_version = '1.0',
                                 status = 'active', updated_at = NOW()
                             WHERE id = ?";
            $update_stmt = $db->prepare($update_module);
            $update_stmt->execute([$module_name, $location_name, $mac_address, $module_db_id]);
        } else {
            // Insert new module
            $insert_module = "INSERT INTO modules 
                             (module_id, name, location, mac_address, firmware_version, status, created_at) 
                             VALUES (?, ?, ?, ?, '1.0', 'active', NOW())";
            $insert_stmt = $db->prepare($insert_module);
            $insert_stmt->execute([$module_id, $module_name, $location_name, $mac_address]);
            $module_db_id = $db->lastInsertId();
        }
        
        // ========== 3. Link Module to Location ==========
        $link_query = "SELECT id FROM module_locations 
                      WHERE module_id = ? AND location_id = ? AND status = 'active'";
        $link_stmt = $db->prepare($link_query);
        $link_stmt->execute([$module_db_id, $location_id]);
        $existing_link = $link_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_link) {
            // Deactivate any existing active links for this module
            $deactivate_query = "UPDATE module_locations 
                                SET status = 'removed', removed_at = NOW() 
                                WHERE module_id = ? AND status = 'active'";
            $deactivate_stmt = $db->prepare($deactivate_query);
            $deactivate_stmt->execute([$module_db_id]);
            
            // Create new link
            $insert_link = "INSERT INTO module_locations 
                           (module_id, location_id, installed_at, status, notes) 
                           VALUES (?, ?, NOW(), 'active', 'Created via QR Generator')";
            $insert_link_stmt = $db->prepare($insert_link);
            $insert_link_stmt->execute([$module_db_id, $location_id]);
        }
        
        // ========== 4. Generate QR Code ==========
        // Build location string
        $location_string = $location_name;
        if ($address) $location_string .= ', ' . $address;
        if ($city) $location_string .= ', ' . $city;
        if ($province) $location_string .= ', ' . $province;
        if ($postal_code) $location_string .= ' ' . $postal_code;
        
        // Create QR data in ESP32 format
        $qr_data = [
            'module_id' => $module_id,
            'module_name' => $module_name,
            'location' => $location_string,
            'system' => 'MDVA',
            'type' => 'donation_module',
            'version' => '1.0',
            'timestamp' => time(),
            'url' => "https://systeme-mdva.com/module/" . urlencode($module_id)
        ];
        
        $qr_data_json = json_encode($qr_data);
        
        // Generate QR code
        require_once __DIR__ . '/../qrlib/phpqrcode/phpqrcode.php'; // FIXED PATH
        
        $qr_dir = "../qr_codes/";
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        
        $qr_filename = "mdva_module_" . $module_id . ".png";
        $qr_path = $qr_dir . $qr_filename;
        
        QRcode::png($qr_data_json, $qr_path, QR_ECLEVEL_L, 10, 2);
        
        $db->commit();
        
        return [
            'success' => true,
            'module_id' => $module_id,
            'module_name' => $module_name,
            'location' => $location_string,
            'qr_path' => $qr_path,
            'qr_data' => $qr_data
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error saving module: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Obtenir les statistiques du système
 */
function get_system_stats($db) {
    $stats = [];
    
    $query = "SELECT COUNT(*) as count FROM charities WHERE approved = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_charities'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $query = "SELECT COUNT(*) as count FROM donors";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_donors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $query = "SELECT SUM(amount) as total FROM donations";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM donations";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_donation_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $query = "SELECT SUM(amount) as total FROM donations WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['today_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM modules WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['active_modules'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $query = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $stats;
}

// =============================================================================
// FONCTIONS DE TRANSACTION VÉRIFIABLE FAIRGIVE (Système de type Blockchain)
// =============================================================================

/**
 * Obtenir le dernier hash de transaction pour un donateur pour maintenir la chaîne de hash
 */
function get_last_transaction_hash($donor_id, $db) {
    $query = "SELECT transaction_hash FROM verifiable_transactions 
              WHERE donor_id = ? 
              ORDER BY id DESC 
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['transaction_hash'] : '0';
}

/**
 * Créer une transaction vérifiable initiale pour les nouveaux donateurs
 */
function create_initial_verifiable_transaction($donor_id, $user_id, $db) {
    $timestamp = time();
    
    $transaction_data = [
        'donor_id' => $user_id,
        'action' => 'account_creation',
        'timestamp' => $timestamp,
        'previous_hash' => '0'
    ];
    
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    $query = "INSERT INTO verifiable_transactions 
              (donor_id, transaction_type, transaction_data, transaction_hash, previous_hash, timestamp) 
              VALUES (?, 'account_creation', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $donor_id, 
        $transaction_data_json, 
        $transaction_hash,
        '0',
        date('Y-m-d H:i:s')
    ]);
    
    error_log("Enregistrement Vérifiable Initial MDVA : Compte donateur $user_id créé. Hash de Transaction : $transaction_hash");
    
    return $transaction_hash;
}

/**
 * Créer un enregistrement vérifiable de sélection d'organisme
 */
function create_verifiable_charity_selection($donor_id, $user_id, $old_charity_id, $new_charity_id, $charity_name, $db) {
    $timestamp = time();
    
    $previous_hash = get_last_transaction_hash($donor_id, $db);
    
    $transaction_data = [
        'donor_id' => $user_id,
        'old_charity_id' => $old_charity_id,
        'new_charity_id' => $new_charity_id,
        'charity_name' => $charity_name,
        'action' => 'charity_selection',
        'timestamp' => $timestamp,
        'previous_hash' => $previous_hash
    ];
    
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    $query = "INSERT INTO verifiable_transactions 
              (donor_id, transaction_type, transaction_data, transaction_hash, previous_hash, timestamp) 
              VALUES (?, 'charity_selection', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $donor_id, 
        $transaction_data_json, 
        $transaction_hash,
        $previous_hash,
        date('Y-m-d H:i:s')
    ]);
    
    $query = "INSERT INTO charity_selection_audit 
              (donor_id, old_charity_id, new_charity_id, transaction_hash, selected_at) 
              VALUES (?, ?, ?, ?, NOW())";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id, $old_charity_id, $new_charity_id, $transaction_hash]);
    
    error_log("Enregistrement Vérifiable MDVA : Donateur $user_id a changé d'organisme de $old_charity_id à $new_charity_id. Hash de Transaction : $transaction_hash");
    
    return $transaction_hash;
}

/**
 * Créer un enregistrement vérifiable de don
 */
function create_verifiable_donation($donor_id, $user_id, $charity_id, $amount, $module_id, $db) {
    $timestamp = time();
    
    $previous_hash = get_last_transaction_hash($donor_id, $db);
    
    $charity_name = get_charity_name($charity_id, $db);
    
    $transaction_data = [
        'donor_id' => $user_id,
        'charity_id' => $charity_id,
        'charity_name' => $charity_name,
        'amount' => $amount,
        'module_id' => $module_id,
        'action' => 'donation',
        'timestamp' => $timestamp,
        'previous_hash' => $previous_hash
    ];
    
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    $query = "INSERT INTO verifiable_transactions 
              (donor_id, transaction_type, transaction_data, transaction_hash, previous_hash, timestamp) 
              VALUES (?, 'donation', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $donor_id, 
        $transaction_data_json, 
        $transaction_hash,
        $previous_hash,
        date('Y-m-d H:i:s')
    ]);
    
    error_log("Enregistrement Vérifiable MDVA : Donateur $user_id a donné $amount à $charity_name via le module $module_id. Hash de Transaction : $transaction_hash");
    
    return $transaction_hash;
}

/**
 * Vérifier l'intégrité de la chaîne de transaction pour un donateur
 */
function verify_donor_transaction_chain($donor_id, $db) {
    $query = "SELECT transaction_hash, previous_hash, transaction_data, timestamp 
              FROM verifiable_transactions 
              WHERE donor_id = ? 
              ORDER BY id ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        return ['valid' => true, 'message' => 'Aucune transaction à vérifier'];
    }
    
    $previous_hash = '0';
    $issues = [];
    
    foreach ($transactions as $index => $transaction) {
        if ($transaction['previous_hash'] !== $previous_hash) {
            $issues[] = "La transaction {$transaction['transaction_hash']} a un hash précédent incorrect. Attendu : $previous_hash, Trouvé : {$transaction['previous_hash']}";
        }
        
        $calculated_hash = hash('sha256', $transaction['transaction_data']);
        if ($calculated_hash !== $transaction['transaction_hash']) {
            $issues[] = "Le hash de la transaction {$transaction['transaction_hash']} ne correspond pas. Les données peuvent avoir été altérées.";
        }
        
        $previous_hash = $transaction['transaction_hash'];
    }
    
    return [
        'valid' => empty($issues),
        'transaction_count' => count($transactions),
        'issues' => $issues,
        'last_transaction_hash' => end($transactions)['transaction_hash'] ?? null
    ];
}

/**
 * Obtenir l'historique des transactions vérifiables d'un donateur
 */
function get_donor_verifiable_history($donor_id, $db, $limit = 50) {
    $query = "SELECT 
                transaction_type,
                transaction_data,
                transaction_hash,
                previous_hash,
                timestamp
              FROM verifiable_transactions 
              WHERE donor_id = ? 
              ORDER BY timestamp DESC 
              LIMIT ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id, $limit]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($transactions as &$transaction) {
        $transaction['parsed_data'] = json_decode($transaction['transaction_data'], true);
    }
    
    return $transactions;
}

/**
 * Générer des données de reçu fiscal simplifiées (pour l'application mobile)
 */
function generate_tax_receipt_data_simple($donor_id, $year, $db) {
    $query = "SELECT 
                SUM(amount) as total_amount,
                COUNT(*) as donation_count,
                MIN(created_at) as first_donation,
                MAX(created_at) as last_donation
              FROM donations 
              WHERE donor_id = ? AND YEAR(created_at) = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id, $year]);
    
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data || $data['total_amount'] == null) {
        return [
            'year' => $year,
            'total_amount' => 0,
            'donation_count' => 0,
            'first_donation' => null,
            'last_donation' => null,
            'receipt_number' => null
        ];
    }
    
    $receipt_number = 'RCPT-' . $year . '-' . str_pad($donor_id, 6, '0', STR_PAD_LEFT) . '-' . time();
    
    return [
        'year' => $year,
        'total_amount' => $data['total_amount'],
        'donation_count' => $data['donation_count'],
        'first_donation' => $data['first_donation'],
        'last_donation' => $data['last_donation'],
        'receipt_number' => $receipt_number
    ];
}

// =============================================================================
// FONCTIONS AJOUTÉES POUR LA GÉNÉRATION DE CODES QR
// =============================================================================

/**
 * Fonction pour générer un QR code simple
 */
function generateSimpleQRCode($data, $filename = null) {
    require_once __DIR__ . '/../qrlib/phpqrcode/phpqrcode.php'; // FIXED PATH
    
    if ($filename === null) {
        $temp_dir = "../temp_qr_codes/";
        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        $filename = $temp_dir . 'qr_' . md5($data . time()) . '.png';
    }
    
    QRcode::png($data, $filename, QR_ECLEVEL_L, 10, 2);
    
    return $filename;
}

/**
 * Fonction pour vérifier si un QR code existe pour un module
 */
function moduleQRCodeExists($module_id) {
    $qr_file = "../qr_codes/mdva_module_" . $module_id . ".png";
    return file_exists($qr_file) && filesize($qr_file) > 0;
}

/**
 * Fonction pour obtenir l'URL du QR code d'un module
 */
function getModuleQRCodeUrl($module_id) {
    $qr_file = "qr_codes/mdva_module_" . $module_id . ".png";
    if (file_exists("../" . $qr_file) && filesize("../" . $qr_file) > 0) {
        return $qr_file;
    }
    return null;
}

/**
 * Fonction pour obtenir les détails complets d'un module avec emplacement
 */
function getModuleWithLocation($module_id, $db) {
    $query = "SELECT 
                m.*,
                l.name as location_name,
                l.address,
                l.city,
                l.province,
                l.postal_code
              FROM modules m
              LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
              LEFT JOIN locations l ON ml.location_id = l.id
              WHERE m.module_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$module_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fonction pour générer un code-barres (non-QR)
 */
function generateBarcode($data, $type = 'C128', $width = 2, $height = 30) {
    // Note: Si vous avez une librairie de codes-barres, incluez-la ici
    // require_once __DIR__ . '/../vendor/autoload.php';
    
    try {
        // Exemple avec TCPDF si installé
        if (class_exists('TCPDF')) {
            $barcode = TCPDFBarcode::getBarcodeHTML($data, $type, $width, $height);
            return $barcode;
        }
        return '<div class="barcode-error">Code-barres non disponible - installez TCPDF</div>';
    } catch (Exception $e) {
        error_log("Erreur génération code-barres: " . $e->getMessage());
        return '<div class="barcode-error">Code-barres non disponible</div>';
    }
}

/**
 * Fonction pour valider les données du module
 */
function validate_module_data($module_data) {
    $errors = [];
    
    if (empty($module_data['module_id'])) {
        $errors[] = "L'ID du module est requis";
    } elseif (!preg_match('/^MDVA_[A-Z0-9]{6}$/', $module_data['module_id'])) {
        $errors[] = "Format d'ID module invalide. Utilisez: MDVA_XXXXXX";
    }
    
    if (empty($module_data['module_name'])) {
        $errors[] = "Le nom du module est requis";
    }
    
    if (empty($module_data['location_name'])) {
        $errors[] = "Le nom de l'emplacement est requis";
    }
    
    if (empty($module_data['mac_address'])) {
        $errors[] = "L'adresse MAC est requise";
    } elseif (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $module_data['mac_address'])) {
        $errors[] = "Format d'adresse MAC invalide";
    }
    
    return $errors;
}

/**
 * Fonction pour obtenir tous les modules avec leur statut QR
 */
function getAllModulesWithQRStatus($db) {
    $query = "SELECT 
                m.module_id,
                m.name as module_name,
                m.status,
                m.location,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM qr_codes q WHERE q.module_id = m.module_id) THEN 1
                    ELSE 0
                END as has_qr_code,
                (SELECT q.file_path FROM qr_codes q WHERE q.module_id = m.module_id ORDER BY q.created_at DESC LIMIT 1) as qr_path
              FROM modules m
              ORDER BY m.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fonction pour nettoyer les anciens fichiers QR temporaires
 */
function cleanup_temp_qr_files($max_age_hours = 24) {
    $temp_dir = "../temp_qr_codes/";
    if (!file_exists($temp_dir)) {
        return 0;
    }
    
    $files = glob($temp_dir . "*.png");
    $deleted_count = 0;
    $cutoff_time = time() - ($max_age_hours * 3600);
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff_time) {
            if (unlink($file)) {
                $deleted_count++;
            }
        }
    }
    
    return $deleted_count;
}

/**
 * Fonction pour obtenir le nombre total de QR codes générés
 */
function get_total_qr_codes_generated($db) {
    $query = "SELECT COUNT(*) as total FROM qr_codes";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

/**
 * Fonction pour enregistrer la génération de QR code dans l'historique
 */
function log_qr_generation($db, $module_id, $user_id, $type = 'single') {
    $query = "INSERT INTO qr_generation_logs (module_id, generated_by, generation_type, generated_at) 
              VALUES (?, ?, ?, NOW())";
    $stmt = $db->prepare($query);
    return $stmt->execute([$module_id, $user_id, $type]);
}

/**
 * Fonction pour obtenir les statistiques de génération de QR codes
 */
function get_qr_generation_stats($db, $days = 30) {
    $query = "SELECT 
                DATE(generated_at) as date,
                COUNT(*) as count,
                generation_type
              FROM qr_generation_logs 
              WHERE generated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY DATE(generated_at), generation_type
              ORDER BY date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fonction pour vérifier si un utilisateur peut générer des QR codes
 */
function can_generate_qr_codes($user_type) {
    $allowed_types = ['admin', 'supervisor'];
    return in_array($user_type, $allowed_types);
}

/**
 * Fonction pour générer un rapport d'activité QR
 */
function generate_qr_activity_report($db, $start_date, $end_date) {
    $query = "SELECT 
                qgl.module_id,
                m.name as module_name,
                u.username as generated_by,
                qgl.generation_type,
                qgl.generated_at,
                (SELECT file_path FROM qr_codes WHERE module_id = qgl.module_id ORDER BY created_at DESC LIMIT 1) as latest_qr_path
              FROM qr_generation_logs qgl
              LEFT JOIN modules m ON qgl.module_id = m.module_id
              LEFT JOIN users u ON qgl.generated_by = u.id
              WHERE qgl.generated_at BETWEEN ? AND ?
              ORDER BY qgl.generated_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fonction pour envoyer une notification par email lors de la génération de QR
 */
function send_qr_generation_notification($email, $module_id, $qr_path, $type = 'single') {
    $subject = "QR Code Généré - Module " . $module_id;
    $message = "Bonjour,\n\n";
    $message .= "Un QR code a été généré pour le module " . $module_id . ".\n";
    $message .= "Type: " . ($type == 'single' ? 'Génération unique' : 'Génération en masse') . "\n";
    $message .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $message .= "Le fichier est disponible à l'emplacement: " . $qr_path . "\n\n";
    $message .= "Cordialement,\nSystème MDVA";
    
    $headers = "From: systeme-mdva@example.com\r\n";
    $headers .= "Reply-To: no-reply@systeme-mdva.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $subject, $message, $headers);
}

/**
 * Fonction pour vérifier les permissions d'accès aux fichiers QR
 */
function check_qr_file_permissions($file_path) {
    if (!file_exists($file_path)) {
        return ['exists' => false, 'readable' => false, 'writable' => false];
    }
    
    return [
        'exists' => true,
        'readable' => is_readable($file_path),
        'writable' => is_writable($file_path),
        'size' => filesize($file_path),
        'modified' => date('Y-m-d H:i:s', filemtime($file_path))
    ];
}

/**
 * Fonction pour créer un zip de tous les QR codes
 */
function create_qr_codes_zip($db) {
    $qr_dir = "../qr_codes/";
    if (!file_exists($qr_dir)) {
        return false;
    }
    
    $files = glob($qr_dir . "*.png");
    if (empty($files)) {
        return false;
    }
    
    $zip = new ZipArchive();
    $zip_filename = '../exports/qr_codes_' . date('Y-m-d_H-i-s') . '.zip';
    
    if (!file_exists('../exports/')) {
        mkdir('../exports/', 0755, true);
    }
    
    if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
        return false;
    }
    
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }
    
    $zip->close();
    
    return $zip_filename;
}

/**
 * Fonction pour obtenir la liste des fichiers QR générés
 */
function get_generated_qr_files($limit = 100) {
    $qr_dir = "../qr_codes/";
    if (!file_exists($qr_dir)) {
        return [];
    }
    
    $files = glob($qr_dir . "*.png");
    $result = [];
    
    foreach ($files as $file) {
        $result[] = [
            'filename' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'modified' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    
    // Trier par date de modification (le plus récent en premier)
    usort($result, function($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
    });
    
    return array_slice($result, 0, $limit);
}

/**
 * Fonction pour supprimer un fichier QR spécifique
 */
function delete_qr_file($filename, $db) {
    $file_path = "../qr_codes/" . $filename;
    
    if (!file_exists($file_path)) {
        return ['success' => false, 'error' => 'Fichier non trouvé'];
    }
    
    // Extraire l'ID du module du nom de fichier
    if (preg_match('/mdva_module_(.+)\.png/', $filename, $matches)) {
        $module_id = $matches[1];
        
        // Enregistrer dans les logs
        $query = "INSERT INTO qr_deletion_logs (module_id, filename, deleted_at, deleted_by) 
                  VALUES (?, ?, NOW(), ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$module_id, $filename, $_SESSION['user_id'] ?? 0]);
    }
    
    if (unlink($file_path)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'error' => 'Impossible de supprimer le fichier'];
    }
}

/**
 * Fonction pour régénérer tous les QR codes expirés
 */
function regenerate_expired_qr_codes($db, $expiry_days = 90) {
    $qr_dir = "../qr_codes/";
    if (!file_exists($qr_dir)) {
        return ['regenerated' => 0, 'errors' => []];
    }
    
    $files = glob($qr_dir . "*.png");
    $regenerated = 0;
    $errors = [];
    
    foreach ($files as $file) {
        $file_age = time() - filemtime($file);
        
        if ($file_age > ($expiry_days * 24 * 3600)) {
            // Extraire l'ID du module
            if (preg_match('/mdva_module_(.+)\.png/', basename($file), $matches)) {
                $module_id = $matches[1];
                $module = getModuleWithLocation($module_id, $db);
                
                if ($module) {
                    try {
                        // Régénérer le QR code
                        generateModuleQRCode(
                            $module_id,
                            $module['name'],
                            $module['location_name'] ?? '',
                            $file
                        );
                        $regenerated++;
                    } catch (Exception $e) {
                        $errors[] = "Erreur avec $module_id: " . $e->getMessage();
                    }
                }
            }
        }
    }
    
    return ['regenerated' => $regenerated, 'errors' => $errors];
}

/**
 * Fonction pour vérifier la validité d'un QR code
 */
function validate_qr_code($file_path) {
    if (!file_exists($file_path)) {
        return ['valid' => false, 'error' => 'Fichier non trouvé'];
    }
    
    $image_info = getimagesize($file_path);
    if (!$image_info) {
        return ['valid' => false, 'error' => 'Fichier image invalide'];
    }
    
    return [
        'valid' => true,
        'width' => $image_info[0],
        'height' => $image_info[1],
        'mime_type' => $image_info['mime'],
        'size' => filesize($file_path)
    ];
}

/**
 * Fonction pour mettre à jour les données du QR code sans régénérer l'image
 */
function update_qr_code_data($module_id, $new_data, $db) {
    $qr_file = "../qr_codes/mdva_module_" . $module_id . ".png";
    
    if (!file_exists($qr_file)) {
        return ['success' => false, 'error' => 'QR code non trouvé'];
    }
    
    // Enregistrer les nouvelles données dans la base de données
    $query = "INSERT INTO qr_code_updates (module_id, old_data, new_data, updated_at, updated_by) 
              VALUES (?, ?, ?, NOW(), ?)";
    $stmt = $db->prepare($query);
    
    // Note: Pour mettre à jour l'image elle-même, il faudrait régénérer le QR code
    return ['success' => true, 'message' => 'Données mises à jour, QR code inchangé'];
}

/**
 * Fonction pour obtenir l'historique des modifications de QR codes
 */
function get_qr_code_update_history($module_id, $db, $limit = 10) {
    $query = "SELECT 
                old_data,
                new_data,
                updated_at,
                updated_by
              FROM qr_code_updates 
              WHERE module_id = ? 
              ORDER BY updated_at DESC 
              LIMIT ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$module_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fonction pour archiver les anciens QR codes
 */
function archive_old_qr_codes($db, $archive_days = 30) {
    $qr_dir = "../qr_codes/";
    $archive_dir = "../qr_codes_archive/" . date('Y-m') . "/";
    
    if (!file_exists($qr_dir)) {
        return ['archived' => 0, 'errors' => []];
    }
    
    if (!file_exists($archive_dir)) {
        mkdir($archive_dir, 0755, true);
    }
    
    $files = glob($qr_dir . "*.png");
    $archived = 0;
    $errors = [];
    $cutoff_time = strtotime("-$archive_days days");
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff_time) {
            $filename = basename($file);
            $destination = $archive_dir . $filename;
            
            if (copy($file, $destination)) {
                // Enregistrer l'archivage
                if (preg_match('/mdva_module_(.+)\.png/', $filename, $matches)) {
                    $module_id = $matches[1];
                    
                    $query = "INSERT INTO qr_code_archive (module_id, filename, archived_at, archived_by) 
                              VALUES (?, ?, NOW(), ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$module_id, $filename, $_SESSION['user_id'] ?? 0]);
                }
                
                $archived++;
            } else {
                $errors[] = "Impossible d'archiver $filename";
            }
        }
    }
    
    return ['archived' => $archived, 'errors' => $errors];
}

/**
 * Fonction pour restaurer un QR code archivé
 */
function restore_archived_qr_code($filename, $db) {
    // Chercher dans tous les sous-dossiers d'archive
    $archive_base = "../qr_codes_archive/";
    $files = [];
    
    foreach (glob($archive_base . "*/*.png") as $file) {
        if (basename($file) == $filename) {
            $files[] = $file;
        }
    }
    
    if (empty($files)) {
        return ['success' => false, 'error' => 'Fichier archivé non trouvé'];
    }
    
    // Prendre le plus récent
    $latest_file = $files[0];
    $latest_mtime = filemtime($latest_file);
    
    foreach ($files as $file) {
        if (filemtime($file) > $latest_mtime) {
            $latest_file = $file;
            $latest_mtime = filemtime($file);
        }
    }
    
    $destination = "../qr_codes/" . $filename;
    
    if (copy($latest_file, $destination)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'error' => 'Impossible de restaurer le fichier'];
    }
}

/**
 * Fonction pour obtenir les statistiques d'utilisation des QR codes
 */
function get_qr_code_usage_stats($db, $module_id = null) {
    $query = "SELECT 
                qr.module_id,
                COUNT(*) as scan_count,
                MIN(scanned_at) as first_scan,
                MAX(scanned_at) as last_scan
              FROM qr_code_scans qr";
    
    if ($module_id) {
        $query .= " WHERE qr.module_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$module_id]);
    } else {
        $query .= " GROUP BY qr.module_id ORDER BY scan_count DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fonction pour enregistrer un scan de QR code
 */
function log_qr_code_scan($db, $module_id, $scanned_by, $device_info = null) {
    $query = "INSERT INTO qr_code_scans (module_id, scanned_by, device_info, scanned_at) 
              VALUES (?, ?, ?, NOW())";
    $stmt = $db->prepare($query);
    return $stmt->execute([$module_id, $scanned_by, $device_info]);
}
?>