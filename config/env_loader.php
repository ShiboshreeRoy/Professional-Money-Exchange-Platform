<?php
/**
 * Environment Configuration Loader
 * Loads environment variables from .env file
 */

class EnvLoader {
    private static $loaded = false;
    
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }
        
        // If no path provided, try different locations
        if ($path === null) {
            // Try parent directory first (where .env is located)
            $paths = [
                dirname(__DIR__) . '/.env',    // Parent directory
                dirname(__DIR__) . '/.env.example', // Parent directory example
                '.env',                        // Current directory
                '.env.example'                // Current directory example
            ];
            
            $path = null;
            foreach ($paths as $try_path) {
                if (file_exists($try_path)) {
                    $path = $try_path;
                    break;
                }
            }
            
            if ($path === null) {
                throw new Exception('Environment file not found');
            }
        } elseif (!file_exists($path)) {
            // If a specific path was provided but doesn't exist, try alternatives
            $alt_path = dirname(__DIR__) . '/.env'; // Try parent directory
            if (file_exists($alt_path)) {
                $path = $alt_path;
            } else {
                $alt_path = dirname(__DIR__) . '/.env.example'; // Try parent example
                if (file_exists($alt_path)) {
                    $path = $alt_path;
                } else {
                    throw new Exception('Environment file not found');
                }
            }
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                // Set environment variable
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    public static function get($key, $default = null) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

// Auto-load environment variables
EnvLoader::load();
?>