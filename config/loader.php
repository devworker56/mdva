<?php
// config/loader.php - Environment Variable Loader

class EnvConfig {
    private static $loaded = false;
    private static $variables = [];
    
    /**
     * Load environment variables from .env file
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return self::$variables;
        }
        
        $path = $path ?: dirname(__DIR__) . '/.env';
        
        // Try to load .env file
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse KEY=VALUE
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if (($value[0] === '"' && substr($value, -1) === '"') || 
                        ($value[0] === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    
                    self::$variables[$key] = $value;
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
            
            error_log("✅ Fichier .env chargé avec " . count(self::$variables) . " variables");
        } else {
            error_log("⚠️  Fichier .env non trouvé à: " . $path);
        }
        
        self::$loaded = true;
        return self::$variables;
    }
    
    /**
     * Get environment variable
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        return self::$variables[$key] ?? $default;
    }
    
    /**
     * Check if environment variable exists
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }
        return isset(self::$variables[$key]);
    }
    
    /**
     * Get all environment variables
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        return self::$variables;
    }
}

// Auto-load on include
EnvConfig::load();

// Helper function (optional but convenient)
function env($key, $default = null) {
    return EnvConfig::get($key, $default);
}

// Optional: Define constants from environment
function defineFromEnv($constantName, $envKey, $default = null) {
    if (!defined($constantName)) {
        define($constantName, env($envKey, $default));
    }
}