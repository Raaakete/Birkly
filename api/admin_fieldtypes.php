<?php
/**
 * HaruCMS Admin Fieldtypes API
 * 
 * Handles dynamic loading and rendering of fieldtypes
 */

// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

require_once __DIR__ . '/../fieldtypes/BaseField.php';

// Autoload fieldtypes
spl_autoload_register(function ($class) {
    // Check if it's a HaruCMS fieldtype
    if (strpos($class, 'HaruCMS\\FieldTypes\\') === 0) {
        $fieldtypeName = str_replace('HaruCMS\\FieldTypes\\', '', $class);
        $fieldtypeName = str_replace('\\', '/', $fieldtypeName);
        $file = __DIR__ . "/../fieldtypes/{$fieldtypeName}.php";
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

function getFieldtypeClass($fieldtypeName) {
    $fieldtypeName = ucfirst(strtolower($fieldtypeName));
    $className = "HaruCMS\\FieldTypes\\{$fieldtypeName}\\{$fieldtypeName}";
    
    if (class_exists($className)) {
        return $className;
    }
    
    return null;
}

function renderFieldtype($fieldtypeName, $config) {
    $className = getFieldtypeClass($fieldtypeName);
    
    if (!$className) {
        return [
            'error' => "Fieldtype '{$fieldtypeName}' not found"
        ];
    }
    
    try {
        $fieldtype = new $className($config);
        $html = $fieldtype->render();
        $javascript = $fieldtype->getJavaScript();
        $css = $fieldtype->getCSS();
        
        return [
            'html' => $html,
            'javascript' => $javascript,
            'css' => $css,
            'config' => $fieldtype->getAdminConfig()
        ];
    } catch (Exception $e) {
        return [
            'error' => "Error rendering fieldtype '{$fieldtypeName}': " . $e->getMessage()
        ];
    }
}

function getAvailableFieldtypes() {
    $fieldtypesDir = __DIR__ . '/../fieldtypes';
    $fieldtypes = [];
    
    if (is_dir($fieldtypesDir)) {
        $dirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $fieldtypeName = basename($dir);
            $className = getFieldtypeClass($fieldtypeName);
            
            if ($className) {
                try {
                    // Create a temporary instance to get config
                    $tempConfig = [
                        'name' => 'temp',
                        'label' => ucfirst($fieldtypeName),
                        'required' => false
                    ];
                    $fieldtype = new $className($tempConfig);
                    $config = $fieldtype->getAdminConfig();
                    
                    $fieldtypes[] = [
                        'name' => strtolower($fieldtypeName),
                        'label' => ucfirst($fieldtypeName),
                        'type' => $config['type'] ?? strtolower($fieldtypeName),
                        'config' => $config
                    ];
                } catch (Exception $e) {
                    // Skip fieldtypes that can't be instantiated
                    continue;
                }
            }
        }
    }
    
    return $fieldtypes;
}

// Helper function to parse config parameters from POST data
function parseConfigFromPost() {
    $config = [];
    
    error_log("parseConfigFromPost - Raw POST data: " . json_encode($_POST));
    
    // Check if config is already an array (from JSON)
    if (isset($_POST['config']) && is_array($_POST['config'])) {
        $config = $_POST['config'];
        error_log("parseConfigFromPost - Using config array directly: " . json_encode($config));
        return $config;
    }
    
    // Check if config is a JSON string
    if (isset($_POST['config']) && is_string($_POST['config'])) {
        $decoded = json_decode($_POST['config'], true);
        if ($decoded !== null) {
            $config = $decoded;
            error_log("parseConfigFromPost - Using decoded JSON config: " . json_encode($config));
            return $config;
        }
    }
    
    // Parse individual config[...] parameters
    foreach ($_POST as $key => $value) {
        error_log("parseConfigFromPost - Processing key: '$key' = '$value'");
        
        if (strpos($key, 'config[') === 0) {
            // Extract the config key from config[key] format
            $configKey = substr($key, 7, -1); // Remove 'config[' and ']'
            error_log("parseConfigFromPost - Extracted configKey: '$configKey'");
            
            if (strpos($configKey, '[') !== false) {
                // Handle nested arrays like config[options][0][label]
                $parts = explode('[', $configKey);
                $parts = array_map(function($part) {
                    return rtrim($part, ']');
                }, $parts);
                
                $current = &$config;
                foreach ($parts as $i => $part) {
                    if ($i === count($parts) - 1) {
                        $current[$part] = $value;
                        error_log("parseConfigFromPost - Set nested config[$part] = '$value'");
                    } else {
                        if (!isset($current[$part])) {
                            $current[$part] = [];
                        }
                        $current = &$current[$part];
                    }
                }
            } else {
                $config[$configKey] = $value;
                error_log("parseConfigFromPost - Set config[$configKey] = '$value'");
            }
        }
    }
    
    // Debug logging
    error_log("parseConfigFromPost - Final config: " . json_encode($config));
    
    return $config;
}

// Handle requests
$action = $_GET['action'] ?? '';

// Only execute the switch statement if this file is being called directly (not included)
if (!isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    switch ($action) {
        case 'render':
            error_log("=== ADMIN_FIELDTYPES API CALL ===");
            $fieldtypeName = $_POST['fieldtype'] ?? '';
            $config = parseConfigFromPost();
            
            if (!$fieldtypeName) {
                if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
                    http_response_code(400);
                }
                echo json_encode(['status' => 'error', 'message' => 'Fieldtype name required']);
                exit;
            }
            
            $result = renderFieldtype($fieldtypeName, $config);
            echo json_encode($result);
            break;
            
        case 'list':
            $fieldtypes = getAvailableFieldtypes();
            echo json_encode([
                'status' => 'ok',
                'data' => $fieldtypes
            ]);
            break;
            
        default:
            if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
                http_response_code(400);
            }
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} 