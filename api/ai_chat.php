<?php
/**
 * AI Chat API
 * Handles chat interactions with AI providers in the admin panel
 */

$__aiChatInternalLibrary = isset($GLOBALS['__HARU_INTERNAL_CALL']);

if (!$__aiChatInternalLibrary) {
// Start output buffering FIRST, before any includes
if (ob_get_level() == 0) {
    ob_start();
}

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('max_execution_time', 120); // overridden per-request for long execute batches
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
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once 'ai_approvals.php';
unset($GLOBALS['__HARU_INTERNAL_CALL']);
require_once __DIR__ . '/../core/ai_tool_registry.php';
require_once __DIR__ . '/../core/ai_playbooks.php';
require_once __DIR__ . '/ai_read_actions.php';

$input = sanitize_input(json_decode(file_get_contents('php://input'), true));
$action = $input['action'] ?? $_GET['action'] ?? '';

// All actions require authentication
requireAuth();

if ($action === 'get_history') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $conversationId = $_GET['conversation_id'] ?? $input['conversation_id'] ?? '';
    $aiConnectionId = $_GET['ai_connection_id'] ?? $input['ai_connection_id'] ?? '';
    if (empty($conversationId)) {
        echo json_encode(['status' => 'error', 'message' => 'conversation_id is required']);
        exit;
    }
    $conversationStorage = new Storage('../data/ai_conversations');
    $conversationData = $conversationStorage->read($conversationId . '.json');
    if (!$conversationData) {
        echo json_encode(['status' => 'ok', 'data' => ['messages' => [], 'conversation_id' => $conversationId]]);
        exit;
    }
    if (!empty($aiConnectionId) && ($conversationData['ai_connection_id'] ?? '') !== $aiConnectionId) {
        echo json_encode(['status' => 'error', 'message' => 'Conversation not found for this connection']);
        exit;
    }
    $currentUserId = $_SESSION['user']['id'] ?? '';
    if (!empty($conversationData['user_id']) && $conversationData['user_id'] !== $currentUserId) {
        echo json_encode(['status' => 'error', 'message' => 'You do not have access to this conversation']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'data' => [
        'conversation_id' => $conversationId,
        'messages' => $conversationData['messages'] ?? [],
        'updated_at' => $conversationData['updated_at'] ?? null,
    ]]);
    exit;
}

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
        $message = trim((string) ($input['message'] ?? ''));
        $chatAttachments = birkly_ai_normalize_chat_attachments($input['attachments'] ?? []);
        $message = birkly_ai_message_with_document_attachments($message, $chatAttachments);
        $conversationId = $input['conversation_id'] ?? '';
        $pageContext = birkly_ai_normalize_page_context($input['page_context'] ?? null);
        $wantStream = !empty($input['stream']);
        $streamActive = false;
        
        if (empty($aiConnectionId) || ($message === '' && $chatAttachments === [])) {
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

        $modelOverride = trim((string) ($input['model'] ?? ''));
        if ($modelOverride !== '') {
            $aiUser['model'] = birkly_ai_resolve_model((string) ($aiUser['provider'] ?? ''), $modelOverride);
        }
        
        // Check if current user can access this connection
        $currentUser = $_SESSION['user'] ?? [];
        $currentUserId = birkly_ai_resolve_session_user_id($currentUser);
        if (!birkly_ai_user_can_access_connection($aiUser, $currentUserId, $currentUser)) {
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
        if (!empty($pageContext)) {
            $systemContext['page_context'] = $pageContext;
        }
        
        // Build system prompt
        $systemPrompt = buildSystemPrompt($systemContext, $aiUser);

        $chatImageAttachments = birkly_ai_chat_image_attachments($chatAttachments);
        if ($chatImageAttachments !== []) {
            $caps = birkly_ai_connection_capabilities($aiUser);
            if (empty($caps['vision'])) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode([
                    'status' => 'error',
                    'message' => 'This connection does not support image attachments. Switch to a vision-capable provider (OpenRouter, OpenAI, Gemini, Anthropic).',
                ]);
                exit;
            }
        }
        
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
        
        // Always call the AI provider — no server-side proceed/status bypasses.
        $openAiTools = birkly_ai_tools_openai_format($aiUser);
        $analysisIntent = ai_chat_message_implies_analysis_intent($message, $conversationHistory);
        $writeIntent = ai_chat_message_implies_write_intent($message, $conversationHistory);
        $executeIntent = ai_chat_message_implies_execute_intent($message);
        if ($writeIntent || $executeIntent) {
            ini_set('max_execution_time', '300');
        }
        $providerType = getSupportedAiProviders()[$aiUser['provider'] ?? '']['type'] ?? ($aiUser['provider'] ?? '');
        $providerSupportsNativeTools = in_array($providerType, ['openai', 'openrouter', 'custom'], true);
        $hasNativeTools = $openAiTools !== [] && $providerSupportsNativeTools;
        $useNonStreaming = $hasNativeTools || $writeIntent;

        $streamCallback = null;
        if ($wantStream && birkly_ai_provider_supports_streaming((string) ($aiUser['provider'] ?? '')) && $chatImageAttachments === [] && !$useNonStreaming) {
            birkly_ai_sse_begin();
            $streamActive = true;
            $streamCallback = function ($token) {
                birkly_ai_sse_send('token', ['content' => $token]);
            };
        }

        // Release session lock before long AI provider calls (approval polling, etc.)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $providerOptions = [];
        if ($writeIntent && $hasNativeTools) {
            $providerOptions['tool_choice'] = 'required';
        }
        if ($writeIntent || $executeIntent) {
            $providerOptions['timeout'] = 120;
        }

        $response = callAiProvider(
            $aiUser['provider'],
            $apiKey,
            $aiUser['model'] ?? '',
            $systemPrompt,
            $message,
            $conversationId,
            $aiUser['api_url'] ?? '',
            $conversationHistory,
            $aiUser,
            $streamCallback,
            $chatAttachments,
            $providerOptions
        );
        
        // Save conversation history after the full response is finalized (see ai_chat_persist_conversation)
        $chatPersistContext = [
            'conversation_id' => $conversationId,
            'ai_connection_id' => $aiConnectionId,
            'user_id' => $currentUserId,
            'user_message' => $message,
            'history' => &$conversationHistory,
            'system_context' => $systemContext,
        ];
        
        if ($response['success']) {
            // Check if response contains action requests
            $actions = extractActionsFromResponse($response['message'] ?? '', $response['tool_calls'] ?? []);
            $actions = ai_chat_filter_already_completed_actions($actions, $conversationHistory);
            
            // Log for debugging
            error_log("AI Response: " . substr((string) ($response['message'] ?? ''), 0, 500));
            error_log("Extracted actions count: " . count($actions));
            if (!empty($actions)) {
                error_log("Actions: " . json_encode($actions));
                
            }

            // Actions come from the model only — no server-side regex fallbacks.

            // Separate read actions (which should be executed immediately and data returned) 
            // from write actions (which may need approval)
            $readActions = [];
            $writeActions = [];
            foreach ($actions as $action) {
                $actionType = $action['type'] ?? '';
                if (birkly_ai_is_read_action($actionType)) {
                    $readActions[] = $action;
                } else {
                    $writeActions[] = $action;
                }
            }

            if ($executeIntent && $writeActions !== []) {
                $readActions = [];
            }

            if ($analysisIntent && $readActions === [] && $writeActions === []
                && ai_chat_is_project_site_intent($message, $conversationHistory)
                && !ai_chat_message_implies_execute_intent($message)) {
                error_log('Analysis intent with no model reads — auto-loading project files');
                $readActions = ai_chat_default_project_read_actions();
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
                            if ($actionData['type'] === 'list_collections' && is_array($result['data']['collections'] ?? null)) {
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
                                        } catch (Throwable $e) {
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
                    } catch (Throwable $e) {
                        error_log("Exception executing read action: " . $e->getMessage());
                        $readData[] = [
                            'success' => false,
                            'message' => 'Error reading entry: ' . $e->getMessage()
                        ];
                    }
                }
            }
            
            // Review/analysis questions: dedicated prose-only follow-up (no more tool calls).
            $analysisHandled = false;
            if ($hasSuccessfulReads && !empty($readData) && $analysisIntent) {
                error_log('Analysis intent detected — running prose-only review follow-up');
                $analysisResponse = ai_chat_run_read_analysis(
                    $aiUser,
                    $apiKey,
                    $systemPrompt,
                    $message,
                    $conversationId,
                    $conversationHistory,
                    $readData,
                    $readActions
                );
                if (($analysisResponse['success'] ?? false) && trim((string) ($analysisResponse['message'] ?? '')) !== '') {
                    $response['message'] = $analysisResponse['message'];
                    $analysisHandled = true;
                }
            }

            // If we successfully read data, make a follow-up call to the AI with the read data
            // This allows the AI to analyze the data and either:
            // 1. Answer questions (like "which collection has the most entries?")
            // 2. Create write actions based on analysis (like "check for typos and fix them")
            if ($hasSuccessfulReads && !empty($readData) && !$analysisHandled && !$executeIntent) {
                // Build a follow-up message with the read data
                $followUpMessage = "Context: The user asked: \"" . $message . "\"\n\n";
                $followUpMessage .= "I've successfully retrieved the requested data. Here's what I found:\n\n";
                foreach ($readData as $readResult) {
                    $formatted = ai_chat_format_read_result_for_followup($readResult, $readActions);
                    if ($formatted !== '') {
                        $followUpMessage .= $formatted;
                    }
                }
                // Build entry counts summary from readData
                $entryCounts = [];
                $collectionNames = [];
                
                // First, extract collection names from list_collections result
                foreach ($readData as $readResult) {
                    if (($readResult['success'] ?? false) && is_array($readResult['data']['collections'] ?? null)) {
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
                    if (($readResult['success'] ?? false) && is_array($readResult['data']['entries'] ?? null)) {
                        $collection = $readResult['data']['collection'] ?? '';
                        if ($collection) {
                            $entryCounts[$collection] = count($readResult['data']['entries']);
                        }
                    }
                }
                
                // Note: We don't add hardcoded summaries here - let the AI analyze the data dynamically
                // The AI has all the entry data above and can calculate counts, content length, etc. as needed
                
                $followUpMessage .= "Answer the user's question directly using the data above. Be clear and complete.\n";
                $followUpMessage .= "If they asked for a write action (create/update/delete), include a JSON action block as described in the system prompt.\n";
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
                        $conversationHistory,
                        $aiUser
                    );
                    
                    if ($followUpResponse['success']) {
                        error_log("Follow-up AI call succeeded, message length: " . strlen($followUpResponse['message'] ?? ''));
                        // Extract actions from follow-up response
                        $followUpActions = extractActionsFromResponse($followUpResponse['message'] ?? '', $followUpResponse['tool_calls'] ?? []);
                        error_log("Follow-up extracted " . count($followUpActions) . " actions");
                        
                        // Separate read and write actions from follow-up
                        $followUpReadActions = [];
                        foreach ($followUpActions as $action) {
                            $actionType = $action['type'] ?? '';
                            if (in_array($actionType, ['read_entry', 'list_entries', 'list_collections', 'list_media', 'read_settings', 'list_users', 'read_user', 'read_collection', 'read_media', 'list_pages', 'read_page', 'list_activity', 'list_roles', 'read_role', 'list_plugins', 'list_project_files', 'read_project_file', 'get_project_info'], true)) {
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
                                $actionKey = ($actionData['type'] ?? '') . '_' . json_encode($actionData['data'] ?? []);
                                if (isset($executedActions[$actionKey])) {
                                    error_log("Skipping duplicate follow-up read: " . ($actionData['type'] ?? ''));
                                    continue;
                                }
                                $executedActions[$actionKey] = true;
                                try {
                                    $result = executeReadAction($actionData, $aiUser);
                                    if (($actionData['type'] ?? '') === 'list_entries' && isset($actionData['data']['collection'])) {
                                        $result['data']['collection'] = $actionData['data']['collection'];
                                    }
                                    $readData[] = $result;
                                    if ($result['success'] ?? false) {
                                        $hasSuccessfulReads = true;
                                    }
                                } catch (Throwable $e) {
                                    error_log("Exception executing follow-up read action: " . $e->getMessage());
                                    $readData[] = [
                                        'success' => false,
                                        'message' => 'Error: ' . $e->getMessage()
                                    ];
                                }
                            }

                            $skipSecondFollowUp = ai_chat_is_project_site_intent($message, $conversationHistory)
                                || ai_chat_read_data_has_project_context($readData);

                            // If we got more data, make another follow-up call with all the data
                            if ($hasSuccessfulReads && !empty($readData) && !$skipSecondFollowUp) {
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
                                        $conversationHistory,
                                        $aiUser
                                    );
                                    
                                    if ($secondFollowUpResponse['success']) {
                                        $response['message'] = $secondFollowUpResponse['message'];
                                        // Check for any write actions in the second follow-up
                                        $secondFollowUpActions = extractActionsFromResponse($secondFollowUpResponse['message'] ?? '', $secondFollowUpResponse['tool_calls'] ?? []);
                                        foreach ($secondFollowUpActions as $action) {
                                            $actionType = $action['type'] ?? '';
                                            if (!in_array($actionType, ['read_entry', 'list_entries', 'list_collections', 'list_media', 'read_settings', 'list_users', 'read_user', 'read_collection', 'read_media', 'list_pages', 'read_page', 'list_activity', 'list_roles', 'read_role', 'list_plugins', 'list_project_files', 'read_project_file', 'get_project_info'], true)) {
                                                $writeActions[] = $action;
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    error_log("Exception in second follow-up call: " . $e->getMessage());
                                }
                            } else {
                                $candidateMessage = (string) ($followUpResponse['message'] ?? '');
                                if (ai_chat_response_is_read_stub($candidateMessage) && $analysisIntent) {
                                    error_log('Follow-up returned read stub — running analysis follow-up');
                                    $analysisResponse = ai_chat_run_read_analysis(
                                        $aiUser,
                                        $apiKey,
                                        $systemPrompt,
                                        $message,
                                        $conversationId,
                                        $conversationHistory,
                                        $readData,
                                        $readActions
                                    );
                                    if (($analysisResponse['success'] ?? false) && trim((string) ($analysisResponse['message'] ?? '')) !== '') {
                                        $response['message'] = $analysisResponse['message'];
                                    } else {
                                        $response['message'] = $candidateMessage;
                                    }
                                } else {
                                    $response['message'] = $candidateMessage;
                                }
                                if ($skipSecondFollowUp ?? false) {
                                    error_log("Skipping second follow-up — project site context already complete");
                                }
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
                                if (($readResult['success'] ?? false) && is_array($readResult['data']['collections'] ?? null)) {
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
                        if (($readResult['success'] ?? false) && is_array($readResult['data']['entries'] ?? null)) {
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
            $writeActions = ai_chat_dedupe_write_actions($writeActions);
            $writeActions = ai_chat_filter_already_completed_actions($writeActions, $conversationHistory);

            $displayForClaimCheck = ai_chat_sanitize_display_message((string) ($response['message'] ?? ''));
            if ($writeActions === []
                && !$executeIntent
                && ($writeIntent || ai_chat_response_implies_false_execution_claim($displayForClaimCheck))) {
                error_log('No write actions after model response — nudging for executable actions');
                $nudgeResponse = ai_chat_nudge_for_actions(
                    $aiUser,
                    $apiKey,
                    $systemPrompt,
                    $message,
                    (string) ($response['message'] ?? ''),
                    $conversationId,
                    $conversationHistory,
                    $readData
                );
                if ($nudgeResponse['success'] ?? false) {
                    $nudgeActions = extractActionsFromResponse(
                        $nudgeResponse['message'] ?? '',
                        $nudgeResponse['tool_calls'] ?? []
                    );
                    $nudgeActions = ai_chat_filter_already_completed_actions($nudgeActions, $conversationHistory);
                    foreach ($nudgeActions as $action) {
                        $actionType = $action['type'] ?? '';
                        if (!birkly_ai_is_read_action($actionType)) {
                            $writeActions[] = $action;
                        }
                    }
                    if ($writeActions !== []) {
                        $nudgeDisplay = ai_chat_sanitize_display_message((string) ($nudgeResponse['message'] ?? ''));
                        if ($nudgeDisplay !== '' && !ai_chat_response_implies_false_execution_claim($nudgeDisplay)) {
                            $response['message'] = $nudgeDisplay;
                        }
                    }
                }
            }

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
            // Admin-role connections skip approval when explicitly configured permission-free
            if (($aiUser['role'] ?? '') === 'admin' && ($aiUser['require_approval'] ?? null) === false) {
                $requireApproval = false;
            }
            error_log("require_approval setting: " . ($requireApproval ? 'true' : 'false') . " (raw: " . var_export($aiUser['require_approval'] ?? 'not set', true) . ")");
            
            if (!empty($actions)) {
                if ($requireApproval) {
                    if (trim((string) ($response['message'] ?? '')) === '') {
                        $response['message'] = ai_chat_summarize_pending_actions($actions);
                    }
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
                            'description' => $actionData['description'] ?? substr((string) ($response['message'] ?? ''), 0, 200),
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

                    $approvalDetails = [];
                    foreach ($approvalIds as $approvalId) {
                        $approval = $approvalStorage->read($approvalId . '.json');
                        if ($approval) {
                            $approvalDetails[] = [
                                'id' => $approval['id'],
                                'action_type' => $approval['action_type'],
                                'description' => $approval['description'],
                                'action_data' => $approval['action_data'],
                                'created_at' => $approval['created_at'],
                                'status' => $approval['status'] ?? 'pending',
                            ];
                        }
                    }

                    ai_chat_emit_response([
                        'status' => 'ok',
                        'data' => [
                            'response' => ai_chat_sanitize_display_message((string) ($response['message'] ?? ''))
                                . "\n\n**" . count($approvalIds) . " action(s) waiting for approval** — open the Approvals tab to run them.",
                            'actions_created' => count($approvalIds),
                            'approval_ids' => $approvalIds,
                            'approvals' => $approvalDetails,
                            'requires_approval' => true,
                            'read_data' => $readData,
                        ],
                    ], $streamActive, $chatPersistContext);
                } else {
                    // Execute actions directly without approval
                    $executedActions = [];
                    $hasErrors = false;
                    foreach ($actions as $actionData) {
                        try {
                            $result = executeActionDirectly($actionData, $aiUser);
                            $result['action_type'] = $actionData['type'] ?? 'unknown';
                            $result['action_data'] = $actionData['data'] ?? [];
                            $executedActions[] = $result;
                            if (!$result['success']) {
                                $hasErrors = true;
                                error_log("Action execution failed: " . json_encode($result));
                            }
                        } catch (Throwable $e) {
                            error_log("Fatal error executing action: " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
                            $executedActions[] = [
                                'success' => false,
                                'message' => 'Fatal error executing action: ' . $e->getMessage(),
                                'action_type' => $actionData['type'] ?? 'unknown',
                                'action_data' => $actionData['data'] ?? [],
                            ];
                            $hasErrors = true;
                        }
                    }
                    
                    $chatPersistContext['execution_results'] = $executedActions;
                    $rawMessage = (string) ($response['message'] ?? '');
                    $displayNarrative = ai_chat_sanitize_display_message($rawMessage);
                    if ($displayNarrative === '' || ai_chat_message_contains_tool_call_markup($rawMessage)) {
                        $displayNarrative = count($executedActions) === 1
                            ? 'Applied the requested change.'
                            : 'Applied **' . count($executedActions) . '** changes to your site.';
                    }
                    if ($executedActions !== []) {
                        $lines = [];
                        foreach ($executedActions as $result) {
                            $lines[] = '- ' . ai_chat_describe_executed_action($result);
                        }
                        $note = "**Actions completed:**\n" . implode("\n", $lines);
                        $response['message'] = $displayNarrative !== '' ? $displayNarrative . "\n\n" . $note : $note;
                    } elseif ($displayNarrative !== '') {
                        $response['message'] = $displayNarrative;
                    }
                    ai_chat_emit_response([
                        'status' => $hasErrors ? 'partial' : 'ok',
                        'data' => [
                            'response' => $response['message'],
                            'actions_executed' => count($executedActions),
                            'execution_results' => $executedActions,
                            'requires_approval' => false,
                            'has_errors' => $hasErrors,
                            'read_data' => $readData,
                        ],
                    ], $streamActive, $chatPersistContext);
                }
            } else {
                // No write actions - return the model response as-is
                $rawMessage = (string) ($response['message'] ?? '');
                $finalMessage = ai_chat_sanitize_display_message($rawMessage);

                if (ai_chat_message_contains_tool_call_markup($rawMessage)) {
                    if (!empty($readData)) {
                        $readSummary = ai_chat_summarize_read_results($readData);
                        $finalMessage = $readSummary !== '' ? $readSummary : 'Retrieved project data.';
                    } elseif ($finalMessage === '') {
                        $finalMessage = 'Working on your request…';
                    }
                }

                if ($actions === []
                    && ai_chat_response_implies_false_execution_claim($finalMessage)) {
                    $finalMessage .= "\n\n⚠️ _No CMS actions were actually executed this turn. Ask again or check Approvals if approval is required._";
                }

                // Stub or empty after reads — run analysis if the user asked for a review
                if ((empty($finalMessage) || ai_chat_response_is_read_stub($finalMessage))
                    && !empty($readData)
                    && ($analysisIntent || ai_chat_message_implies_analysis_intent($message, $conversationHistory))) {
                    error_log('Read stub or empty message after reads — retrying analysis follow-up');
                    $analysisResponse = ai_chat_run_read_analysis(
                        $aiUser,
                        $apiKey,
                        $systemPrompt,
                        $message,
                        $conversationId,
                        $conversationHistory,
                        $readData,
                        $readActions ?? []
                    );
                    if (($analysisResponse['success'] ?? false) && trim((string) ($analysisResponse['message'] ?? '')) !== '') {
                        $finalMessage = ai_chat_sanitize_display_message((string) $analysisResponse['message']);
                    }
                }

                // Only use fallback formatting if AI didn't provide any response at all
                if (empty($finalMessage) && !empty($readData)) {
                    error_log("AI didn't provide a response but we have read data - using generic fallback");
                    $finalMessage = "I've retrieved the requested data (" . count($readData) . " read operation(s)), "
                        . "but couldn't produce an analysis with the current model. "
                        . "Try asking again or switch to a stronger model on OpenRouter.";
                }
                
                ai_chat_emit_response([
                    'status' => 'ok',
                    'data' => [
                        'response' => $finalMessage,
                        'requires_approval' => false,
                        'read_data' => $readData,
                    ],
                ], $streamActive, $chatPersistContext);
            }
        } else {
            $providerError = $response['message'] ?? 'Failed to get AI response';

            ai_chat_emit_response([
                'status' => 'error',
                'message' => str_contains(strtolower($providerError), 'openai api error') && ($aiUser['provider'] ?? '') === 'openrouter'
                    ? str_replace('OpenAI API error', 'OpenRouter API error', $providerError)
                    : $providerError,
                'error_details' => is_array($response['error_details'] ?? null) ? $response['error_details'] : null,
            ], $streamActive);
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
            'message' => 'A fatal error occurred: ' . $e->getMessage(),
            'error_type' => get_class($e),
        ]);
        exit;
    }
}

} // end !$__aiChatInternalLibrary router

if ($__aiChatInternalLibrary) {
    require_once __DIR__ . '/storage.php';
    require_once __DIR__ . '/ai_helpers.php';
    require_once __DIR__ . '/../core/ai_tool_registry.php';
}

// Build system prompt with context (SIMPLIFIED VERSION)
function buildSystemPrompt($context, $aiUser) {
    // Use simplified prompt builder
    require_once __DIR__ . '/ai_chat_simple_prompt.php';
    return buildSystemPromptSimple($context, $aiUser);
}

// OLD COMPLEX PROMPT - KEPT FOR REFERENCE BUT NOT USED
function buildSystemPromptOld($context, $aiUser) {
    $prompt = "You are an AI assistant integrated into Birkly, a flat-file content management system.\n\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "CRITICAL CONTEXT: You are operating WITHIN Birkly.\n";
    $prompt .= "All user questions are about THIS specific Birkly installation.\n";
    $prompt .= "DO NOT provide generic web development advice (React, Vue, external APIs, etc.).\n";
    $prompt .= "ALWAYS provide Birkly-specific solutions using Birkly templating syntax.\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n\n";
    
    $prompt .= "SYSTEM INFORMATION:\n";
    $prompt .= "- CMS Name: " . ($context['system_info']['cms_name'] ?? 'Birkly') . "\n";
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
    $prompt .= "BIRKLY TEMPLATING SYSTEM - THIS IS HOW YOU DISPLAY COLLECTIONS ON WEBSITES\n";
    $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
    $prompt .= "Birkly uses its OWN custom templating syntax with SINGLE braces: { like this }\n";
    $prompt .= "NOT Twig, NOT Liquid, NOT any other templating engine.\n\n";
    
    $prompt .= "BASIC SYNTAX:\n";
    $prompt .= "- Display variable: { title }\n";
    $prompt .= "- Loop entries: {for each entry in 'collection-slug'} ... {endfor}\n";
    $prompt .= "- Hyphenated collection slugs (nav-links) work in quoted names; do not rename collections to remove hyphens.\n";
    $prompt .= "- Public site loops only receive published entries. Use status: published when creating nav/menu entries (or update_entry after create).\n";
    $prompt .= "- Template tags work in any HTML element in <body>; client renders outermost elements containing Birkly syntax.\n";
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
    $prompt .= "    <script src=\"/birkly-client.js\"></script>\n";
    $prompt .= "</body>\n";
    $prompt .= "</html>\n\n";
    
    $prompt .= "KEY POINTS:\n";
    $prompt .= "1. Include the Birkly client script: <script src=\"/birkly-client.js\"></script>\n";
    $prompt .= "2. Use {for each entry in 'collection-slug'} to loop through entries\n";
    $prompt .= "3. Use field names from the collection schema (e.g., { title }, { text }, { name })\n";
    $prompt .= "4. The client library automatically fetches and renders the content\n";
    $prompt .= "5. Collections are accessed via the Birkly API, not external APIs\n\n";
    
    $prompt .= "WHEN USERS ASK 'how to display collection X on my website':\n";
    $prompt .= "1. Provide Birkly templating code using {for each entry in 'collection-slug'}\n";
    $prompt .= "2. Show them how to include the client library script\n";
    $prompt .= "3. Use the actual field names from the collection schema\n";
    $prompt .= "4. DO NOT suggest React, Vue, external APIs, or other frameworks\n";
    $prompt .= "5. DO NOT suggest Twig, Liquid, or other templating engines\n";
    $prompt .= "6. Reference the Birkly documentation provided below\n\n";
    
    if (!empty($context['collections'])) {
        $prompt .= "AVAILABLE COLLECTIONS IN THIS BIRKLY INSTALLATION:\n";
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
        $prompt .= "NOTE: No collections are currently configured in this Birkly installation.\n\n";
    }
    
    $prompt .= "YOUR CAPABILITIES:\n";
    $prompt .= "- Answer questions about Birkly and this specific installation\n";
    $prompt .= "- List and view content from collections\n";
    $prompt .= "- CREATE NEW COLLECTIONS (if you have admin/editor permissions)\n";
    $prompt .= "- Create new entries in existing collections (if you have write permissions)\n";
    $prompt .= "- Update existing entries (if you have write permissions)\n";
    $prompt .= "- Help users understand the CMS structure and collections\n\n";
    
    $prompt .= "IMPORTANT CONTEXT RULES:\n";
    $prompt .= "1. When users ask 'what collections do I have' or 'which collections', they mean the collections in THIS Birkly installation listed above.\n";
    $prompt .= "2. Always refer to the specific collections listed in the 'AVAILABLE COLLECTIONS' section above.\n";
    $prompt .= "3. You are working within Birkly - not a generic system. Be specific about this installation.\n";
    $prompt .= "4. If no collections are listed, inform the user that no collections are configured yet.\n";
    $prompt .= "5. When answering questions, always reference the actual collections and data from THIS Birkly system.\n\n";
    
    $prompt .= "ACTION FORMAT (REQUIRED FOR ALL ACTIONS):\n";
    $prompt .= "When the user asks you to create collections, create entries, update, or delete content, you MUST include a JSON object in your response.\n";
    $prompt .= "The JSON must be in this exact format:\n";
    $prompt .= '```json' . "\n";
    $prompt .= '{"actions": [{"type": "action_type", "data": {...}, "description": "..."}]}' . "\n";
    $prompt .= '```' . "\n";
    $prompt .= "\n";
    $prompt .= "SUPPORTED ACTION TYPES (Birkly ONLY - NEVER OTHER PLATFORMS):\n";
    $prompt .= "READ ACTIONS (execute immediately, no approval needed):\n";
    $prompt .= "1. 'read_entry' - Read a specific entry by slug or ID\n";
    $prompt .= "   Data format: {\"collection\": \"collection_slug\", \"slug\": \"entry-slug\"} OR {\"collection\": \"collection_slug\", \"id\": \"entry_id\"}\n";
    $prompt .= "   Use this when the user asks you to check, review, or read a specific entry.\n";
    $prompt .= "2. 'list_entries' - List entries in a collection\n";
    $prompt .= "   Data format: {\"collection\": \"collection_slug\", \"limit\": 100}\n";
    $prompt .= "   Use this when the user asks you to list or show entries from a collection.\n";
    $prompt .= "\n";
    $prompt .= "WRITE ACTIONS (may require approval depending on AI connection settings):\n";
    $prompt .= "3. 'create_collection' - Create a new Birkly collection\n";
    $prompt .= "   Data format: {\"name\": \"Collection Name\", \"slug\": \"collection-slug\", \"fields\": [{\"name\": \"title\", \"type\": \"text\", \"label\": \"Title\"}, ...]}\n";
    $prompt .= "   ALWAYS assume the user means a Birkly collection (flat-file collection in this CMS), NEVER MongoDB, SQL tables, Airtable, Notion, etc.\n";
    $prompt .= "   Example: If the user says \"create a new collection called Approval Test\", you MUST:\n";
    $prompt .= "     - Treat this as a Birkly collection\n";
    $prompt .= "     - Pick a reasonable slug (e.g., \"approval-test\")\n";
    $prompt .= "     - Define a simple fields array (e.g., title, text)\n";
    $prompt .= "     - Return a JSON action block like:\n";
    $prompt .= "       ```json\n";
    $prompt .= "       {\"actions\":[{\"type\":\"create_collection\",\"data\":{\"name\":\"Approval Test\",\"slug\":\"approval-test\",\"fields\":[{\"name\":\"title\",\"type\":\"text\",\"label\":\"Title\",\"required\":true},{\"name\":\"text\",\"type\":\"textarea\",\"label\":\"Text\"}]},\"description\":\"Create Approval Test collection in Birkly\"}]}\n";
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
    $prompt .= "9. NEVER ask \"which platform\", \"which database\", or similar - ALWAYS assume Birkly.\n";
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
        $prompt .= "BIRKLY DOCUMENTATION (CRITICAL - READ THIS CAREFULLY):\n";
        $prompt .= "The following documentation contains essential information about how Birkly works.\n";
        $prompt .= "You MUST use this documentation to answer questions about Birkly functionality.\n";
        $prompt .= "DO NOT make assumptions about how Birkly works - refer to the documentation below.\n";
        $prompt .= "When users ask about displaying collections, templating, or website integration, you MUST use the Birkly-specific syntax from this documentation.\n\n";
        
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
        $prompt .= "1. Birkly uses a custom templating syntax with SINGLE braces { like this }\n";
        $prompt .= "   NOT Twig, NOT Liquid, NOT any other templating engine.\n";
        $prompt .= "2. To display collections on a website, use: {for each entry in 'collection-slug'} ... {endfor}\n";
        $prompt .= "3. The client library (birkly-client.js) handles template rendering.\n";
        $prompt .= "4. Collections are accessed via the Birkly API, not external APIs or databases.\n";
        $prompt .= "5. When users ask 'how to display collection X', they want Birkly templating code.\n";
        $prompt .= "6. DO NOT suggest React, Vue, Svelte, or other frameworks unless explicitly asked.\n";
        $prompt .= "7. DO NOT suggest external APIs, databases, or headless CMS solutions.\n";
        $prompt .= "8. ALWAYS provide Birkly-specific solutions using the syntax shown above.\n";
        $prompt .= "9. If you're unsure, refer to the documentation above - it contains the correct syntax.\n";
        $prompt .= "10. Example answer format for 'how to display collection X':\n";
        $prompt .= "    'To display the X collection on your website, use this Birkly template code:\n";
        $prompt .= "    {for each entry in \"x\"}\n";
        $prompt .= "        <div>{ title }</div>\n";
        $prompt .= "    {endfor}\n";
        $prompt .= "    Include the script: <script src=\"/birkly-client.js\"></script>'\n\n";
    }
    
    return $prompt;
}

// End of old complex prompt

// Call AI provider API
function callAiProvider($provider, $apiKey, $model, $systemPrompt, $userMessage, $conversationId = '', $apiUrl = '', $conversationHistory = [], $aiUser = null, $onToken = null, array $attachments = [], array $providerOptions = []) {
    $providers = getSupportedAiProviders();
    $providerInfo = $providers[$provider] ?? null;
    $providerType = $providerInfo['type'] ?? $provider;
    $imageAttachments = birkly_ai_chat_image_attachments($attachments);
    $toolChoice = (string) ($providerOptions['tool_choice'] ?? 'auto');
    $disableTools = !empty($providerOptions['disable_tools']);
    $curlTimeout = max(30, (int) ($providerOptions['timeout'] ?? 60));

    $openAiTools = [];
    if (is_array($aiUser) && !$disableTools) {
        $openAiTools = birkly_ai_tools_openai_format($aiUser);
    }
    
    $resolvedModel = birkly_ai_resolve_model($provider, $model);

    switch ($providerType) {
        case 'openai':
        case 'openrouter':
            $url = $apiUrl ?: ($providerInfo['api_url'] ?? 'https://api.openai.com/v1/chat/completions');
            if ($onToken && empty($openAiTools) && $imageAttachments === []) {
                return callOpenAIStream($apiKey, $resolvedModel, $systemPrompt, $userMessage, $url, $conversationHistory, $onToken);
            }
            return callOpenAI($apiKey, $resolvedModel, $systemPrompt, $userMessage, $url, $conversationHistory, $openAiTools, $imageAttachments, $toolChoice, $curlTimeout);
        case 'anthropic':
            return callAnthropic($apiKey, $resolvedModel, $systemPrompt, $userMessage, $conversationHistory, $imageAttachments);
        case 'google':
            return callGoogle($apiKey, $resolvedModel, $systemPrompt, $userMessage, $conversationHistory, $imageAttachments);
        case 'ollama':
            $url = $apiUrl ?: ($providerInfo['api_url'] ?? 'http://localhost:11434/api/chat');
            if ($onToken && $imageAttachments === []) {
                return callOllamaStream($url, $resolvedModel, $systemPrompt, $userMessage, $conversationHistory, $onToken);
            }
            return callOllama($url, $resolvedModel, $systemPrompt, $userMessage, $conversationHistory);
        case 'custom':
            $url = $apiUrl;
            if (empty($url)) {
                return ['success' => false, 'message' => 'API URL required for custom provider'];
            }
            if ($resolvedModel === '') {
                return ['success' => false, 'message' => 'Model required for custom provider'];
            }
            if ($onToken && empty($openAiTools) && $imageAttachments === []) {
                return callOpenAIStream($apiKey, $resolvedModel, $systemPrompt, $userMessage, $url, $conversationHistory, $onToken);
            }
            return callOpenAI($apiKey, $resolvedModel, $systemPrompt, $userMessage, $url, $conversationHistory, $openAiTools, $imageAttachments, $toolChoice, $curlTimeout);
        default:
            return ['success' => false, 'message' => 'Unsupported provider'];
    }
}

/**
 * Prose-only follow-up after reads — for review/optimize questions (no tool calls).
 *
 * @param list<array<string,mixed>> $readData
 * @param list<array<string,mixed>> $readActions
 */
function ai_chat_run_read_analysis(
    array $aiUser,
    string $apiKey,
    string $systemPrompt,
    string $originalMessage,
    string $conversationId,
    array $conversationHistory,
    array $readData,
    array $readActions = []
): array {
    $analysisMessage = "The user asked:\n\"{$originalMessage}\"\n\n";
    $analysisMessage .= "You already have the project/site data below. Do NOT call tools or say you will read files.\n";
    $analysisMessage .= "Write a complete, helpful answer now — specific findings and actionable recommendations.\n\n";
    $analysisMessage .= "Cover where relevant: HTML structure, CSS/styling, performance, SEO, accessibility, Birkly templating, and CMS content gaps.\n\n";
    $analysisMessage .= "DATA RETRIEVED:\n\n";
    foreach ($readData as $readResult) {
        $formatted = ai_chat_format_read_result_for_followup($readResult, $readActions);
        if ($formatted !== '') {
            $analysisMessage .= $formatted;
        } elseif (($readResult['success'] ?? false) && is_array($readResult['data'] ?? null)) {
            $analysisMessage .= json_encode($readResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        }
    }

    return callAiProvider(
        $aiUser['provider'] ?? '',
        $apiKey,
        $aiUser['model'] ?? '',
        $systemPrompt,
        $analysisMessage,
        $conversationId,
        $aiUser['api_url'] ?? '',
        $conversationHistory,
        $aiUser,
        null,
        [],
        ['disable_tools' => true]
    );
}

/**
 * Second-chance provider call when the model replied in prose but omitted executable actions.
 *
 * @param list<array<string,mixed>> $readData
 */
function ai_chat_nudge_for_actions(
    array $aiUser,
    string $apiKey,
    string $systemPrompt,
    string $originalMessage,
    string $assistantMessage,
    string $conversationId,
    array $conversationHistory,
    array $readData = []
): array {
    $providers = getSupportedAiProviders();
    $providerType = $providers[$aiUser['provider'] ?? '']['type'] ?? ($aiUser['provider'] ?? '');
    $hasNativeTools = in_array($providerType, ['openai', 'openrouter', 'custom'], true)
        && birkly_ai_tools_openai_format($aiUser) !== [];

    $nudgeMessage = "The user asked:\n\"{$originalMessage}\"\n\n";
    $nudgeMessage .= "Your previous reply did not include executable CMS actions (no tool calls and no JSON action block).\n";
    $nudgeMessage .= "Do NOT claim work is done. Execute the requested changes now.\n";
    if (trim($assistantMessage) !== '') {
        $nudgeMessage .= "\nYour previous reply (for context):\n" . substr($assistantMessage, 0, 2000) . "\n";
    }
    if ($readData !== []) {
        $nudgeMessage .= "\nData already retrieved this turn:\n"
            . substr(json_encode($readData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 12000) . "\n";
    }
    if ($hasNativeTools) {
        $nudgeMessage .= "\nUse native tool calls for every change. Paths: index.html, css/style.css (no project/ prefix).\n";
    } else {
        $nudgeMessage .= "\nRespond with ONLY a fenced ```json block: {\"actions\":[{\"type\":\"...\", ...}]}\n";
    }

    $providerOptions = ['tool_choice' => $hasNativeTools ? 'required' : 'auto'];

    return callAiProvider(
        $aiUser['provider'] ?? '',
        $apiKey,
        $aiUser['model'] ?? '',
        $systemPrompt,
        $nudgeMessage,
        $conversationId,
        $aiUser['api_url'] ?? '',
        $conversationHistory,
        $aiUser,
        null,
        [],
        $providerOptions
    );
}

// Execute action directly (when approval not required)
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
function callOpenAI($apiKey, $model, $systemPrompt, $userMessage, $url = 'https://api.openai.com/v1/chat/completions', $conversationHistory = [], $tools = [], array $imageAttachments = [], string $toolChoice = 'auto', int $curlTimeout = 60) {
    $apiLabel = birkly_ai_compatible_api_label($url);
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
    
    // Add current user message (multimodal when images attached)
    $messages[] = [
        'role' => 'user',
        'content' => birkly_ai_build_openai_user_content($userMessage, $imageAttachments),
    ];
    
    $data = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7
    ];

    if (!empty($tools)) {
        $data['tools'] = $tools;
        $allowedToolChoice = ['auto', 'required', 'none'];
        $data['tool_choice'] = in_array($toolChoice, $allowedToolChoice, true) ? $toolChoice : 'auto';
    }
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    
    // OpenRouter requires additional headers
    if (strpos($url, 'openrouter.ai') !== false) {
        $headers[] = 'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $headers[] = 'X-Title: Birkly';
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, $curlTimeout);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'message' => 'Connection error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        if (!empty($tools) && $toolChoice === 'required' && in_array((int) $httpCode, [400, 422], true)) {
            error_log($apiLabel . ' rejected tool_choice=required — retrying with auto');
            return callOpenAI($apiKey, $model, $systemPrompt, $userMessage, $url, $conversationHistory, $tools, $imageAttachments, 'auto', $curlTimeout);
        }
        return birkly_ai_build_provider_error(
            $apiLabel,
            (int) $httpCode,
            is_string($response) ? $response : '',
            $model,
            stripos($url, 'openrouter.ai') !== false ? 'openrouter' : (stripos($url, 'api.openai.com') !== false ? 'openai' : 'custom')
        );
    }
    
    $result = json_decode($response, true);
    $choiceMessage = $result['choices'][0]['message'] ?? null;
    if (!$result || !$choiceMessage) {
        return [
            'success' => false,
            'message' => 'Invalid response from ' . $apiLabel,
            'error_details' => [
                'provider_label' => $apiLabel,
                'model' => $model,
                'hint' => 'The provider returned an unexpected response format.',
                'raw_body' => is_string($response) ? substr($response, 0, 800) : '',
            ],
        ];
    }

    $toolCalls = $choiceMessage['tool_calls'] ?? [];
    $content = $choiceMessage['content'] ?? '';
    if (!empty($toolCalls)) {
        return [
            'success' => true,
            'message' => is_string($content) ? $content : '',
            'tool_calls' => $toolCalls,
        ];
    }
    if ($content === '' || $content === null) {
        return ['success' => false, 'message' => 'Empty response from ' . $apiLabel];
    }

    return ['success' => true, 'message' => $content];
}

// Anthropic API call
function callAnthropic($apiKey, $model, $systemPrompt, $userMessage, $conversationHistory = [], array $imageAttachments = []) {
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
    $messages[] = [
        'role' => 'user',
        'content' => birkly_ai_build_anthropic_user_content($userMessage, $imageAttachments),
    ];
    
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
        return birkly_ai_build_provider_error('Anthropic', (int) $httpCode, is_string($response) ? $response : '', $model, 'anthropic');
    }
    
    $result = json_decode($response, true);
    if (!$result || !isset($result['content'][0]['text'])) {
        return ['success' => false, 'message' => 'Invalid response from Anthropic'];
    }
    
    return ['success' => true, 'message' => $result['content'][0]['text']];
}

// Google Gemini API call
function callGoogle($apiKey, $model, $systemPrompt, $userMessage, $conversationHistory = [], array $imageAttachments = []) {
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
    $currentText = trim($userMessage) !== '' ? $userMessage : 'Please analyze the attached image(s).';
    $conversationText .= "User: " . $currentText;

    if ($imageAttachments !== []) {
        $parts = [['text' => $conversationText]];
        foreach ($imageAttachments as $img) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => (string) ($img['mime'] ?? 'image/png'),
                    'data' => (string) ($img['base64'] ?? ''),
                ],
            ];
        }
        $data = ['contents' => [['parts' => $parts]]];
    } else {
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $conversationText],
                    ],
                ],
            ],
        ];
    }
    
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
        return birkly_ai_build_provider_error('Google Gemini', (int) $httpCode, is_string($response) ? $response : '', $model, 'google');
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

/**
 * Begin SSE response for streaming admin chat (P39-M12).
 */
function birkly_ai_sse_begin(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
    }
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
}

/**
 * Send one SSE event to the client.
 *
 * @param array<string,mixed> $data
 */
function birkly_ai_sse_send(string $event, array $data): void
{
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

/**
 * Summarize queued write actions when the model returned no text.
 *
 * @param list<array<string, mixed>> $actions
 */
function ai_chat_summarize_pending_actions(array $actions): string
{
    $lines = [];
    foreach ($actions as $action) {
        $lines[] = '• ' . (string) ($action['description'] ?? $action['type'] ?? 'Action');
    }
    return "I've prepared the following for your review:\n\n" . implode("\n", $lines);
}

/**
 * Persist conversation after the final assistant message is known.
 */
function ai_chat_persist_conversation(
    string $conversationId,
    string $aiConnectionId,
    string $userId,
    array $conversationHistory,
    string $userMessage,
    string $assistantMessage,
    string $sessionNotes = ''
): void {
    if ($conversationId === '') {
        return;
    }

    $conversationStorage = new Storage('../data/ai_conversations');
    if (!is_dir($conversationStorage->basePath)) {
        mkdir($conversationStorage->basePath, 0755, true);
    }

    $history = $conversationHistory;
    $lastUser = end($history);
    if (!$lastUser || ($lastUser['role'] ?? '') !== 'user' || ($lastUser['content'] ?? '') !== $userMessage) {
        $history[] = ['role' => 'user', 'content' => $userMessage];
    }
    if (trim($assistantMessage) !== '') {
        $content = $assistantMessage;
        if ($sessionNotes !== '') {
            $content .= $sessionNotes;
        }
        $history[] = ['role' => 'assistant', 'content' => $content];
    }
    if (count($history) > 10) {
        $history = array_slice($history, -10);
    }

    $conversationStorage->write($conversationId . '.json', [
        'id' => $conversationId,
        'ai_connection_id' => $aiConnectionId,
        'user_id' => $userId,
        'messages' => $history,
        'updated_at' => date('c'),
    ]);
}

/**
 * Execute write actions immediately and emit chat response (used for deterministic proceed).
 *
 * @param list<array<string,mixed>> $actions
 * @param array<string,mixed> $aiUser
 * @param array<string,mixed> $chatPersistContext
 * @param list<array<string,mixed>> $readData
 */
function ai_chat_emit_direct_action_results(
    array $actions,
    string $responseMessage,
    array $aiUser,
    array $chatPersistContext,
    bool $streamActive,
    array $readData = [],
    string $providerError = ''
): void {
    $executedActions = [];
    $hasErrors = false;
    foreach ($actions as $actionData) {
        try {
            $result = executeActionDirectly($actionData, $aiUser);
            $result['action_type'] = $actionData['type'] ?? 'unknown';
            $result['action_data'] = $actionData['data'] ?? [];
            $executedActions[] = $result;
            if (!$result['success']) {
                $hasErrors = true;
                error_log('Proceed fallback action failed: ' . json_encode($result));
            }
        } catch (Throwable $e) {
            error_log('Proceed fallback action exception: ' . $e->getMessage());
            $executedActions[] = [
                'success' => false,
                'message' => 'Error executing action: ' . $e->getMessage(),
                'action_type' => $actionData['type'] ?? 'unknown',
                'action_data' => $actionData['data'] ?? [],
            ];
            $hasErrors = true;
        }
    }

    $message = ai_chat_sanitize_display_message($responseMessage);
    if ($providerError !== '' && $hasErrors) {
        $message .= "\n\n(Provider error: " . $providerError . ')';
    }

    if ($executedActions !== []) {
        $lines = [];
        foreach ($executedActions as $result) {
            $lines[] = '- ' . ai_chat_describe_executed_action($result);
        }
        $note = "**Actions completed:**\n" . implode("\n", $lines);
        $message = $message !== '' ? $message . "\n\n" . $note : $note;
    }

    $chatPersistContext['execution_results'] = $executedActions;
    ai_chat_emit_response([
        'status' => $hasErrors ? 'partial' : 'ok',
        'data' => [
            'response' => $message,
            'actions_executed' => count($executedActions),
            'execution_results' => $executedActions,
            'requires_approval' => false,
            'has_errors' => $hasErrors,
            'read_data' => $readData,
        ],
    ], $streamActive, $chatPersistContext);
}

/**
 * Emit final chat response as JSON or SSE done event.
 *
 * @param array<string,mixed> $payload
 * @param array<string,mixed>|null $persistContext
 */
function ai_chat_emit_response(array $payload, bool $streaming, ?array $persistContext = null): void
{
    $status = (string) ($payload['status'] ?? '');
    if ($status === '' && isset($payload['data']) && is_array($payload['data'])) {
        $payload['status'] = !empty($payload['data']['has_errors']) ? 'partial' : 'ok';
        $status = $payload['status'];
    }

    if ($persistContext !== null && in_array($status, ['ok', 'partial'], true)) {
        $sessionNotes = '';
        $executionResults = $persistContext['execution_results'] ?? null;
        if (is_array($executionResults) && $executionResults !== []) {
            $sessionNotes = ai_chat_build_session_notes($executionResults);
        }
        if (isset($payload['data']['response']) && is_string($payload['data']['response'])) {
            $payload['data']['response'] = ai_chat_sanitize_display_message($payload['data']['response']);
        }
        ai_chat_persist_conversation(
            (string) ($persistContext['conversation_id'] ?? ''),
            (string) ($persistContext['ai_connection_id'] ?? ''),
            (string) ($persistContext['user_id'] ?? ''),
            is_array($persistContext['history'] ?? null) ? $persistContext['history'] : [],
            (string) ($persistContext['user_message'] ?? ''),
            (string) (($payload['data']['response'] ?? '') ?: ''),
            $sessionNotes
        );
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if ($streaming) {
        if (!headers_sent()) {
            birkly_ai_sse_begin();
        }
        birkly_ai_sse_send('done', $payload);
    } else {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

/**
 * Stream OpenAI-compatible chat completions token-by-token.
 *
 * @param callable(string):void $onToken
 */
function callOpenAIStream($apiKey, $model, $systemPrompt, $userMessage, $url = 'https://api.openai.com/v1/chat/completions', $conversationHistory = [], $onToken) {
    $apiLabel = birkly_ai_compatible_api_label($url);
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
    ];

    foreach ($conversationHistory as $historyMsg) {
        if ($historyMsg['role'] === 'user' && $historyMsg['content'] === $userMessage && end($conversationHistory) === $historyMsg) {
            continue;
        }
        $messages[] = $historyMsg;
    }

    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $data = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
        'stream' => true,
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    if (strpos($url, 'openrouter.ai') !== false) {
        $headers[] = 'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $headers[] = 'X-Title: Birkly';
    }

    $buffer = '';
    $fullContent = '';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$buffer, &$fullContent, $onToken) {
        $buffer .= $chunk;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = trim(substr($buffer, 0, $pos));
            $buffer = substr($buffer, $pos + 1);
            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }
            if (strpos($line, 'data: ') !== 0) {
                continue;
            }
            $json = json_decode(substr($line, 6), true);
            if (!is_array($json)) {
                continue;
            }
            $token = $json['choices'][0]['delta']['content'] ?? '';
            if ($token !== '') {
                $fullContent .= $token;
                $onToken($token);
            }
        }
        return strlen($chunk);
    });

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'message' => 'Connection error: ' . $curlError];
    }

    if ($httpCode !== 200) {
        return birkly_ai_build_provider_error(
            $apiLabel,
            (int) $httpCode,
            $buffer,
            $model,
            stripos($url, 'openrouter.ai') !== false ? 'openrouter' : (stripos($url, 'api.openai.com') !== false ? 'openai' : 'custom')
        );
    }

    if ($fullContent === '') {
        return ['success' => false, 'message' => 'Empty response from ' . $apiLabel];
    }

    return ['success' => true, 'message' => $fullContent];
}

/**
 * Stream Ollama chat responses token-by-token.
 *
 * @param callable(string):void $onToken
 */
function callOllamaStream($url, $model, $systemPrompt, $userMessage, $conversationHistory = [], $onToken) {
    if (strpos($url, '/api/chat') === false) {
        $url = rtrim($url, '/') . '/api/chat';
    }

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
    ];

    foreach ($conversationHistory as $historyMsg) {
        if ($historyMsg['role'] === 'user' && $historyMsg['content'] === $userMessage && end($conversationHistory) === $historyMsg) {
            continue;
        }
        $messages[] = $historyMsg;
    }

    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $data = [
        'model' => $model,
        'messages' => $messages,
        'stream' => true,
        'options' => [
            'temperature' => 0.7,
        ],
    ];

    $buffer = '';
    $fullContent = '';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$buffer, &$fullContent, $onToken) {
        $buffer .= $chunk;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = trim(substr($buffer, 0, $pos));
            $buffer = substr($buffer, $pos + 1);
            if ($line === '') {
                continue;
            }
            $json = json_decode($line, true);
            if (!is_array($json)) {
                continue;
            }
            if (!empty($json['error'])) {
                continue;
            }
            $token = $json['message']['content'] ?? '';
            if ($token !== '') {
                $fullContent .= $token;
                $onToken($token);
            }
        }
        return strlen($chunk);
    });

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'message' => 'Connection error: ' . $curlError];
    }

    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Ollama API error: HTTP ' . $httpCode];
    }

    if ($fullContent === '') {
        return ['success' => false, 'message' => 'Empty response from Ollama'];
    }

    return ['success' => true, 'message' => $fullContent];
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
function extractActionsFromResponse($response, $toolCalls = []) {
    $actions = [];
    $response = (string) $response;

    if (!empty($toolCalls)) {
        $actions = array_merge($actions, birkly_ai_tool_calls_to_actions($toolCalls));
    }

    $actions = array_merge($actions, ai_chat_extract_xml_tool_calls($response));

    // Fenced ```json blocks — brace matching (CSS/HTML in content breaks naive regex)
    if (preg_match_all('/```(?:json)?\s*/i', $response, $fenceMatches, PREG_OFFSET_CAPTURE)) {
        foreach ($fenceMatches[0] as $match) {
            $searchFrom = $match[1] + strlen($match[0]);
            $brace = strpos($response, '{', $searchFrom);
            if ($brace === false) {
                continue;
            }
            $end = ai_chat_find_json_object_end($response, $brace);
            if ($end === null) {
                continue;
            }
            $json = json_decode(substr($response, $brace, $end - $brace), true);
            if (!is_array($json)) {
                continue;
            }
            if (isset($json['actions']) && is_array($json['actions'])) {
                $actions = array_merge($actions, $json['actions']);
            } elseif (isset($json['type'])) {
                $actions[] = $json;
            }
        }
    }

    if ($actions === []) {
        foreach (ai_chat_extract_json_objects($response, 'actions') as $json) {
            if (isset($json['actions']) && is_array($json['actions'])) {
                $actions = array_merge($actions, $json['actions']);
            }
        }
    }

    if ($actions === []) {
        foreach (ai_chat_extract_json_objects($response, 'type') as $json) {
            if (isset($json['type']) && is_string($json['type'])) {
                $actions[] = $json;
            }
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($actions as $action) {
        if (!is_array($action)) {
            continue;
        }
        $type = $action['type'] ?? $action['action'] ?? $action['name'] ?? '';
        if ($type === '') {
            continue;
        }
        $payload = birkly_ai_normalize_action_data($type, birkly_ai_extract_action_payload($action));
        $dedupeKey = $type . ':' . json_encode($payload);
        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;
        $validationError = birkly_ai_validate_action_before_execute([
            'type' => $type,
            'data' => $payload,
        ]);
        if ($validationError !== null) {
            error_log('Skipping invalid action: ' . $validationError);
            continue;
        }
        $normalized[] = [
            'type' => $type,
            'data' => $payload,
            'description' => $action['description'] ?? $type,
        ];
    }

    return $normalized;
}

// Default response for unknown actions (API router only — not when included as provider library)
if (!$__aiChatInternalLibrary) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit;
}

