<?php
// DEBUG: Show all errors for troubleshooting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Fatal error: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
    exit;
});
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../fieldtypes/FieldRegistry.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_all':
        echo json_encode(getAllFieldtypes());
        break;
    case 'get_config':
        $fieldtype = $_GET['fieldtype'] ?? '';
        if (empty($fieldtype)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Fieldtype parameter required']);
            break;
        }
        echo json_encode(getFieldtypeConfig($fieldtype));
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

function getAllFieldtypes() {
    $fieldtypesDir = __DIR__ . '/../fieldtypes';
    $fieldtypes = [];
    
    if (!is_dir($fieldtypesDir)) {
        return ['status' => 'error', 'message' => 'Fieldtypes directory not found'];
    }
    
    $dirs = array_filter(scandir($fieldtypesDir), function($item) use ($fieldtypesDir) {
        return is_dir($fieldtypesDir . '/' . $item) && !in_array($item, ['.', '..']);
    });
    
    foreach ($dirs as $dir) {
        $fieldtypePath = $fieldtypesDir . '/' . $dir;
        $phpFile = $fieldtypePath . '/' . $dir . '.php';
        $jsFile = $fieldtypePath . '/fieldtype.js';
        
        if (file_exists($phpFile) && file_exists($jsFile)) {
            // Get basic info from JavaScript file
            $jsContent = file_get_contents($jsFile);
            
            // Extract fieldtype info from JS - use a more flexible approach
            if (preg_match('/window\.HaruFieldtypes\[\'([^\']+)\'\]\s*=\s*\{/s', $jsContent, $nameMatches)) {
                $fieldtypeName = $nameMatches[1];
                
                // Extract label and category
                preg_match('/label:\s*[\'"]([^\'"]+)[\'"]/', $jsContent, $labelMatch);
                preg_match('/category:\s*[\'"]([^\'"]+)[\'"]/', $jsContent, $categoryMatch);
                
                // Check for renderConfig function
                $hasConfig = strpos($jsContent, 'renderConfig') !== false;
                
                $fieldtypes[] = [
                    'name' => $fieldtypeName,
                    'label' => $labelMatch[1] ?? ucfirst($fieldtypeName),
                    'category' => $categoryMatch[1] ?? 'content',
                    'directory' => $dir,
                    'has_config' => $hasConfig
                ];
            }
        }
    }
    
    return [
        'status' => 'ok',
        'data' => [
            'fieldtypes' => $fieldtypes
        ]
    ];
}

function getFieldtypeConfig($fieldtypeName) {
    $fieldtypesDir = __DIR__ . '/../fieldtypes';
    $fieldtypeDir = $fieldtypesDir . '/' . ucfirst($fieldtypeName);
    $jsFile = $fieldtypeDir . '/fieldtype.js';
    
    if (!file_exists($jsFile)) {
        return ['status' => 'error', 'message' => 'Fieldtype not found'];
    }
    
    $jsContent = file_get_contents($jsFile);
    
    // Extract the fieldtype definition
    if (!preg_match('/window\.HaruFieldtypes\[\'([^\']+)\'\]\s*=\s*\{/s', $jsContent, $nameMatches)) {
        return ['status' => 'error', 'message' => 'Invalid fieldtype definition'];
    }
    
    $fieldtypeName = $nameMatches[1];
    
    // Extract properties
    preg_match('/label:\s*[\'"]([^\'"]+)[\'"]/', $jsContent, $labelMatch);
    preg_match('/category:\s*[\'"]([^\'"]+)[\'"]/', $jsContent, $categoryMatch);
    
    $hasConfig = strpos($jsContent, 'renderConfig') !== false;
    
    return [
        'status' => 'ok',
        'data' => [
            'name' => $fieldtypeName,
            'label' => $labelMatch[1] ?? ucfirst($fieldtypeName),
            'category' => $categoryMatch[1] ?? 'content',
            'has_config' => $hasConfig,
            'js_file' => "/fieldtypes/$fieldtypeName/fieldtype.js"
        ]
    ];
} 