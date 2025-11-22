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
    // Normalize fieldtype name (handle various formats)
    $normalized = strtolower(trim($fieldtypeName));
    
    // Handle special aliases (these are just convenience mappings, not hardcoded fieldtypes)
    $aliases = [
        'tag' => 'tags', // Tag fieldtype uses Tags class
        'dynamic-items' => 'dynamicitems',
        'content-editor' => 'contenteditor', // ContentEditor fieldtype directory name
    ];
    
    if (isset($aliases[$normalized])) {
        $normalized = $aliases[$normalized];
    }
    
    // Auto-discover fieldtype by scanning directory
    $fieldtypesDir = __DIR__ . '/../fieldtypes';
    
    if (!is_dir($fieldtypesDir)) {
        error_log("Fieldtypes directory not found: {$fieldtypesDir}");
        return null;
    }
    
    // Try to find matching directory
    $dirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
    $matchedDir = null;
    $matchedName = null;
    
    foreach ($dirs as $dir) {
        $dirName = basename($dir);
        $dirNameLower = strtolower($dirName);
        
        // Exact match (case-insensitive)
        if ($dirNameLower === $normalized) {
            $matchedDir = $dir;
            $matchedName = $dirName;
            break;
        }
        
        // Try camelCase variations (e.g., "multiselect" matches "MultiSelect")
        $camelCase = ucfirst($normalized);
        if (strtolower($dirName) === strtolower($camelCase)) {
            $matchedDir = $dir;
            $matchedName = $dirName;
            break;
        }
    }
    
    if (!$matchedDir || !$matchedName) {
        error_log("Fieldtype directory not found for: {$fieldtypeName}");
        return null;
    }
    
    // Construct class name from directory name
    // Directory name should match class name (e.g., "Text" directory => "Text" class)
    $className = "HaruCMS\\FieldTypes\\{$matchedName}\\{$matchedName}";
    
    // Try loading the file
    $fieldtypeFile = "{$matchedDir}/{$matchedName}.php";
    
    if (file_exists($fieldtypeFile)) {
        require_once $fieldtypeFile;
    }
    
    // Check if class exists
    if (class_exists($className)) {
        return $className;
    }
    
    error_log("Fieldtype class not found: {$fieldtypeName} (tried {$className} from {$matchedDir})");
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
                    $category = method_exists($fieldtype, 'getCategory') ? $fieldtype->getCategory() : 'Misc';
                    
                    $fieldtypes[] = [
                        'name' => strtolower($fieldtypeName),
                        'label' => ucfirst($fieldtypeName),
                        'type' => $config['type'] ?? strtolower($fieldtypeName),
                        'category' => $category,
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
    
    // Check if config is already an array (from JSON)
    if (isset($_POST['config']) && is_array($_POST['config'])) {
        $config = $_POST['config'];
        return $config;
    }
    
    // Check if config is a JSON string
    if (isset($_POST['config']) && is_string($_POST['config'])) {
        $decoded = json_decode($_POST['config'], true);
        if ($decoded !== null) {
            $config = $decoded;
            return $config;
        }
    }
    
    // Parse individual config[...] parameters
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'config[') === 0) {
            // Extract the config key from config[key] format
            $configKey = substr($key, 7, -1); // Remove 'config[' and ']'
            
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
                    } else {
                        if (!isset($current[$part])) {
                            $current[$part] = [];
                        }
                        $current = &$current[$part];
                    }
                }
            } else {
                $config[$configKey] = $value;
            }
        }
    }
    
    return $config;
}

// Handle requests
// CRITICAL: Only execute switch if NOT being included by another file
if (isset($GLOBALS['__HARU_INTERNAL_CALL']) && $GLOBALS['__HARU_INTERNAL_CALL'] === true) {
    // Being included - don't execute switch statement, just provide functions
    // Do nothing and let execution continue
} else {
    // File is being called directly (not included) - handle API requests
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Execute the switch statement for direct calls
    switch ($action) {
        case 'render':
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
            
        case 'get_config_ui':
            $fieldtypeName = $_GET['fieldtype'] ?? $_POST['fieldtype'] ?? '';
            $fieldIndex = $_GET['fieldIndex'] ?? $_POST['fieldIndex'] ?? '0';
            $existingDataJson = $_GET['existingData'] ?? $_POST['existingData'] ?? '{}';
            $existingData = json_decode($existingDataJson, true) ?? [];
            
            if (!$fieldtypeName) {
                if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
                    http_response_code(400);
                }
                echo json_encode(['status' => 'error', 'message' => 'Fieldtype name required']);
                exit;
            }
            
            $className = getFieldtypeClass($fieldtypeName);
            if (!$className) {
                if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
                    http_response_code(404);
                }
                echo json_encode(['status' => 'error', 'message' => "Fieldtype '{$fieldtypeName}' not found"]);
                exit;
            }
            
            try {
                // Create a temporary instance to get config UI
                $tempConfig = [
                    'name' => 'temp',
                    'label' => ucfirst($fieldtypeName),
                    'required' => false
                ];
                $fieldtype = new $className($tempConfig);
                
                // Get the configuration UI
                $configUI = method_exists($fieldtype, 'getConfigUI') 
                    ? $fieldtype->getConfigUI($fieldIndex, $existingData)
                    : '';
                
                echo json_encode([
                    'status' => 'ok',
                    'html' => $configUI
                ]);
            } catch (Exception $e) {
                if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
                    http_response_code(500);
                }
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error getting config UI: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'render_for_dynamicitems':
            // Render a fieldtype instance for DynamicItems with full configuration
            $fieldtypeName = $_POST['fieldtype'] ?? '';
            $blockName = $_POST['blockName'] ?? '';
            $blockLabel = $_POST['blockLabel'] ?? '';
            $blockRequired = isset($_POST['blockRequired']) && $_POST['blockRequired'] === 'true';
            $blockValue = $_POST['blockValue'] ?? null;
            $blockSettings = isset($_POST['blockSettings']) ? json_decode($_POST['blockSettings'], true) : [];
            
            if (!$fieldtypeName || !$blockName) {
                if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
                    http_response_code(400);
                }
                echo json_encode(['status' => 'error', 'message' => 'Fieldtype name and block name required']);
                exit;
            }
            
            $className = getFieldtypeClass($fieldtypeName);
            if (!$className) {
                if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
                    http_response_code(404);
                }
                echo json_encode(['status' => 'error', 'message' => "Fieldtype '{$fieldtypeName}' not found"]);
                exit;
            }
            
            try {
                // Build full config for the fieldtype
                $config = [
                    'name' => $blockName,
                    'label' => $blockLabel ?: ucfirst($blockName),
                    'required' => $blockRequired,
                    'value' => $blockValue
                ];
                
                // Merge any additional settings
                if (is_array($blockSettings)) {
                    $config = array_merge($config, $blockSettings);
                }
                
                $fieldtype = new $className($config);
                $html = $fieldtype->render();
                $javascript = $fieldtype->getJavaScript();
                $css = $fieldtype->getCSS();
                
                // Add data attributes to help with identification
                $html = preg_replace_callback(
                    '/<(input|textarea|select|div|span)([^>]*)>/i',
                    function($matches) use ($fieldtypeName, $blockName) {
                        $tag = $matches[1];
                        $attrs = $matches[2];
                        if (strpos($attrs, 'data-fieldtype') === false) {
                            $attrs .= ' data-fieldtype="' . htmlspecialchars($fieldtypeName) . '"';
                        }
                        if (strpos($attrs, 'data-block-name') === false) {
                            $attrs .= ' data-block-name="' . htmlspecialchars($blockName) . '"';
                        }
                        return "<{$tag}{$attrs}>";
                    },
                    $html,
                    1
                );
                
                echo json_encode([
                    'status' => 'ok',
                    'html' => $html,
                    'javascript' => $javascript,
                    'css' => $css
                ]);
            } catch (Exception $e) {
                if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
                    http_response_code(500);
                }
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error rendering fieldtype: ' . $e->getMessage()
                ]);
            }
            break;
            
        default:
            if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            }
            break;
    }
    exit; // Exit after handling direct API call
} 