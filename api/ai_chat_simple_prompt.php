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

    $prompt .= "CHAT STYLE (important):\n";
    $prompt .= "- Reply like a normal chat assistant: clear prose first, full sentences, no boilerplate.\n";
    $prompt .= "- NEVER paste file contents, HTML, CSS, or JSON action blocks in your visible reply.\n";
    $prompt .= "- Use native tools when available. JSON actions are executed server-side and hidden from the user.\n";
    $prompt .= "- You CANNOT browse live URLs or see the user's screen. Do not claim you verified a live page unless you read project files or the user attached a screenshot.\n";
    $prompt .= "- For site/design questions: call list_project_files and read_project_file first, then answer from actual file contents.\n";
    $prompt .= "- Only write/update project files when the user asks you to change something — not when they only ask a question or want a review.\n";
    $prompt .= "- Project paths are relative to project/ — use index.html, css/style.css (NOT project/index.html).\n";
    $prompt .= "- There is NO create_user action — direct users to Settings > Users.\n\n";

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
    $prompt .= "- Stylesheets: href=\"css/style.css\" (relative to /project/ URL)\n\n";

    if (!empty($context['page_context']) && is_array($context['page_context'])) {
        $prompt .= birkly_ai_page_context_prompt_section($context['page_context']);
    }

    if (!empty($context['context_pack'])) {
        $prompt .= "BIRKLY REFERENCE:\n";
        $prompt .= $context['context_pack'] . "\n\n";
    }

    return $prompt;
}
