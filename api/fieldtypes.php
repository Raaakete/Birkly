<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
header('Content-Type: application/json');

// SECURITY FIX: Replace wildcard CORS with origin whitelist
require_once __DIR__ . '/../core/cors.php';
set_cors_headers('admin'); // Fieldtypes API is admin-only

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../fieldtypes/FieldRegistry.php';

$fieldtypesDirect = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__);
if ($fieldtypesDirect && empty($GLOBALS['__HARU_INTERNAL_CALL'])) {
    require_once __DIR__ . '/session.php';
    require_once __DIR__ . '/auth.php';
    requireAuth();
}

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

/**
 * fieldtype.js for one type. The name is a single segment (Text, oEmbed, URL).
 * ".." and slashes cannot leave fieldtypes/.
 */
function birkly_fieldtype_js_path(string $fieldtypeName): ?string
{
    if ($fieldtypeName === '' || preg_match('/^[A-Za-z0-9_-]+$/', $fieldtypeName) !== 1) {
        return null;
    }
    $fieldtypesDir = __DIR__ . '/../fieldtypes';
    $root = realpath($fieldtypesDir);
    if ($root === false) {
        return null;
    }
    $jsFile = $root . DIRECTORY_SEPARATOR . ucfirst($fieldtypeName) . DIRECTORY_SEPARATOR . 'fieldtype.js';
    if (!is_file($jsFile)) {
        return null;
    }
    $real = realpath($jsFile);
    if ($real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $real;
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
    $jsFile = birkly_fieldtype_js_path((string) $fieldtypeName);
    if ($jsFile === null) {
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