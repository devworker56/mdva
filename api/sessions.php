<?php
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = (new Database())->getConnection();
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? '';

try {
    switch($action) {
        case 'start_anonymous':
            $m_id = $input['module_id']; // This is the MAC-based ID
            $c_id = $input['charity_id'];
            $s_id = $input['session_id'];

            $c_stmt = $db->prepare("SELECT name FROM charities WHERE id = ? AND approved = 1");
            $c_stmt->execute([$c_id]);
            $charity = $c_stmt->fetch(PDO::FETCH_ASSOC);
            if(!$charity) throw new Exception("Charity invalid");

            $pusher_data = ['session_id' => $s_id, 'charity_id' => $c_id, 'charity_name' => $charity['name']];
            notify_pusher('start_session', $pusher_data, "module_" . $m_id);

            $sql = "INSERT INTO anonymous_sessions (session_id, charity_id, module_id, status, started_at) VALUES (?, ?, ?, 'active', NOW()) ON DUPLICATE KEY UPDATE status='active'";
            $db->prepare($sql)->execute([$s_id, $c_id, $m_id]);
            
            echo json_encode(['success' => true, 'message' => 'Session started']);
            break;

        case 'end_anonymous':
            $s_id = $input['session_id'];
            $m_id = $input['module_id'];
            $db->prepare("UPDATE anonymous_sessions SET status='completed' WHERE session_id=?")->execute([$s_id]);
            notify_pusher('end_session', ['action' => 'session_ended'], "module_" . $m_id);
            echo json_encode(['success' => true]);
            break;

        case 'get_session':
            $s_id = $_GET['session_id'] ?? '';
            $stmt = $db->prepare("SELECT s.*, c.name as charity_name FROM anonymous_sessions s JOIN charities c ON s.charity_id = c.id WHERE s.session_id = ?");
            $stmt->execute([$s_id]);
            echo json_encode(['success' => true, 'session' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        default:
            throw new Exception("Action not found");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
ob_end_flush();