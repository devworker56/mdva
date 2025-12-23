<?php
// Database.php
require_once __DIR__ . '/../config/loader.php'; // Add this

class Database {
    // Configuration de la base de données - USING ENVIRONMENT VARIABLES
    private $host = null;
    private $db_name = null;
    private $username = null;
    private $password = null;
    public $conn;
    
    public function __construct() {
        // Load credentials from environment variables
        $this->host = env('DB_HOST', 'localhost');
        $this->db_name = env('DB_DATABASE', '');
        $this->username = env('DB_USERNAME', '');
        $this->password = env('DB_PASSWORD', '');
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            // Validate that all required credentials are set
            if (empty($this->db_name) || empty($this->username)) {
                throw new Exception('Database credentials are not configured in .env file');
            }
            
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
            
            error_log("✅ Connexion à la base de données établie : " . $this->db_name);
            
        } catch(PDOException $exception) {
            error_log("❌ Erreur de connexion à la base de données : " . $exception->getMessage());
            $this->conn = null;
        }
        
        return $this->conn;
    }
    
    // Optional: Check if credentials are configured
    public function isConfigured() {
        return !empty($this->db_name) && !empty($this->username);
    }
}
?>