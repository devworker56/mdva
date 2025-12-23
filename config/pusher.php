<?php
// config/pusher.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/loader.php'; // Add this line

// Configuration Pusher - USING ENVIRONMENT VARIABLES
define('PUSHER_APP_ID', env('PUSHER_APP_ID', ''));
define('PUSHER_KEY', env('PUSHER_APP_KEY', '')); 
define('PUSHER_SECRET', env('PUSHER_APP_SECRET', ''));
define('PUSHER_CLUSTER', env('PUSHER_APP_CLUSTER', 'mt1'));

function getPusher() {
    try {
        // Validate that all required credentials are set
        if (empty(PUSHER_APP_ID) || empty(PUSHER_KEY) || empty(PUSHER_SECRET)) {
            throw new Exception('Pusher credentials are not configured in .env file');
        }
        
        $pusher = new Pusher\Pusher(
            PUSHER_KEY,
            PUSHER_SECRET, 
            PUSHER_APP_ID,
            [
                'cluster' => PUSHER_CLUSTER,
                'useTLS' => true,
                'encrypted' => true,
                'debug' => env('PUSHER_DEBUG', false) === 'true',
                'curl_options' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 10
                ]
            ]
        );
        
        error_log("✅ Pusher initialisé : App " . PUSHER_APP_ID . " sur le cluster " . PUSHER_CLUSTER);
        return $pusher;
        
    } catch (Exception $e) {
        error_log("❌ Échec d'initialisation de Pusher : " . $e->getMessage());
        return null;
    }
}
?>