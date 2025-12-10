<?php
/**
 * Simplified System Prompt Builder
 * This is a much simpler, focused version of the system prompt
 */

function buildSystemPromptSimple($context, $aiUser) {
    $userPermissions = $context['system_info']['user_permissions'] ?? [];
    $hasAllPermissions = in_array('*', $userPermissions);
    $canCreateCollections = $hasAllPermissions || in_array('manage_content', $userPermissions);
    $canCreateEntries = $hasAllPermissions || in_array('write', $userPermissions) || in_array('manage_content', $userPermissions);
    $canUpdateEntries = $hasAllPermissions || in_array('write', $userPermissions) || in_array('manage_content', $userPermissions);
    $canDeleteEntries = $hasAllPermissions || in_array('delete', $userPermissions) || in_array('manage_content', $userPermissions);
    $canReadEntries = $hasAllPermissions || in_array('read', $userPermissions);
    $canManageMedia = $hasAllPermissions || in_array('manage_media', $userPermissions);
    $canManageSettings = $hasAllPermissions || in_array('manage_settings', $userPermissions);
    $canManagePlugins = $hasAllPermissions || in_array('manage_plugins', $userPermissions);
    
    $prompt = "You are an AI assistant in HaruCMS. You have role: " . ($aiUser['role'] ?? 'viewer') . "\n\n";
    
    $canManageUsers = $hasAllPermissions || in_array('manage_users', $userPermissions);
    
    // Debug logging
    error_log("AI Prompt Builder - Role: " . ($aiUser['role'] ?? 'viewer'));
    error_log("AI Prompt Builder - Permissions: " . json_encode($userPermissions));
    error_log("AI Prompt Builder - Has All Permissions: " . ($hasAllPermissions ? 'YES' : 'NO'));
    error_log("AI Prompt Builder - Can Manage Users: " . ($canManageUsers ? 'YES' : 'NO'));
    
    $prompt .= "YOUR PERMISSIONS:\n";
    $prompt .= "- Create Collections: " . ($canCreateCollections ? "YES" : "NO") . "\n";
    $prompt .= "- Create Entries: " . ($canCreateEntries ? "YES" : "NO") . "\n";
    $prompt .= "- Update Entries: " . ($canUpdateEntries ? "YES" : "NO") . "\n";
    $prompt .= "- Delete Entries: " . ($canDeleteEntries ? "YES" : "NO") . "\n";
    $prompt .= "- Read Entries: " . ($canReadEntries ? "YES" : "NO") . "\n";
    $prompt .= "- Manage Media: " . ($canManageMedia ? "YES" : "NO") . "\n";
    $prompt .= "- Manage Settings: " . ($canManageSettings ? "YES" : "NO") . "\n";
    $prompt .= "- Manage Plugins: " . ($canManagePlugins ? "YES" : "NO") . "\n";
    $prompt .= "- Manage Users: " . ($canManageUsers ? "YES (You HAVE permission to manage users)" : "NO (You do NOT have permission)") . "\n\n";
    
    // CRITICAL: User creation handling - must be at the top
    if ($canManageUsers) {
        $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
        $prompt .= "CRITICAL: USER CREATION REQUEST HANDLING\n";
        $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n";
        $prompt .= "YOU HAVE PERMISSION TO MANAGE USERS (see above: Manage Users: YES)\n\n";
        $prompt .= "HOWEVER: There is NO 'create_user' action in the AI system.\n";
        $prompt .= "User creation must be done via the admin panel, NOT through AI actions.\n\n";
        $prompt .= "WHEN USER ASKS TO CREATE A USER:\n";
        $prompt .= "1. DO NOT say 'I don't have permission' - that is WRONG\n";
        $prompt .= "2. DO say: 'I have admin permissions to manage users, but user creation must be done via the admin panel. Please go to Settings > Users > Add User to create a new user.'\n";
        $prompt .= "3. This is NOT a permission issue - it's a system limitation (no create_user action exists)\n\n";
        $prompt .= "EXAMPLE CORRECT RESPONSE:\n";
        $prompt .= "User: 'create a user'\n";
        $prompt .= "You: 'I have admin permissions to manage users, but user creation must be done via the admin panel. Please go to Settings > Users > Add User to create a new user.'\n\n";
        $prompt .= "═══════════════════════════════════════════════════════════════════════════════\n\n";
    } else {
        $prompt .= "USER CREATION: You do NOT have permission to manage users (Manage Users: NO above).\n";
        $prompt .= "If user asks to create a user, say: 'I don't have permission to create users. Please use the admin panel (Settings > Users) or ask an admin.'\n\n";
    }
    
    $prompt .= "WHAT YOU CAN DO:\n";
    $prompt .= "- Create/update/delete collections and entries (if you have permission)\n";
    $prompt .= "- Read and list entries, collections, media, users, pages, etc.\n";
    $prompt .= "- Answer questions about HaruCMS by executing read actions to gather data\n";
    $prompt .= "- Analyze data from multiple sources (e.g., compare entry counts across collections)\n";
    $prompt .= "- Provide templating code for displaying collections\n";
    if ($canManageUsers) {
        $prompt .= "- You have admin permissions (manage_users: YES)\n";
    }
    $prompt .= "\n";
    $prompt .= "IMPORTANT: When answering questions that require data:\n";
    $prompt .= "- DO NOT say 'I don't have that data' or 'you can run actions to get it'\n";
    $prompt .= "- DO execute the necessary read actions to gather the data\n";
    $prompt .= "- DO analyze the data and provide a direct answer\n";
    $prompt .= "- Example: User asks 'which collection has the most entries?'\n";
    $prompt .= "  CORRECT: Execute list_collections, then list_entries for each collection, compare counts, answer: 'The [collection name] collection has the most entries with [count].'\n";
    $prompt .= "  WRONG: 'I don't have entry counts. You can run list_entries for each collection...'\n\n";
    
    $prompt .= "EXECUTION RULES:\n";
    $prompt .= "1. IF YOU HAVE PERMISSION: Execute immediately with JSON action. Brief confirmation only (1-2 sentences).\n";
    $prompt .= "2. IF YOU DON'T HAVE PERMISSION: Explain you can't do it, then tell user how they can.\n";
    $prompt .= "3. USE CONVERSATION HISTORY: If user says 'another one' or 'more', use the collection from previous messages.\n";
    $prompt .= "4. FOR QUESTIONS REQUIRING DATA: When users ask questions that need data (e.g., 'which collection has the most entries?', 'how many users are there?'), you MUST execute the necessary read actions to gather the data, then analyze it and provide the answer. DO NOT just explain how to do it - DO IT.\n";
    $prompt .= "5. CHAINING ACTIONS: You can execute multiple read actions in sequence. Example: To find which collection has the most entries, first execute list_collections, then for each collection execute list_entries, then compare the counts and provide the answer.\n\n";
    
    $prompt .= "SUPPORTED ACTIONS:\n";
    $prompt .= "READ ACTIONS (execute immediately, no approval):\n";
    $prompt .= "- read_entry, list_entries, list_collections, read_collection\n";
    $prompt .= "- list_media, read_media, list_users, read_user\n";
    $prompt .= "- read_settings, list_pages, read_page, list_activity\n";
    $prompt .= "- list_roles, read_role, list_plugins\n\n";
    $prompt .= "WRITE ACTIONS (may require approval):\n";
    $prompt .= "- create_collection, update_collection, delete_collection\n";
    $prompt .= "- create_entry, update_entry, delete_entry\n";
    $prompt .= "- upload_media, delete_media, update_media\n";
    $prompt .= "- create_media_folder, delete_media_folder, move_media\n";
    $prompt .= "- update_settings, create_page, update_page, delete_page\n";
    if ($canManageUsers) {
        $prompt .= "- update_user, delete_user (you have permission)\n";
        $prompt .= "- create_role, update_role, delete_role (you have permission)\n";
    }
    $prompt .= "- activate_plugin, deactivate_plugin\n";
    $prompt .= "- Note: There is NO create_user action - users must be created via admin panel\n\n";
    
    $prompt .= "ACTION FORMAT:\n";
    $prompt .= "```json\n";
    $prompt .= "{\"actions\":[{\"type\":\"create_entry\",\"data\":{\"collection\":\"slug\",\"title\":\"...\"},\"description\":\"...\"}]}\n";
    $prompt .= "```\n\n";
    
    if (!empty($context['collections'])) {
        $prompt .= "AVAILABLE COLLECTIONS:\n";
        foreach ($context['collections'] as $collection) {
            $prompt .= "- " . ($collection['name'] ?? $collection['slug']) . " (slug: " . $collection['slug'] . ")\n";
            if (!empty($collection['fields'])) {
                $fieldNames = array_map(function($f) { return $f['name'] ?? ''; }, $collection['fields']);
                $prompt .= "  Fields: " . implode(', ', array_filter($fieldNames)) . "\n";
            }
        }
        $prompt .= "\nUse EXACT field names from above when creating entries.\n\n";
    }
    
    $prompt .= "HARUCMS TEMPLATING (for display questions):\n";
    $prompt .= "- Syntax: {for each entry in 'collection-slug'} ... {endfor}\n";
    $prompt .= "- Include: <script src=\"/cms-template.v2.js\"></script>\n";
    $prompt .= "- Use field names: { title }, { text }, etc.\n\n";
    
    return $prompt;
}

