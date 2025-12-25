<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Simple Test</h2>";

// Test .env loading
require_once 'config/loader.php';
$env = EnvConfig::all();
echo "<p>Environment variables loaded: " . count($env) . "</p>";

// Test database
require_once 'config/database.php';
try {
    $database = new Database();
    echo "<p>Database object created</p>";
    
    if ($database->isConfigured()) {
        echo "<p style='color: green;'>Database is configured</p>";
    } else {
        echo "<p style='color: red;'>Database is NOT configured</p>";
    }
    
    $db = $database->getConnection();
    if ($db) {
        echo "<p style='color: green;'>✓ Database connected successfully!</p>";
        
        // Test a simple query
        $stmt = $db->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "<p>✓ Query test successful: " . $result['test'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}