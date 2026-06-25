<?php
/**
 * MCP (Model Context Protocol) Server
 * Allows external AI tools (Claude Desktop, etc.) to connect to Birkly
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once 'storage.php';
require_once 'auth.php';
require_once 'ai_helpers.php';
require_once 'ai_approvals.php';
require_once 'log.php';

// MCP uses stdio transport, but we'll use HTTP for simplicity
// In production, you'd want a proper MCP server implementation

$input = json_decode(file_get_contents('php://input'), true);
$method = $input['method'] ?? $_GET['method'] ?? '';
$params = $input['params'] ?? [];

// MCP authentication via API key
$apiKey = $_SERVER['HTTP_X_MCP_API_KEY'] ?? $_GET['api_key'] ?? '';

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $input['id'] ?? null,
        'error' => ['code' => -32000, 'message' => 'API key required']
    ]);
    exit;
}

// Find AI connection by API key (we'll use a special MCP API key system)
$usersStorage = new Storage('../users');
$aiUser = findAiUserByApiKey($apiKey);

if (!$aiUser) {
    http_response_code(401);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $input['id'] ?? null,
        'error' => ['code' => -32000, 'message' => 'Invalid API key']
    ]);
    exit;
}

// Handle MCP methods
$result = null;
$error = null;

switch ($method) {
    case 'initialize':
        $result = handleInitialize($aiUser);
        break;
        
    case 'tools/list':
        $result = handleToolsList();
        break;
        
    case 'tools/call':
        $result = handleToolCall($params, $aiUser);
        break;
        
    case 'resources/list':
        $result = handleResourcesList();
        break;
        
    case 'resources/read':
        $result = handleResourceRead($params);
        break;
        
    default:
        $error = ['code' => -32601, 'message' => 'Method not found'];
}

// Send response
echo json_encode([
    'jsonrpc' => '2.0',
    'id' => $input['id'] ?? null,
    'result' => $result,
    'error' => $error
]);

// Find AI user by API key
function findAiUserByApiKey($apiKey) {
    // For MCP, we'll generate a special API key when creating the connection
    // For now, we'll check if the API key matches any AI user's encrypted key
    $usersStorage = new Storage('../users');
    $users = $usersStorage->list('');
    
    foreach ($users as $userFile) {
        $user = $usersStorage->read($userFile);
        if ($user && isset($user['type']) && $user['type'] === 'ai') {
            // Check if this is an MCP connection with matching key
            if (isset($user['mcp_api_key']) && $user['mcp_api_key'] === $apiKey) {
                return $user;
            }
        }
    }
    
    return null;
}

// Initialize MCP connection
function handleInitialize($aiUser) {
    return [
        'protocolVersion' => '2024-11-05',
        'capabilities' => [
            'tools' => [],
            'resources' => []
        ],
        'serverInfo' => [
            'name' => 'Birkly MCP Server',
            'version' => '1.0.0'
        ]
    ];
}

// List available tools
function handleToolsList() {
    return [
        'tools' => [
            [
                'name' => 'list_collections',
                'description' => 'List all content collections',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => []
                ]
            ],
            [
                'name' => 'get_collection',
                'description' => 'Get details of a specific collection',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Collection slug']
                    ],
                    'required' => ['slug']
                ]
            ],
            [
                'name' => 'list_entries',
                'description' => 'List entries in a collection',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'collection' => ['type' => 'string', 'description' => 'Collection slug'],
                        'limit' => ['type' => 'number', 'description' => 'Maximum number of entries']
                    ],
                    'required' => ['collection']
                ]
            ],
            [
                'name' => 'create_entry',
                'description' => 'Create a new entry (requires approval)',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'collection' => ['type' => 'string', 'description' => 'Collection slug'],
                        'data' => ['type' => 'object', 'description' => 'Entry data']
                    ],
                    'required' => ['collection', 'data']
                ]
            ],
            [
                'name' => 'update_entry',
                'description' => 'Update an existing entry (requires approval)',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'collection' => ['type' => 'string', 'description' => 'Collection slug'],
                        'id' => ['type' => 'string', 'description' => 'Entry ID'],
                        'data' => ['type' => 'object', 'description' => 'Updated entry data']
                    ],
                    'required' => ['collection', 'id', 'data']
                ]
            ],
            [
                'name' => 'get_system_context',
                'description' => 'Get system context including collections, schemas, and documentation',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => []
                ]
            ]
        ]
    ];
}

// Call a tool
function handleToolCall($params, $aiUser) {
    require_once __DIR__ . '/../core/ai_permissions.php';

    $toolName = $params['name'] ?? '';
    $arguments = $params['arguments'] ?? [];

    $denied = birkly_ai_enforce_action_permission($aiUser, $toolName);
    if ($denied !== null) {
        return [
            'isError' => true,
            'content' => [['type' => 'text', 'text' => $denied['message']]],
        ];
    }
    
    switch ($toolName) {
        case 'list_collections':
            return handleListCollections();
            
        case 'get_collection':
            return handleGetCollection($arguments['slug'] ?? '');
            
        case 'list_entries':
            return handleListEntries($arguments['collection'] ?? '', $arguments['limit'] ?? 10);
            
        case 'create_entry':
            return handleCreateEntry($arguments, $aiUser);
            
        case 'update_entry':
            return handleUpdateEntry($arguments, $aiUser);
            
        case 'get_system_context':
            return handleGetSystemContext($aiUser);
            
        default:
            return [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => 'Unknown tool: ' . $toolName]]
            ];
    }
}

// Tool implementations
function handleListCollections() {
    $contentStorage = new Storage('../content');
    $collectionsDir = '../content/collections';
    $collections = [];
    
    if (is_dir($collectionsDir)) {
        $dirs = array_filter(glob($collectionsDir . '/*'), 'is_dir');
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $collectionFile = $dir . '/collection.json';
            if (file_exists($collectionFile)) {
                $collection = json_decode(file_get_contents($collectionFile), true);
                if ($collection) {
                    $collections[] = [
                        'slug' => $slug,
                        'name' => $collection['name'] ?? $slug
                    ];
                }
            }
        }
    }
    
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode($collections, JSON_PRETTY_PRINT)
        ]]
    ];
}

function handleGetCollection($slug) {
    $collectionFile = '../content/collections/' . $slug . '/collection.json';
    if (!file_exists($collectionFile)) {
        return [
            'isError' => true,
            'content' => [['type' => 'text', 'text' => 'Collection not found']]
        ];
    }
    
    $collection = json_decode(file_get_contents($collectionFile), true);
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode($collection, JSON_PRETTY_PRINT)
        ]]
    ];
}

function handleListEntries($collection, $limit) {
    $entriesDir = '../content/collections/' . $collection . '/entries';
    $entries = [];
    
    if (is_dir($entriesDir)) {
        $files = glob($entriesDir . '/*.json');
        $count = 0;
        foreach ($files as $file) {
            if ($count >= $limit) break;
            $entry = json_decode(file_get_contents($file), true);
            if ($entry) {
                $entries[] = $entry;
                $count++;
            }
        }
    }
    
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode($entries, JSON_PRETTY_PRINT)
        ]]
    ];
}

function handleCreateEntry($arguments, $aiUser) {
    // Create approval request
    $approvalStorage = new Storage('../data/ai_approvals');
    $approvalId = 'approval_' . bin2hex(random_bytes(8));
    $approval = [
        'id' => $approvalId,
        'ai_user_id' => $aiUser['id'],
        'action_type' => 'create_entry',
        'action_data' => $arguments,
        'description' => 'Create entry in ' . ($arguments['collection'] ?? 'unknown') . ' collection',
        'status' => 'pending',
        'created_at' => date('c'),
        'source' => 'mcp'
    ];
    
    $approvalStorage->write($approvalId . '.json', $approval);
    
    log_security_event('mcp_action_created', [
        'approval_id' => $approvalId,
        'ai_user_id' => $aiUser['id'],
        'action' => 'create_entry'
    ]);
    
    return [
        'content' => [[
            'type' => 'text',
            'text' => 'Entry creation requested. Approval ID: ' . $approvalId . '. Please approve in the admin panel.'
        ]]
    ];
}

function handleUpdateEntry($arguments, $aiUser) {
    // Create approval request
    $approvalStorage = new Storage('../data/ai_approvals');
    $approvalId = 'approval_' . bin2hex(random_bytes(8));
    $approval = [
        'id' => $approvalId,
        'ai_user_id' => $aiUser['id'],
        'action_type' => 'update_entry',
        'action_data' => $arguments,
        'description' => 'Update entry ' . ($arguments['id'] ?? 'unknown') . ' in ' . ($arguments['collection'] ?? 'unknown') . ' collection',
        'status' => 'pending',
        'created_at' => date('c'),
        'source' => 'mcp'
    ];
    
    $approvalStorage->write($approvalId . '.json', $approval);
    
    log_security_event('mcp_action_created', [
        'approval_id' => $approvalId,
        'ai_user_id' => $aiUser['id'],
        'action' => 'update_entry'
    ]);
    
    return [
        'content' => [[
            'type' => 'text',
            'text' => 'Entry update requested. Approval ID: ' . $approvalId . '. Please approve in the admin panel.'
        ]]
    ];
}

function handleGetSystemContext($aiUser) {
    $context = generateSystemContext($aiUser);
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode($context, JSON_PRETTY_PRINT)
        ]]
    ];
}

// List available resources
function handleResourcesList() {
    return [
        'resources' => [
            [
                'uri' => 'birkly://collections',
                'name' => 'All Collections',
                'description' => 'List of all content collections',
                'mimeType' => 'application/json'
            ],
            [
                'uri' => 'birkly://documentation',
                'name' => 'Documentation',
                'description' => 'System documentation',
                'mimeType' => 'text/markdown'
            ]
        ]
    ];
}

// Read a resource
function handleResourceRead($params) {
    $uri = $params['uri'] ?? '';
    
    if ($uri === 'birkly://collections') {
        $result = handleListCollections();
        return [
            'contents' => $result['content']
        ];
    }
    
    if ($uri === 'birkly://documentation') {
        $docsDir = __DIR__ . '/../docs';
        $docs = [];
        if (is_dir($docsDir)) {
            $files = glob($docsDir . '/*.md');
            foreach ($files as $file) {
                $docs[] = [
                    'file' => basename($file),
                    'content' => file_get_contents($file)
                ];
            }
        }
        return [
            'contents' => [[
                'uri' => $uri,
                'mimeType' => 'text/markdown',
                'text' => json_encode($docs, JSON_PRETTY_PRINT)
            ]]
        ];
    }
    
    return [
        'contents' => [[
            'uri' => $uri,
            'mimeType' => 'text/plain',
            'text' => 'Resource not found'
        ]]
    ];
}

