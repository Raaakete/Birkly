<?php
/**
 * System prompt for Birkly AI chat — conversational assistant with CMS context.
 */

function buildSystemPromptSimple($context, $aiUser) {
    require_once __DIR__ . '/../core/ai_tool_registry.php';

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
    $canManageUsers = $hasAllPermissions || in_array('manage_users', $userPermissions);

    $prompt = "You are a helpful AI assistant embedded in Birkly, a headless CMS.\n";
    $prompt .= "Role for this connection: " . ($aiUser['role'] ?? 'viewer') . "\n\n";

    $prompt .= "Respond naturally and completely to the user's question or request.\n";
    $prompt .= "- For questions, explanations, and analysis: give a full, direct answer.\n";
    $prompt .= "- For CMS changes (create/update/delete): use the available tools or JSON actions when you have permission.\n";
    $prompt .= "- When you need live CMS data, use read actions (list_collections, list_entries, etc.) rather than guessing.\n";
    $prompt .= "- There is NO create_user action — direct users to Settings > Users for account creation.\n\n";

    $prompt .= "YOUR PERMISSIONS:\n";
    $prompt .= "- Create Collections: " . ($canCreateCollections ? "YES" : "NO") . "\n";
    $prompt .= "- Create Entries: " . ($canCreateEntries ? "YES" : "NO") . "\n";
    $prompt .= "- Update Entries: " . ($canUpdateEntries ? "YES" : "NO") . "\n";
    $prompt .= "- Delete Entries: " . ($canDeleteEntries ? "YES" : "NO") . "\n";
    $prompt .= "- Read Entries: " . ($canReadEntries ? "YES" : "NO") . "\n";
    $prompt .= "- Manage Media: " . ($canManageMedia ? "YES" : "NO") . "\n";
    $prompt .= "- Manage Settings: " . ($canManageSettings ? "YES" : "NO") . "\n";
    $prompt .= "- Manage Plugins: " . ($canManagePlugins ? "YES" : "NO") . "\n";
    $prompt .= "- Manage Users: " . ($canManageUsers ? "YES" : "NO") . "\n\n";

    $prompt .= birkly_ai_tools_prompt_section($aiUser) . "\n";

    $prompt .= "ACTION FORMAT (when native tools are unavailable):\n";
    $prompt .= "```json\n";
    $prompt .= "{\"actions\":[{\"type\":\"create_entry\",\"collection\":\"slug\",\"title\":\"Title\",\"status\":\"published\"}]}\n";
    $prompt .= "```\n\n";

    if (!empty($context['collections'])) {
        $prompt .= "AVAILABLE COLLECTIONS:\n";
        foreach ($context['collections'] as $collection) {
            $prompt .= "- " . ($collection['name'] ?? $collection['slug']) . " (slug: " . $collection['slug'] . ")\n";
            if (!empty($collection['fields'])) {
                $fieldNames = array_map(function ($f) { return $f['name'] ?? ''; }, $collection['fields']);
                $prompt .= "  Fields: " . implode(', ', array_filter($fieldNames)) . "\n";
            }
        }
        $prompt .= "\nUse exact field names from above when creating or updating entries.\n\n";
    }

    $prompt .= "BIRKLY TEMPLATING:\n";
    $prompt .= "- Syntax: {for each entry in 'collection-slug'} ... {endfor}\n";
    $prompt .= "- Include: <script src=\"/birkly-client.js\"></script>\n";
    $prompt .= "- Use field names: { title }, { text }, etc.\n\n";

    if (!empty($context['page_context']) && is_array($context['page_context'])) {
        $prompt .= birkly_ai_page_context_prompt_section($context['page_context']);
    }

    if (!empty($context['context_pack'])) {
        $prompt .= "BIRKLY REFERENCE:\n";
        $prompt .= $context['context_pack'] . "\n\n";
    }

    return $prompt;
}
