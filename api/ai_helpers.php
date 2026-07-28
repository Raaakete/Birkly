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
 * Normalize collection action payloads from AI (slug/name/fields may be nested).
 *
 * Common shapes:
 * - { slug, name, fields }
 * - { collection: "slug", ... }
 * - { collection: { slug, name, fields, ... } }
 * - { data: { slug | collection, ... } }
 *
 * @return array{slug: string, name?: string, description?: string, fields?: array, entry_method?: string}
 */
function birkly_ai_normalize_collection_action_data($actionData): array
{
    if (!is_array($actionData)) {
        if (is_string($actionData) && trim($actionData) !== '') {
            return ['slug' => trim($actionData)];
        }
        return [];
    }

    $normalized = $actionData;

    if (isset($actionData['data']) && is_array($actionData['data'])) {
        $normalized = array_merge($actionData, $actionData['data']);
    }

    if (isset($normalized['collection']) && is_array($normalized['collection'])) {
        $coll = $normalized['collection'];
        unset($normalized['collection']);
        foreach (['slug', 'name', 'description', 'fields', 'entry_method'] as $key) {
            if (!array_key_exists($key, $normalized) && array_key_exists($key, $coll)) {
                $normalized[$key] = $coll[$key];
            }
        }
    } elseif (isset($normalized['collection']) && is_string($normalized['collection'])) {
        if (empty($normalized['slug'])) {
            $normalized['slug'] = $normalized['collection'];
        }
        unset($normalized['collection']);
    }

    if (isset($normalized['slug']) && is_string($normalized['slug'])) {
        $normalized['slug'] = trim($normalized['slug']);
    }

    foreach (['collection_slug', 'collectionSlug'] as $alias) {
        if (($normalized['slug'] ?? '') === '' && !empty($normalized[$alias]) && is_string($normalized[$alias])) {
            $normalized['slug'] = trim($normalized[$alias]);
        }
    }

    if (empty($normalized['fields'])) {
        require_once __DIR__ . '/../core/collection_schema.php';
        $schema = birkly_normalize_collection_schema($normalized);
        if (!empty($schema['fields'])) {
            $normalized['fields'] = $schema['fields'];
        }
    }

    return $normalized;
}

/**
 * Pull action payload fields from data/arguments and sibling keys on the action object.
 * The model often emits { type, slug, fields } without a data wrapper.
 */
function birkly_ai_extract_action_payload(array $action): array
{
    $data = $action['data'] ?? $action['arguments'] ?? $action['payload'] ?? [];
    if (!is_array($data)) {
        $data = birkly_ai_normalize_collection_action_data($data);
    }

    $reserved = ['type', 'action', 'description', 'data', 'arguments', 'payload'];
    foreach ($action as $key => $value) {
        if (in_array($key, $reserved, true)) {
            continue;
        }
        if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
            $data[$key] = $value;
        }
    }

    return is_array($data) ? $data : [];
}

/**
 * Normalize action payloads before execution (project paths, collection slugs, etc.).
 *
 * @return array<string, mixed>
 */
function birkly_ai_normalize_action_data(string $type, array $data): array
{
    if (in_array($type, ['write_project_file', 'create_project_file', 'delete_project_file', 'read_project_file'], true)
        && !empty($data['path']) && is_string($data['path'])) {
        $data['path'] = birkly_ai_normalize_project_file_path($data['path']);
    }

    if (in_array($type, ['update_collection', 'create_collection', 'create_entry', 'update_entry'], true)) {
        $data = birkly_ai_normalize_collection_action_data($data);
    }

    return $data;
}

/**
 * Resolve a collection slug/dir from a normalized payload (slug, name, or aliases).
 */
function birkly_ai_resolve_collection_slug_from_payload(array $payload, ?Storage $storage = null): string
{
    $payload = birkly_ai_normalize_collection_action_data($payload);

    foreach (['slug', 'collection_slug', 'collectionSlug', 'collection'] as $key) {
        if (!empty($payload[$key]) && is_string($payload[$key])) {
            return trim($payload[$key]);
        }
    }

    require_once __DIR__ . '/../core/entry_slug.php';

    foreach (['name', 'title'] as $key) {
        if (empty($payload[$key]) || !is_string($payload[$key])) {
            continue;
        }
        $ref = trim($payload[$key]);
        if ($ref === '') {
            continue;
        }
        if ($storage !== null) {
            require_once __DIR__ . '/../core/content_resolve.php';
            $dir = birkly_content_resolve_collection_dir_name($storage, $ref);
            if ($dir !== null) {
                return $dir;
            }
        }
        $generated = birkly_generate_slug($ref);
        if ($generated !== '') {
            return $generated;
        }
    }

    return '';
}

/**
 * Get supported AI providers
 */
function getSupportedAiProviders() {
    return [
        'openai' => [
            'name' => 'OpenAI',
            'default_model' => 'gpt-4o-mini',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'type' => 'openai',
            'dynamic_models' => true,
        ],
        'anthropic' => [
            'name' => 'Anthropic (Claude)',
            'default_model' => 'claude-3-5-haiku-latest',
            'api_url' => 'https://api.anthropic.com/v1/messages',
            'type' => 'anthropic',
            'dynamic_models' => false,
            'custom_models' => true,
        ],
        'google' => [
            'name' => 'Google (Gemini)',
            'default_model' => 'gemini-2.5-flash',
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'type' => 'google',
            'dynamic_models' => true,
        ],
        'openrouter' => [
            'name' => 'OpenRouter',
            'default_model' => 'openai/gpt-4o-mini',
            'api_url' => 'https://openrouter.ai/api/v1/chat/completions',
            'type' => 'openai',
            'dynamic_models' => true,
            'custom_models' => true,
        ],
        'ollama' => [
            'name' => 'Ollama (Local)',
            'default_model' => 'llama3',
            'api_url' => 'http://localhost:11434/api/chat',
            'type' => 'ollama',
            'dynamic_models' => true,
            'custom_models' => true,
        ],
        'custom' => [
            'name' => 'Custom Provider',
            'api_url' => '',
            'type' => 'custom',
            'custom_models' => true,
            'requires_url' => true,
        ]
    ];
}

/**
 * Provider metadata for admin clients (no static model lists when loaded dynamically).
 *
 * @return array<string, array<string, mixed>>
 */
function birkly_ai_providers_for_client(): array
{
    $providers = getSupportedAiProviders();
    foreach ($providers as $key => $info) {
        if (!empty($info['dynamic_models'])) {
            unset($providers[$key]['models']);
        }
    }

    return $providers;
}

/**
 * Default chat model for a provider when none is configured.
 */
function birkly_ai_default_model(string $provider): string
{
    $providers = getSupportedAiProviders();
    $info = $providers[$provider] ?? null;
    if (!$info) {
        return 'gpt-4o-mini';
    }

    if (!empty($info['default_model'])) {
        return (string) $info['default_model'];
    }

    $models = $info['models'] ?? [];
    return $models[0] ?? 'gpt-4o-mini';
}

/**
 * Composer capabilities for an AI connection (used by admin chat UI).
 *
 * @param array<string,mixed> $connection
 * @return array<string,bool>
 */
function birkly_ai_connection_capabilities(array $connection): array
{
    $provider = (string) ($connection['provider'] ?? '');
    $providers = getSupportedAiProviders();
    $info = $providers[$provider] ?? [];
    $supportsDynamicModels = !empty($info['dynamic_models']) || !empty($info['custom_models']);

    return [
        'model_picker' => $supportsDynamicModels,
        'vision' => in_array($provider, ['openai', 'openrouter', 'anthropic', 'google', 'custom'], true),
        'documents' => in_array($provider, ['openai', 'openrouter', 'anthropic', 'google', 'custom'], true),
        'streaming' => birkly_ai_provider_supports_streaming($provider),
    ];
}

/**
 * Resolve the model to send to a provider, including legacy aliases.
 */
function birkly_ai_resolve_model(string $provider, ?string $model): string
{
    $resolved = trim((string) $model);
    if ($resolved === '') {
        if ($provider === 'custom') {
            return '';
        }
        return birkly_ai_default_model($provider);
    }

    if ($provider === 'openai') {
        $legacy = [
            'gpt-4' => 'gpt-4o',
        ];
        return $legacy[$resolved] ?? $resolved;
    }

    if ($provider === 'google') {
        $legacy = [
            'gemini-1.5-flash' => 'gemini-2.5-flash',
            'gemini-1.5-pro' => 'gemini-2.5-pro',
            'gemini-pro' => 'gemini-pro-latest',
            'gemini-pro-vision' => 'gemini-2.5-flash',
        ];
        return $legacy[$resolved] ?? $resolved;
    }

    return $resolved;
}

/**
 * Extract a human-readable message from a provider error response body.
 */
function birkly_ai_parse_provider_error(?string $responseBody): string
{
    if ($responseBody === null || $responseBody === '') {
        return '';
    }

    $data = json_decode($responseBody, true);
    if (!is_array($data)) {
        return '';
    }

    $message = $data['error']['message'] ?? $data['message'] ?? '';
    if (!is_string($message)) {
        $message = '';
    }
    if ($message === '' && isset($data['error']['status']) && is_string($data['error']['status'])) {
        $message = $data['error']['status'];
    }

    $message = trim((string) $message);
    if (strlen($message) > 240) {
        return substr($message, 0, 237) . '...';
    }

    return $message;
}

/**
 * Human label for OpenAI-compatible API endpoints (OpenAI, OpenRouter, custom).
 */
function birkly_ai_compatible_api_label(string $url, string $provider = ''): string
{
    if (stripos($url, 'openrouter.ai') !== false || $provider === 'openrouter') {
        return 'OpenRouter';
    }
    if (stripos($url, 'api.openai.com') !== false || $provider === 'openai') {
        return 'OpenAI';
    }
    if ($provider === 'ollama') {
        return 'Ollama';
    }
    if ($provider === 'custom') {
        return 'Custom API';
    }

    return 'AI provider';
}

/**
 * Build a structured provider API failure for chat UI and logs.
 *
 * @return array{success: false, message: string, error_details: array<string, mixed>}
 */
function birkly_ai_build_provider_error(
    string $providerLabel,
    int $httpCode,
    ?string $responseBody,
    string $model = '',
    string $provider = ''
): array {
    $detail = birkly_ai_parse_provider_error($responseBody);
    $message = $providerLabel . ' API error: ' . $httpCode;
    if ($detail !== '') {
        $message .= ' — ' . $detail;
    }
    if ($model !== '') {
        $message .= ' (model: ' . $model . ')';
    }

    $hint = '';
    if ($httpCode === 404 && $provider === 'google') {
        $hint = 'Model not found or unavailable for your API key. Edit the connection in Settings → Built-in assistant, reload models, and choose one from the list.';
    } elseif ($httpCode === 404) {
        $hint = 'The model endpoint was not found. Verify the model name in your connection settings.';
    } elseif ($httpCode === 429) {
        $hint = 'Rate limit exceeded. Wait a few minutes, choose a different model, or add credits to your provider account.';
    } elseif ($httpCode === 401 || $httpCode === 403) {
        $hint = 'Authentication failed. Check that your API key is valid and has access to this model.';
    } elseif ($httpCode >= 500) {
        $hint = 'The provider returned a server error. Try again in a moment or switch models.';
    }

    return [
        'success' => false,
        'message' => $message,
        'error_details' => [
            'provider' => $provider,
            'provider_label' => $providerLabel,
            'http_code' => $httpCode,
            'model' => $model,
            'provider_message' => $detail,
            'hint' => $hint,
            'raw_body' => is_string($responseBody) ? substr($responseBody, 0, 800) : '',
        ],
    ];
}

/**
 * Strip leading project/ from paths — files already live under project/.
 */
function birkly_ai_normalize_project_file_path(string $path): string
{
    $path = ltrim(str_replace('\\', '/', trim($path)), '/');
    while (str_starts_with(strtolower($path), 'project/')) {
        // "project/" is 8 characters — substr(..., 9) incorrectly ate the first path char (index→ndex).
        $path = substr($path, 8);
    }
    return birkly_ai_fix_common_project_path_typos($path);
}

/**
 * Correct filenames corrupted by the old project/ strip bug or model typos.
 */
function birkly_ai_fix_common_project_path_typos(string $path): string
{
    $dir = dirname($path);
    $base = basename($path);
    $fixes = [
        'ndex.html' => 'index.html',
        'bout.html' => 'about.html',
        'ontact.html' => 'contact.html',
        'rtworks.html' => 'artworks.html',
        'rtwork.html' => 'artwork.html',
    ];
    if (!isset($fixes[$base])) {
        return $path;
    }
    $fixed = $fixes[$base];
    error_log("Correcting project path typo: {$base} → {$fixed}");
    return ($dir === '.' || $dir === '') ? $fixed : $dir . '/' . $fixed;
}

/**
 * Normalize OpenAI message content (string or multimodal parts array).
 */
function birkly_ai_normalize_openai_message_content(mixed $content): string
{
    if (is_string($content)) {
        return $content;
    }
    if (!is_array($content)) {
        return '';
    }
    $parts = [];
    foreach ($content as $part) {
        if (is_string($part)) {
            $parts[] = $part;
            continue;
        }
        if (!is_array($part)) {
            continue;
        }
        if (isset($part['text']) && is_string($part['text'])) {
            $parts[] = $part['text'];
        } elseif (isset($part['content']) && is_string($part['content'])) {
            $parts[] = $part['content'];
        }
    }

    return implode("\n", $parts);
}

/**
 * Sanitize stored chat history before sending to OpenAI-compatible providers.
 *
 * @param list<array<string,mixed>> $history
 * @return list<array{role: string, content: string}>
 */
function birkly_ai_sanitize_conversation_history_for_provider(array $history): array
{
    $out = [];
    foreach ($history as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $role = (string) ($msg['role'] ?? '');
        if (!in_array($role, ['user', 'assistant', 'system'], true)) {
            continue;
        }
        $content = birkly_ai_normalize_openai_message_content($msg['content'] ?? '');
        $content = trim($content);
        if ($content === '') {
            continue;
        }
        if (strlen($content) > 12000) {
            $content = substr($content, 0, 12000) . "\n\n[truncated for context limit]";
        }
        $out[] = ['role' => $role, 'content' => $content];
    }

    return $out;
}

/**
 * Parse an OpenAI-compatible chat completion JSON payload.
 *
 * @return array{success: bool, message?: string, tool_calls?: list<array<string,mixed>>, error_details?: array<string,mixed>}
 */
function birkly_ai_parse_openai_chat_response(?array $result, string $apiLabel, string $rawBody = ''): array
{
    if (!is_array($result)) {
        return [
            'success' => false,
            'message' => 'Invalid response from ' . $apiLabel,
            'error_details' => [
                'provider_label' => $apiLabel,
                'hint' => 'Response was not valid JSON.',
                'raw_body' => substr($rawBody, 0, 800),
            ],
        ];
    }

    if (isset($result['error']) && is_array($result['error'])) {
        $errMsg = (string) ($result['error']['message'] ?? json_encode($result['error']));
        return [
            'success' => false,
            'message' => $apiLabel . ' error: ' . $errMsg,
            'error_details' => [
                'provider_label' => $apiLabel,
                'hint' => 'The provider returned an error object.',
                'raw_body' => substr($rawBody, 0, 800),
            ],
        ];
    }

    $choice = $result['choices'][0] ?? null;
    if (!is_array($choice)) {
        return [
            'success' => false,
            'message' => 'Invalid response from ' . $apiLabel,
            'error_details' => [
                'provider_label' => $apiLabel,
                'hint' => 'No choices array in provider response (rate limit or model unavailable).',
                'raw_body' => substr($rawBody, 0, 800),
            ],
        ];
    }

    $choiceMessage = $choice['message'] ?? $choice['delta'] ?? null;
    if (!is_array($choiceMessage)) {
        if (isset($choice['text']) && is_string($choice['text']) && trim($choice['text']) !== '') {
            return ['success' => true, 'message' => $choice['text']];
        }
        return [
            'success' => false,
            'message' => 'Invalid response from ' . $apiLabel,
            'error_details' => [
                'provider_label' => $apiLabel,
                'hint' => 'The provider returned an unexpected response format.',
                'raw_body' => substr($rawBody, 0, 800),
            ],
        ];
    }

    $toolCalls = is_array($choiceMessage['tool_calls'] ?? null) ? $choiceMessage['tool_calls'] : [];
    $content = birkly_ai_normalize_openai_message_content($choiceMessage['content'] ?? '');

    foreach (['reasoning', 'reasoning_content', 'reasoning_details'] as $reasonKey) {
        if ($content !== '') {
            break;
        }
        $reasonVal = $choiceMessage[$reasonKey] ?? null;
        if (is_string($reasonVal) && trim($reasonVal) !== '') {
            $content = $reasonVal;
        } elseif (is_array($reasonVal)) {
            $content = birkly_ai_normalize_openai_message_content($reasonVal);
        }
    }

    if ($toolCalls !== []) {
        return [
            'success' => true,
            'message' => $content,
            'tool_calls' => $toolCalls,
        ];
    }

    if (trim($content) === '') {
        return [
            'success' => false,
            'message' => 'Empty response from ' . $apiLabel,
            'error_details' => [
                'provider_label' => $apiLabel,
                'hint' => 'Model returned no text and no tool calls.',
                'raw_body' => substr($rawBody, 0, 800),
            ],
        ];
    }

    return ['success' => true, 'message' => $content];
}

/**
 * Find the end index (exclusive) of a JSON object starting at $start.
 */
function ai_chat_find_json_object_end(string $text, int $start): ?int
{
    $depth = 0;
    $inString = false;
    $escape = false;
    $len = strlen($text);
    for ($i = $start; $i < $len; $i++) {
        $c = $text[$i];
        if ($inString) {
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($c === '\\') {
                $escape = true;
                continue;
            }
            if ($c === '"') {
                $inString = false;
            }
            continue;
        }
        if ($c === '"') {
            $inString = true;
            continue;
        }
        if ($c === '{') {
            $depth++;
            continue;
        }
        if ($c === '}') {
            $depth--;
            if ($depth === 0) {
                return $i + 1;
            }
        }
    }

    return null;
}

/**
 * True when text embeds model tool-call markup (OpenRouter XML-style or similar).
 */
function ai_chat_message_contains_tool_call_markup(string $message): bool
{
    return (bool) preg_match('/<tool_call\b[\s>]/i', $message)
        || (bool) preg_match('/<arg_key>[\s\S]*?<\/arg_value>/i', $message);
}

/**
 * Parse XML-style tool calls some OpenRouter models emit inside message content.
 *
 * @return list<array<string,mixed>>
 */
function ai_chat_extract_xml_tool_calls(string $response): array
{
    $actions = [];
    if (!preg_match_all('/<tool_call>\s*([a-z_][a-z0-9_]*)\s*([\s\S]*?)<\/tool_call>/i', $response, $blocks, PREG_SET_ORDER)) {
        return $actions;
    }

    foreach ($blocks as $block) {
        $name = (string) $block[1];
        $body = (string) $block[2];
        if ($name === '') {
            continue;
        }
        $args = [];
        if (preg_match_all('/<arg_key>([^<]*)<\/arg_key>\s*<arg_value>([\s\S]*?)<\/arg_value>/i', $body, $argMatches, PREG_SET_ORDER)) {
            foreach ($argMatches as $argMatch) {
                $key = trim((string) $argMatch[1]);
                if ($key === '') {
                    continue;
                }
                $args[$key] = trim((string) $argMatch[2]);
            }
        }
        $actions[] = ['type' => $name] + $args;
    }

    return $actions;
}

/**
 * Brief user-facing summary after read actions (no raw file dumps).
 *
 * @param list<array<string,mixed>> $readData
 */
function ai_chat_summarize_read_results(array $readData): string
{
    $parts = [];
    foreach ($readData as $readResult) {
        if (!($readResult['success'] ?? false)) {
            continue;
        }
        $data = is_array($readResult['data'] ?? null) ? $readResult['data'] : [];
        if (isset($data['file']) && is_array($data['file'])) {
            $path = (string) ($data['file']['path'] ?? $data['file']['relative_path'] ?? '');
            if ($path !== '') {
                $parts[] = $path;
            }
            continue;
        }
        if (isset($data['collection']) && isset($data['entries'])) {
            $parts[] = 'entries in ' . $data['collection'];
            continue;
        }
        if (isset($data['path']) && isset($data['count']) && !isset($data['collection'])) {
            $parts[] = 'files at ' . $data['path'];
            continue;
        }
        if (isset($data['site_dir'])) {
            $parts[] = 'project info';
        }
    }
    $parts = array_values(array_unique($parts));
    if ($parts === []) {
        return '';
    }

    return 'Retrieved **' . implode('**, **', $parts) . '**.';
}

/**
 * Remove action JSON, session notes, and other non-chat noise from assistant text.
 */
function ai_chat_sanitize_display_message(string $message): string
{
    $message = (string) $message;
    if ($message === '') {
        return '';
    }

    $message = preg_replace('/<tool_call\b[^>]*>[\s\S]*?<\/tool_call>/iu', '', $message) ?? $message;
    $message = preg_replace('/<tool_call\b[^>]*>[\s\S]*/iu', '', $message) ?? $message;
    $message = preg_replace('/```(?:json)?\s*[\s\S]*?```/iu', '', $message) ?? $message;
    $message = preg_replace('/\n*\[Session notes[^\]]*\]\s*/u', '', $message) ?? $message;

    $patterns = ['{"actions"', '{ "actions"', '{"type":"write_project_file"', '{ "type": "write_project_file"'];
    foreach ($patterns as $needle) {
        $offset = 0;
        while (($pos = stripos($message, $needle, $offset)) !== false) {
            $end = ai_chat_find_json_object_end($message, $pos);
            if ($end === null) {
                break;
            }
            $message = substr($message, 0, $pos) . substr($message, $end);
            $offset = $pos;
        }
    }

    $message = preg_replace("/\n{3,}/", "\n\n", trim($message)) ?? trim($message);

    return trim($message);
}

/**
 * Extract decoded JSON objects from text using brace matching (handles nested {} in strings).
 *
 * @return list<array<string,mixed>>
 */
function ai_chat_extract_json_objects(string $text, ?string $requiredKey = null): array
{
    $objects = [];
    $offset = 0;
    $len = strlen($text);
    while ($offset < $len) {
        $pos = strpos($text, '{', $offset);
        if ($pos === false) {
            break;
        }
        if ($requiredKey !== null) {
            $snippet = substr($text, $pos, min(48, $len - $pos));
            if (stripos($snippet, '"' . $requiredKey . '"') === false) {
                $offset = $pos + 1;
                continue;
            }
        }
        $end = ai_chat_find_json_object_end($text, $pos);
        if ($end === null) {
            $offset = $pos + 1;
            continue;
        }
        $chunk = substr($text, $pos, $end - $pos);
        $json = json_decode($chunk, true);
        if (is_array($json)) {
            $objects[] = $json;
        }
        $offset = $end;
    }

    return $objects;
}

/**
 * Validate a write/read action before execution. Returns error message or null if OK.
 */
function birkly_ai_validate_action_before_execute(array $action): ?string
{
    $type = (string) ($action['type'] ?? '');
    $data = is_array($action['data'] ?? null) ? $action['data'] : [];

    if (in_array($type, ['write_project_file', 'create_project_file'], true)) {
        $path = (string) ($data['path'] ?? '');
        $content = (string) ($data['content'] ?? '');
        if ($path === '') {
            return 'write_project_file missing path';
        }
        if ($content === '') {
            return 'write_project_file missing content for ' . $path;
        }
        if (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_./-]*$#', $path)) {
            return 'Invalid project file path: ' . $path;
        }
    }

    if ($type === 'delete_project_file') {
        $path = (string) ($data['path'] ?? '');
        if ($path === '' || !str_contains($path, '.')) {
            return 'delete_project_file requires a file path (directories cannot be deleted with this action): ' . $path;
        }
    }

    if ($type === 'update_settings') {
        $category = (string) ($data['category'] ?? '');
        $settings = $data['settings'] ?? null;
        if ($category === '' || !is_array($settings) || $settings === []) {
            return 'update_settings requires category and settings object';
        }
    }

    return null;
}

/**
 * Normalize chat attachment payloads from the browser.
 *
 * @return list<array{type: string, name?: string, mime?: string, base64?: string, content?: string}>
 */
function birkly_ai_normalize_chat_attachments(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = (string) ($item['type'] ?? '');
        if ($type === 'image') {
            $base64 = (string) ($item['base64'] ?? '');
            if ($base64 === '' && !empty($item['data_url'])) {
                $base64 = preg_replace('#^data:[^;]+;base64,#', '', (string) $item['data_url']) ?? '';
            }
            $base64 = preg_replace('/\s+/', '', $base64);
            if ($base64 === '' || strlen($base64) > 6 * 1024 * 1024) {
                continue;
            }
            $mime = (string) ($item['mime'] ?? 'image/png');
            if (!str_starts_with($mime, 'image/')) {
                $mime = 'image/png';
            }
            $out[] = [
                'type' => 'image',
                'name' => (string) ($item['name'] ?? 'image.png'),
                'mime' => $mime,
                'base64' => $base64,
            ];
        } elseif ($type === 'document') {
            $content = (string) ($item['content'] ?? '');
            if ($content === '') {
                continue;
            }
            $out[] = [
                'type' => 'document',
                'name' => (string) ($item['name'] ?? 'document.txt'),
                'content' => mb_substr($content, 0, 12000),
            ];
        }
    }

    return array_slice($out, 0, 6);
}

/**
 * @param list<array<string,mixed>> $attachments
 */
function birkly_ai_chat_image_attachments(array $attachments): array
{
    return array_values(array_filter($attachments, static fn (array $item): bool => ($item['type'] ?? '') === 'image'));
}

/**
 * @param list<array<string,mixed>> $attachments
 */
function birkly_ai_message_with_document_attachments(string $message, array $attachments): string
{
    $out = trim($message);
    foreach ($attachments as $item) {
        if (($item['type'] ?? '') !== 'document') {
            continue;
        }
        $name = (string) ($item['name'] ?? 'document.txt');
        $content = (string) ($item['content'] ?? '');
        $out .= "\n\n[Attached document: {$name}]\n```\n{$content}\n```";
    }
    return $out;
}

/**
 * @param list<array<string,mixed>> $imageAttachments
 * @return array<int, array<string, mixed>>|string
 */
function birkly_ai_build_openai_user_content(string $text, array $imageAttachments)
{
    if ($imageAttachments === []) {
        return $text;
    }

    $parts = [];
    if (trim($text) !== '') {
        $parts[] = ['type' => 'text', 'text' => $text];
    }
    foreach ($imageAttachments as $img) {
        $mime = (string) ($img['mime'] ?? 'image/png');
        $parts[] = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:' . $mime . ';base64,' . ($img['base64'] ?? ''),
            ],
        ];
    }

    return $parts !== [] ? $parts : $text;
}

/**
 * @param list<array<string,mixed>> $imageAttachments
 * @return list<array<string,mixed>>
 */
function birkly_ai_build_anthropic_user_content(string $text, array $imageAttachments): array
{
    $blocks = [];
    if (trim($text) !== '') {
        $blocks[] = ['type' => 'text', 'text' => $text];
    }
    foreach ($imageAttachments as $img) {
        $blocks[] = [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => (string) ($img['mime'] ?? 'image/png'),
                'data' => (string) ($img['base64'] ?? ''),
            ],
        ];
    }
    if ($blocks === []) {
        $blocks[] = ['type' => 'text', 'text' => ''];
    }
    return $blocks;
}

/**
 * @param list<array<string,mixed>> $imageAttachments
 * @return list<array<string,mixed>>
 */
function birkly_ai_build_google_user_parts(string $text, array $imageAttachments): array
{
    $parts = [];
    if (trim($text) !== '') {
        $parts[] = ['text' => $text];
    }
    foreach ($imageAttachments as $img) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => (string) ($img['mime'] ?? 'image/png'),
                'data' => (string) ($img['base64'] ?? ''),
            ],
        ];
    }
    if ($parts === []) {
        $parts[] = ['text' => ''];
    }
    return $parts;
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
 * Core context pack plus active plugin doc summaries (P6.5-M5).
 */
function birkly_ai_context_pack_with_plugins(): string
{
    $pack = loadBirklyContextPack();
    if (!function_exists('birkly_plugin_docs_ai_summaries')) {
        return $pack;
    }
    $pluginBlock = birkly_plugin_docs_ai_summaries();
    if ($pluginBlock === '') {
        return $pack;
    }
    return trim($pack . "\n\n" . $pluginBlock);
}

/**
 * Load a Birkly docs topic for AI depth (P5.4-M3).
 *
 * @return array{slug: string, title: string, text: string}|null
 */
function readBirklyDocsTopic(string $slug): ?array
{
    static $cache = [];
    $slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower(trim($slug)));
    if ($slug === '') {
        return null;
    }
    if (isset($cache[$slug])) {
        return $cache[$slug];
    }

    require_once __DIR__ . '/../core/ai_playbooks.php';
    $topics = birkly_ai_core_docs_topics();

    if (isset($topics[$slug])) {
        $root = dirname(__DIR__);
        if (isset($topics[$slug]['path'])) {
            $path = $root . '/' . $topics[$slug]['path'];
        } else {
            $path = $root . '/docs/' . $topics[$slug]['file'];
        }
        if (is_file($path)) {
            $text = trim((string) file_get_contents($path));
            if (strlen($text) > 120000) {
                $text = substr($text, 0, 120000) . "\n\n[truncated]";
            }
            $cache[$slug] = [
                'slug' => $slug,
                'title' => $topics[$slug]['title'],
                'text' => $text,
            ];
            return $cache[$slug];
        }
    }

    // Active plugin docs (P6.5-M5) — after core playbooks so stubs don't override full playbooks
    if (function_exists('get_plugin_docs_by_topic_slug')) {
        if (function_exists('birkly_bootstrap_plugins_for_docs')) {
            birkly_bootstrap_plugins_for_docs();
        }
        $pluginDoc = get_plugin_docs_by_topic_slug($slug);
        if ($pluginDoc !== null) {
            $text = read_plugin_doc_markdown((string) $pluginDoc['plugin_id'], 'ai');
            if ($text === null) {
                $text = trim((string) ($pluginDoc['summary'] ?? ''));
            }
            if ($text !== '') {
                $cache[$slug] = [
                    'slug' => $slug,
                    'title' => (string) ($pluginDoc['title'] ?? $slug),
                    'text' => $text,
                    'source' => 'plugin',
                    'plugin_id' => (string) ($pluginDoc['plugin_id'] ?? ''),
                ];
                return $cache[$slug];
            }
        }
    }

    return null;
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
function validateProviderConnection(string $provider, string $apiKey, string $apiUrl = '', string $model = ''): array
{
    if ($provider === 'mcp') {
        return ['valid' => true, 'message' => 'MCP endpoints are managed separately'];
    }

    if ($provider === 'ollama') {
        return validateOllamaEndpoint($apiUrl);
    }

    return validateApiKeyForProvider($provider, $apiKey, $apiUrl, $model);
}

/**
 * Perform a lightweight HTTP request for provider validation.
 *
 * @return array{http_code: int, body: string, curl_error: string}
 */
function birkly_ai_provider_http_request(string $url, array $options = []): array
{
    if (!function_exists('curl_init')) {
        return ['http_code' => 0, 'body' => '', 'curl_error' => 'curl unavailable'];
    }

    $method = strtoupper((string) ($options['method'] ?? 'POST'));
    $headers = $options['headers'] ?? [];
    $body = $options['body'] ?? null;
    $timeout = (int) ($options['timeout'] ?? 15);

    $ch = curl_init();
    $curlOpts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($method === 'GET') {
        $curlOpts[CURLOPT_HTTPGET] = true;
    } else {
        $curlOpts[CURLOPT_POST] = true;
        if ($body !== null) {
            $curlOpts[CURLOPT_POSTFIELDS] = $body;
        }
    }

    curl_setopt_array($ch, $curlOpts);
    $responseBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'body' => is_string($responseBody) ? $responseBody : '',
        'curl_error' => $curlError,
    ];
}

/**
 * OpenRouter-specific key validation (avoids chat completion + model availability issues).
 *
 * @return array{valid: bool, message: string, provider_error?: string}
 */
function validateOpenRouterApiKey(string $apiKey): array
{
    $referer = '';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $referer = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ];
    if ($referer !== '') {
        $headers[] = 'HTTP-Referer: ' . $referer;
    }
    $headers[] = 'X-Title: Birkly';

    foreach (['https://openrouter.ai/api/v1/auth/key', 'https://openrouter.ai/api/v1/key'] as $url) {
        $result = birkly_ai_provider_http_request($url, [
            'method' => 'GET',
            'headers' => $headers,
        ]);

        if ($result['curl_error'] !== '') {
            return [
                'valid' => false,
                'message' => 'Connection failed: ' . $result['curl_error'],
                'provider_error' => $result['curl_error'],
            ];
        }

        if ($result['http_code'] === 200) {
            return ['valid' => true, 'message' => 'OpenRouter API key verified'];
        }

        if ($result['http_code'] === 401 || $result['http_code'] === 403) {
            return [
                'valid' => false,
                'message' => 'Invalid OpenRouter API key (HTTP ' . $result['http_code'] . ')',
                'provider_error' => birkly_ai_parse_provider_error($result['body']) ?: ('HTTP ' . $result['http_code']),
            ];
        }

        if ($result['http_code'] >= 500) {
            return [
                'valid' => false,
                'message' => 'OpenRouter is temporarily unavailable (HTTP ' . $result['http_code'] . '). Try again shortly.',
                'provider_error' => 'HTTP ' . $result['http_code'],
            ];
        }
    }

    return [
        'valid' => false,
        'message' => 'Could not verify OpenRouter API key',
        'provider_error' => 'HTTP ' . ($result['http_code'] ?? 0),
    ];
}

/**
 * Google Gemini key validation via models list (no deprecated model ping).
 *
 * @return array{valid: bool, message: string, provider_error?: string}
 */
function validateGoogleApiKey(string $apiKey, string $model = ''): array
{
    $listUrl = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($apiKey);
    $listResult = birkly_ai_provider_http_request($listUrl, [
        'method' => 'GET',
        'headers' => ['Content-Type: application/json'],
    ]);

    if ($listResult['curl_error'] !== '') {
        return [
            'valid' => false,
            'message' => 'Connection failed: ' . $listResult['curl_error'],
            'provider_error' => $listResult['curl_error'],
        ];
    }

    if ($listResult['http_code'] === 401 || $listResult['http_code'] === 403) {
        return [
            'valid' => false,
            'message' => 'Invalid Gemini API key (HTTP ' . $listResult['http_code'] . ')',
            'provider_error' => birkly_ai_parse_provider_error($listResult['body']) ?: ('HTTP ' . $listResult['http_code']),
        ];
    }

    if ($listResult['http_code'] !== 200) {
        $detail = birkly_ai_parse_provider_error($listResult['body']);
        $message = 'Gemini API returned HTTP ' . $listResult['http_code'];
        if ($detail !== '') {
            $message .= ': ' . $detail;
        }
        return [
            'valid' => false,
            'message' => $message,
            'provider_error' => 'HTTP ' . $listResult['http_code'],
        ];
    }

    $resolvedModel = birkly_ai_resolve_model('google', $model !== '' ? $model : birkly_ai_default_model('google'));
    $generateUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' .
        rawurlencode($resolvedModel) . ':generateContent?key=' . urlencode($apiKey);
    $generateResult = birkly_ai_provider_http_request($generateUrl, [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode(['contents' => [['parts' => [['text' => 'ping']]]]]),
    ]);

    if ($generateResult['http_code'] === 200) {
        return ['valid' => true, 'message' => 'Gemini API key verified'];
    }

    if ($generateResult['http_code'] === 404) {
        return [
            'valid' => true,
            'message' => 'Gemini API key verified (model "' . $resolvedModel . '" may need to be changed in settings)',
        ];
    }

    if ($generateResult['http_code'] === 401 || $generateResult['http_code'] === 403) {
        return [
            'valid' => false,
            'message' => 'Invalid Gemini API key (HTTP ' . $generateResult['http_code'] . ')',
            'provider_error' => birkly_ai_parse_provider_error($generateResult['body']) ?: ('HTTP ' . $generateResult['http_code']),
        ];
    }

    $detail = birkly_ai_parse_provider_error($generateResult['body']);
    $message = 'Gemini API returned HTTP ' . $generateResult['http_code'];
    if ($detail !== '') {
        $message .= ': ' . $detail;
    }

    return [
        'valid' => false,
        'message' => $message,
        'provider_error' => 'HTTP ' . $generateResult['http_code'],
    ];
}

/**
 * Validate provider API key with a lightweight request (best-effort).
 *
 * @return array{valid: bool, message: string, provider_error?: string}
 */
function validateApiKeyForProvider(string $provider, string $apiKey, string $apiUrl = '', string $model = ''): array
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

    if ($provider === 'openrouter') {
        return validateOpenRouterApiKey($apiKey);
    }

    if ($provider === 'google') {
        return validateGoogleApiKey($apiKey, $model);
    }

    $url = $apiUrl ?: ($providers[$provider]['api_url'] ?? '');
    if ($url === '') {
        return ['valid' => true, 'message' => 'Skipped validation (no URL)'];
    }

    $resolvedModel = birkly_ai_resolve_model($provider, $model !== '' ? $model : birkly_ai_default_model($provider));
    $headers = match ($provider) {
        'anthropic' => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        default => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
    };

    $body = match ($provider) {
        'anthropic' => json_encode([
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 1,
            'messages' => [['role' => 'user', 'content' => 'ping']],
        ]),
        default => json_encode([
            'model' => $resolvedModel,
            'max_tokens' => 1,
            'messages' => [['role' => 'user', 'content' => 'ping']],
        ]),
    };

    $result = birkly_ai_provider_http_request($url, [
        'method' => 'POST',
        'headers' => $headers,
        'body' => $body,
    ]);

    if ($result['curl_error'] !== '') {
        return [
            'valid' => false,
            'message' => 'Connection failed: ' . $result['curl_error'],
            'provider_error' => $result['curl_error'],
        ];
    }

    $httpCode = $result['http_code'];
    $responseBody = $result['body'];

    if ($httpCode === 401 || $httpCode === 403) {
        return [
            'valid' => false,
            'message' => 'Invalid API key (HTTP ' . $httpCode . ')',
            'provider_error' => birkly_ai_parse_provider_error($responseBody) ?: ('HTTP ' . $httpCode),
        ];
    }

    if ($httpCode === 402) {
        return [
            'valid' => true,
            'message' => 'API key accepted — add credits to your provider account before chatting',
        ];
    }

    if ($httpCode === 200) {
        return ['valid' => true, 'message' => 'Connection verified'];
    }

    $detail = birkly_ai_parse_provider_error($responseBody);
    $message = 'Provider returned HTTP ' . $httpCode;
    if ($detail !== '') {
        $message .= ': ' . $detail;
    }
    if ($httpCode === 404) {
        $message .= ' (check API URL and model name)';
    }

    return [
        'valid' => false,
        'message' => $message,
        'provider_error' => $detail !== '' ? $detail : ('HTTP ' . $httpCode),
    ];
}

/**
 * Normalize a provider model entry for the admin picker.
 *
 * @return array{id: string, name: string, description: string, context_length: int|null}
 */
function birkly_ai_normalize_model_entry(string $id, string $name = '', string $description = '', ?int $contextLength = null): array
{
    $id = trim($id);
    return [
        'id' => $id,
        'name' => $name !== '' ? $name : $id,
        'description' => trim($description),
        'context_length' => $contextLength,
    ];
}

/**
 * Sort and dedupe model entries by id.
 *
 * @param list<array{id: string, name: string, description: string, context_length: int|null}> $models
 * @return list<array{id: string, name: string, description: string, context_length: int|null}>
 */
function birkly_ai_sort_models(array $models): array
{
    $byId = [];
    foreach ($models as $model) {
        $id = (string) ($model['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $byId[$id] = $model;
    }
    $models = array_values($byId);
    usort($models, static function (array $a, array $b): int {
        return strcasecmp((string) $a['name'], (string) $b['name']);
    });

    return $models;
}

/**
 * @return array{ok: bool, message: string, models: list<array{id: string, name: string, description: string, context_length: int|null}>}
 */
function birkly_ai_fetch_openrouter_models(string $apiKey = ''): array
{
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $headers[] = 'HTTP-Referer: ' . $scheme . '://' . $_SERVER['HTTP_HOST'];
        }
        $headers[] = 'X-Title: Birkly';
    }

    $result = birkly_ai_provider_http_request('https://openrouter.ai/api/v1/models', [
        'method' => 'GET',
        'headers' => $headers,
        'timeout' => 20,
    ]);

    if ($result['curl_error'] !== '') {
        return ['ok' => false, 'message' => 'Could not reach OpenRouter: ' . $result['curl_error'], 'models' => []];
    }

    if ($result['http_code'] !== 200) {
        $detail = birkly_ai_parse_provider_error($result['body']);
        $message = 'OpenRouter returned HTTP ' . $result['http_code'];
        if ($detail !== '') {
            $message .= ': ' . $detail;
        }
        return ['ok' => false, 'message' => $message, 'models' => []];
    }

    $payload = json_decode($result['body'], true);
    $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $models = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $context = isset($row['context_length']) ? (int) $row['context_length'] : null;
        $models[] = birkly_ai_normalize_model_entry(
            $id,
            (string) ($row['name'] ?? $id),
            (string) ($row['description'] ?? ''),
            $context > 0 ? $context : null
        );
    }

    return [
        'ok' => true,
        'message' => count($models) . ' models loaded from OpenRouter',
        'models' => birkly_ai_sort_models($models),
    ];
}

/**
 * @return array{ok: bool, message: string, models: list<array{id: string, name: string, description: string, context_length: int|null}>}
 */
function birkly_ai_fetch_google_models(string $apiKey): array
{
    if ($apiKey === '') {
        return ['ok' => false, 'message' => 'API key is required to load Gemini models', 'models' => []];
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($apiKey);
    $result = birkly_ai_provider_http_request($url, [
        'method' => 'GET',
        'headers' => ['Content-Type: application/json'],
        'timeout' => 20,
    ]);

    if ($result['curl_error'] !== '') {
        return ['ok' => false, 'message' => 'Could not reach Gemini: ' . $result['curl_error'], 'models' => []];
    }

    if ($result['http_code'] === 401 || $result['http_code'] === 403) {
        return ['ok' => false, 'message' => 'Invalid Gemini API key', 'models' => []];
    }

    if ($result['http_code'] !== 200) {
        $detail = birkly_ai_parse_provider_error($result['body']);
        return [
            'ok' => false,
            'message' => 'Gemini returned HTTP ' . $result['http_code'] . ($detail !== '' ? ': ' . $detail : ''),
            'models' => [],
        ];
    }

    $payload = json_decode($result['body'], true);
    $rows = is_array($payload['models'] ?? null) ? $payload['models'] : [];
    $models = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $methods = $row['supportedGenerationMethods'] ?? [];
        if (is_array($methods) && $methods !== [] && !in_array('generateContent', $methods, true)) {
            continue;
        }
        $rawName = (string) ($row['name'] ?? '');
        $id = preg_replace('#^models/#', '', $rawName);
        if ($id === '') {
            continue;
        }
        $context = isset($row['inputTokenLimit']) ? (int) $row['inputTokenLimit'] : null;
        $models[] = birkly_ai_normalize_model_entry(
            $id,
            (string) ($row['displayName'] ?? $id),
            (string) ($row['description'] ?? ''),
            $context > 0 ? $context : null
        );
    }

    return [
        'ok' => true,
        'message' => count($models) . ' models loaded from Gemini',
        'models' => birkly_ai_sort_models($models),
    ];
}

/**
 * @return array{ok: bool, message: string, models: list<array{id: string, name: string, description: string, context_length: int|null}>}
 */
function birkly_ai_fetch_openai_models(string $apiKey): array
{
    if ($apiKey === '') {
        return ['ok' => false, 'message' => 'API key is required to load OpenAI models', 'models' => []];
    }

    $result = birkly_ai_provider_http_request('https://api.openai.com/v1/models', [
        'method' => 'GET',
        'headers' => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        'timeout' => 20,
    ]);

    if ($result['curl_error'] !== '') {
        return ['ok' => false, 'message' => 'Could not reach OpenAI: ' . $result['curl_error'], 'models' => []];
    }

    if ($result['http_code'] === 401 || $result['http_code'] === 403) {
        return ['ok' => false, 'message' => 'Invalid OpenAI API key', 'models' => []];
    }

    if ($result['http_code'] !== 200) {
        $detail = birkly_ai_parse_provider_error($result['body']);
        return [
            'ok' => false,
            'message' => 'OpenAI returned HTTP ' . $result['http_code'] . ($detail !== '' ? ': ' . $detail : ''),
            'models' => [],
        ];
    }

    $payload = json_decode($result['body'], true);
    $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $models = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        if (!preg_match('/^(gpt-|o[0-9]|chatgpt-)/i', $id)) {
            continue;
        }
        $models[] = birkly_ai_normalize_model_entry($id, $id);
    }

    return [
        'ok' => true,
        'message' => count($models) . ' models loaded from OpenAI',
        'models' => birkly_ai_sort_models($models),
    ];
}

/**
 * @return array{ok: bool, message: string, models: list<array{id: string, name: string, description: string, context_length: int|null}>}
 */
function birkly_ai_fetch_ollama_models(string $apiUrl = ''): array
{
    $base = resolveOllamaBaseUrl($apiUrl);
    $url = rtrim($base, '/') . '/api/tags';
    $result = birkly_ai_provider_http_request($url, [
        'method' => 'GET',
        'headers' => ['Content-Type: application/json'],
        'timeout' => 15,
    ]);

    if ($result['curl_error'] !== '') {
        return ['ok' => false, 'message' => 'Could not reach Ollama: ' . $result['curl_error'], 'models' => []];
    }

    if ($result['http_code'] !== 200) {
        return [
            'ok' => false,
            'message' => 'Ollama returned HTTP ' . $result['http_code'],
            'models' => [],
        ];
    }

    $payload = json_decode($result['body'], true);
    $rows = is_array($payload['models'] ?? null) ? $payload['models'] : [];
    $models = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = trim((string) ($row['name'] ?? ''));
        if ($id === '') {
            continue;
        }
        $models[] = birkly_ai_normalize_model_entry($id, $id);
    }

    return [
        'ok' => true,
        'message' => count($models) . ' models loaded from Ollama',
        'models' => birkly_ai_sort_models($models),
    ];
}

/**
 * Fetch live model catalog from a provider API.
 *
 * @return array{ok: bool, message: string, models: list<array{id: string, name: string, description: string, context_length: int|null}>, default_model?: string}
 */
function birkly_ai_fetch_provider_models(string $provider, string $apiKey = '', string $apiUrl = ''): array
{
    $providers = getSupportedAiProviders();
    if (!isset($providers[$provider])) {
        return ['ok' => false, 'message' => 'Unsupported provider', 'models' => []];
    }

    $info = $providers[$provider];
    $defaultModel = birkly_ai_default_model($provider);

    if ($provider === 'openrouter') {
        $result = birkly_ai_fetch_openrouter_models($apiKey);
    } elseif ($provider === 'google') {
        $result = birkly_ai_fetch_google_models($apiKey);
    } elseif ($provider === 'openai') {
        $result = birkly_ai_fetch_openai_models($apiKey);
    } elseif ($provider === 'ollama') {
        $result = birkly_ai_fetch_ollama_models($apiUrl);
    } elseif ($provider === 'anthropic') {
        $result = [
            'ok' => true,
            'message' => 'Enter a Claude model ID manually (Anthropic has no public model list API)',
            'models' => birkly_ai_sort_models([
                birkly_ai_normalize_model_entry('claude-3-5-haiku-latest', 'Claude 3.5 Haiku (latest)'),
                birkly_ai_normalize_model_entry('claude-3-5-sonnet-latest', 'Claude 3.5 Sonnet (latest)'),
                birkly_ai_normalize_model_entry('claude-3-opus-latest', 'Claude 3 Opus (latest)'),
            ]),
        ];
    } elseif ($provider === 'custom') {
        return ['ok' => true, 'message' => 'Enter the model ID required by your custom endpoint', 'models' => []];
    } else {
        return ['ok' => false, 'message' => 'Model listing is not available for this provider', 'models' => []];
    }

    if ($result['ok']) {
        $result['default_model'] = $defaultModel;
    }

    return $result;
}

/**
 * Normalize page context from admin chat client (P39-M11).
 *
 * @param array<string,mixed>|null $raw
 * @return array<string,mixed>
 */
function birkly_ai_normalize_page_context($raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $context = [
        'route' => substr((string) ($raw['route'] ?? $raw['page'] ?? ''), 0, 80),
        'collection' => substr((string) ($raw['collection'] ?? ''), 0, 120),
        'entry_id' => substr((string) ($raw['entry_id'] ?? ''), 0, 120),
        'sub_collection' => substr((string) ($raw['sub_collection'] ?? ''), 0, 120),
        'sub_entry_id' => substr((string) ($raw['sub_entry_id'] ?? ''), 0, 120),
        'field_names' => [],
        'entry_title' => '',
        'collection_name' => '',
    ];

    if (!empty($raw['field_names']) && is_array($raw['field_names'])) {
        foreach ($raw['field_names'] as $name) {
            $name = substr(trim((string) $name), 0, 80);
            if ($name !== '') {
                $context['field_names'][] = $name;
            }
        }
        $context['field_names'] = array_values(array_unique($context['field_names']));
    }

    return birkly_ai_enrich_page_context($context);
}

/**
 * Enrich page context with collection/entry metadata when ids are present.
 *
 * @param array<string,mixed> $context
 * @return array<string,mixed>
 */
function birkly_ai_enrich_page_context(array $context): array
{
    $collectionSlug = (string) ($context['collection'] ?? '');
    if ($collectionSlug === '') {
        return $context;
    }

    $collectionPath = __DIR__ . '/../content/collections/' . $collectionSlug;
    $collectionFile = $collectionPath . '/collection.json';
    if (is_file($collectionFile)) {
        $collection = json_decode((string) file_get_contents($collectionFile), true);
        if (is_array($collection)) {
            $context['collection_name'] = (string) ($collection['name'] ?? $collectionSlug);
        }
    }

    $schemaFile = $collectionPath . '/schema.json';
    if (empty($context['field_names']) && is_file($schemaFile)) {
        $schema = json_decode((string) file_get_contents($schemaFile), true);
        if (is_array($schema) && !empty($schema['fields']) && is_array($schema['fields'])) {
            foreach ($schema['fields'] as $field) {
                $name = trim((string) ($field['name'] ?? ''));
                if ($name !== '') {
                    $context['field_names'][] = $name;
                }
            }
        }
    }

    $entryId = (string) ($context['entry_id'] ?? '');
    if ($entryId !== '') {
        $entryFile = $collectionPath . '/entries/' . $entryId . '.json';
        if (is_file($entryFile)) {
            $entry = json_decode((string) file_get_contents($entryFile), true);
            if (is_array($entry)) {
                $context['entry_title'] = (string) ($entry['title'] ?? $entry['name'] ?? '');
                if (empty($context['field_names']) && !empty($entry['fields']) && is_array($entry['fields'])) {
                    $context['field_names'] = array_keys($entry['fields']);
                }
            }
        }
    }

    return $context;
}

/**
 * Build prompt section describing current admin page context.
 *
 * @param array<string,mixed> $pageContext
 */
function birkly_ai_page_context_prompt_section(array $pageContext): string
{
    if (empty($pageContext)) {
        return '';
    }

    $lines = ["CURRENT ADMIN PAGE CONTEXT:"];
    if (!empty($pageContext['route'])) {
        $lines[] = '- Route: ' . $pageContext['route'];
    }
    if (!empty($pageContext['collection'])) {
        $label = $pageContext['collection_name'] ?? $pageContext['collection'];
        $lines[] = '- Collection: ' . $label . ' (slug: ' . $pageContext['collection'] . ')';
    }
    if (!empty($pageContext['entry_id'])) {
        $entryLine = '- Entry ID: ' . $pageContext['entry_id'];
        if (!empty($pageContext['entry_title'])) {
            $entryLine .= ' (title: "' . $pageContext['entry_title'] . '")';
        }
        $lines[] = $entryLine;
    }
    if (!empty($pageContext['sub_collection'])) {
        $lines[] = '- Sub-collection: ' . $pageContext['sub_collection'];
    }
    if (!empty($pageContext['sub_entry_id'])) {
        $lines[] = '- Sub-entry ID: ' . $pageContext['sub_entry_id'];
    }
    if (!empty($pageContext['field_names']) && is_array($pageContext['field_names'])) {
        $lines[] = '- Visible fields: ' . implode(', ', $pageContext['field_names']);
    }
    $lines[] = 'Use this context when the user asks about "this entry", "this collection", or the current page.';
    $lines[] = 'Prefer read_entry / update_entry scoped to the collection and entry above when relevant.';

    return implode("\n", $lines) . "\n\n";
}

/**
 * Whether the provider supports token streaming for admin chat.
 */
function birkly_ai_provider_supports_streaming(string $provider): bool
{
    $providers = getSupportedAiProviders();
    $providerInfo = $providers[$provider] ?? null;
    $providerType = $providerInfo['type'] ?? $provider;

    return in_array($providerType, ['openai', 'openrouter', 'custom', 'ollama'], true);
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
        'context_pack' => birkly_ai_context_pack_with_plugins()
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
                $flatPermissions[] = 'read_media';
            }
            if (in_array('update', $actions) || in_array('write', $actions)) {
                $flatPermissions[] = 'manage_media';
                $flatPermissions[] = 'read_media';
            }
            if (in_array('delete', $actions)) {
                $flatPermissions[] = 'manage_media';
                $flatPermissions[] = 'read_media';
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
        // Public site (project/) — P38
        elseif ($resource === 'project_site' || $resource === 'project') {
            if (in_array('view', $actions) || in_array('read', $actions)) {
                $flatPermissions[] = 'read';
                $flatPermissions[] = 'manage_project_site';
            }
            if (in_array('create', $actions) || in_array('update', $actions) || in_array('write', $actions) || in_array('edit', $actions)) {
                $flatPermissions[] = 'manage_project_site';
            }
            if (in_array('delete', $actions)) {
                $flatPermissions[] = 'manage_project_site';
            }
        }
        
        // Also check if any action directly matches a permission name (for backward compatibility)
        foreach ($actions as $action) {
            $action = strtolower($action);
            if (in_array($action, ['read', 'write', 'delete', 'manage_content', 'manage_users', 
                                   'manage_media', 'manage_plugins', 'manage_settings', 'manage_project_site',
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
function birkly_ai_user_can_access_connection(array $connection, string $userId, ?array $currentUser = null): bool
{
    if ($userId === '') {
        return false;
    }

    $createdBy = (string) ($connection['created_by'] ?? '');
    if ($createdBy !== '' && $createdBy === $userId) {
        return true;
    }

    // Legacy connections saved without created_by — allow settings managers
    if ($createdBy === '' && $currentUser !== null) {
        if (checkPermission($currentUser, '*') || checkPermission($currentUser, 'manage_settings')) {
            return true;
        }
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
 * Resolve stable user id from session (md5 of email when id missing).
 */
function birkly_ai_resolve_session_user_id(?array $sessionUser = null): string
{
    require_once __DIR__ . '/../core/birkly_users.php';
    $sessionUser = $sessionUser ?? ($_SESSION['user'] ?? []);
    if (!is_array($sessionUser)) {
        return '';
    }
    $userId = trim((string) ($sessionUser['id'] ?? ''));
    if ($userId !== '') {
        return $userId;
    }
    $email = trim((string) ($sessionUser['email'] ?? ''));
    if ($email !== '') {
        return birkly_user_id_from_email($email);
    }
    return '';
}

/**
 * List AI connections the session user may use (outbound chat / AI Presence).
 *
 * @return list<array<string, mixed>>
 */
function birkly_list_accessible_ai_connections_for_session_user(?array $sessionUser = null): array
{
    require_once __DIR__ . '/../core/birkly_users.php';

    $sessionUser = $sessionUser ?? ($_SESSION['user'] ?? []);
    $userId = birkly_ai_resolve_session_user_id($sessionUser);
    $out = [];
    $seenIds = [];

    foreach (birkly_users_search_dirs() as $dir) {
        $storage = new Storage($dir);
        if (!is_dir($storage->basePath)) {
            continue;
        }
        foreach ($storage->list('') as $userFile) {
            try {
                $user = $storage->read($userFile);
                if (!$user || ($user['type'] ?? '') !== 'ai') {
                    continue;
                }
                if (($user['provider'] ?? '') === 'mcp') {
                    continue;
                }
                if (($user['status'] ?? 'active') !== 'active') {
                    continue;
                }
                if (!birkly_ai_user_can_access_connection($user, $userId, $sessionUser)) {
                    continue;
                }
                $id = (string) ($user['id'] ?? '');
                if ($id === '' && preg_match('/^(.+)\.json$/', $userFile, $m)) {
                    $id = $m[1];
                }
                if ($id === '' || isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;
                unset($user['api_key_encrypted'], $user['mcp_api_key']);
                $user['id'] = $id;
                $user['capabilities'] = birkly_ai_connection_capabilities($user);
                $out[] = $user;
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    return $out;
}

/**
 * Load an AI connection JSON record by id from any users directory.
 *
 * @return array<string, mixed>|null
 */
function birkly_ai_load_connection_record(string $connectionId): ?array
{
    $connectionId = trim($connectionId);
    if ($connectionId === '') {
        return null;
    }

    require_once __DIR__ . '/../core/birkly_users.php';
    require_once __DIR__ . '/storage.php';

    foreach (birkly_users_search_dirs() as $dir) {
        $storage = new Storage($dir);
        $record = $storage->read($connectionId . '.json');
        if (is_array($record) && ($record['type'] ?? '') === 'ai') {
            return $record;
        }
    }

    return null;
}

/**
 * Ensure callAiProvider() is available without running the chat API router.
 */
function birkly_ai_bootstrap_call_provider(): void
{
    if (function_exists('callAiProvider')) {
        return;
    }
    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/ai_chat.php';
    unset($GLOBALS['__HARU_INTERNAL_CALL']);
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
    $agent = birkly_ai_agents_find_by_endpoint_id($aiUserId);
    if ($endpoint === null && $agent === null) {
        return false;
    }

    $currentUser = is_array($currentUser) ? $currentUser : [];
    if (birkly_ai_agents_is_admin($currentUser)) {
        return true;
    }
    if (checkPermission($currentUser, 'manage_settings')) {
        return true;
    }

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
            'manage_content', 'manage_media', 'manage_plugins', 'read', 'write', 'delete', 'upload', 'read_media'
        ],
        'author' => [
            'manage_content', 'manage_media', 'read', 'write', 'upload', 'read_media'
        ],
        'viewer' => ['read', 'read_media']
    ];
    
    $permissions = $legacyRoles[$roleIdOrName] ?? [];
    error_log("AI Permissions: Role '{$roleIdOrName}' using legacy fallback, permissions: " . json_encode($permissions));
    return $permissions;
}

