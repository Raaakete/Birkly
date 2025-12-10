<?php
/**
 * AI Integration Helpers
 * Functions for managing AI connections, encryption, and context generation
 */

require_once __DIR__ . '/storage.php';

/**
 * Simple encryption/decryption for API keys
 * Uses a secret key stored in settings
 */
function getEncryptionKey() {
    $settingsStorage = new Storage('../settings');
    $security = $settingsStorage->read('security.json') ?? [];
    
    if (!isset($security['encryption_key'])) {
        // Generate a new key if it doesn't exist
        $key = bin2hex(random_bytes(32));
        $security['encryption_key'] = $key;
        $settingsStorage->write('security.json', $security);
    }
    
    return $security['encryption_key'];
}

function encryptApiKey($apiKey) {
    $key = getEncryptionKey();
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($apiKey, 'AES-256-CBC', hex2bin($key), 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptApiKey($encrypted) {
    $key = getEncryptionKey();
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', hex2bin($key), 0, $iv);
}

/**
 * Check if a user is an AI user
 */
function isAiUser($user) {
    return isset($user['type']) && $user['type'] === 'ai';
}

/**
 * Get supported AI providers
 */
function getSupportedAiProviders() {
    return [
        'openai' => [
            'name' => 'OpenAI',
            'models' => ['gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo', 'gpt-4o', 'gpt-4o-mini'],
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'type' => 'openai'
        ],
        'anthropic' => [
            'name' => 'Anthropic (Claude)',
            'models' => ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku', 'claude-3-5-sonnet', 'claude-3-5-haiku'],
            'api_url' => 'https://api.anthropic.com/v1/messages',
            'type' => 'anthropic'
        ],
        'google' => [
            'name' => 'Google (Gemini)',
            'models' => ['gemini-pro', 'gemini-pro-vision', 'gemini-1.5-pro', 'gemini-1.5-flash'],
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'type' => 'google'
        ],
        'openrouter' => [
            'name' => 'OpenRouter',
            'models' => ['openai/gpt-4', 'anthropic/claude-3-opus', 'google/gemini-pro', 'meta-llama/llama-3-70b-instruct', 'mistralai/mistral-large', 'perplexity/llama-3-sonar-large-32k-online'],
            'api_url' => 'https://openrouter.ai/api/v1/chat/completions',
            'type' => 'openai', // Uses OpenAI-compatible API
            'custom_models' => true
        ],
        'ollama' => [
            'name' => 'Ollama (Local)',
            'models' => ['llama3', 'mistral', 'codellama', 'phi', 'gemma'],
            'api_url' => 'http://localhost:11434/api/chat',
            'type' => 'ollama',
            'custom_models' => true
        ],
        'custom' => [
            'name' => 'Custom Provider',
            'models' => [],
            'api_url' => '',
            'type' => 'custom',
            'custom_models' => true,
            'requires_url' => true
        ],
        'mcp' => [
            'name' => 'MCP (Model Context Protocol)',
            'models' => ['any'],
            'api_url' => 'mcp://',
            'type' => 'mcp'
        ]
    ];
}

/**
 * Generate system context for AI
 * Includes collections, schemas, permissions, and documentation
 */
function generateSystemContext($aiUser) {
    $context = [
        'system_info' => [
            'cms_name' => 'HaruCMS',
            'version' => '1.0',
            'user_role' => $aiUser['role'] ?? 'viewer',
            'user_permissions' => getPermissionsForRole($aiUser['role'] ?? 'viewer')
        ],
        'collections' => [],
        'documentation' => []
    ];
    
    // Load collections
    $contentStorage = new Storage('../content');
    $collectionsDir = '../content/collections';
    if (is_dir($collectionsDir)) {
        $collections = array_filter(glob($collectionsDir . '/*'), 'is_dir');
        foreach ($collections as $collectionPath) {
            $collectionSlug = basename($collectionPath);
            $collectionFile = $collectionPath . '/collection.json';
            $schemaFile = $collectionPath . '/schema.json';
            if (file_exists($collectionFile)) {
                $collection = json_decode(file_get_contents($collectionFile), true);
                if ($collection) {
                    // Load schema fields if available
                    $schemaFields = [];
                    if (file_exists($schemaFile)) {
                        $schema = json_decode(file_get_contents($schemaFile), true);
                        if ($schema && isset($schema['fields'])) {
                            $schemaFields = $schema['fields'];
                        }
                    }
                    
                    $context['collections'][] = [
                        'slug' => $collectionSlug,
                        'name' => $collection['name'] ?? $collectionSlug,
                        'fields' => $schemaFields, // Use schema fields, not collection fields
                        'entry_method' => $collection['entry_method'] ?? 'admin_only'
                    ];
                }
            }
        }
    }
    
    // Load documentation - include both .md and .html files
    $docsDir = __DIR__ . '/../docs';
    $adminDocsDir = __DIR__ . '/../admin/docs';
    
    // Load from main docs directory
    if (is_dir($docsDir)) {
        $docFiles = array_merge(
            glob($docsDir . '/*.md') ?: [],
            glob($docsDir . '/*.html') ?: []
        );
        foreach ($docFiles as $docFile) {
            $content = file_get_contents($docFile);
            // Extract text content from HTML files (strip tags but keep structure)
            if (pathinfo($docFile, PATHINFO_EXTENSION) === 'html') {
                // Remove script and style tags
                $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi', '', $content);
                $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi', '', $content);
                // Convert HTML to readable text while preserving structure
                $content = strip_tags($content);
                $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $context['documentation'][] = [
                'file' => basename($docFile),
                'content' => $content
            ];
        }
    }
    
    // Load from admin/docs directory (PHP files that contain documentation)
    if (is_dir($adminDocsDir)) {
        $adminDocFiles = glob($adminDocsDir . '/*.php');
        foreach ($adminDocFiles as $docFile) {
            $content = file_get_contents($docFile);
            // Extract content from PHP files (they contain HTML documentation)
            // Remove PHP tags and extract the HTML content
            $content = preg_replace('/<\?php\s*/', '', $content);
            $content = preg_replace('/\?>/', '', $content);
            // Remove script and style tags
            $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi', '', $content);
            $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi', '', $content);
            // Convert HTML to readable text
            $content = strip_tags($content);
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            $context['documentation'][] = [
                'file' => 'admin/' . basename($docFile),
                'content' => $content
            ];
        }
    }
    
    return $context;
}

/**
 * Get role by ID or name from settings/roles.json
 */
function getRoleByIdOrName($roleIdOrName) {
    $storage = new Storage('../settings');
    $roles = $storage->read('roles.json') ?: [];
    
    // First try to find by ID
    foreach ($roles as $role) {
        if (isset($role['id']) && $role['id'] === $roleIdOrName) {
            return $role;
        }
    }
    
    // Then try to find by name (for backward compatibility)
    foreach ($roles as $role) {
        if (isset($role['name']) && $role['name'] === $roleIdOrName) {
            return $role;
        }
    }
    
    return null;
}

/**
 * Convert resource-based permissions to flat permission list
 * Roles can store permissions in two formats:
 * 1. Resource-based: { "collections": ["view", "create"], "users": ["view"] }
 * 2. Flat array: ["read", "write", "manage_content"]
 * 
 * This function handles both formats and converts resource-based to flat.
 * 
 * Action mapping:
 * - "view" on content/collections → "read"
 * - "create", "update" on content/collections → "write", "manage_content"
 * - "delete" on content/collections → "delete", "manage_content"
 * - "view", "create", "update", "delete" on users → "manage_users"
 * - "view", "create", "update" on media → "manage_media", "upload" (if create)
 * - "view", "create", "update", "delete" on plugins → "manage_plugins"
 * - "view", "update" on settings → "manage_settings"
 */
function convertRolePermissionsToFlatList($rolePermissions) {
    if (!is_array($rolePermissions)) {
        return [];
    }
    
    // If it's already a flat array (numeric keys or first element is a string), return as-is
    if (empty($rolePermissions) || (isset($rolePermissions[0]) && is_string($rolePermissions[0]))) {
        return $rolePermissions;
    }
    
    // Otherwise, it's resource-based format: { "collections": ["view"], "users": ["create"], ... }
    $flatPermissions = [];
    
    foreach ($rolePermissions as $resource => $actions) {
        if (!is_array($actions)) {
            continue;
        }
        
        // Check for wildcard resource - if resource is "*", it means all resources
        if ($resource === '*') {
            // If actions include "*" or "all", return all permissions
            if (in_array('*', $actions) || in_array('all', $actions)) {
                return ['*']; // All permissions
            }
            // If resource is "*" and has multiple admin actions (view, edit, create, delete), it's full admin access
            $hasView = in_array('view', $actions);
            $hasEdit = in_array('edit', $actions);
            $hasCreate = in_array('create', $actions);
            $hasDelete = in_array('delete', $actions);
            // If it has 3+ of these actions on all resources, it's admin
            $adminActionCount = ($hasView ? 1 : 0) + ($hasEdit ? 1 : 0) + ($hasCreate ? 1 : 0) + ($hasDelete ? 1 : 0);
            if ($adminActionCount >= 3) {
                return ['*']; // Full admin access
            }
        }
        
        // Map resource actions to flat permissions
        $resource = strtolower($resource);
        
        // Collections/Content
        if ($resource === 'collections' || $resource === 'content') {
            if (in_array('view', $actions) || in_array('read', $actions)) {
                $flatPermissions[] = 'read';
            }
            if (in_array('create', $actions) || in_array('update', $actions) || in_array('write', $actions)) {
                $flatPermissions[] = 'write';
                $flatPermissions[] = 'manage_content';
            }
            if (in_array('delete', $actions)) {
                $flatPermissions[] = 'delete';
                $flatPermissions[] = 'manage_content';
            }
        }
        // Users
        elseif ($resource === 'users') {
            if (in_array('view', $actions) || in_array('read', $actions) || in_array('create', $actions) || 
                in_array('update', $actions) || in_array('delete', $actions) || in_array('write', $actions)) {
                $flatPermissions[] = 'manage_users';
            }
        }
        // Media
        elseif ($resource === 'media') {
            if (in_array('view', $actions) || in_array('read', $actions) || in_array('create', $actions) || 
                in_array('update', $actions) || in_array('write', $actions)) {
                $flatPermissions[] = 'manage_media';
                if (in_array('create', $actions) || in_array('upload', $actions)) {
                    $flatPermissions[] = 'upload';
                }
            }
        }
        // Plugins
        elseif ($resource === 'plugins') {
            if (in_array('view', $actions) || in_array('read', $actions) || in_array('create', $actions) || 
                in_array('update', $actions) || in_array('delete', $actions) || in_array('write', $actions)) {
                $flatPermissions[] = 'manage_plugins';
            }
        }
        // Settings
        elseif ($resource === 'settings') {
            if (in_array('view', $actions) || in_array('read', $actions) || in_array('update', $actions) || 
                in_array('write', $actions)) {
                $flatPermissions[] = 'manage_settings';
            }
        }
        
        // Also check if any action directly matches a permission name (for backward compatibility)
        foreach ($actions as $action) {
            $action = strtolower($action);
            if (in_array($action, ['read', 'write', 'delete', 'manage_content', 'manage_users', 
                                   'manage_media', 'manage_plugins', 'manage_settings', 'upload', '*', 'all'])) {
                $flatPermissions[] = $action;
            }
        }
    }
    
    // Remove duplicates
    $flatPermissions = array_unique($flatPermissions);
    
    // If we have wildcard, return just that
    if (in_array('*', $flatPermissions) || in_array('all', $flatPermissions)) {
        return ['*'];
    }
    
    return array_values($flatPermissions);
}

/**
 * Get permissions for a role (by ID or name)
 * Returns a flat list of permissions like ['read', 'write', 'manage_content', '*']
 */
function getPermissionsForRole($roleIdOrName) {
    if (empty($roleIdOrName)) {
        return [];
    }
    
    // First, try to get role from settings/roles.json
    $role = getRoleByIdOrName($roleIdOrName);
    
    if ($role && isset($role['permissions'])) {
        // Convert resource-based permissions to flat list
        $permissions = convertRolePermissionsToFlatList($role['permissions']);
        error_log("AI Permissions: Role '{$roleIdOrName}' found in roles.json, permissions: " . json_encode($permissions));
        return $permissions;
    }
    
    // Fallback to legacy hardcoded roles for backward compatibility
    $legacyRoles = [
        'admin' => ['*'], // All permissions
        'editor' => [
            'manage_content', 'manage_media', 'manage_plugins', 'read', 'write', 'delete', 'upload'
        ],
        'author' => [
            'manage_content', 'manage_media', 'read', 'write', 'upload'
        ],
        'viewer' => ['read']
    ];
    
    $permissions = $legacyRoles[$roleIdOrName] ?? [];
    error_log("AI Permissions: Role '{$roleIdOrName}' using legacy fallback, permissions: " . json_encode($permissions));
    return $permissions;
}

