<?php
/**
 * AI Chat API
 * Handles chat interactions with AI providers in the admin panel
 */

// Start output buffering FIRST, before any includes
if (ob_get_level() == 0) {
    ob_start();
}

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('max_execution_time', 120); // 2 minutes for AI calls (reduced for faster responses)
ini_set('memory_limit', '256M');

require_once 'session.php';
haru_secure_session_start();

// Discard any output from includes
if (ob_get_level() > 0) {
    ob_clean();
}

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once 'storage.php';
require_once 'auth.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once 'log.php';
require_once 'ai_helpers.php';
require_once 'ai_approvals.php';

$input = sanitize_input(json_decode(file_get_contents('php://input'), true));
$action = $input['action'] ?? $_GET['action'] ?? '';

// All actions require authentication
requireAuth();

// Ensure clean JSON output for all responses
register_shutdown_function(function() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
});

// Send chat message
if ($action === 'chat') {
    try {
        // Clear output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        $csrf = $input['_csrf'] ?? '';
        if (!validate_csrf_token($csrf)) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $aiConnectionId = $input['ai_connection_id'] ?? '';
        $message = $input['message'] ?? '';
        $conversationId = $input['conversation_id'] ?? '';
        
        if (empty($aiConnectionId) || empty($message)) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['status' => 'error', 'message' => 'AI connection and message are required']);
            exit;
        }
        
        // Load AI connection
        $usersStorage = new Storage('../users');
        $aiUser = $usersStorage->read($aiConnectionId . '.json');
        
        if (!$aiUser || !isset($aiUser['type']) || $aiUser['type'] !== 'ai') {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['status' => 'error', 'message' => 'AI connection not found']);
            exit;
        }
        
        if (($aiUser['status'] ?? 'active') !== 'active') {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['status' => 'error', 'message' => 'AI connection is not active']);
            exit;
        }
        
        // Check if current user can access this connection
        $currentUserId = $_SESSION['user']['id'] ?? '';
        $isOwner = isset($aiUser['created_by']) && $aiUser['created_by'] === $currentUserId;
        $shared = $aiUser['shared'] ?? 'none'; // 'none', 'all', or 'specific'
        $canAccess = false;
        
        if ($isOwner) {
            $canAccess = true;
        } elseif ($shared === 'all') {
            $canAccess = true;
        } elseif ($shared === 'specific') {
            $allowedUsers = $aiUser['allowed_users'] ?? [];
            $canAccess = in_array($currentUserId, $allowedUsers);
        }
        
        if (!$canAccess) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['status' => 'error', 'message' => 'You do not have access to this AI connection']);
            exit;
        }
        
        // Get API key (not required for MCP or Ollama)
        $apiKey = '';
        $providersWithoutKey = ['mcp', 'ollama'];
        if (!in_array($aiUser['provider'], $providersWithoutKey) && !empty($aiUser['api_key_encrypted'])) {
            $apiKey = decryptApiKey($aiUser['api_key_encrypted']);
            if (!$apiKey) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['status' => 'error', 'message' => 'Failed to decrypt API key']);
                exit;
            }
        }
        
        // Generate system context
        $systemContext = generateSystemContext($aiUser);
        
        // Build system prompt
        $systemPrompt = buildSystemPrompt($systemContext, $aiUser);
        
        // Load conversation history if conversationId is provided
        $conversationHistory = [];
        if (!empty($conversationId)) {
            $conversationStorage = new Storage('../data/ai_conversations');
            $conversationFile = $conversationId . '.json';
            $conversationData = $conversationStorage->read($conversationFile);
            if ($conversationData && isset($conversationData['messages']) && is_array($conversationData['messages'])) {
                $conversationHistory = $conversationData['messages'];
                // Keep last 10 messages for better context (reduce token usage)
                if (count($conversationHistory) > 10) {
                    $conversationHistory = array_slice($conversationHistory, -10);
                }
            }
        }
        
        // Check if this is a short follow-up request that should skip AI and go straight to fallback
        $isShortFollowUp = strlen(trim($message)) < 25 && preg_match('/\b(another|more|one|again|same|another one|yes|do it|that)\b/i', $message);
        $skipAiCall = false;
        $foundCollection = null;
        
        if ($isShortFollowUp && !empty($conversationHistory)) {
            error_log("Short follow-up detected: '$message' - searching history for collection");
            // Check if we can find a collection from recent history (check both JSON and text)
            foreach (array_reverse($conversationHistory) as $histMsg) {
                $histContent = $histMsg['content'] ?? '';
                
                // First try: Look for JSON with collection field (most reliable)
                if (preg_match('/"collection"\s*:\s*"([^"]+)"/i', $histContent, $m)) {
                    $possibleSlug = trim($m[1]);
                    // Verify it's a valid collection
                    if (!empty($systemContext['collections'])) {
                        foreach ($systemContext['collections'] as $coll) {
                            if (strtolower($coll['slug'] ?? '') === strtolower($possibleSlug)) {
                                $foundCollection = $coll['slug'];
                                error_log("Short follow-up: Found collection '$foundCollection' from JSON in history - skipping AI call");
                                $skipAiCall = true;
                                break 2;
                            }
                        }
                    }
                }
                
                // Second try: Look for collection mentions in text
                if (preg_match('/(?:for|in|to)\s+(?:the\s+)?([a-z0-9][a-z0-9\s-]+?)(?:\s+collection|$)/i', $histContent, $m)) {
                    $possibleCollection = trim($m[1]);
                    $possibleCollection = preg_replace('/\b(for|in|to|the|collection|demo|new|a|an)\b/i', '', $possibleCollection);
                    $possibleCollection = trim($possibleCollection);
                    $normalizedPossible = strtolower(preg_replace('/[^a-z0-9]+/', '-', $possibleCollection));
                    
                    if (!empty($systemContext['collections'])) {
                        foreach ($systemContext['collections'] as $coll) {
                            $collSlug = strtolower($coll['slug'] ?? '');
                            if ($collSlug === $normalizedPossible || strpos($collSlug, str_replace('-', '', $normalizedPossible)) !== false) {
                                $foundCollection = $coll['slug'];
                                error_log("Short follow-up: Found collection '$foundCollection' from text in history - skipping AI call");
                                $skipAiCall = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        
        // Call AI provider with conversation history (unless we're skipping for short follow-up)
        if (!$skipAiCall) {
            $response = callAiProvider(
                $aiUser['provider'], 
                $apiKey, 
                $aiUser['model'] ?? '', 
                $systemPrompt, 
                $message, 
                $conversationId,
                $aiUser['api_url'] ?? '',
                $conversationHistory
            );
        } else {
            // Create a fake response that will trigger fallback immediately
            // Store the found collection in a way the fallback can access it
            $response = [
                'success' => true,
                'message' => 'Creating another entry.' // Brief message, fallback will handle the rest
            ];
            // Store found collection in response so fallback can use it
            if ($foundCollection) {
                $response['_fallback_collection'] = $foundCollection;
                error_log("Skipped AI call for short follow-up - found collection: $foundCollection");
            } else {
                error_log("Skipped AI call for short follow-up - collection not found in history, will search in fallback");
            }
        }
        
        // Save conversation history after getting response
        if (!empty($conversationId) && $response['success']) {
            // Ensure storage directory exists
            $conversationStorage = new Storage('../data/ai_conversations');
            if (!is_dir($conversationStorage->basePath)) {
                mkdir($conversationStorage->basePath, 0755, true);
            }
            
            // Add user message and AI response to history (avoid duplicates)
            $lastUserMsg = end($conversationHistory);
            if (!$lastUserMsg || $lastUserMsg['role'] !== 'user' || $lastUserMsg['content'] !== $message) {
                $conversationHistory[] = ['role' => 'user', 'content' => $message];
            }
            $conversationHistory[] = ['role' => 'assistant', 'content' => $response['message']];
            
            // Keep only last 10 messages to avoid token limits and improve speed
            if (count($conversationHistory) > 10) {
                $conversationHistory = array_slice($conversationHistory, -10);
            }
            
            // Save conversation
            $conversationData = [
                'id' => $conversationId,
                'ai_connection_id' => $aiConnectionId,
                'user_id' => $currentUserId,
                'messages' => $conversationHistory,
                'updated_at' => date('c')
            ];
            $conversationStorage->write($conversationId . '.json', $conversationData);
        }
        
        if ($response['success']) {
            // Check if response contains action requests
            $actions = extractActionsFromResponse($response['message']);
            
            // Log for debugging
            error_log("AI Response: " . substr($response['message'], 0, 500));
            error_log("Extracted actions count: " . count($actions));
            if (!empty($actions)) {
                error_log("Actions: " . json_encode($actions));
                
            }

            // FALLBACK: Detect read-only requests and auto-generate actions
            if (empty($actions)) {
                $messageLower = strtolower($message);
                // List collections
                if (preg_match('/\b(list|show|get|display).*collection/i', $messageLower) || preg_match('/collection.*list|all.*collection/i', $messageLower)) {
                    $actions[] = ['type' => 'list_collections', 'data' => [], 'description' => 'List all collections'];
                    error_log("Auto-generated list_collections action");
                }
                // Questions about collections/entries (e.g., "which collection has the most entries?")
                elseif (preg_match('/\b(which|what|how many|count|most|least|largest|smallest).*(collection|entry|entries)/i', $messageLower) || 
                        preg_match('/\b(collection|entry|entries).*(most|least|largest|smallest|count|how many)/i', $messageLower)) {
                    // Auto-generate list_collections first
                    $actions[] = ['type' => 'list_collections', 'data' => [], 'description' => 'List all collections to analyze entry counts'];
                    error_log("Auto-generated list_collections action for analysis question");
                    // Note: After list_collections executes, the follow-up will get collections data
                    // and the AI should generate list_entries for each collection if needed
                }
                // List media
                elseif (preg_match('/\b(list|show|get|display).*media|media.*list|all.*media/i', $messageLower)) {
                    $actions[] = ['type' => 'list_media', 'data' => [], 'description' => 'List all media files'];
                    error_log("Auto-generated list_media action");
                }
                // List users
                elseif (preg_match('/\b(list|show|get|display).*user|user.*list|all.*user/i', $messageLower)) {
                    $actions[] = ['type' => 'list_users', 'data' => [], 'description' => 'List all users'];
                    error_log("Auto-generated list_users action");
                }
                // List pages
                elseif (preg_match('/\b(list|show|get|display).*page|page.*list|all.*page/i', $messageLower)) {
                    $actions[] = ['type' => 'list_pages', 'data' => [], 'description' => 'List all pages'];
                    error_log("Auto-generated list_pages action");
                }
                // Read settings
                elseif (preg_match('/\b(show|get|read|display).*setting|setting.*show/i', $messageLower)) {
                    $actions[] = ['type' => 'read_settings', 'data' => [], 'description' => 'Read system settings'];
                    error_log("Auto-generated read_settings action");
                }
            }

            // SIMPLIFIED FALLBACK: Only trigger if no actions AND user asked for an action
            $userAskedForAction = preg_match('/\b(create|add|make|update|delete|fix|change|another|more|one)\b/i', $message);
            
            if (empty($actions) && $userAskedForAction) {
                error_log("No actions found but user asked for action - using simplified fallback");
                // Detect "create a new collection called/ named X"
                $collectionName = null;
                if (preg_match('/create\\s+(?:a\\s+)?(?:new\\s+)?collection\\s+(?:called|named)\\s+\"?([^\"\\n]+)\"?/i', $message, $m)) {
                    $collectionName = trim($m[1]);
                } elseif (preg_match('/create\\s+(?:a\\s+)?(?:new\\s+)?collection\\s+\"?([^\"\\n]+)\"?/i', $message, $m2)) {
                    $collectionName = trim($m2[1]);
                }

                if (!empty($collectionName)) {
                    // Generate a slug from the name
                    $slug = strtolower($collectionName);
                    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
                    $slug = trim($slug, '-');
                    if ($slug === '') {
                        $slug = 'collection-' . bin2hex(random_bytes(3));
                    }

                    $fallbackAction = [
                        'type' => 'create_collection',
                        'data' => [
                            'name' => $collectionName,
                            'slug' => $slug,
                            'fields' => [
                                [
                                    'name' => 'title',
                                    'type' => 'text',
                                    'label' => 'Title',
                                    'required' => true,
                                ],
                                [
                                    'name' => 'text',
                                    'type' => 'textarea',
                                    'label' => 'Text',
                                ],
                            ],
                        ],
                        'description' => 'Create collection "' . $collectionName . '" (auto-generated fallback action)',
                    ];

                    $actions[] = $fallbackAction;
                    error_log("Fallback: auto-generated create_collection action from user message");
                }
                
                // Detect "create entry/entries for collection X" or "add entry/entries to collection X"
                $entryCollection = null;
                $entryCount = 1;
                
                // Check if this is a follow-up request ("another", "more", "additional", "one", "another one")
                $isFollowUp = preg_match('/\b(another|more|additional|one more|again|another one|one|same)\b/i', $message);
                
                // Also check if message is very short (likely a follow-up) - prioritize this
                $isShortFollowUp = strlen(trim($message)) < 25 && preg_match('/\b(another|more|one|again|same|yes|do it|that)\b/i', $message);
                if ($isShortFollowUp) {
                    $isFollowUp = true;
                    error_log("Fallback: Detected short follow-up message: '$message' (length: " . strlen(trim($message)) . ")");
                }
                
                // If it's a follow-up, try to find the collection from conversation history
                if ($isFollowUp && !empty($conversationHistory)) {
                    error_log("Fallback: Detected follow-up request, searching conversation history for collection");
                    // Look backwards through conversation history for collection mentions
                    $historyMessages = array_reverse($conversationHistory);
                    foreach ($historyMessages as $histMsg) {
                        $histContent = $histMsg['content'] ?? '';
                        // Look for collection mentions in previous messages
                        if (preg_match('/(?:collection|for|in|to)\s+([a-z0-9][a-z0-9\s-]+?)(?:\s+collection|$)/i', $histContent, $m)) {
                            $possibleCollection = trim($m[1]);
                            $possibleCollection = preg_replace('/\b(for|in|to|the|collection|demo|new|a|an)\b/i', '', $possibleCollection);
                            $possibleCollection = trim($possibleCollection);
                            // Check if this matches a known collection
                            if (!empty($context['collections'])) {
                                foreach ($context['collections'] as $coll) {
                                    $collSlug = strtolower($coll['slug'] ?? '');
                                    $collName = strtolower($coll['name'] ?? '');
                                    $normalizedPossible = strtolower(preg_replace('/[^a-z0-9]+/', '-', $possibleCollection));
                                    if ($collSlug === $normalizedPossible || 
                                        strpos($collSlug, str_replace('-', '', $normalizedPossible)) !== false ||
                                        strpos($collName, str_replace('-', ' ', $normalizedPossible)) !== false) {
                                        $entryCollection = $coll['slug'];
                                        error_log("Fallback: Found collection '$entryCollection' from conversation history");
                                        break 2;
                                    }
                                }
                            }
                        }
                        // Also check for create_entry actions in history
                        if (preg_match('/"collection"\s*:\s*"([^"]+)"/i', $histContent, $m)) {
                            $possibleSlug = trim($m[1]);
                            if (!empty($context['collections'])) {
                                foreach ($context['collections'] as $coll) {
                                    if (strtolower($coll['slug'] ?? '') === strtolower($possibleSlug)) {
                                        $entryCollection = $coll['slug'];
                                        error_log("Fallback: Found collection '$entryCollection' from action in history");
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
                
                // More comprehensive patterns to catch variations
                $entryPatterns = [
                    // "create a demo entry for the approval test collection"
                    '/create\\s+(?:a\\s+)?(?:demo\\s+)?(?:new\\s+)?entry\\s+(?:for|in|to)\\s+(?:the\\s+)?([^\"\'\\n]+?)(?:\\s+collection)?$/i',
                    // "add entry to collection X"
                    '/add\\s+(?:a\\s+)?(?:demo\\s+)?(?:new\\s+)?entry\\s+(?:for|in|to)\\s+(?:the\\s+)?([^\"\'\\n]+?)(?:\\s+collection)?$/i',
                    // "create entry for the X collection"
                    '/create\\s+(?:a\\s+)?(?:demo\\s+)?entry\\s+for\\s+the\\s+([^\"\'\\n]+?)\\s+collection/i',
                    // "create entry for collection X" (X at the end)
                    '/create\\s+(?:a\\s+)?(?:demo\\s+)?entry\\s+for\\s+(?:the\\s+)?collection\\s+([^\"\'\\n]+)/i',
                    // "create entry in X"
                    '/create\\s+(?:a\\s+)?(?:demo\\s+)?entry\\s+in\\s+([^\"\'\\n]+)/i',
                    // "add entry to X"
                    '/add\\s+(?:a\\s+)?(?:demo\\s+)?entry\\s+to\\s+([^\"\'\\n]+)/i',
                    // "create entry X" (simpler pattern)
                    '/create\\s+(?:a\\s+)?(?:demo\\s+)?entry\\s+([^\"\'\\n]+)/i',
                    // "another entry" or "more entries" (without collection specified)
                    '/\b(another|more|additional)\s+(?:demo\s+)?entry/i',
                ];
                
                foreach ($entryPatterns as $pattern) {
                    if (preg_match($pattern, $message, $m)) {
                        if (isset($m[1]) && !empty($m[1])) {
                            $entryCollection = trim($m[1]);
                            // Remove common words that might be captured
                            $entryCollection = preg_replace('/\\b(for|in|to|the|collection|demo|new|a|an)\\b/i', '', $entryCollection);
                            $entryCollection = trim($entryCollection);
                            // Remove quotes if present
                            $entryCollection = trim($entryCollection, '"\'');
                            if (!empty($entryCollection)) {
                                error_log("Fallback: Detected entry collection from pattern: '$entryCollection'");
                                break;
                            }
                        } elseif (preg_match('/\b(another|more|additional)\s+(?:demo\s+)?entry/i', $message)) {
                            // This is "another entry" without collection - we'll use history or default
                            error_log("Fallback: Detected 'another/more entry' pattern without collection");
                            break;
                        }
                    }
                }
                
                // If still not found, try a more aggressive pattern that looks for collection names
                if (empty($entryCollection)) {
                    // Look for common collection name patterns after "entry" keywords
                    if (preg_match('/(?:entry|entries).*?(?:for|in|to|collection).*?([a-z0-9][a-z0-9\s-]+?)(?:\s+collection|$)/i', $message, $m)) {
                        $entryCollection = trim($m[1]);
                        $entryCollection = preg_replace('/\\b(for|in|to|the|collection|demo|new|a|an)\\b/i', '', $entryCollection);
                        $entryCollection = trim($entryCollection);
                        $entryCollection = trim($entryCollection, '"\'');
                        if (!empty($entryCollection)) {
                            error_log("Fallback: Detected entry collection from aggressive pattern: '$entryCollection'");
                        }
                    }
                }
                
                // Check if collection was pre-found when skipping AI call
                if (empty($entryCollection) && isset($response['_fallback_collection'])) {
                    $entryCollection = $response['_fallback_collection'];
                    error_log("Fallback: Using pre-found collection from skip-AI logic: '$entryCollection'");
                }
                
                // If still no collection found but it's a follow-up, try to use the most recent collection from history
                if (empty($entryCollection) && ($isFollowUp || $isShortFollowUp) && !empty($conversationHistory)) {
                    error_log("Fallback: Searching conversation history for collection (follow-up detected)");
                    // Look for the most recently mentioned collection in actions (check both user and assistant messages)
                    foreach (array_reverse($conversationHistory) as $histMsg) {
                        $histContent = $histMsg['content'] ?? '';
                        
                        // Look for JSON with collection field (most reliable)
                        if (preg_match('/"collection"\s*:\s*"([^"]+)"/i', $histContent, $m)) {
                            $possibleSlug = trim($m[1]);
                            // Verify it's a valid collection
                            if (!empty($context['collections'])) {
                                foreach ($context['collections'] as $coll) {
                                    if (strtolower($coll['slug'] ?? '') === strtolower($possibleSlug)) {
                                        $entryCollection = $coll['slug'];
                                        error_log("Fallback: Using most recent collection '$entryCollection' from JSON in history");
                                        break 2;
                                    }
                                }
                            }
                        }
                        
                        // Also check for collection mentions in text (e.g., "approval test collection")
                        if (preg_match('/(?:for|in|to)\s+(?:the\s+)?([a-z0-9][a-z0-9\s-]+?)(?:\s+collection|$)/i', $histContent, $m)) {
                            $possibleCollection = trim($m[1]);
                            $possibleCollection = preg_replace('/\b(for|in|to|the|collection|demo|new|a|an)\b/i', '', $possibleCollection);
                            $possibleCollection = trim($possibleCollection);
                            $normalizedPossible = strtolower(preg_replace('/[^a-z0-9]+/', '-', $possibleCollection));
                            
                            if (!empty($context['collections'])) {
                                foreach ($context['collections'] as $coll) {
                                    $collSlug = strtolower($coll['slug'] ?? '');
                                    if ($collSlug === $normalizedPossible || strpos($collSlug, str_replace('-', '', $normalizedPossible)) !== false) {
                                        $entryCollection = $coll['slug'];
                                        error_log("Fallback: Using collection '$entryCollection' from text mention in history");
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Check for multiple entries
                if (preg_match('/(\\d+)\\s+entries?/i', $message, $mCount)) {
                    $entryCount = (int)$mCount[1];
                } elseif (preg_match('/entries?/i', $message) && preg_match('/two|2|three|3|four|4|five|5/i', $message)) {
                    if (preg_match('/two|2/i', $message)) $entryCount = 2;
                    elseif (preg_match('/three|3/i', $message)) $entryCount = 3;
                    elseif (preg_match('/four|4/i', $message)) $entryCount = 4;
                    elseif (preg_match('/five|5/i', $message)) $entryCount = 5;
                }
                
                // If no collection found but user asked for entry, try to use first available collection as fallback
                if (empty($entryCollection) && preg_match('/\b(entry|entries|another|more)\b/i', $message)) {
                    if (!empty($context['collections']) && count($context['collections']) === 1) {
                        // Only one collection available, use it
                        $entryCollection = $context['collections'][0]['slug'];
                        error_log("Fallback: No collection specified, using only available collection: '$entryCollection'");
                    } elseif (!empty($context['collections'])) {
                        // Multiple collections - try to find a "demo" or common one
                        foreach ($context['collections'] as $coll) {
                            $collSlug = strtolower($coll['slug'] ?? '');
                            if (strpos($collSlug, 'demo') !== false || strpos($collSlug, 'test') !== false) {
                                $entryCollection = $coll['slug'];
                                error_log("Fallback: No collection specified, using demo/test collection: '$entryCollection'");
                                break;
                            }
                        }
                        // If still not found, use the first one
                        if (empty($entryCollection)) {
                            $entryCollection = $context['collections'][0]['slug'];
                            error_log("Fallback: No collection specified, using first available: '$entryCollection'");
                        }
                    }
                }
                
                if (!empty($entryCollection)) {
                    // Normalize collection name (remove "collection" suffix, handle "approval test" -> "approval-test")
                    $collectionName = trim($entryCollection);
                    $collectionName = preg_replace('/\\s+collection$/i', '', $collectionName);
                    
                    // Find the collection in context first (try exact match, then fuzzy match)
                    $targetCollection = null;
                    $collectionSlug = null;
                    
                    if (!empty($context['collections'])) {
                        // First try: exact slug match
                        $normalizedInput = strtolower($collectionName);
                        $normalizedInput = preg_replace('/[^a-z0-9]+/', '-', $normalizedInput);
                        $normalizedInput = trim($normalizedInput, '-');
                        
                        foreach ($context['collections'] as $coll) {
                            $collSlug = $coll['slug'] ?? '';
                            $collName = strtolower($coll['name'] ?? '');
                            
                            // Try exact slug match
                            if (strtolower($collSlug) === $normalizedInput) {
                                $targetCollection = $coll;
                                $collectionSlug = $collSlug;
                                break;
                            }
                            
                            // Try name match (handle "approval test" -> "approval-test")
                            $normalizedCollName = preg_replace('/[^a-z0-9]+/', '-', $collName);
                            $normalizedCollName = trim($normalizedCollName, '-');
                            if ($normalizedCollName === $normalizedInput) {
                                $targetCollection = $coll;
                                $collectionSlug = $collSlug;
                                break;
                            }
                            
                            // Try partial match (e.g., "approval test" matches "approval-test")
                            if (strpos($normalizedInput, str_replace('-', '', $normalizedCollName)) !== false ||
                                strpos(str_replace('-', '', $normalizedCollName), str_replace(' ', '', $normalizedInput)) !== false) {
                                $targetCollection = $coll;
                                $collectionSlug = $collSlug;
                                break;
                            }
                        }
                        
                        // If still not found, use the normalized input as slug
                        if (!$targetCollection) {
                            $collectionSlug = $normalizedInput;
                            error_log("Fallback: Collection not found in context, using normalized slug: $collectionSlug");
                        }
                    } else {
                        // No collections in context, use normalized input
                        $collectionSlug = strtolower($collectionName);
                        $collectionSlug = preg_replace('/[^a-z0-9]+/', '-', $collectionSlug);
                        $collectionSlug = trim($collectionSlug, '-');
                        error_log("Fallback: No collections in context, using slug: $collectionSlug");
                    }
                    
                    // Generate entry/entries
                    for ($i = 1; $i <= $entryCount; $i++) {
                        $entryTitle = $entryCount > 1 ? "Demo Entry $i" : "Demo Entry";
                        $entrySlug = strtolower($entryTitle);
                        $entrySlug = preg_replace('/[^a-z0-9]+/', '-', $entrySlug);
                        $entrySlug = trim($entrySlug, '-') . '-' . bin2hex(random_bytes(2));
                        
                        $entryData = [
                            'collection' => $collectionSlug,
                            'title' => $entryTitle,
                            'slug' => $entrySlug,
                        ];
                        
                        // Add fields from collection schema if available
                        if ($targetCollection && !empty($targetCollection['fields'])) {
                            foreach ($targetCollection['fields'] as $field) {
                                $fieldName = $field['name'] ?? '';
                                if ($fieldName && $fieldName !== 'title' && $fieldName !== 'slug') {
                                    $fieldType = $field['type'] ?? 'text';
                                    // Generate demo content based on field type
                                    if ($fieldType === 'textarea' || $fieldType === 'markdown') {
                                        $entryData[$fieldName] = "This is demo content for the $fieldName field.";
                                    } elseif ($fieldType === 'text') {
                                        $entryData[$fieldName] = "Demo $fieldName";
                                    } elseif ($fieldType === 'date') {
                                        $entryData[$fieldName] = date('Y-m-d');
                                    } else {
                                        $entryData[$fieldName] = "Demo value";
                                    }
                                }
                            }
                        } else {
                            // Default fields if schema not found
                            $entryData['text'] = "This is a demo entry for the $collectionSlug collection.";
                        }
                        
                        $fallbackAction = [
                            'type' => 'create_entry',
                            'data' => $entryData,
                            'description' => 'Create demo entry in ' . $collectionSlug . ' collection (auto-generated fallback action)',
                        ];
                        
                        $actions[] = $fallbackAction;
                    }
                    
                    error_log("Fallback: auto-generated " . $entryCount . " create_entry action(s) for collection '$collectionSlug' from user message");
                }
            }
            
            // Separate read actions (which should be executed immediately and data returned) 
            // from write actions (which may need approval)
            $readActions = [];
            $writeActions = [];
            foreach ($actions as $action) {
                $actionType = $action['type'] ?? '';
                if (in_array($actionType, ['read_entry', 'list_entries', 'list_collections', 'list_media', 'read_settings', 'list_users', 'read_user', 'read_collection', 'read_media', 'list_pages', 'read_page', 'list_activity', 'list_roles', 'read_role', 'list_plugins'])) {
                    $readActions[] = $action;
                } else {
                    $writeActions[] = $action;
                }
            }
            
            error_log("Initial readActions count: " . count($readActions));
            error_log("Initial writeActions count: " . count($writeActions));
            
            // Execute read actions first and include their data in the response
            $readData = [];
            $hasSuccessfulReads = false;
            $executedActions = []; // Track which actions we've executed to avoid duplicates
            if (!empty($readActions)) {
                foreach ($readActions as $actionData) {
                    $actionKey = ($actionData['type'] ?? '') . '_' . json_encode($actionData['data'] ?? []);
                    if (isset($executedActions[$actionKey])) {
                        continue; // Skip if already executed
                    }
                    $executedActions[$actionKey] = true;
                    
                    try {
                        $result = executeReadAction($actionData, $aiUser);
                        // Store the action data with the result so we can match them later
                        if ($result['success'] ?? false) {
                            // Add collection slug to list_entries results for easier matching
                            if (($actionData['type'] ?? '') === 'list_entries' && isset($actionData['data']['collection'])) {
                                $result['data']['collection'] = $actionData['data']['collection'];
                            }
                        }
                        $readData[] = $result;
                        if ($result['success'] ?? false) {
                            $hasSuccessfulReads = true;
                            
                            // AUTO-CHAIN: If user asked about entry counts and we just got collections, auto-generate list_entries for each
                            if ($actionData['type'] === 'list_collections' && isset($result['data']['collections'])) {
                                $messageLower = strtolower($message);
                                if (preg_match('/\b(which|what|how many|count|most|least|largest|smallest).*(collection|entry|entries)/i', $messageLower) || 
                                    preg_match('/\b(collection|entry|entries).*(most|least|largest|smallest|count|how many)/i', $messageLower)) {
                                    $collections = $result['data']['collections'];
                                    error_log("Auto-generating list_entries for " . count($collections) . " collections to answer entry count question");
                                    
                                    // Generate and execute list_entries for each collection
                                    $additionalReadActions = [];
                                    foreach ($collections as $collection) {
                                        $slug = $collection['slug'] ?? '';
                                        if ($slug) {
                                            $additionalReadActions[] = [
                                                'type' => 'list_entries',
                                                'data' => ['collection' => $slug, 'limit' => 1000],
                                                'description' => 'Count entries in ' . ($collection['name'] ?? $slug)
                                            ];
                                        }
                                    }
                                    
                                    // Execute the newly generated list_entries actions
                                    foreach ($additionalReadActions as $newActionData) {
                                        $newActionKey = ($newActionData['type'] ?? '') . '_' . json_encode($newActionData['data'] ?? []);
                                        if (isset($executedActions[$newActionKey])) {
                                            continue;
                                        }
                                        $executedActions[$newActionKey] = true;
                                        
                                        try {
                                            $newResult = executeReadAction($newActionData, $aiUser);
                                            $readData[] = $newResult;
                                            if ($newResult['success'] ?? false) {
                                                $hasSuccessfulReads = true;
                                            }
                                        } catch (Exception $e) {
                                            error_log("Exception executing auto-generated list_entries: " . $e->getMessage());
                                            $readData[] = [
                                                'success' => false,
                                                'message' => 'Error: ' . $e->getMessage()
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Exception executing read action: " . $e->getMessage());
                        $readData[] = [
                            'success' => false,
                            'message' => 'Error reading entry: ' . $e->getMessage()
                        ];
                    }
                }
            }
            
            // If we successfully read data, make a follow-up call to the AI with the read data
            // This allows the AI to analyze the data and either:
            // 1. Answer questions (like "which collection has the most entries?")
            // 2. Create write actions based on analysis (like "check for typos and fix them")
            if ($hasSuccessfulReads && !empty($readData)) {
                // Build a follow-up message with the read data
                $followUpMessage = "Context: The user asked: \"" . $message . "\"\n\n";
                $followUpMessage .= "I've successfully retrieved the requested data. Here's what I found:\n\n";
                foreach ($readData as $readResult) {
                    if ($readResult['success'] ?? false) {
                        if (isset($readResult['data']['entry'])) {
                            $entry = $readResult['data']['entry'];
                            // Extract collection from the read action that was executed
                            $collectionSlug = '';
                            foreach ($readActions as $readAction) {
                                if (isset($readAction['data']['collection'])) {
                                    $collectionSlug = $readAction['data']['collection'];
                                    break;
                                }
                            }
                            $followUpMessage .= "ENTRY DATA:\n";
                            $followUpMessage .= "- Collection: " . $collectionSlug . "\n";
                            $followUpMessage .= "- Title: " . ($entry['title'] ?? 'N/A') . "\n";
                            $followUpMessage .= "- Slug: " . ($entry['slug'] ?? 'N/A') . "\n";
                            $followUpMessage .= "- ID: " . ($entry['id'] ?? 'N/A') . "\n";
                            $followUpMessage .= "\nFULL ENTRY CONTENT (JSON):\n";
                            $followUpMessage .= json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                        } elseif (isset($readResult['data']['entries'])) {
                            $entries = $readResult['data']['entries'];
                            $collectionSlug = '';
                            foreach ($readActions as $readAction) {
                                if (($readAction['type'] ?? '') === 'list_entries' && isset($readAction['data']['collection'])) {
                                    $collectionSlug = $readAction['data']['collection'];
                                    break;
                                }
                            }
                            
                            // Always include full entry data so AI can analyze content length, word count, etc.
                            $followUpMessage .= "FOUND " . count($entries) . " ENTRIES in collection '" . $collectionSlug . "':\n";
                            foreach ($entries as $entry) {
                                $followUpMessage .= "- " . ($entry['title'] ?? 'Untitled') . " (slug: " . ($entry['slug'] ?? 'N/A') . ", id: " . ($entry['id'] ?? 'N/A') . ")\n";
                            }
                            $followUpMessage .= "\nFULL ENTRIES DATA (JSON):\n";
                            $followUpMessage .= json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                        } elseif (isset($readResult['data']['collections'])) {
                            $collections = $readResult['data']['collections'];
                            $followUpMessage .= "FOUND " . count($collections) . " COLLECTIONS:\n";
                            foreach ($collections as $collection) {
                                $followUpMessage .= "- " . ($collection['name'] ?? 'Untitled') . " (slug: " . ($collection['slug'] ?? 'N/A') . ")\n";
                                if (!empty($collection['description'])) {
                                    $followUpMessage .= "  Description: " . $collection['description'] . "\n";
                                }
                            }
                            $followUpMessage .= "\nFULL COLLECTIONS DATA (JSON):\n";
                            $followUpMessage .= json_encode($collections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                            
                            // If user asked about entry counts, hint that AI should generate list_entries for each collection
                            $messageLower = strtolower($message);
                            if (preg_match('/\b(which|what|how many|count|most|least|largest|smallest).*(collection|entry|entries)/i', $messageLower) || 
                                preg_match('/\b(collection|entry|entries).*(most|least|largest|smallest|count|how many)/i', $messageLower)) {
                                $followUpMessage .= "═══════════════════════════════════════════════════════════════════════════════\n";
                                $followUpMessage .= "CRITICAL: USER ASKED ABOUT ENTRY COUNTS\n";
                                $followUpMessage .= "═══════════════════════════════════════════════════════════════════════════════\n";
                                $followUpMessage .= "To answer the user's question, you MUST execute list_entries for EACH collection listed above.\n";
                                $followUpMessage .= "DO NOT just explain - EXECUTE the actions!\n\n";
                                $followUpMessage .= "You MUST include a JSON action block with list_entries for each collection:\n";
                                $followUpMessage .= "```json\n";
                                $followUpMessage .= "{\"actions\":[\n";
                                $actionExamples = [];
                                foreach ($collections as $collection) {
                                    $slug = $collection['slug'] ?? '';
                                    if ($slug) {
                                        $actionExamples[] = "  {\"type\":\"list_entries\",\"data\":{\"collection\":\"$slug\",\"limit\":1000},\"description\":\"Count entries in " . ($collection['name'] ?? $slug) . "\"}";
                                    }
                                }
                                $followUpMessage .= implode(",\n", $actionExamples) . "\n";
                                $followUpMessage .= "]}\n";
                                $followUpMessage .= "```\n\n";
                                $followUpMessage .= "After I execute these actions, I will provide you with all entry counts, and you can then answer the user's question.\n";
                                $followUpMessage .= "═══════════════════════════════════════════════════════════════════════════════\n\n";
                            }
                        } elseif (isset($readResult['data']['media'])) {
                            $media = $readResult['data']['media'];
                            $followUpMessage .= "FOUND " . count($media) . " MEDIA FILES:\n";
                            foreach ($media as $item) {
                                $followUpMessage .= "- " . ($item['name'] ?? 'Untitled') . " (type: " . ($item['type'] ?? 'N/A') . ", size: " . ($item['size'] ?? 0) . " bytes)\n";
                            }
                            $followUpMessage .= "\nFULL MEDIA DATA (JSON):\n";
                            $followUpMessage .= json_encode($media, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                        } elseif (isset($readResult['data']['users'])) {
                            $users = $readResult['data']['users'];
                            $followUpMessage .= "FOUND " . count($users) . " USERS:\n";
                            foreach ($users as $user) {
                                $followUpMessage .= "- " . ($user['email'] ?? 'N/A') . " (role: " . ($user['role'] ?? 'N/A') . ")\n";
                            }
                            $followUpMessage .= "\nFULL USERS DATA (JSON):\n";
                            $followUpMessage .= json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                        } elseif (isset($readResult['data']['pages'])) {
                            $pages = $readResult['data']['pages'];
                            $followUpMessage .= "FOUND " . count($pages) . " PAGES:\n";
                            foreach ($pages as $page) {
                                $followUpMessage .= "- " . ($page['title'] ?? 'Untitled') . " (slug: " . ($page['slug'] ?? 'N/A') . ")\n";
                            }
                            $followUpMessage .= "\nFULL PAGES DATA (JSON):\n";
                            $followUpMessage .= json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                        }
                    }
                }
                // Build entry counts summary from readData
                $entryCounts = [];
                $collectionNames = [];
                
                // First, extract collection names from list_collections result
                foreach ($readData as $readResult) {
                    if ($readResult['success'] ?? false && isset($readResult['data']['collections'])) {
                        foreach ($readResult['data']['collections'] as $coll) {
                            $slug = $coll['slug'] ?? '';
                            if ($slug) {
                                $collectionNames[$slug] = $coll['name'] ?? $coll['title'] ?? $slug;
                            }
                        }
                    }
                }
                
                // Then, extract entry counts from list_entries results (collection slug is now in result data)
                foreach ($readData as $readResult) {
                    if ($readResult['success'] ?? false && isset($readResult['data']['entries'])) {
                        $collection = $readResult['data']['collection'] ?? '';
                        if ($collection) {
                            $entryCounts[$collection] = count($readResult['data']['entries']);
                        }
                    }
                }
                
                // Note: We don't add hardcoded summaries here - let the AI analyze the data dynamically
                // The AI has all the entry data above and can calculate counts, content length, etc. as needed
                
                $followUpMessage .= "INSTRUCTIONS:\n";
                $followUpMessage .= "1. Based on the user's request: \"" . $message . "\", analyze ALL the data above and provide a direct answer.\n";
                $followUpMessage .= "2. You have FULL entry data for all collections above. Analyze the content, length, word count, or any other aspect as needed.\n";
                $followUpMessage .= "3. For questions about content length: Calculate the total character/word count for each collection by summing all entry content fields (title, content, description, etc.).\n";
                $followUpMessage .= "4. For questions about entry counts: Compare the entry counts listed above.\n";
                $followUpMessage .= "5. For ANY analysis question: Analyze the data and provide the answer directly. DO NOT say you need more data - you have it above.\n";
                $followUpMessage .= "6. If the user asked to list/show data, format it nicely in a readable way (tables, lists, etc.).\n";
                $followUpMessage .= "7. If the user asked you to check for issues (grammar, typos, etc.), identify all problems.\n";
                $followUpMessage .= "8. If the user asked you to fix issues, create an 'update_entry' action with the corrected data.\n";
                $followUpMessage .= "9. Use the EXACT field names from the collection schema (as listed in the system prompt).\n";
                $followUpMessage .= "10. Include the collection slug and entry slug/id in your update_entry action.\n";
                $followUpMessage .= "11. Provide a clear, direct answer to the user's question. DO NOT include explanations about how to do it - just do it and answer.\n";
                $followUpMessage .= "12. IMPORTANT: For questions requiring data analysis, provide the answer directly. For write requests, include JSON action blocks.\n";
                
                // Make follow-up call to AI with the read data
                error_log("Making follow-up AI call with read data");
                try {
                    $followUpResponse = callAiProvider(
                        $aiUser['provider'], 
                        $apiKey, 
                        $aiUser['model'] ?? '', 
                        $systemPrompt, 
                        $followUpMessage, 
                        $conversationId,
                        $aiUser['api_url'] ?? '',
                        $conversationHistory // Pass conversation history to follow-up call too
                    );
                    
                    if ($followUpResponse['success']) {
                        error_log("Follow-up AI call succeeded, message length: " . strlen($followUpResponse['message'] ?? ''));
                        // Extract actions from follow-up response
                        $followUpActions = extractActionsFromResponse($followUpResponse['message']);
                        error_log("Follow-up extracted " . count($followUpActions) . " actions");
                        
                        // Separate read and write actions from follow-up
                        $followUpReadActions = [];
                        foreach ($followUpActions as $action) {
                            $actionType = $action['type'] ?? '';
                            if (in_array($actionType, ['read_entry', 'list_entries', 'list_collections', 'list_media', 'read_settings', 'list_users', 'read_user', 'read_collection', 'read_media', 'list_pages', 'read_page', 'list_activity', 'list_roles', 'read_role', 'list_plugins'])) {
                                $followUpReadActions[] = $action;
                            } else {
                                $writeActions[] = $action;
                                error_log("Added write action from follow-up: " . $actionType);
                            }
                        }
                        
                        // Execute any additional read actions from follow-up (for chaining, e.g., list_collections then list_entries for each)
                        if (!empty($followUpReadActions)) {
                            error_log("Executing " . count($followUpReadActions) . " additional read actions from follow-up");
                            foreach ($followUpReadActions as $actionData) {
                                try {
                                    $result = executeReadAction($actionData, $aiUser);
                                    $readData[] = $result;
                                    if ($result['success'] ?? false) {
                                        $hasSuccessfulReads = true;
                                    }
                                } catch (Exception $e) {
                                    error_log("Exception executing follow-up read action: " . $e->getMessage());
                                    $readData[] = [
                                        'success' => false,
                                        'message' => 'Error: ' . $e->getMessage()
                                    ];
                                }
                            }
                            
                            // If we got more data, make another follow-up call with all the data
                            if ($hasSuccessfulReads && !empty($readData)) {
                                error_log("Making second follow-up call with all collected data");
                                $secondFollowUpMessage = "Context: The user asked: \"" . $message . "\"\n\n";
                                $secondFollowUpMessage .= "I've now collected ALL the necessary data. Here's everything:\n\n";
                                foreach ($readData as $readResult) {
                                    if ($readResult['success'] ?? false) {
                                        $secondFollowUpMessage .= json_encode($readResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                                    }
                                }
                                $secondFollowUpMessage .= "INSTRUCTIONS:\n";
                                $secondFollowUpMessage .= "1. Analyze ALL the data above to answer the user's question: \"" . $message . "\"\n";
                                $secondFollowUpMessage .= "2. You have FULL entry data for all collections. Analyze content length, word count, entry counts, or any other aspect as needed.\n";
                                $secondFollowUpMessage .= "3. Provide a direct, clear answer based on your analysis.\n";
                                $secondFollowUpMessage .= "4. DO NOT ask for more data - you have everything you need.\n";
                                
                                try {
                                    $secondFollowUpResponse = callAiProvider(
                                        $aiUser['provider'], 
                                        $apiKey, 
                                        $aiUser['model'] ?? '', 
                                        $systemPrompt, 
                                        $secondFollowUpMessage, 
                                        $conversationId,
                                        $aiUser['api_url'] ?? '',
                                        $conversationHistory
                                    );
                                    
                                    if ($secondFollowUpResponse['success']) {
                                        $response['message'] = $secondFollowUpResponse['message'];
                                        // Check for any write actions in the second follow-up
                                        $secondFollowUpActions = extractActionsFromResponse($secondFollowUpResponse['message']);
                                        foreach ($secondFollowUpActions as $action) {
                                            $actionType = $action['type'] ?? '';
                                            if (!in_array($actionType, ['read_entry', 'list_entries', 'list_collections', 'list_media', 'read_settings', 'list_users', 'read_user', 'read_collection', 'read_media', 'list_pages', 'read_page', 'list_activity', 'list_roles', 'read_role', 'list_plugins'])) {
                                                $writeActions[] = $action;
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    error_log("Exception in second follow-up call: " . $e->getMessage());
                                }
                            } else {
                                // Update the main response message to include the follow-up analysis
                                $response['message'] = $followUpResponse['message'];
                            }
                        } else {
                            // Update the main response message to include the follow-up analysis
                            $response['message'] = $followUpResponse['message'];
                        }
                    } else {
                        error_log("Follow-up AI call failed: " . ($followUpResponse['message'] ?? 'Unknown error'));
                        error_log("Follow-up response: " . json_encode($followUpResponse));
                        // If follow-up fails but we have read data, format it ourselves
                        if (!empty($readData) && empty($writeActions)) {
                            error_log("Follow-up failed but we have read data - formatting response manually");
                            
                            // Build entry counts summary
                            $entryCounts = [];
                            $collectionNames = [];
                            
                            // Extract collection names
                            foreach ($readData as $readResult) {
                                if ($readResult['success'] ?? false && isset($readResult['data']['collections'])) {
                                    foreach ($readResult['data']['collections'] as $coll) {
                                        $slug = $coll['slug'] ?? '';
                                        if ($slug) {
                                            $collectionNames[$slug] = $coll['name'] ?? $coll['title'] ?? $slug;
                                        }
                                    }
                                }
                            }
                            
                    // Extract entry counts - collection slug is now stored in the result data
                    foreach ($readData as $readResult) {
                        if ($readResult['success'] ?? false && isset($readResult['data']['entries'])) {
                            $collection = $readResult['data']['collection'] ?? '';
                            if ($collection) {
                                $entryCounts[$collection] = count($readResult['data']['entries']);
                            }
                        }
                    }
                            
                            // Generic fallback response:
                            // At this point we successfully retrieved data, but the AI analysis call failed (timeout / connection).
                            // To avoid hard-coded answers (like \"most entries\"), just explain what we have and that analysis failed.
                            $totalCollections = 0;
                            $totalEntries     = 0;
                            foreach ($readData as $readResult) {
                                if ($readResult['success'] ?? false) {
                                    if (isset($readResult['data']['collections']) && is_array($readResult['data']['collections'])) {
                                        $totalCollections += count($readResult['data']['collections']);
                                    }
                                    if (isset($readResult['data']['entries']) && is_array($readResult['data']['entries'])) {
                                        $totalEntries += count($readResult['data']['entries']);
                                    }
                                }
                            }

                            $formattedResponse  = "I've retrieved the requested data (";
                            $formattedResponse .= $totalCollections . " collections, " . $totalEntries . " entries), ";
                            $formattedResponse .= "but the analysis step timed out due to a connection issue with the AI provider.\n\n";
                            $formattedResponse .= "Please try asking the question again in a moment so the AI can analyze the data dynamically ";
                            $formattedResponse .= "(e.g. longest content, statistics, comparisons) instead of a static fallback.\n";

                            $response['message'] = $formattedResponse;
                        } else {
                            // Keep the original response if follow-up fails
                        }
                    }
                } catch (Exception $e) {
                    error_log("Exception in follow-up AI call: " . $e->getMessage());
                    error_log("Stack trace: " . $e->getTraceAsString());
                    // Keep the original response if follow-up fails
                }
            }
            
            // Use only write actions for approval/execution
            $actions = $writeActions;
            
            error_log("Final writeActions count before processing: " . count($actions));
            if (!empty($actions)) {
                error_log("Final writeActions: " . json_encode($actions));
            }
            
            $requireApproval = $aiUser['require_approval'] ?? true;
            // Handle boolean string values from form submissions
            if (is_string($requireApproval)) {
                $requireApproval = ($requireApproval === '1' || $requireApproval === 'true' || $requireApproval === 'yes');
            }
            error_log("require_approval setting: " . ($requireApproval ? 'true' : 'false') . " (raw: " . var_export($aiUser['require_approval'] ?? 'not set', true) . ")");
            
            if (!empty($actions)) {
                if ($requireApproval) {
                    // Create approval requests for each action
                    $approvalIds = [];
                    $approvalStorage = new Storage('../data/ai_approvals');
                    
                    // Ensure storage directory exists
                    if (!is_dir($approvalStorage->basePath)) {
                        mkdir($approvalStorage->basePath, 0755, true);
                    }
                    
                    foreach ($actions as $actionData) {
                        $approvalId = 'approval_' . bin2hex(random_bytes(8));
                        $approval = [
                            'id' => $approvalId,
                            'ai_user_id' => $aiConnectionId,
                            'action_type' => $actionData['type'] ?? 'unknown',
                            'action_data' => $actionData['data'] ?? [],
                            'description' => $actionData['description'] ?? substr($response['message'], 0, 200),
                            'status' => 'pending',
                            'created_at' => date('c'),
                            'conversation_id' => $conversationId
                        ];
                        
                        $writeResult = $approvalStorage->write($approvalId . '.json', $approval);
                        if ($writeResult) {
                            $approvalIds[] = $approvalId;
                            error_log("Created approval: " . $approvalId);
                        } else {
                            error_log("Failed to write approval: " . $approvalId);
                        }
                    }
                    
                    // Clear output buffers and send response
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header('Content-Type: application/json; charset=utf-8');
                    }
                    // Load full approval details for the chat UI
                    $approvalDetails = [];
                    foreach ($approvalIds as $approvalId) {
                        $approval = $approvalStorage->read($approvalId . '.json');
                        if ($approval) {
                            $approvalDetails[] = [
                                'id' => $approval['id'],
                                'action_type' => $approval['action_type'],
                                'description' => $approval['description'],
                                'action_data' => $approval['action_data'],
                                'created_at' => $approval['created_at']
                            ];
                        }
                    }
                    
                    $output = json_encode([
                        'status' => 'ok',
                        'data' => [
                            'response' => $response['message'],
                            'actions_created' => count($approvalIds),
                            'approval_ids' => $approvalIds,
                            'approvals' => $approvalDetails, // Include full approval details for chat UI
                            'requires_approval' => true,
                            'read_data' => $readData
                        ]
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    echo $output;
                    exit;
                } else {
                    // Execute actions directly without approval
                    $executedActions = [];
                    $hasErrors = false;
                    foreach ($actions as $actionData) {
                        try {
                            $result = executeActionDirectly($actionData, $aiUser);
                            $executedActions[] = $result;
                            if (!$result['success']) {
                                $hasErrors = true;
                                error_log("Action execution failed: " . json_encode($result));
                            }
                        } catch (Exception $e) {
                            error_log("Exception executing action: " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
                            $executedActions[] = [
                                'success' => false,
                                'message' => 'Error executing action: ' . $e->getMessage()
                            ];
                            $hasErrors = true;
                        } catch (Error $e) {
                            error_log("Fatal error executing action: " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
                            $executedActions[] = [
                                'success' => false,
                                'message' => 'Fatal error executing action: ' . $e->getMessage()
                            ];
                            $hasErrors = true;
                        }
                    }
                    
                    // Clear output buffers and send response
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header('Content-Type: application/json; charset=utf-8');
                    }
                    $output = json_encode([
                        'status' => $hasErrors ? 'partial' : 'ok',
                        'data' => [
                            'response' => $response['message'],
                            'actions_executed' => count($executedActions),
                            'execution_results' => $executedActions,
                            'requires_approval' => false,
                            'has_errors' => $hasErrors,
                            'read_data' => $readData
                        ]
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    echo $output;
                    exit;
                }
            } else {
                // No write actions - just return the response with read data
                // Ensure we have a message - use AI's response from follow-up call
                $finalMessage = $response['message'] ?? '';
                
                // Only use fallback formatting if AI didn't provide any response at all
                if (empty($finalMessage) && !empty($readData)) {
                    error_log("AI didn't provide a response but we have read data - using generic fallback");
                    $finalMessage = "I've retrieved the requested data. " . count($readData) . " read operation(s) completed successfully.";
                }
                
                // Clear output buffers and send response
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                }
                $output = json_encode([
                    'status' => 'ok',
                    'data' => [
                        'response' => $finalMessage,
                        'requires_approval' => false,
                        'read_data' => $readData
                    ]
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo $output;
                exit;
            }
        } else {
            // Clear output buffers and send error
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $output = json_encode([
                'status' => 'error',
                'message' => $response['message'] ?? 'Failed to get AI response'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo $output;
            exit;
        }
    } catch (Exception $e) {
        // Log the error
        error_log("AI Chat Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Clear output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'An error occurred while processing your request: ' . $e->getMessage()
        ]);
        exit;
    } catch (Error $e) {
        // Catch fatal errors (PHP 7+)
        error_log("AI Chat Fatal Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Clear output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'A fatal error occurred. Please check the server logs.'
        ]);
        exit;
    }
}

// Build system prompt with context (SIMPLIFIED VERSION)
function buildSystemPrompt($context, $aiUser) {
    // Use simplified prompt builder
    require_once __DIR__ . '/ai_chat_simple_prompt.php';
    return buildSystemPromptSimple($context, $aiUser);
}

// OLD COMPLEX PROMPT - KEPT FOR REFERENCE BUT NOT USED
function buildSystemPromptOld($context, $aiUser) {
    $prompt = "You are an AI assistant integrated into HaruCMS, a flat-file content management system.\n\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "CRITICAL CONTEXT: You are operating WITHIN HaruCMS.\n";
    $prompt .= "All user questions are about THIS specific HaruCMS installation.\n";
    $prompt .= "DO NOT provide generic web development advice (React, Vue, external APIs, etc.).\n";
    $prompt .= "ALWAYS provide HaruCMS-specific solutions using HaruCMS templating syntax.\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n\n";
    
    $prompt .= "SYSTEM INFORMATION:\n";
    $prompt .= "- CMS Name: " . ($context['system_info']['cms_name'] ?? 'HaruCMS') . "\n";
    $prompt .= "- Version: " . ($context['system_info']['version'] ?? '1.0') . "\n";
    $prompt .= "- Your role: " . ($aiUser['role'] ?? 'viewer') . "\n";
    $userPermissions = $context['system_info']['user_permissions'] ?? [];
    $hasAllPermissions = in_array('*', $userPermissions);
    $prompt .= "- Your permissions: " . implode(', ', $userPermissions) . "\n";
    $prompt .= "- Has all permissions: " . ($hasAllPermissions ? 'YES' : 'NO') . "\n\n";
    
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "CRITICAL: ROLE-BASED EXECUTION RULES\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "YOUR PERMISSIONS DETERMINE WHAT YOU CAN DO:\n\n";
    
    // Map permissions to actions
    $canCreateCollections = $hasAllPermissions || in_array('manage_content', $userPermissions);
    $canCreateEntries = $hasAllPermissions || in_array('write', $userPermissions) || in_array('manage_content', $userPermissions);
    $canUpdateEntries = $hasAllPermissions || in_array('write', $userPermissions) || in_array('manage_content', $userPermissions);
    $canDeleteEntries = $hasAllPermissions || in_array('delete', $userPermissions) || in_array('manage_content', $userPermissions);
    $canReadEntries = $hasAllPermissions || in_array('read', $userPermissions);
    
    $prompt .= "- CREATE COLLECTIONS: " . ($canCreateCollections ? "YES - You CAN and MUST execute create_collection actions" : "NO - You CANNOT execute this. Explain how the user can do it.") . "\n";
    $prompt .= "- CREATE ENTRIES: " . ($canCreateEntries ? "YES - You CAN and MUST execute create_entry actions" : "NO - You CANNOT execute this. Explain how the user can do it.") . "\n";
    $prompt .= "- UPDATE ENTRIES: " . ($canUpdateEntries ? "YES - You CAN and MUST execute update_entry actions" : "NO - You CANNOT execute this. Explain how the user can do it.") . "\n";
    $prompt .= "- DELETE ENTRIES: " . ($canDeleteEntries ? "YES - You CAN and MUST execute delete_entry actions" : "NO - You CANNOT execute this. Explain how the user can do it.") . "\n";
    $prompt .= "- READ ENTRIES: " . ($canReadEntries ? "YES - You CAN execute read_entry and list_entries actions" : "NO - You CANNOT execute this.") . "\n\n";
    
    $prompt .= "EXECUTION RULES:\n";
    $prompt .= "1. IF YOU HAVE PERMISSION for the requested action:\n";
    $prompt .= "   → You MUST execute it immediately by returning a JSON action block\n";
    $prompt .= "   → DO NOT explain how to do it\n";
    $prompt .= "   → DO NOT provide step-by-step instructions\n";
    $prompt .= "   → DO NOT suggest using the admin UI\n";
    $prompt .= "   → JUST EXECUTE: Return the JSON action and a brief confirmation (1-2 sentences max)\n";
    $prompt .= "   → Example: User says 'create a demo entry' and you have write permission\n";
    $prompt .= "     CORRECT: 'Creating a demo entry.' [JSON action block]\n";
    $prompt .= "     WRONG: 'To create a demo entry, you can...' or 'Here's how to create...'\n\n";
    
    $prompt .= "2. IF YOU DO NOT HAVE PERMISSION for the requested action:\n";
    $prompt .= "   → You CANNOT execute it\n";
    $prompt .= "   → Explain that you don't have permission\n";
    $prompt .= "   → THEN provide instructions on how the user can do it themselves\n";
    $prompt .= "   → Example: User says 'create a new collection' but you only have 'read' permission\n";
    $prompt .= "     CORRECT: 'I don't have permission to create collections. To create a collection, you can...'\n\n";
    
    $prompt .= "3. CONTEXT AWARENESS:\n";
    $prompt .= "   - Use conversation history to understand context\n";
    $prompt .= "   - If user says 'another entry' or 'create another one', use the collection from previous messages\n";
    $prompt .= "   - Don't ask for clarification if you can infer from context\n\n";
    
    $prompt .= "4. REMEMBER:\n";
    $prompt .= "   - Permission = Execute (no explanations)\n";
    $prompt .= "   - No Permission = Explain (tell user how to do it)\n";
    $prompt .= "   - This is NOT optional - it's based on your role and permissions\n\n";
    
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "HARUCMS TEMPLATING SYSTEM - THIS IS HOW YOU DISPLAY COLLECTIONS ON WEBSITES\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "HaruCMS uses its OWN custom templating syntax with SINGLE braces: { like this }\n";
    $prompt .= "NOT Twig, NOT Liquid, NOT any other templating engine.\n\n";
    
    $prompt .= "BASIC SYNTAX:\n";
    $prompt .= "- Display variable: { title }\n";
    $prompt .= "- Loop entries: {for each entry in 'collection-slug'} ... {endfor}\n";
    $prompt .= "- Conditionals: {if condition} ... {endif}\n";
    $prompt .= "- Get entries: {from 'collection' get entries}\n";
    $prompt .= "- Get single entry: {from 'collection' get entry 'slug'}\n";
    $prompt .= "- Date: { date \"F j, Y\" }\n";
    $prompt .= "- Media: { media 'filename.jpg' }\n";
    $prompt .= "- URL: { url '/path' slug=slug }\n";
    $prompt .= "- Count: { count of 'collection' }\n\n";
    
    $prompt .= "HOW TO DISPLAY A COLLECTION ON YOUR WEBSITE:\n";
    $prompt .= "Example: Displaying the 'coco' collection entries:\n\n";
    $prompt .= "<!DOCTYPE html>\n";
    $prompt .= "<html>\n";
    $prompt .= "<head>\n";
    $prompt .= "    <title>My Website</title>\n";
    $prompt .= "</head>\n";
    $prompt .= "<body>\n";
    $prompt .= "    <h1>Coco Collection</h1>\n";
    $prompt .= "    <div class=\"entries\">\n";
    $prompt .= "        {for each entry in 'coco'}\n";
    $prompt .= "            <article>\n";
    $prompt .= "                <h2>{ title }</h2>\n";
    $prompt .= "                <p>{ text }</p>\n";
    $prompt .= "            </article>\n";
    $prompt .= "        {endfor}\n";
    $prompt .= "    </div>\n";
    $prompt .= "    <script src=\"/cms-template.v2.js\"></script>\n";
    $prompt .= "</body>\n";
    $prompt .= "</html>\n\n";
    
    $prompt .= "KEY POINTS:\n";
    $prompt .= "1. Include the HaruCMS client script: <script src=\"/cms-template.v2.js\"></script>\n";
    $prompt .= "2. Use {for each entry in 'collection-slug'} to loop through entries\n";
    $prompt .= "3. Use field names from the collection schema (e.g., { title }, { text }, { name })\n";
    $prompt .= "4. The client library automatically fetches and renders the content\n";
    $prompt .= "5. Collections are accessed via the HaruCMS API, not external APIs\n\n";
    
    $prompt .= "WHEN USERS ASK 'how to display collection X on my website':\n";
    $prompt .= "1. Provide HaruCMS templating code using {for each entry in 'collection-slug'}\n";
    $prompt .= "2. Show them how to include the client library script\n";
    $prompt .= "3. Use the actual field names from the collection schema\n";
    $prompt .= "4. DO NOT suggest React, Vue, external APIs, or other frameworks\n";
    $prompt .= "5. DO NOT suggest Twig, Liquid, or other templating engines\n";
    $prompt .= "6. Reference the HaruCMS documentation provided below\n\n";
    
    if (!empty($context['collections'])) {
        $prompt .= "AVAILABLE COLLECTIONS IN THIS HARUCMS INSTALLATION:\n";
        foreach ($context['collections'] as $collection) {
            $prompt .= "- " . ($collection['name'] ?? $collection['slug']) . " (slug: " . $collection['slug'] . ")\n";
            if (!empty($collection['fields'])) {
                $prompt .= "  Schema fields (USE THESE EXACT FIELD NAMES when creating entries):\n";
                foreach ($collection['fields'] as $field) {
                    $fieldName = $field['name'] ?? '';
                    $fieldType = $field['type'] ?? 'text';
                    $fieldLabel = $field['label'] ?? $fieldName;
                    $required = !empty($field['required']) ? ' (required)' : '';
                    $prompt .= "    - " . $fieldName . " (" . $fieldType . ", label: " . $fieldLabel . ")" . $required . "\n";
                }
            } else {
                $prompt .= "  No custom fields defined (only system fields: title, status)\n";
            }
        }
        $prompt .= "\n";
        $prompt .= "CRITICAL: When creating entries, you MUST use the exact field names from the collection's schema above.\n";
        $prompt .= "- The 'title' field is always available as a system field (required for all entries)\n";
        $prompt .= "- Use the EXACT field names from the 'Schema fields' list (e.g., if schema has 'text', use 'text', NOT 'content')\n";
        $prompt .= "- If a collection has a field named 'name', use 'name' in your data\n";
        $prompt .= "- If a collection has a field named 'text', use 'text' in your data, NOT 'content'\n";
        $prompt .= "- Always check the schema fields before creating entries to use the correct field names\n\n";
    } else {
        $prompt .= "NOTE: No collections are currently configured in this HaruCMS installation.\n\n";
    }
    
    $prompt .= "YOUR CAPABILITIES:\n";
    $prompt .= "- Answer questions about HaruCMS and this specific installation\n";
    $prompt .= "- List and view content from collections\n";
    $prompt .= "- CREATE NEW COLLECTIONS (if you have admin/editor permissions)\n";
    $prompt .= "- Create new entries in existing collections (if you have write permissions)\n";
    $prompt .= "- Update existing entries (if you have write permissions)\n";
    $prompt .= "- Help users understand the CMS structure and collections\n\n";
    
    $prompt .= "IMPORTANT CONTEXT RULES:\n";
    $prompt .= "1. When users ask 'what collections do I have' or 'which collections', they mean the collections in THIS HaruCMS installation listed above.\n";
    $prompt .= "2. Always refer to the specific collections listed in the 'AVAILABLE COLLECTIONS' section above.\n";
    $prompt .= "3. You are working within HaruCMS - not a generic system. Be specific about this installation.\n";
    $prompt .= "4. If no collections are listed, inform the user that no collections are configured yet.\n";
    $prompt .= "5. When answering questions, always reference the actual collections and data from THIS HaruCMS system.\n\n";
    
    $prompt .= "ACTION FORMAT (REQUIRED FOR ALL ACTIONS):\n";
    $prompt .= "When the user asks you to create collections, create entries, update, or delete content, you MUST include a JSON object in your response.\n";
    $prompt .= "The JSON must be in this exact format:\n";
    $prompt .= '```json' . "\n";
    $prompt .= '{"actions": [{"type": "action_type", "data": {...}, "description": "..."}]}' . "\n";
    $prompt .= '```' . "\n";
    $prompt .= "\n";
    $prompt .= "SUPPORTED ACTION TYPES (HaruCMS ONLY - NEVER OTHER PLATFORMS):\n";
    $prompt .= "READ ACTIONS (execute immediately, no approval needed):\n";
    $prompt .= "1. 'read_entry' - Read a specific entry by slug or ID\n";
    $prompt .= "   Data format: {\"collection\": \"collection_slug\", \"slug\": \"entry-slug\"} OR {\"collection\": \"collection_slug\", \"id\": \"entry_id\"}\n";
    $prompt .= "   Use this when the user asks you to check, review, or read a specific entry.\n";
    $prompt .= "2. 'list_entries' - List entries in a collection\n";
    $prompt .= "   Data format: {\"collection\": \"collection_slug\", \"limit\": 100}\n";
    $prompt .= "   Use this when the user asks you to list or show entries from a collection.\n";
    $prompt .= "\n";
    $prompt .= "WRITE ACTIONS (may require approval depending on AI connection settings):\n";
    $prompt .= "3. 'create_collection' - Create a new HaruCMS collection\n";
    $prompt .= "   Data format: {\"name\": \"Collection Name\", \"slug\": \"collection-slug\", \"fields\": [{\"name\": \"title\", \"type\": \"text\", \"label\": \"Title\"}, ...]}\n";
    $prompt .= "   ALWAYS assume the user means a HaruCMS collection (flat-file collection in this CMS), NEVER MongoDB, SQL tables, Airtable, Notion, etc.\n";
    $prompt .= "   Example: If the user says \"create a new collection called Approval Test\", you MUST:\n";
    $prompt .= "     - Treat this as a HaruCMS collection\n";
    $prompt .= "     - Pick a reasonable slug (e.g., \"approval-test\")\n";
    $prompt .= "     - Define a simple fields array (e.g., title, text)\n";
    $prompt .= "     - Return a JSON action block like:\n";
    $prompt .= "       ```json\n";
    $prompt .= "       {\"actions\":[{\"type\":\"create_collection\",\"data\":{\"name\":\"Approval Test\",\"slug\":\"approval-test\",\"fields\":[{\"name\":\"title\",\"type\":\"text\",\"label\":\"Title\",\"required\":true},{\"name\":\"text\",\"type\":\"textarea\",\"label\":\"Text\"}]},\"description\":\"Create Approval Test collection in HaruCMS\"}]}\n";
    $prompt .= "       ```\n";
    $prompt .= "4. 'create_entry' - Create a new entry in an existing collection\n";
    $prompt .= "   Data format: {\"collection\": \"collection_slug\", \"title\": \"Entry Title\", \"field_name\": \"value\", ...}\n";
    $prompt .= "   CRITICAL: Use the EXACT field names from the collection's schema (listed in AVAILABLE COLLECTIONS above).\n";
    $prompt .= "   Don't use generic names like 'content' - use the actual schema field names like 'text', 'body', 'description', etc.\n";
    $prompt .= "5. 'update_entry' - Update an existing entry\n";
    $prompt .= "   Data format: {\"collection\": \"collection_slug\", \"id\": \"entry_id\" OR \"slug\": \"entry-slug\", \"title\": \"New Title\", ...}\n";
    $prompt .= "   You can use either 'id' or 'slug' to identify the entry. If the user mentions a slug, use 'slug'.\n";
    $prompt .= "6. 'delete_entry' - Delete an entry\n";
    $prompt .= "   Data format: {\"collection\": \"collection_slug\", \"id\": \"entry_id\" OR \"slug\": \"entry-slug\"}\n";
    $prompt .= "\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "ACTION EXECUTION FORMAT\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "When you execute an action (because you have permission), use this format:\n";
    $prompt .= "1. Brief confirmation (1-2 sentences): 'Creating a demo entry in the approval-test collection.'\n";
    $prompt .= "2. JSON action block:\n";
    $prompt .= "   ```json\n";
    $prompt .= "   {\n";
    $prompt .= "     \"actions\": [\n";
    $prompt .= "       {\n";
    $prompt .= "         \"type\": \"create_entry\",\n";
    $prompt .= "         \"data\": { ... },\n";
    $prompt .= "         \"description\": \"...\"\n";
    $prompt .= "       }\n";
    $prompt .= "     ]\n";
    $prompt .= "   }\n";
    $prompt .= "   ```\n";
    $prompt .= "\n";
    $prompt .= "DO NOT include:\n";
    $prompt .= "- Step-by-step instructions\n";
    $prompt .= "- 'You can...' or 'Here's how...' explanations\n";
    $prompt .= "- File paths or manual procedures\n";
    $prompt .= "- Long paragraphs about the process\n";
    $prompt .= "\n";
    $prompt .= "2. ALWAYS use read actions FIRST when the user asks you to check, review, or read an entry.\n";
    $prompt .= "   Example: If user says 'check the entry called cocojungle', first use 'read_entry' to get the entry data, THEN analyze it and use 'update_entry' if needed.\n";
    $prompt .= "\n";
    $prompt .= "3. When the user mentions an entry by name/title/slug, use 'read_entry' with the 'slug' parameter to find it.\n";
    $prompt .= "\n";
    $prompt .= "4. ALWAYS include the JSON action block when performing ANY action (reading, creating, updating, etc.)\n";
    $prompt .= "   - The JSON block is REQUIRED, not optional\n";
    $prompt .= "   - You MAY include a short explanation, but the JSON action block is MANDATORY\n";
    $prompt .= "\n";
    $prompt .= "5. You CAN read entries - use 'read_entry' to get entry data before updating or analyzing it.\n";
    $prompt .= "\n";
    $prompt .= "6. You CAN create collections - use type 'create_collection' with name, slug, and fields array.\n";
    $prompt .= "\n";
    $prompt .= "7. You CAN create entries - use type 'create_entry' with collection, title, and field values.\n";
    $prompt .= "   - Use the EXACT field names from the collection schema (listed above)\n";
    $prompt .= "   - If the schema has 'text', use 'text', NOT 'content'\n";
    $prompt .= "\n";
    $prompt .= "8. You CAN create multiple entries - include multiple actions in the array.\n";
    $prompt .= "\n";
    $prompt .= "9. NEVER ask \"which platform\", \"which database\", or similar - ALWAYS assume HaruCMS.\n";
    $prompt .= "\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "CONTEXT AND CONVERSATION AWARENESS\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "- Use conversation history to understand context\n";
    $prompt .= "- If user says 'another entry', 'more entries', or 'create another one', use the collection from previous messages\n";
    $prompt .= "- If user says 'create entry' without specifying collection, check recent conversation for collection name\n";
    $prompt .= "- Don't ask for clarification if you can infer from context\n";
    $prompt .= "- Example: If previous message was 'create a demo entry for approval-test', and user says 'create another one',\n";
    $prompt .= "  you should create another entry in the 'approval-test' collection (same as before)\n";
    $prompt .= "\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "\n";
    $prompt .= "All actions require human approval before execution (unless approval is disabled in your connection settings).\n\n";
    
    if (!empty($context['documentation'])) {
        $prompt .= "HARUCMS DOCUMENTATION (CRITICAL - READ THIS CAREFULLY):\n";
        $prompt .= "The following documentation contains essential information about how HaruCMS works.\n";
        $prompt .= "You MUST use this documentation to answer questions about HaruCMS functionality.\n";
        $prompt .= "DO NOT make assumptions about how HaruCMS works - refer to the documentation below.\n";
        $prompt .= "When users ask about displaying collections, templating, or website integration, you MUST use the HaruCMS-specific syntax from this documentation.\n\n";
        
        // Prioritize templating and client integration docs
        $priorityDocs = [];
        $otherDocs = [];
        
        foreach ($context['documentation'] as $doc) {
            $filename = strtolower($doc['file']);
            if (strpos($filename, 'templating') !== false || 
                strpos($filename, 'client-integration') !== false || 
                strpos($filename, 'templates') !== false ||
                strpos($filename, 'getting-started') !== false) {
                $priorityDocs[] = $doc;
            } else {
                $otherDocs[] = $doc;
            }
        }
        
        // Add priority docs first
        foreach ($priorityDocs as $doc) {
            $prompt .= "=== " . strtoupper($doc['file']) . " (HIGH PRIORITY) ===\n";
            $prompt .= $doc['content'] . "\n\n";
        }
        
        // Add other docs
        foreach ($otherDocs as $doc) {
            $prompt .= "=== " . strtoupper($doc['file']) . " ===\n";
            // Limit length of non-priority docs to avoid token limits
            $content = $doc['content'];
            if (strlen($content) > 5000) {
                $content = substr($content, 0, 5000) . "\n\n[... content truncated for length ...]";
            }
            $prompt .= $content . "\n\n";
        }
        
        $prompt .= "END OF DOCUMENTATION\n\n";
        $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
        $prompt .= "CRITICAL REMINDERS - READ BEFORE ANSWERING ANY QUESTION:\n";
        $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
        $prompt .= "1. HaruCMS uses a custom templating syntax with SINGLE braces { like this }\n";
        $prompt .= "   NOT Twig, NOT Liquid, NOT any other templating engine.\n";
        $prompt .= "2. To display collections on a website, use: {for each entry in 'collection-slug'} ... {endfor}\n";
        $prompt .= "3. The client library (harucms-client.js or cms-template.v2.js) handles template rendering.\n";
        $prompt .= "4. Collections are accessed via the HaruCMS API, not external APIs or databases.\n";
        $prompt .= "5. When users ask 'how to display collection X', they want HaruCMS templating code.\n";
        $prompt .= "6. DO NOT suggest React, Vue, Svelte, or other frameworks unless explicitly asked.\n";
        $prompt .= "7. DO NOT suggest external APIs, databases, or headless CMS solutions.\n";
        $prompt .= "8. ALWAYS provide HaruCMS-specific solutions using the syntax shown above.\n";
        $prompt .= "9. If you're unsure, refer to the documentation above - it contains the correct syntax.\n";
        $prompt .= "10. Example answer format for 'how to display collection X':\n";
        $prompt .= "    'To display the X collection on your website, use this HaruCMS template code:\n";
        $prompt .= "    {for each entry in \"x\"}\n";
        $prompt .= "        <div>{ title }</div>\n";
        $prompt .= "    {endfor}\n";
        $prompt .= "    Include the script: <script src=\"/cms-template.v2.js\"></script>'\n\n";
    }
    
    return $prompt;
}

// End of old complex prompt

// Call AI provider API
function callAiProvider($provider, $apiKey, $model, $systemPrompt, $userMessage, $conversationId = '', $apiUrl = '', $conversationHistory = []) {
    $providers = getSupportedAiProviders();
    $providerInfo = $providers[$provider] ?? null;
    $providerType = $providerInfo['type'] ?? $provider;
    
    switch ($providerType) {
        case 'openai':
        case 'openrouter':
            $url = $apiUrl ?: ($providerInfo['api_url'] ?? 'https://api.openai.com/v1/chat/completions');
            return callOpenAI($apiKey, $model ?: 'gpt-4', $systemPrompt, $userMessage, $url, $conversationHistory);
        case 'anthropic':
            return callAnthropic($apiKey, $model ?: 'claude-3-sonnet', $systemPrompt, $userMessage, $conversationHistory);
        case 'google':
            return callGoogle($apiKey, $model ?: 'gemini-pro', $systemPrompt, $userMessage, $conversationHistory);
        case 'ollama':
            $url = $apiUrl ?: ($providerInfo['api_url'] ?? 'http://localhost:11434/api/chat');
            return callOllama($url, $model ?: 'llama3', $systemPrompt, $userMessage, $conversationHistory);
        case 'custom':
            $url = $apiUrl;
            if (empty($url)) {
                return ['success' => false, 'message' => 'API URL required for custom provider'];
            }
            // Try to detect API type or default to OpenAI format
            return callOpenAI($apiKey, $model, $systemPrompt, $userMessage, $url);
        default:
            return ['success' => false, 'message' => 'Unsupported provider'];
    }
}

// Execute action directly (when approval not required)
// Execute read actions (read_entry, list_entries) - these don't need approval
function executeReadAction($actionData, $aiUser) {
    require_once __DIR__ . '/storage.php';
    
    $actionType = $actionData['type'] ?? '';
    $actionPayload = $actionData['data'] ?? [];
    
    $contentStorage = new Storage('../content');
    
    if ($actionType === 'read_entry') {
        $collection = $actionPayload['collection'] ?? '';
        $slug = $actionPayload['slug'] ?? '';
        $entryId = $actionPayload['id'] ?? '';
        
        if (empty($collection)) {
            return ['success' => false, 'message' => 'Collection is required'];
        }
        
        // Find collection directory
        $collectionsDir = '../content/collections';
        $collectionDir = null;
        if (is_dir($collectionsDir)) {
            $collections = array_filter(glob($collectionsDir . '/*'), 'is_dir');
            foreach ($collections as $dir) {
                $collectionSlug = basename($dir);
                $collectionFile = $dir . '/collection.json';
                if (file_exists($collectionFile)) {
                    $collectionMeta = json_decode(file_get_contents($collectionFile), true);
                    if ($collectionMeta && ($collectionMeta['slug'] ?? $collectionSlug) === $collection) {
                        $collectionDir = $dir;
                        break;
                    }
                }
            }
        }
        
        if (!$collectionDir) {
            return ['success' => false, 'message' => 'Collection not found: ' . $collection];
        }
        
        // Find entry by slug or ID
        $entry = null;
        if ($slug) {
            $entries = $contentStorage->list("collections/" . basename($collectionDir) . "/entries");
            foreach ($entries as $entryFile) {
                $entryData = $contentStorage->read("collections/" . basename($collectionDir) . "/entries/" . $entryFile);
                if ($entryData && isset($entryData['slug']) && $entryData['slug'] === $slug) {
                    $entry = $entryData;
                    break;
                }
            }
        } elseif ($entryId) {
            $entry = $contentStorage->read("collections/" . basename($collectionDir) . "/entries/" . $entryId . ".json");
        }
        
        if (!$entry) {
            return ['success' => false, 'message' => 'Entry not found'];
        }
        
        return ['success' => true, 'data' => ['entry' => $entry]];
        
    } elseif ($actionType === 'list_entries') {
        $collection = $actionPayload['collection'] ?? '';
        $limit = $actionPayload['limit'] ?? 100;
        
        if (empty($collection)) {
            return ['success' => false, 'message' => 'Collection is required'];
        }
        
        // Find collection directory
        $collectionsDir = '../content/collections';
        $collectionDir = null;
        if (is_dir($collectionsDir)) {
            $collections = array_filter(glob($collectionsDir . '/*'), 'is_dir');
            foreach ($collections as $dir) {
                $collectionSlug = basename($dir);
                $collectionFile = $dir . '/collection.json';
                if (file_exists($collectionFile)) {
                    $collectionMeta = json_decode(file_get_contents($collectionFile), true);
                    if ($collectionMeta && ($collectionMeta['slug'] ?? $collectionSlug) === $collection) {
                        $collectionDir = $dir;
                        break;
                    }
                }
            }
        }
        
        if (!$collectionDir) {
            return ['success' => false, 'message' => 'Collection not found: ' . $collection];
        }
        
        // List entries
        $entries = $contentStorage->list("collections/" . basename($collectionDir) . "/entries");
        $entryData = [];
        $count = 0;
        foreach ($entries as $entryFile) {
            if ($count >= $limit) break;
            $data = $contentStorage->read("collections/" . basename($collectionDir) . "/entries/" . $entryFile);
            if ($data) {
                $entryData[] = $data;
                $count++;
            }
        }
        
        // Include collection slug in the result for easier matching
        $result = ['success' => true, 'data' => ['entries' => $entryData, 'count' => count($entryData), 'collection' => $collection]];
        error_log("executeReadAction list_entries: Returning result with collection '$collection', entry count: " . count($entryData));
        return $result;
        
    } elseif ($actionType === 'list_collections') {
        // List all collections
        $collectionsDir = '../content/collections';
        $collections = [];
        
        if (is_dir($collectionsDir)) {
            $collectionDirs = array_filter(glob($collectionsDir . '/*'), 'is_dir');
            foreach ($collectionDirs as $dir) {
                $collectionFile = $dir . '/collection.json';
                if (file_exists($collectionFile)) {
                    $collectionMeta = json_decode(file_get_contents($collectionFile), true);
                    if ($collectionMeta) {
                        $collections[] = [
                            'slug' => $collectionMeta['slug'] ?? basename($dir),
                            'name' => $collectionMeta['name'] ?? $collectionMeta['title'] ?? basename($dir),
                            'description' => $collectionMeta['description'] ?? '',
                            'fields' => $collectionMeta['fields'] ?? []
                        ];
                    }
                }
            }
        }
        
        return ['success' => true, 'data' => ['collections' => $collections, 'count' => count($collections)]];
        
    } elseif ($actionType === 'list_media') {
        // List media files
        require_once __DIR__ . '/storage.php';
        $mediaDir = __DIR__ . '/../media';
        $media = [];
        
        if (is_dir($mediaDir)) {
            $mainMetadataPath = $mediaDir . '/.metadata.json';
            if (file_exists($mainMetadataPath)) {
                $metadata = json_decode(file_get_contents($mainMetadataPath), true) ?: [];
                foreach ($metadata as $id => $item) {
                    $media[] = [
                        'id' => $id,
                        'name' => $item['name'] ?? '',
                        'type' => $item['type'] ?? '',
                        'size' => $item['size'] ?? 0,
                        'url' => '/media/' . ($item['folder'] ?? '') . ($item['folder'] ? '/' : '') . $id . '.' . ($item['ext'] ?? ''),
                        'folder' => $item['folder'] ?? ''
                    ];
                }
            }
        }
        
        return ['success' => true, 'data' => ['media' => $media, 'count' => count($media)]];
        
    } elseif ($actionType === 'read_settings') {
        // Read system settings
        require_once __DIR__ . '/storage.php';
        $settingsStorage = new Storage('../settings');
        $settings = [];
        
        $categories = ['site', 'admin', 'media', 'security'];
        foreach ($categories as $cat) {
            $settings[$cat] = $settingsStorage->read("$cat.json") ?? [];
        }
        
        return ['success' => true, 'data' => ['settings' => $settings]];
        
    } elseif ($actionType === 'list_users') {
        // List users (read-only, no passwords)
        require_once __DIR__ . '/storage.php';
        $usersStorage = new Storage('../users');
        $users = [];
        $userFiles = glob('../users/*.json');
        
        foreach ($userFiles as $file) {
            $user = json_decode(file_get_contents($file), true);
            if ($user) {
                unset($user['password']); // Never expose passwords
                $users[] = $user;
            }
        }
        
        return ['success' => true, 'data' => ['users' => $users, 'count' => count($users)]];
        
    } elseif ($actionType === 'read_user') {
        // Read a specific user
        require_once __DIR__ . '/storage.php';
        $usersStorage = new Storage('../users');
        $userId = $actionPayload['id'] ?? $actionPayload['email'] ?? '';
        
        if (empty($userId)) {
            return ['success' => false, 'message' => 'User ID or email is required'];
        }
        
        // Try to find user by ID or email
        $userFiles = glob('../users/*.json');
        $user = null;
        foreach ($userFiles as $file) {
            $userData = json_decode(file_get_contents($file), true);
            if ($userData && ($userData['id'] === $userId || $userData['email'] === $userId)) {
                unset($userData['password']); // Never expose passwords
                $user = $userData;
                break;
            }
        }
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        return ['success' => true, 'data' => ['user' => $user]];
        
    } elseif ($actionType === 'read_collection') {
        // Read a specific collection
        $collectionSlug = $actionPayload['slug'] ?? '';
        if (empty($collectionSlug)) {
            return ['success' => false, 'message' => 'Collection slug is required'];
        }
        
        $collectionFile = '../content/collections/' . $collectionSlug . '/collection.json';
        if (!file_exists($collectionFile)) {
            return ['success' => false, 'message' => 'Collection not found'];
        }
        
        $collection = json_decode(file_get_contents($collectionFile), true);
        if (!$collection) {
            return ['success' => false, 'message' => 'Failed to read collection'];
        }
        
        // Also load schema if it exists
        $schemaFile = '../content/collections/' . $collectionSlug . '/schema.json';
        if (file_exists($schemaFile)) {
            $schema = json_decode(file_get_contents($schemaFile), true);
            if ($schema) {
                $collection['fields'] = $schema['fields'] ?? [];
            }
        }
        
        return ['success' => true, 'data' => ['collection' => $collection]];
        
    } elseif ($actionType === 'read_media') {
        // Read a specific media file
        $mediaId = $actionPayload['id'] ?? '';
        if (empty($mediaId)) {
            return ['success' => false, 'message' => 'Media ID is required'];
        }
        
        $mediaDir = __DIR__ . '/../media';
        $mainMetadataPath = $mediaDir . '/.metadata.json';
        if (!file_exists($mainMetadataPath)) {
            return ['success' => false, 'message' => 'Media not found'];
        }
        
        $metadata = json_decode(file_get_contents($mainMetadataPath), true) ?: [];
        if (!isset($metadata[$mediaId])) {
            return ['success' => false, 'message' => 'Media not found'];
        }
        
        $media = $metadata[$mediaId];
        $media['id'] = $mediaId;
        $media['url'] = '/media/' . ($media['folder'] ?? '') . ($media['folder'] ? '/' : '') . $mediaId . '.' . ($media['ext'] ?? '');
        
        return ['success' => true, 'data' => ['media' => $media]];
        
    } elseif ($actionType === 'list_pages') {
        // List pages
        require_once __DIR__ . '/storage.php';
        $contentStorage = new Storage('../content');
        $pagesDir = '../content/pages';
        $pages = [];
        
        if (is_dir($pagesDir)) {
            $pageFiles = glob($pagesDir . '/*.json');
            foreach ($pageFiles as $file) {
                $page = json_decode(file_get_contents($file), true);
                if ($page) {
                    $pages[] = $page;
                }
            }
        }
        
        return ['success' => true, 'data' => ['pages' => $pages, 'count' => count($pages)]];
        
    } elseif ($actionType === 'read_page') {
        // Read a specific page
        $pageSlug = $actionPayload['slug'] ?? $actionPayload['id'] ?? '';
        if (empty($pageSlug)) {
            return ['success' => false, 'message' => 'Page slug or ID is required'];
        }
        
        $pageFile = '../content/pages/' . $pageSlug . '.json';
        if (!file_exists($pageFile)) {
            // Try to find by slug in all pages
            $pagesDir = '../content/pages';
            if (is_dir($pagesDir)) {
                $pageFiles = glob($pagesDir . '/*.json');
                foreach ($pageFiles as $file) {
                    $page = json_decode(file_get_contents($file), true);
                    if ($page && ($page['slug'] === $pageSlug || $page['id'] === $pageSlug)) {
                        return ['success' => true, 'data' => ['page' => $page]];
                    }
                }
            }
            return ['success' => false, 'message' => 'Page not found'];
        }
        
        $page = json_decode(file_get_contents($pageFile), true);
        if (!$page) {
            return ['success' => false, 'message' => 'Failed to read page'];
        }
        
        return ['success' => true, 'data' => ['page' => $page]];
        
    } elseif ($actionType === 'list_activity') {
        // List activity logs
        require_once __DIR__ . '/activity.php';
        $limit = $actionPayload['limit'] ?? 50;
        $activityFile = '../data/activity.json';
        
        $activities = [];
        if (file_exists($activityFile)) {
            $allActivities = json_decode(file_get_contents($activityFile), true) ?: [];
            $activities = array_slice(array_reverse($allActivities), 0, $limit);
        }
        
        return ['success' => true, 'data' => ['activities' => $activities, 'count' => count($activities)]];
        
    } elseif ($actionType === 'list_roles') {
        // List roles
        require_once __DIR__ . '/storage.php';
        $settingsStorage = new Storage('../settings');
        $roles = $settingsStorage->read('roles.json') ?: [];
        
        return ['success' => true, 'data' => ['roles' => $roles, 'count' => count($roles)]];
        
    } elseif ($actionType === 'read_role') {
        // Read a specific role
        require_once __DIR__ . '/storage.php';
        $settingsStorage = new Storage('../settings');
        $roles = $settingsStorage->read('roles.json') ?: [];
        $roleId = $actionPayload['id'] ?? $actionPayload['name'] ?? '';
        
        if (empty($roleId)) {
            return ['success' => false, 'message' => 'Role ID or name is required'];
        }
        
        foreach ($roles as $role) {
            if (($role['id'] ?? '') === $roleId || ($role['name'] ?? '') === $roleId) {
                return ['success' => true, 'data' => ['role' => $role]];
            }
        }
        
        return ['success' => false, 'message' => 'Role not found'];
        
    } elseif ($actionType === 'list_plugins') {
        // List plugins
        require_once __DIR__ . '/../core/plugins.php';
        $pluginsDir = '../plugins';
        $plugins = [];
        
        if (is_dir($pluginsDir)) {
            $pluginDirs = array_filter(glob($pluginsDir . '/*'), 'is_dir');
            foreach ($pluginDirs as $dir) {
                $pluginFile = $dir . '/plugin.json';
                if (file_exists($pluginFile)) {
                    $plugin = json_decode(file_get_contents($pluginFile), true);
                    if ($plugin) {
                        $plugin['id'] = basename($dir);
                        $plugins[] = $plugin;
                    }
                }
            }
        }
        
        return ['success' => true, 'data' => ['plugins' => $plugins, 'count' => count($plugins)]];
    }
    
    return ['success' => false, 'message' => 'Unknown read action type: ' . $actionType];
}

function executeActionDirectly($actionData, $aiUser) {
    require_once __DIR__ . '/ai_approvals.php';
    
    // The actionData structure from extractActionsFromResponse is:
    // {type: "create_entry", data: {collection: "slug", title: "...", ...}, description: "..."}
    
    // For create_entry, the data already contains collection at the top level
    // We just need to pass it through correctly - DON'T restructure it!
    $actionPayload = $actionData['data'] ?? [];
    
    // Log for debugging
    error_log("executeActionDirectly: Action type: " . ($actionData['type'] ?? 'unknown'));
    error_log("executeActionDirectly: Original action data: " . json_encode($actionData));
    error_log("executeActionDirectly: Action payload (data field): " . json_encode($actionPayload));
    
    // For create_entry, the payload should already have collection at top level
    // Format: {collection: "slug", title: "...", content: "..."}
    // We pass this directly to executeCreateEntry which expects this format
    // DO NOT restructure it - just pass it through!
    
    $approval = [
        'action_type' => $actionData['type'],
        'action_data' => $actionPayload, // Pass the data field directly
        'ai_user_id' => $aiUser['id']
    ];
    
    error_log("executeActionDirectly: Final approval action_data: " . json_encode($approval['action_data']));
    
    $result = executeAiAction($approval);
    return $result;
}

// OpenAI API call (also works for OpenRouter and OpenAI-compatible APIs)
function callOpenAI($apiKey, $model, $systemPrompt, $userMessage, $url = 'https://api.openai.com/v1/chat/completions', $conversationHistory = []) {
    // Build messages array with conversation history
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];
    
    // Add conversation history (excluding the last user message if it's the same)
    foreach ($conversationHistory as $historyMsg) {
        // Skip the last message if it's the same as current user message (avoid duplicates)
        if ($historyMsg['role'] === 'user' && $historyMsg['content'] === $userMessage && end($conversationHistory) === $historyMsg) {
            continue;
        }
        $messages[] = $historyMsg;
    }
    
    // Add current user message
    $messages[] = ['role' => 'user', 'content' => $userMessage];
    
    $data = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    
    // OpenRouter requires additional headers
    if (strpos($url, 'openrouter.ai') !== false) {
        $headers[] = 'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $headers[] = 'X-Title: HaruCMS';
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 second connection timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 second total timeout (reduced from default)
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'message' => 'Connection error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'OpenAI API error: ' . $httpCode];
    }
    
    $result = json_decode($response, true);
    if (!$result || !isset($result['choices'][0]['message']['content'])) {
        return ['success' => false, 'message' => 'Invalid response from OpenAI'];
    }
    
    return ['success' => true, 'message' => $result['choices'][0]['message']['content']];
}

// Anthropic API call
function callAnthropic($apiKey, $model, $systemPrompt, $userMessage, $conversationHistory = []) {
    $url = 'https://api.anthropic.com/v1/messages';
    
    // Build messages array with conversation history
    $messages = [];
    
    // Add conversation history
    foreach ($conversationHistory as $historyMsg) {
        // Skip the last message if it's the same as current user message (avoid duplicates)
        if ($historyMsg['role'] === 'user' && $historyMsg['content'] === $userMessage && end($conversationHistory) === $historyMsg) {
            continue;
        }
        $messages[] = $historyMsg;
    }
    
    // Add current user message
    $messages[] = ['role' => 'user', 'content' => $userMessage];
    
    $data = [
        'model' => $model,
        'max_tokens' => 4096,
        'system' => $systemPrompt,
        'messages' => $messages
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01'
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'message' => 'Connection error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Anthropic API error: ' . $httpCode];
    }
    
    $result = json_decode($response, true);
    if (!$result || !isset($result['content'][0]['text'])) {
        return ['success' => false, 'message' => 'Invalid response from Anthropic'];
    }
    
    return ['success' => true, 'message' => $result['content'][0]['text']];
}

// Google Gemini API call
function callGoogle($apiKey, $model, $systemPrompt, $userMessage, $conversationHistory = []) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;
    
    // Build conversation context
    $conversationText = $systemPrompt . "\n\n";
    
    // Add conversation history
    foreach ($conversationHistory as $historyMsg) {
        if ($historyMsg['role'] === 'user') {
            $conversationText .= "User: " . $historyMsg['content'] . "\n\n";
        } elseif ($historyMsg['role'] === 'assistant') {
            $conversationText .= "Assistant: " . $historyMsg['content'] . "\n\n";
        }
    }
    
    // Add current user message
    $conversationText .= "User: " . $userMessage;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $conversationText]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'message' => 'Connection error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Google API error: ' . $httpCode];
    }
    
    $result = json_decode($response, true);
    if (!$result || !isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return ['success' => false, 'message' => 'Invalid response from Google'];
    }
    
    return ['success' => true, 'message' => $result['candidates'][0]['content']['parts'][0]['text']];
}

// Ollama API call
function callOllama($url, $model, $systemPrompt, $userMessage, $conversationHistory = []) {
    // Ensure URL ends with /api/chat if it's just the base URL
    if (strpos($url, '/api/chat') === false) {
        $url = rtrim($url, '/') . '/api/chat';
    }
    
    // Build messages array with conversation history
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];
    
    // Add conversation history
    foreach ($conversationHistory as $historyMsg) {
        // Skip the last message if it's the same as current user message (avoid duplicates)
        if ($historyMsg['role'] === 'user' && $historyMsg['content'] === $userMessage && end($conversationHistory) === $historyMsg) {
            continue;
        }
        $messages[] = $historyMsg;
    }
    
    // Add current user message
    $messages[] = ['role' => 'user', 'content' => $userMessage];
    
    $data = [
        'model' => $model,
        'messages' => $messages,
        'stream' => false,
        'options' => [
            'temperature' => 0.7
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Ollama can be slow
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'message' => 'Connection error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        $errorMsg = 'Ollama API error: HTTP ' . $httpCode;
        if ($response) {
            $errorData = json_decode($response, true);
            if (isset($errorData['error'])) {
                $errorMsg .= ' - ' . $errorData['error'];
            } else {
                $errorMsg .= ' - ' . substr($response, 0, 200);
            }
        }
        return ['success' => false, 'message' => $errorMsg];
    }
    
    if (empty($response)) {
        return ['success' => false, 'message' => 'Empty response from Ollama'];
    }
    
    $result = json_decode($response, true);
    if (!$result) {
        return ['success' => false, 'message' => 'Invalid JSON response from Ollama: ' . substr($response, 0, 200)];
    }
    
    // Check for error in response
    if (isset($result['error'])) {
        return ['success' => false, 'message' => 'Ollama error: ' . $result['error']];
    }
    
    // Ollama response format: { "message": { "content": "..." } }
    if (!isset($result['message']['content'])) {
        return ['success' => false, 'message' => 'Invalid response format from Ollama. Response: ' . json_encode($result)];
    }
    
    return ['success' => true, 'message' => $result['message']['content']];
}

// Clean response message to remove explanations when actions are present
function cleanResponseMessage($message, $actions) {
    if (empty($actions)) {
        return $message; // No actions, return original message
    }
    
    // Extract JSON action block (keep it)
    $jsonBlock = '';
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?"actions"[\s\S]*?\})\s*```/i', $message, $matches)) {
        $jsonBlock = '```json' . "\n" . $matches[1] . "\n" . '```';
    } elseif (preg_match('/\{[\s\S]*?"actions"[\s\S]*?\}/', $message, $matches)) {
        $jsonBlock = '```json' . "\n" . $matches[0] . "\n" . '```';
    }
    
    // Remove explanation patterns and keep only brief confirmation
    $lines = explode("\n", $message);
    $cleanedLines = [];
    $foundAction = false;
    $inJsonBlock = false;
    
    foreach ($lines as $line) {
        $lineTrimmed = trim($line);
        
        // Skip empty lines at the start
        if (empty($cleanedLines) && empty($lineTrimmed)) {
            continue;
        }
        
        // Detect JSON code block
        if (preg_match('/```(?:json)?/i', $line)) {
            $inJsonBlock = !$inJsonBlock;
            if ($inJsonBlock && !empty($jsonBlock)) {
                $cleanedLines[] = $jsonBlock;
                $foundAction = true;
            }
            continue;
        }
        
        // If we're in a JSON block, skip (we already added it)
        if ($inJsonBlock) {
            continue;
        }
        
        // Skip explanation patterns
        $lowerLine = strtolower($lineTrimmed);
        $isExplanation = preg_match('/^(to|you can|here\'?s|follow|simply|just|go to|navigate|click|paste|copy|create the file|add a file)/i', $lowerLine) ||
                        preg_match('/(you can|here\'?s how|follow these|step \d+|first,|then,|finally,)/i', $lowerLine) ||
                        preg_match('/^(1\.|2\.|3\.|\-|•)/', $lineTrimmed);
        
        if ($isExplanation) {
            continue;
        }
        
        // Keep brief confirmations and questions
        if (preg_match('/^(creating|i\'?ll|i\'?m|done|ready|sure|okay|yes|no|which|what|where|when|how many)/i', $lowerLine) ||
            preg_match('/(created|will create|creating|done|ready)/i', $lowerLine)) {
            $cleanedLines[] = $line;
            continue;
        }
        
        // If we haven't found an action yet and this line doesn't look like explanation, keep it
        if (!$foundAction && !$isExplanation && !empty($lineTrimmed)) {
            $cleanedLines[] = $line;
        }
    }
    
    // If we have actions but no cleaned message, create a brief confirmation
    if (empty($cleanedLines) && !empty($actions)) {
        $actionType = $actions[0]['type'] ?? 'action';
        $actionTypeLabel = str_replace('_', ' ', $actionType);
        $cleanedLines[] = ucfirst($actionTypeLabel) . ' created.';
    }
    
    // Add JSON block if we have actions
    if (!empty($jsonBlock) && !in_array($jsonBlock, $cleanedLines)) {
        $cleanedLines[] = '';
        $cleanedLines[] = $jsonBlock;
    }
    
    return implode("\n", $cleanedLines);
}

// Extract action requests from AI response
function extractActionsFromResponse($response) {
    $actions = [];
    
    // Try to find JSON block in response (could be in code blocks or plain)
    // First, try to find JSON in code blocks
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?"actions"[\s\S]*?\})\s*```/i', $response, $matches)) {
        $json = json_decode($matches[1], true);
        if ($json && isset($json['actions']) && is_array($json['actions'])) {
            $actions = $json['actions'];
        }
    }
    // Try to find JSON object with "actions" key anywhere in response
    elseif (preg_match('/\{[\s\S]*?"actions"[\s\S]*?\}/', $response, $matches)) {
        $json = json_decode($matches[0], true);
        if ($json && isset($json['actions']) && is_array($json['actions'])) {
            $actions = $json['actions'];
        }
    }
    // Try to find standalone JSON object
    elseif (preg_match('/\{[\s\S]+\}/', $response, $matches)) {
        $json = json_decode($matches[0], true);
        if ($json && isset($json['actions']) && is_array($json['actions'])) {
            $actions = $json['actions'];
        }
    }
    
    // Log for debugging
    error_log("Extracted actions: " . json_encode($actions));
    error_log("Response preview: " . substr($response, 0, 500));
    
    return $actions;
}

// Default response for unknown actions
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
exit;

