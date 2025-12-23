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
        require_once '../config/pusher.php';
        
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

/**
 * Générer les données de code QR pour le Module MDVA
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
        'url' => "https://tech-ideapad.com/donate.php?module=" . urlencode($module_id)
    ];
    
    return json_encode($qr_data);
}

/**
 * Générer un code QR unique pour un module
 */
function generateModuleQRCode($module_id, $module_name = '', $location = '', $save_path = null) {
    require_once 'Lib/phpqrcode/qrlib.php';
    
    $qr_data = generateModuleQRData($module_id, $module_name, $location);
    
    // Si aucun chemin de sauvegarde n'est fourni, générer un nom de fichier
    if ($save_path === null) {
        $qr_dir = "../qr_codes/";
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        $save_path = $qr_dir . "mdva_module_" . $module_id . ".png";
    }
    
    // Générer et sauvegarder le code QR
    QRcode::png($qr_data, $save_path, QR_ECLEVEL_L, 10, 2);
    
    return $save_path;
}

/**
 * Générer plusieurs codes QR pour tous les modules
 */
function generateAllModuleQRCodes($db) {
    require_once 'Lib/phpqrcode/qrlib.php';
    
    $qr_dir = "../qr_codes/";
    if (!file_exists($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    
    // Obtenir tous les modules actifs
    $query = "SELECT m.*, l.name as location_name, l.address, l.city, l.state 
              FROM modules m 
              LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
              LEFT JOIN locations l ON ml.location_id = l.id 
              WHERE m.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $generated = [];
    
    foreach ($modules as $module) {
        $location = $module['location_name'] ? 
            $module['location_name'] . ', ' . $module['address'] . ', ' . $module['city'] . ', ' . $module['state'] : 
            $module['location'];
            
        $filename = "mdva_module_" . $module['module_id'] . ".png";
        $filepath = $qr_dir . $filename;
        
        // Générer le code QR en utilisant la fonction de code QR unique
        generateModuleQRCode(
            $module['module_id'],
            $module['name'],
            $location,
            $filepath
        );
        
        $generated[] = [
            'module_id' => $module['module_id'],
            'module_name' => $module['name'],
            'qr_file' => $filename
        ];
    }
    
    return $generated;
}

/**
 * Obtenir les statistiques du système
 */
function get_system_stats($db) {
    $stats = [];
    
    // Organismes totaux
    $query = "SELECT COUNT(*) as count FROM charities WHERE approved = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_charities'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Donateurs totaux
    $query = "SELECT COUNT(*) as count FROM donors";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_donors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Dons totaux
    $query = "SELECT SUM(amount) as total FROM donations";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Nombre total de dons
    $query = "SELECT COUNT(*) as count FROM donations";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_donation_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Dons d'aujourd'hui
    $query = "SELECT SUM(amount) as total FROM donations WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['today_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    return $stats;
}

// =============================================================================
// FONCTIONS DE TRANSACTION VÉRIFIABLE FAIRGIVE (Système de type Blockchain)
// =============================================================================

/**
 * Obtenir le dernier hash de transaction pour un donateur pour maintenir la chaîne de hash
 * Utilisé pour le système de dons vérifiable FairGive
 */
function get_last_transaction_hash($donor_id, $db) {
    $query = "SELECT transaction_hash FROM verifiable_transactions 
              WHERE donor_id = ? 
              ORDER BY id DESC 
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['transaction_hash'] : '0'; // Hash de genèse pour la première transaction
}

/**
 * Créer une transaction vérifiable initiale pour les nouveaux donateurs
 * Utilisé pour le système de dons vérifiable FairGive
 */
function create_initial_verifiable_transaction($donor_id, $user_id, $db) {
    $timestamp = time();
    
    $transaction_data = [
        'donor_id' => $user_id,
        'action' => 'account_creation',
        'timestamp' => $timestamp,
        'previous_hash' => '0' // Bloc de genèse
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
 * Utilisé pour le système de dons vérifiable FairGive
 */
function create_verifiable_charity_selection($donor_id, $user_id, $old_charity_id, $new_charity_id, $charity_name, $db) {
    $timestamp = time();
    
    // Obtenir le hash de la transaction précédente pour maintenir la chaîne
    $previous_hash = get_last_transaction_hash($donor_id, $db);
    
    // Créer les données de transaction pour le hachage cryptographique
    $transaction_data = [
        'donor_id' => $user_id,
        'old_charity_id' => $old_charity_id,
        'new_charity_id' => $new_charity_id,
        'charity_name' => $charity_name,
        'action' => 'charity_selection',
        'timestamp' => $timestamp,
        'previous_hash' => $previous_hash
    ];
    
    // Générer le hash cryptographique (vérification de type blockchain)
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    // Stocker dans la table des transactions vérifiables
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
    
    // Également journaliser dans la table d'audit pour une visibilité immédiate et des rapports
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
 * Utilisé pour le système de dons vérifiable FairGive
 */
function create_verifiable_donation($donor_id, $user_id, $charity_id, $amount, $module_id, $db) {
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
        'amount' => $amount,
        'module_id' => $module_id,
        'action' => 'donation',
        'timestamp' => $timestamp,
        'previous_hash' => $previous_hash
    ];
    
    // Générer le hash cryptographique (vérification de type blockchain)
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    // Stocker dans la table des transactions vérifiables
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
 * Utilisé pour l'audit du système de dons vérifiable FairGive
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
    
    $previous_hash = '0'; // Commencer avec le hash de genèse
    $issues = [];
    
    foreach ($transactions as $index => $transaction) {
        // Vérifier si le hash précédent correspond
        if ($transaction['previous_hash'] !== $previous_hash) {
            $issues[] = "La transaction {$transaction['transaction_hash']} a un hash précédent incorrect. Attendu : $previous_hash, Trouvé : {$transaction['previous_hash']}";
        }
        
        // Vérifier que le hash actuel est correct
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
 * Utilisé pour les rapports du système de dons vérifiable FairGive
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
    
    // Analyser les données de transaction
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
    
    // Générer un numéro de reçu
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

?>