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

/**
 * Ensure settings/security.json contains encryption_key (fresh installs).
 *
 * @return bool True when a usable key is present after this call
 */
function ensureEncryptionKeyBootstrapped(): bool
{
    $key = getEncryptionKey();
    return is_string($key) && $key !== '';
}

function getEncryptionKey() {
    $settingsStorage = new Storage('../settings');
    $security = $settingsStorage->read('security.json') ?? [];
    
    if (!isset($security['encryption_key']) || $security['encryption_key'] === '') {
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
        ]
    ];
}

/**
 * Load curated AI context pack (not full docs dump).
 */
function loadBirklyContextPack(): string
{
    $packFile = __DIR__ . '/../docs/ai/birkly-context-pack.md';
    if (!is_file($packFile)) {
        return '';
    }
    return trim((string) file_get_contents($packFile));
}

/**
 * Strip sensitive fields from user records returned to AI.
 */
function birkly_scrub_user_for_ai(array $user): array
{
    $sensitive = [
        'password', 'api_key_encrypted', 'mcp_api_key',
        'password_hash', 'reset_token', 'verification_token',
    ];
    foreach ($sensitive as $field) {
        unset($user[$field]);
    }
    return $user;
}

/**
 * Resolve Ollama host base URL from chat endpoint URL.
 */
function resolveOllamaBaseUrl(string $apiUrl = ''): string
{
    $url = $apiUrl !== '' ? $apiUrl : 'http://localhost:11434/api/chat';
    $parsed = parse_url($url);
    $scheme = $parsed['scheme'] ?? 'http';
    $host = $parsed['host'] ?? 'localhost';
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

    return $scheme . '://' . $host . $port;
}

/**
 * Ping Ollama /api/tags to verify the server is reachable from Birkly.
 *
 * @return array{valid: bool, message: string, provider_error?: string}
 */
function validateOllamaEndpoint(string $apiUrl = ''): array
{
    if (!function_exists('curl_init')) {
        return ['valid' => true, 'message' => 'Skipped Ollama check (curl unavailable)'];
    }

    $base = resolveOllamaBaseUrl($apiUrl);
    $tagsUrl = rtrim($base, '/') . '/api/tags';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $tagsUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPGET => true,
    ]);
    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return [
            'valid' => false,
            'message' => 'Cannot reach Ollama at ' . $base,
            'provider_error' => $curlError,
        ];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['valid' => true, 'message' => 'Ollama reachable (HTTP ' . $httpCode . ')'];
    }

    return [
        'valid' => false,
        'message' => 'Ollama returned HTTP ' . $httpCode,
        'provider_error' => 'GET ' . $tagsUrl . ' → HTTP ' . $httpCode,
    ];
}

/**
 * Validate outbound provider credentials / reachability before save.
 *
 * @return array{valid: bool, message: string, provider_error?: string}
 */
function validateProviderConnection(string $provider, string $apiKey, string $apiUrl = ''): array
{
    if ($provider === 'mcp') {
        return ['valid' => true, 'message' => 'MCP endpoints are managed separately'];
    }

    if ($provider === 'ollama') {
        return validateOllamaEndpoint($apiUrl);
    }

    return validateApiKeyForProvider($provider, $apiKey, $apiUrl);
}

/**
 * Validate provider API key with a lightweight request (best-effort).
 *
 * @return array{valid: bool, message: string, provider_error?: string}
 */
function validateApiKeyForProvider(string $provider, string $apiKey, string $apiUrl = ''): array
{
    if ($provider === 'mcp') {
        return ['valid' => true, 'message' => 'No key validation required'];
    }

    if ($apiKey === '') {
        return ['valid' => false, 'message' => 'API key is required'];
    }

    if (!function_exists('curl_init')) {
        return ['valid' => true, 'message' => 'Skipped validation (curl unavailable)'];
    }

    $providers = getSupportedAiProviders();
    if (!isset($providers[$provider])) {
        return ['valid' => false, 'message' => 'Unsupported provider'];
    }

    $url = $apiUrl ?: ($providers[$provider]['api_url'] ?? '');
    if ($url === '') {
        return ['valid' => true, 'message' => 'Skipped validation (no URL)'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => match ($provider) {
            'anthropic' => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            'google' => ['Content-Type: application/json'],
            default => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        },
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => match ($provider) {
            'anthropic' => json_encode([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 1,
                'messages' => [['role' => 'user', 'content' => 'ping']],
            ]),
            'google' => json_encode(['contents' => [['parts' => [['text' => 'ping']]]]]),
            default => json_encode([
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 1,
                'messages' => [['role' => 'user', 'content' => 'ping']],
            ]),
        },
    ]);

    if ($provider === 'google') {
        curl_setopt($ch, CURLOPT_URL, rtrim($url, '/') . '/gemini-1.5-flash:generateContent?key=' . urlencode($apiKey));
    }

    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return [
            'valid' => false,
            'message' => 'Connection failed: ' . $curlError,
            'provider_error' => $curlError,
        ];
    }

    if ($httpCode === 401 || $httpCode === 403) {
        return [
            'valid' => false,
            'message' => 'Invalid API key (HTTP ' . $httpCode . ')',
            'provider_error' => 'HTTP ' . $httpCode,
        ];
    }

    if ($httpCode >= 200 && $httpCode < 500) {
        return ['valid' => true, 'message' => 'Key accepted (HTTP ' . $httpCode . ')'];
    }

    return [
        'valid' => false,
        'message' => 'Provider returned HTTP ' . $httpCode,
        'provider_error' => 'HTTP ' . $httpCode,
    ];
}

/**
 * Generate system context for AI
 * Includes collections, schemas, permissions, and curated documentation
 */
function generateSystemContext($aiUser) {
    $context = [
        'system_info' => [
            'cms_name' => 'Birkly',
            'version' => '1.0',
            'user_role' => $aiUser['role'] ?? 'viewer',
            'user_permissions' => getPermissionsForRole($aiUser['role'] ?? 'viewer')
        ],
        'collections' => [],
        'context_pack' => loadBirklyContextPack()
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
        
        // Map resource actions to flat permissions used across the API layer
        $resource = strtolower($resource);
        
        // Collections/Content  → content & entry permissions
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
        // Users  → user-management permissions
        elseif ($resource === 'users') {
            if (in_array('view', $actions) || in_array('read', $actions)) {
                $flatPermissions[] = 'list_users';
            }
            if (in_array('create', $actions)) {
                $flatPermissions[] = 'create_user';
                $flatPermissions[] = 'list_users';
                $flatPermissions[] = 'manage_users';
            }
            if (in_array('update', $actions) || in_array('write', $actions)) {
                $flatPermissions[] = 'update_user';
                $flatPermissions[] = 'list_users';
                $flatPermissions[] = 'manage_users';
            }
            if (in_array('delete', $actions)) {
                $flatPermissions[] = 'delete_user';
                $flatPermissions[] = 'list_users';
                $flatPermissions[] = 'manage_users';
            }
        }
        // Media  → media permissions
        elseif ($resource === 'media') {
            if (in_array('view', $actions) || in_array('read', $actions)) {
                $flatPermissions[] = 'read_media';
            }
            if (in_array('create', $actions) || in_array('upload', $actions)) {
                $flatPermissions[] = 'upload';
                $flatPermissions[] = 'manage_media';
            }
            if (in_array('update', $actions) || in_array('write', $actions)) {
                $flatPermissions[] = 'manage_media';
            }
            if (in_array('delete', $actions)) {
                $flatPermissions[] = 'manage_media';
            }
        }
        // Plugins  → plugin-management permissions
        elseif ($resource === 'plugins') {
            if (in_array('view', $actions) || in_array('read', $actions) || in_array('create', $actions) || 
                in_array('update', $actions) || in_array('delete', $actions) || in_array('write', $actions)) {
                $flatPermissions[] = 'manage_plugins';
            }
        }
        // Settings  → settings-management permissions
        elseif ($resource === 'settings') {
            if (in_array('view', $actions) || in_array('read', $actions)) {
                $flatPermissions[] = 'read_settings';
            }
            if (in_array('update', $actions) || in_array('write', $actions)) {
                $flatPermissions[] = 'manage_settings';
            }
        }
        
        // Also check if any action directly matches a permission name (for backward compatibility)
        foreach ($actions as $action) {
            $action = strtolower($action);
            if (in_array($action, ['read', 'write', 'delete', 'manage_content', 'manage_users', 
                                   'manage_media', 'manage_plugins', 'manage_settings',
                                   'upload', 'read_media', 'read_settings',
                                   'create_user', 'update_user', 'delete_user', 'list_users',
                                   '*', 'all'])) {
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
/**
 * Normalize shared_type from connection record (handles legacy boolean shared field).
 */
function birkly_ai_normalize_shared_type(array $connection): string
{
    if (isset($connection['shared_type']) && is_string($connection['shared_type'])) {
        $type = $connection['shared_type'];
        if (in_array($type, ['none', 'all', 'specific'], true)) {
            return $type;
        }
    }

    $shared = $connection['shared'] ?? false;
    if ($shared === true || $shared === 'all') {
        return 'all';
    }
    if ($shared === 'specific') {
        return 'specific';
    }

    return 'none';
}

/**
 * Whether a human user may use (chat with) an AI connection.
 */
function birkly_ai_user_can_access_connection(array $connection, string $userId): bool
{
    if ($userId === '') {
        return false;
    }

    if (isset($connection['created_by']) && $connection['created_by'] === $userId) {
        return true;
    }

    $sharedType = birkly_ai_normalize_shared_type($connection);
    if ($sharedType === 'all') {
        return true;
    }
    if ($sharedType === 'specific') {
        return in_array($userId, $connection['allowed_users'] ?? [], true);
    }

    return false;
}

/**
 * Whether a human user may update/delete an AI connection.
 */
function birkly_ai_user_can_manage_connection(array $connection, string $userId, ?array $currentUser = null): bool
{
    if ($userId === '') {
        return false;
    }

    if (isset($connection['created_by']) && $connection['created_by'] === $userId) {
        return true;
    }

    if ($currentUser !== null) {
        if (checkPermission($currentUser, '*')) {
            return true;
        }
        if (checkPermission($currentUser, 'manage_settings')) {
            return true;
        }
    }

    return false;
}

/**
 * Whether a human user may view/approve/reject an MCP (inbound connector) approval.
 */
function birkly_ai_user_can_review_mcp_approval(array $approval, string $userId, ?array $currentUser = null): bool
{
    $aiUserId = (string) ($approval['ai_user_id'] ?? '');
    if ($aiUserId === '' || !str_starts_with($aiUserId, 'mcp_ep_')) {
        return false;
    }

    require_once __DIR__ . '/../core/mcp_endpoints.php';
    require_once __DIR__ . '/../core/ai_agents.php';

    $endpoint = birkly_mcp_find_endpoint($aiUserId);
    if ($endpoint === null) {
        return false;
    }

    $currentUser = is_array($currentUser) ? $currentUser : [];
    if (birkly_ai_agents_is_admin($currentUser)) {
        return true;
    }
    if (checkPermission($currentUser, 'manage_settings')) {
        return true;
    }

    $agent = birkly_ai_agents_find_by_endpoint_id($aiUserId);
    $authorizedBy = (string) ($approval['authorized_by'] ?? '');

    if ($agent !== null && ($agent['type'] ?? '') === 'personal') {
        return $authorizedBy !== '' && hash_equals($authorizedBy, $userId);
    }

    if ($agent !== null && birkly_ai_user_can_see_agent($agent, $currentUser)) {
        return true;
    }

    return false;
}

/**
 * Whether a human user may view/approve/reject an approval for an AI connection.
 */
function birkly_ai_user_can_review_approval(array $approval, string $userId, ?array $currentUser = null): bool
{
    if ($userId === '') {
        return false;
    }

    $aiUserId = $approval['ai_user_id'] ?? '';
    if ($aiUserId === '') {
        return false;
    }

    if (str_starts_with((string) $aiUserId, 'mcp_ep_')) {
        return birkly_ai_user_can_review_mcp_approval($approval, $userId, $currentUser);
    }

    $usersStorage = new Storage('../users');
    $aiUser = $usersStorage->read($aiUserId . '.json');
    if (!$aiUser || ($aiUser['type'] ?? '') !== 'ai') {
        return false;
    }

    if (birkly_ai_user_can_manage_connection($aiUser, $userId, $currentUser)) {
        return true;
    }

    return birkly_ai_user_can_access_connection($aiUser, $userId);
}

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

