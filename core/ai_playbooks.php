<?php
/**
 * AI playbooks — structured workflows for complex multi-step tasks.
 * Playbooks are loaded on demand via read_birkly_docs; summaries are injected into the system prompt.
 */

/** @return array<string, array{title: string, topic: string, summary: string, triggers: string, category: string}> */
function birkly_ai_playbook_catalog(): array
{
    return [
        'playbooks-index' => [
            'title' => 'Playbook master index & decision tree',
            'topic' => 'playbooks-index',
            'summary' => 'Which playbook to read for any task; feature map and anti-patterns.',
            'triggers' => 'where to start, what playbook, complex task, not sure how',
            'category' => 'Meta',
        ],
        'content-model' => [
            'title' => 'Collections, entries & schema',
            'topic' => 'content-model',
            'summary' => 'Create collections, fields, entries, publishing, slug rules.',
            'triggers' => 'collection, entry, schema, fields, blog, content type',
            'category' => 'Core CMS',
        ],
        'site-building' => [
            'title' => 'Website & portfolio builder (project/)',
            'topic' => 'site-building',
            'summary' => 'Plan → schema → entries → HTML/CSS in project/ with Birkly templating.',
            'triggers' => 'website, portfolio, project folder, build a site, landing page',
            'category' => 'Core CMS',
        ],
        'templating' => [
            'title' => 'Client templating language (full spec)',
            'topic' => 'templating',
            'summary' => 'Loops, conditionals, {from…get}, field access, forms.',
            'triggers' => 'template syntax, {for each entry}, display collection on site',
            'category' => 'Core CMS',
        ],
        'public-forms' => [
            'title' => 'Public forms & outside submission',
            'topic' => 'public-forms',
            'summary' => 'Contact forms, {entry for}, public_api, newsletter, visitor submissions.',
            'triggers' => 'contact form, public form, submit from website, newsletter signup, external submission',
            'category' => 'Public & forms',
        ],
        'sub-collections' => [
            'title' => 'Sub-collections & nested content',
            'topic' => 'sub-collections',
            'summary' => 'Comments, reviews, line items; nested sub-collections; public_api on sub-entries.',
            'triggers' => 'sub-collection, sub-entry, nested entries, comments on posts',
            'category' => 'Public & forms',
        ],
        'cms-pages' => [
            'title' => 'CMS pages vs project site',
            'topic' => 'cms-pages',
            'summary' => 'When to use create_page vs write_project_file.',
            'triggers' => 'cms page, admin-managed page, create page',
            'category' => 'Core CMS',
        ],
        'media' => [
            'title' => 'Media library',
            'topic' => 'media',
            'summary' => 'Upload, folders, image/gallery fields in entries.',
            'triggers' => 'media, upload image, gallery, media library',
            'category' => 'Core CMS',
        ],
        'content-editor' => [
            'title' => 'Rich text Editor fieldtype',
            'topic' => 'content-editor',
            'summary' => 'HTML tables, grids, safe updates to editor fields.',
            'triggers' => 'editor field, rich text, content editor, html article',
            'category' => 'Core CMS',
        ],
        'agent-guide' => [
            'title' => 'Agent Guide (AI Presence)',
            'topic' => 'agent-guide',
            'summary' => 'Sources, products, claims, publish /.well-known/agent-guide.json.',
            'triggers' => 'agent guide, brand context, ai presence, well-known',
            'category' => 'AI & integration',
        ],
        'mcp-setup' => [
            'title' => 'Connect Claude / ChatGPT / Cursor (MCP)',
            'topic' => 'mcp-setup',
            'summary' => 'Personal connector URL, OAuth, service agents, MCP resources.',
            'triggers' => 'mcp, connect claude, connect cursor, connect chatgpt, connector url',
            'category' => 'AI & integration',
        ],
        'ai-connections' => [
            'title' => 'Built-in admin AI assistant',
            'topic' => 'ai-connections',
            'summary' => 'API keys, roles, approvals, floating chat.',
            'triggers' => 'built-in assistant, api key, ai connection, approvals tab',
            'category' => 'AI & integration',
        ],
        'plugins-overview' => [
            'title' => 'Plugins & marketplace',
            'topic' => 'plugins-overview',
            'summary' => 'Install, enable, official plugins, dependencies.',
            'triggers' => 'install plugin, marketplace, enable commerce, activate plugin',
            'category' => 'Plugins',
        ],
        'commerce' => [
            'title' => 'Commerce (shop & checkout)',
            'topic' => 'commerce',
            'summary' => 'Cart, checkout, variants, fulfillment, template buy URLs.',
            'triggers' => 'shop, e-commerce, cart, checkout, product, sell online',
            'category' => 'Plugins',
        ],
        'automation' => [
            'title' => 'Automation workflows',
            'topic' => 'automation',
            'summary' => 'Triggers, webhooks, Slack/email, entry & commerce events.',
            'triggers' => 'automation, workflow, webhook, slack notification, when entry created',
            'category' => 'Plugins',
        ],
        'events' => [
            'title' => 'Events & RSVP',
            'topic' => 'events',
            'summary' => 'Event listings, RSVP, paid tickets (with Commerce).',
            'triggers' => 'events, rsvp, calendar, tickets, upcoming events',
            'category' => 'Plugins',
        ],
        'user-management' => [
            'title' => 'Site users & registration',
            'topic' => 'user-management',
            'summary' => 'Registration, login, tiers, user_submit sub-collections.',
            'triggers' => 'user registration, member login, site users, user profile',
            'category' => 'Plugins',
        ],
        'settings-roles' => [
            'title' => 'Settings, roles & permissions',
            'topic' => 'settings-roles',
            'summary' => 'Custom roles, global settings, security.',
            'triggers' => 'role, permission, settings, security',
            'category' => 'Admin',
        ],
    ];
}

/**
 * Core docs topic → file map (playbooks + reference). Checked before plugin ai-context stubs.
 *
 * @return array<string, array{file?: string, path?: string, title: string}>
 */
function birkly_ai_core_docs_topics(): array
{
    $playbook = static fn (string $file, string $title): array => [
        'file' => 'ai/playbooks/' . $file,
        'title' => $title,
    ];

    return [
        'playbooks-index' => $playbook('playbooks-index.md', 'AI playbooks — master index'),
        'content-model' => $playbook('content-model-playbook.md', 'Content model playbook'),
        'public-forms' => $playbook('public-forms-playbook.md', 'Public forms playbook'),
        'sub-collections' => $playbook('sub-collections-playbook.md', 'Sub-collections playbook'),
        'agent-guide' => $playbook('agent-guide-playbook.md', 'Agent Guide playbook'),
        'mcp-setup' => $playbook('mcp-setup-playbook.md', 'MCP setup playbook'),
        'ai-connections' => $playbook('ai-connections-playbook.md', 'Built-in AI assistant playbook'),
        'plugins-overview' => $playbook('plugins-overview-playbook.md', 'Plugins & marketplace playbook'),
        'commerce' => $playbook('commerce-playbook.md', 'Commerce playbook'),
        'automation' => $playbook('automation-playbook.md', 'Automation playbook'),
        'events' => $playbook('events-playbook.md', 'Events playbook'),
        'user-management' => $playbook('user-management-playbook.md', 'User Management playbook'),
        'user_management' => $playbook('user-management-playbook.md', 'User Management playbook'),
        'media' => $playbook('media-playbook.md', 'Media library playbook'),
        'cms-pages' => $playbook('cms-pages-playbook.md', 'CMS pages playbook'),
        'content-editor' => $playbook('content-editor-playbook.md', 'Content Editor playbook'),
        'settings-roles' => $playbook('settings-roles-playbook.md', 'Settings & roles playbook'),
        'site-building' => ['file' => 'ai/site-building-playbook.md', 'title' => 'Website & portfolio builder (project/)'],
        'portfolio' => ['file' => 'ai/site-building-playbook.md', 'title' => 'Website & portfolio builder (project/)'],
        'templating' => ['file' => 'TEMPLATE_LANGUAGE_SPEC.md', 'title' => 'Client templating language'],
        'admin' => ['file' => 'ai/birkly-context-pack.md', 'title' => 'Admin & platform overview'],
        'plugins' => ['file' => 'client-sites/cms-vendoring.md', 'title' => 'Plugins & client embed'],
        'ai' => ['file' => 'ai/BYOAI-ARCHITECTURE.md', 'title' => 'BYOAI & MCP architecture'],
        'mcp' => ['path' => 'admin/docs/snapshots/mcp-connections.md', 'title' => 'MCP setup guide (admin snapshot)'],
    ];
}

/**
 * MCP doc resources for playbooks (merged into resources/list).
 *
 * @return list<array{uri: string, name: string, description: string, mimeType: string}>
 */
function birkly_ai_playbook_mcp_resources(): array
{
    $out = [];
    foreach (birkly_ai_playbook_catalog() as $def) {
        $topic = $def['topic'];
        $out[] = [
            'uri' => 'birkly://docs/' . $topic,
            'name' => $def['title'],
            'description' => $def['summary'],
            'mimeType' => 'text/markdown',
        ];
    }
    return $out;
}

/**
 * Short playbook index for every chat system prompt (L1).
 */
function birkly_ai_playbooks_prompt_section(): string
{
    $byCategory = [];
    foreach (birkly_ai_playbook_catalog() as $def) {
        $byCategory[$def['category']][] = $def;
    }

    $lines = [
        'PLAYBOOKS — read before complex tasks (`read_birkly_docs` with topic slug):',
        'Unsure where to start? Read `playbooks-index` first.',
        'Site/portfolio build: `site-building` then execute — do not loop on get_project_info.',
        'Forms / outside submission: `public-forms`. Nested content: `sub-collections`.',
        '',
    ];

    foreach ($byCategory as $category => $items) {
        $lines[] = $category . ':';
        foreach ($items as $def) {
            $lines[] = '  - ' . $def['topic'] . ': ' . $def['title'];
        }
        $lines[] = '';
    }

    $lines[] = 'After reading a playbook: follow its step order. Do not repeat discovery reads you already have in this turn.';
    return implode("\n", $lines);
}

/**
 * Detect website / project-folder build intent from message + recent history.
 */
function ai_chat_is_planning_request(string $message): bool
{
    return (bool) preg_match(
        '/\b(outline|plan(?:ning)?|break\s*down|roadmap|step\s*by\s*step|layout|sketch|before you (?:start|execute)|clear path|plant this task|think through|map out)\b/i',
        $message
    );
}

function ai_chat_is_proceed_message(string $message): bool
{
    if (ai_chat_is_planning_request($message)) {
        return false;
    }

    return (bool) preg_match(
        '/\b(proceed|continue|next step|go on|keep going|move on|yes|do it|finish(?:ed|ing)?|complete(?:ly|d)?|start executing|run it|go ahead|until (?:everything|done|finished)|til everything)\b/i',
        $message
    );
}

function ai_chat_is_status_request(string $message): bool
{
    return (bool) preg_match(
        '/\b(what comes next|what(?:\'s| is) next|where are we|current status|progress update|what did you do|what have you done|what was done|show progress|status report)\b/i',
        $message
    );
}

/**
 * True when deterministic site-build automation has no remaining steps.
 *
 * @param array<string,mixed> $systemContext
 */
function ai_chat_site_build_is_automated_complete(array $conversationHistory, array $systemContext): bool
{
    if (!ai_chat_session_has_site_build_progress($conversationHistory)) {
        return false;
    }

    return str_contains(
        ai_chat_site_build_next_step_hint(ai_chat_site_build_state($conversationHistory, $systemContext)),
        'Automated site-build steps are complete'
    );
}

/**
 * Drop write actions that session notes already recorded as completed.
 *
 * @param list<array<string,mixed>> $actions
 * @return list<array<string,mixed>>
 */
function ai_chat_filter_already_completed_actions(array $actions, array $conversationHistory): array
{
    $done = ai_chat_parse_session_notes_from_history($conversationHistory);

    return array_values(array_filter($actions, static function (array $action) use ($done): bool {
        $type = (string) ($action['type'] ?? '');
        $data = is_array($action['data'] ?? null) ? $action['data'] : [];

        if (in_array($type, ['write_project_file', 'create_project_file'], true)) {
            $path = (string) ($data['path'] ?? '');
            return $path === '' || !in_array($path, $done['project_files'], true);
        }
        if ($type === 'create_entry') {
            $coll = (string) ($data['collection'] ?? '');
            return $coll === '' || !in_array($coll, $done['entries'], true);
        }
        if ($type === 'update_collection') {
            $slug = (string) ($data['slug'] ?? $data['collection'] ?? '');
            return $slug === '' || !in_array($slug, $done['collections'], true);
        }

        return true;
    }));
}

function ai_chat_session_has_site_build_progress(array $conversationHistory): bool
{
    $done = ai_chat_parse_session_notes_from_history($conversationHistory);
    if ($done['collections'] !== [] || $done['entries'] !== [] || $done['project_files'] !== []) {
        return true;
    }

    $haystack = strtolower(implode("\n", array_column(array_slice($conversationHistory, -8), 'content')));
    return str_contains($haystack, '[session notes') && str_contains($haystack, 'collection:');
}

function ai_chat_is_project_site_intent(string $message, array $conversationHistory = []): bool
{
    if (ai_chat_session_has_site_build_progress($conversationHistory)) {
        return true;
    }

    $haystack = strtolower($message);
    $recent = array_slice($conversationHistory, -6);
    foreach ($recent as $msg) {
        $haystack .= ' ' . strtolower((string) ($msg['content'] ?? ''));
    }
    return (bool) preg_match(
        '/\b(website|web site|portfolio|project\/|project folder|index\.html|write_project|create_project|build\s+(?:a\s+)?(?:site|website|gallery)|site builder|gallery site|landing page|\bgallery\b|\bartist\b|artworks?\b|ute bayrak)\b/i',
        $haystack
    );
}

/**
 * True when read results already include project site discovery data.
 *
 * @param list<array<string,mixed>> $readData
 */
function ai_chat_read_data_has_project_context(array $readData): bool
{
    $hasInfo = false;
    $hasFiles = false;
    foreach ($readData as $result) {
        if (!($result['success'] ?? false)) {
            continue;
        }
        $data = $result['data'] ?? [];
        if (isset($data['site_dir']) || isset($data['templating'])) {
            $hasInfo = true;
        }
        if (isset($data['path']) && (isset($data['files']) || isset($data['entries'])) && !isset($data['collection'])) {
            $hasFiles = true;
        }
    }
    return $hasInfo && $hasFiles;
}

/**
 * Format one read-action result for follow-up AI context.
 *
 * @param array<string,mixed> $readResult
 * @param list<array<string,mixed>> $readActions
 */
function ai_chat_format_read_result_for_followup(array $readResult, array $readActions): string
{
    if (!($readResult['success'] ?? false)) {
        return '';
    }
    $data = $readResult['data'] ?? [];
    if (!is_array($data)) {
        return '';
    }

    if (isset($data['file']) && is_array($data['file'])) {
        $path = $data['file']['path'] ?? $data['file']['relative_path'] ?? 'unknown';
        $content = $data['file']['content'] ?? '';
        if (is_string($content) && strlen($content) > 12000) {
            $content = substr($content, 0, 12000) . "\n\n[truncated]";
        }
        return "PROJECT FILE ({$path}):\n" . json_encode($data['file'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    if (isset($data['site_dir']) || isset($data['templating'])) {
        return "PROJECT SITE INFO (from get_project_info — do NOT call get_project_info again this turn):\n"
            . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    if (isset($data['path']) && isset($data['count']) && !isset($data['collection'])) {
        $files = $data['files'] ?? $data['entries'] ?? [];
        return "PROJECT FILES at path '{$data['path']}' (count: {$data['count']}):\n"
            . json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    if (isset($data['entry'])) {
        $entry = $data['entry'];
        $collectionSlug = '';
        foreach ($readActions as $readAction) {
            if (isset($readAction['data']['collection'])) {
                $collectionSlug = (string) $readAction['data']['collection'];
                break;
            }
        }
        $out = "ENTRY DATA:\n- Collection: {$collectionSlug}\n";
        $out .= "- Title: " . ($entry['title'] ?? 'N/A') . "\n";
        $out .= "- Slug: " . ($entry['slug'] ?? 'N/A') . "\n";
        $out .= "\nFULL ENTRY (JSON):\n" . json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        return $out;
    }

    if (isset($data['entries']) && is_array($data['entries'])) {
        $collectionSlug = '';
        foreach ($readActions as $readAction) {
            if (($readAction['type'] ?? '') === 'list_entries' && isset($readAction['data']['collection'])) {
                $collectionSlug = (string) $readAction['data']['collection'];
                break;
            }
        }
        $entries = $data['entries'];
        $out = "FOUND " . count($entries) . " ENTRIES in collection '{$collectionSlug}':\n";
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $out .= '- ' . ($entry['title'] ?? 'Untitled') . ' (slug: ' . ($entry['slug'] ?? 'N/A') . ")\n";
        }
        $out .= "\nFULL ENTRIES (JSON):\n" . json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        return $out;
    }

    if (isset($data['collections']) && is_array($data['collections'])) {
        $collections = $data['collections'];
        $out = 'FOUND ' . count($collections) . " COLLECTIONS:\n";
        foreach ($collections as $collection) {
            if (!is_array($collection)) {
                continue;
            }
            $out .= '- ' . ($collection['name'] ?? 'Untitled') . ' (slug: ' . ($collection['slug'] ?? 'N/A') . ")\n";
        }
        $out .= "\nFULL COLLECTIONS (JSON):\n" . json_encode($collections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        return $out;
    }

    if (isset($data['pages'])) {
        return "PAGES:\n" . json_encode($data['pages'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    if (isset($data['media'])) {
        return "MEDIA:\n" . json_encode($data['media'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    if (isset($data['users'])) {
        return "USERS:\n" . json_encode($data['users'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    if (isset($data['slug'], $data['title']) && isset($data['text'])) {
        return "BIRKLY DOCS TOPIC:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    return "READ RESULT:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

/**
 * Dedupe write actions by type + target path/slug.
 *
 * @param list<array<string,mixed>> $actions
 * @return list<array<string,mixed>>
 */
function ai_chat_dedupe_write_actions(array $actions): array
{
    $seen = [];
    $out = [];
    foreach ($actions as $action) {
        $type = (string) ($action['type'] ?? '');
        $data = is_array($action['data'] ?? null) ? $action['data'] : [];
        $key = $type;
        if ($type === 'write_project_file' || $type === 'create_project_file' || $type === 'delete_project_file') {
            $key .= ':' . ($data['path'] ?? '');
        } elseif ($type === 'update_collection' || $type === 'delete_collection' || $type === 'read_collection') {
            $key .= ':' . ($data['slug'] ?? $data['collection'] ?? '');
        } elseif ($type === 'create_page' || $type === 'update_page' || $type === 'delete_page') {
            $key .= ':' . ($data['slug'] ?? '');
        } elseif ($type === 'create_entry' || $type === 'update_entry') {
            $key .= ':' . ($data['collection'] ?? '') . ':' . ($data['slug'] ?? $data['title'] ?? '');
        }
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $action;
    }
    return $out;
}

/**
 * Compact session notes appended to persisted assistant messages.
 *
 * @param list<array<string,mixed>> $executionResults
 */
function ai_chat_build_session_notes(array $executionResults): string
{
    $written = [];
    $updated = [];
    $failed = [];
    foreach ($executionResults as $result) {
        if (!($result['success'] ?? false)) {
            $failed[] = ($result['action_type'] ?? 'action') . ': ' . ($result['message'] ?? 'failed');
            continue;
        }
        $type = (string) ($result['action_type'] ?? '');
        $data = is_array($result['action_data'] ?? null) ? $result['action_data'] : [];
        if (in_array($type, ['write_project_file', 'create_project_file'], true) && !empty($data['path'])) {
            $written[] = (string) $data['path'];
        } elseif ($type === 'update_collection' && !empty($data['slug'])) {
            $updated[] = 'collection:' . $data['slug'];
        } elseif ($type === 'create_entry' && !empty($data['collection'])) {
            $updated[] = 'entry:' . $data['collection'];
        }
    }
    if ($written === [] && $updated === [] && $failed === []) {
        return '';
    }
    $parts = [];
    if ($written !== []) {
        $parts[] = 'project files: ' . implode(', ', array_unique($written));
    }
    if ($updated !== []) {
        $parts[] = 'cms: ' . implode(', ', array_unique($updated));
    }
    if ($failed !== []) {
        $parts[] = 'failed: ' . implode('; ', array_slice($failed, 0, 3));
    }
    return "\n\n[Session notes — already done this conversation, do not repeat: " . implode(' | ', $parts) . ']';
}

/**
 * Parse session notes embedded in persisted assistant messages.
 *
 * @return array{collections: list<string>, entries: list<string>, project_files: list<string>}
 */
function ai_chat_parse_session_notes_from_history(array $conversationHistory): array
{
    $parsed = ['collections' => [], 'entries' => [], 'project_files' => []];
    foreach ($conversationHistory as $msg) {
        $content = (string) ($msg['content'] ?? '');
        if (!str_contains($content, '[Session notes')) {
            continue;
        }
        if (preg_match('/project files:\s*([^|\]]+)/', $content, $m)) {
            foreach (explode(',', $m[1]) as $path) {
                $path = trim($path);
                if ($path !== '') {
                    $parsed['project_files'][] = $path;
                }
            }
        }
        if (preg_match('/cms:\s*([^|\]]+)/', $content, $m)) {
            foreach (explode(',', $m[1]) as $item) {
                $item = trim($item);
                if (str_starts_with($item, 'collection:')) {
                    $parsed['collections'][] = substr($item, strlen('collection:'));
                } elseif (str_starts_with($item, 'entry:')) {
                    $parsed['entries'][] = substr($item, strlen('entry:'));
                }
            }
        }
    }
    $parsed['collections'] = array_values(array_unique($parsed['collections']));
    $parsed['entries'] = array_values(array_unique($parsed['entries']));
    $parsed['project_files'] = array_values(array_unique($parsed['project_files']));
    return $parsed;
}

/**
 * @param array<string,mixed> $systemContext
 * @return array{collections: list<string>, entries: list<string>, project_files: list<string>, site_slugs: list<string>}
 */
function ai_chat_site_build_state(array $conversationHistory, array $systemContext): array
{
    $done = ai_chat_parse_session_notes_from_history($conversationHistory);
    $collectionMap = [];
    foreach ($systemContext['collections'] ?? [] as $coll) {
        if (!is_array($coll)) {
            continue;
        }
        $slug = (string) ($coll['slug'] ?? '');
        if ($slug !== '') {
            $collectionMap[$slug] = $coll;
        }
    }

    $siteSlugs = array_values(array_filter(
        ['about', 'artworks', 'contact', 'gallery', 'projects', 'portfolio'],
        static fn (string $s): bool => isset($collectionMap[$s])
    ));
    if ($siteSlugs === []) {
        $siteSlugs = array_slice(array_keys($collectionMap), 0, 4);
    }

    return [
        'collections' => $done['collections'],
        'entries' => $done['entries'],
        'project_files' => $done['project_files'],
        'site_slugs' => $siteSlugs,
        'collection_map' => $collectionMap,
    ];
}

/**
 * @param array<string,mixed> $action
 */
function ai_chat_describe_planned_action(array $action): string
{
    $description = trim((string) ($action['description'] ?? ''));
    if ($description !== '') {
        return $description;
    }

    $type = (string) ($action['type'] ?? 'action');
    $data = is_array($action['data'] ?? null) ? $action['data'] : [];

    switch ($type) {
        case 'create_entry':
            $coll = (string) ($data['collection'] ?? '');
            $title = (string) ($data['title'] ?? '');
            return 'Create published entry in **' . $coll . '**' . ($title !== '' ? ' ("' . $title . '")' : '');
        case 'write_project_file':
        case 'create_project_file':
            return 'Write **project/' . (string) ($data['path'] ?? '?') . '**';
        case 'update_collection':
            return 'Update collection **' . (string) ($data['slug'] ?? $data['collection'] ?? '?') . '**';
        case 'create_page':
            return 'Create page **' . (string) ($data['title'] ?? $data['slug'] ?? '?') . '**';
        default:
            return $type;
    }
}

/**
 * @param array<string,mixed> $result
 */
function ai_chat_describe_executed_action(array $result): string
{
    $ok = !empty($result['success']);
    $mark = $ok ? '✓' : '✗';
    $type = (string) ($result['action_type'] ?? 'action');
    $data = is_array($result['action_data'] ?? null) ? $result['action_data'] : [];
    $resultData = is_array($result['data'] ?? null) ? $result['data'] : [];
    $detail = '';

    switch ($type) {
        case 'create_entry':
            $coll = (string) ($data['collection'] ?? '');
            $title = (string) ($data['title'] ?? ($resultData['entry']['title'] ?? ''));
            $entryId = (string) ($resultData['entry_id'] ?? ($resultData['entry']['id'] ?? ''));
            $detail = 'entry in **' . $coll . '**';
            if ($title !== '') {
                $detail .= ' — "' . $title . '"';
            }
            if ($entryId !== '') {
                $detail .= ' (id: ' . $entryId . ')';
            }
            break;
        case 'write_project_file':
        case 'create_project_file':
            $path = (string) ($data['path'] ?? ($resultData['path'] ?? ''));
            $detail = '**project/' . $path . '**';
            break;
        case 'update_collection':
            $detail = 'collection **' . (string) ($data['slug'] ?? $data['collection'] ?? '') . '**';
            break;
        case 'create_page':
            $detail = 'page **' . (string) ($data['title'] ?? $data['slug'] ?? '') . '**';
            break;
        default:
            $detail = trim((string) ($data['path'] ?? $data['slug'] ?? $data['collection'] ?? ''));
            if ($detail === '') {
                $detail = $type;
            }
    }

    $line = $mark . ' **' . $type . '** — ' . $detail;
    if (!$ok) {
        $err = trim((string) ($result['message'] ?? 'failed'));
        if ($err !== '') {
            $line .= ' — _' . $err . '_';
        }
    }

    return $line;
}

/**
 * @param array{collections: list<string>, entries: list<string>, project_files: list<string>, site_slugs: list<string>, collection_map: array<string,mixed>} $state
 */
function ai_chat_site_build_next_step_hint(array $state): string
{
    $siteSlugs = $state['site_slugs'];
    $doneEntries = $state['entries'];
    $doneFiles = $state['project_files'];
    $collectionMap = $state['collection_map'];

    foreach ($siteSlugs as $slug) {
        if (!in_array($slug, $doneEntries, true)) {
            return 'Say **proceed** to seed the next collection entries (' . $slug . ' and others pending).';
        }
    }

    $hasIndex = in_array('index.html', $doneFiles, true);
    if (!$hasIndex) {
        return 'Say **proceed** to create **project/index.html** and **project/css/style.css**.';
    }

    $pendingPages = [];
    if (isset($collectionMap['about']) && !in_array('about.html', $doneFiles, true)) {
        $pendingPages[] = 'about.html';
    }
    if (isset($collectionMap['contact']) && !in_array('contact.html', $doneFiles, true)) {
        $pendingPages[] = 'contact.html';
    }
    if ($pendingPages !== []) {
        return 'Say **proceed** to create **project/' . implode('** and **project/', $pendingPages) . '**.';
    }

    return 'Automated site-build steps are complete. Open **project/** to review, or describe refinements (design, copy, extra pages).';
}

/**
 * @param array{collections: list<string>, entries: list<string>, project_files: list<string>, site_slugs: list<string>, collection_map: array<string,mixed>} $state
 */
function ai_chat_format_site_build_plan_status(array $state): string
{
    $lines = ['### Plan status'];

    $entryParts = [];
    foreach ($state['site_slugs'] as $slug) {
        $entryParts[] = $slug . (in_array($slug, $state['entries'], true) ? ' ✓' : ' ○');
    }
    if ($entryParts !== []) {
        $lines[] = '**Phase 1 — Seed entries:** ' . implode(', ', $entryParts);
    }

    $fileTargets = ['index.html', 'css/style.css', 'about.html', 'contact.html'];
    $fileParts = [];
    foreach ($fileTargets as $path) {
        if ($path === 'about.html' && !isset($state['collection_map']['about'])) {
            continue;
        }
        if ($path === 'contact.html' && !isset($state['collection_map']['contact'])) {
            continue;
        }
        $fileParts[] = $path . (in_array($path, $state['project_files'], true) ? ' ✓' : ' ○');
    }
    if ($fileParts !== []) {
        $lines[] = '**Phase 2 — Project files:** ' . implode(', ', $fileParts);
    }

    $automatedComplete = str_contains(ai_chat_site_build_next_step_hint($state), 'Automated site-build steps are complete');
    $lines[] = '**Phase 3 — Review & refine:** ' . ($automatedComplete ? 'ready' : 'in progress');

    return implode("\n", $lines);
}

/**
 * @param array{collections: list<string>, entries: list<string>, project_files: list<string>, site_slugs: list<string>, collection_map: array<string,mixed>} $state
 * @param list<array<string,mixed>> $executedActions
 * @return array{collections: list<string>, entries: list<string>, project_files: list<string>, site_slugs: list<string>, collection_map: array<string,mixed>}
 */
function ai_chat_state_after_execution(array $state, array $executedActions): array
{
    foreach ($executedActions as $result) {
        if (empty($result['success'])) {
            continue;
        }
        $type = (string) ($result['action_type'] ?? '');
        $data = is_array($result['action_data'] ?? null) ? $result['action_data'] : [];
        if ($type === 'create_entry' && !empty($data['collection'])) {
            $state['entries'][] = (string) $data['collection'];
        }
        if (in_array($type, ['write_project_file', 'create_project_file'], true) && !empty($data['path'])) {
            $state['project_files'][] = (string) $data['path'];
        }
        if ($type === 'update_collection' && !empty($data['slug'])) {
            $state['collections'][] = (string) $data['slug'];
        }
    }
    $state['entries'] = array_values(array_unique($state['entries']));
    $state['project_files'] = array_values(array_unique($state['project_files']));
    $state['collections'] = array_values(array_unique($state['collections']));
    return $state;
}

/**
 * @param list<array<string,mixed>> $executedActions
 * @param list<array<string,mixed>> $plannedActions
 * @param array<string,mixed> $systemContext
 */
function ai_chat_format_execution_report(
    array $executedActions,
    array $plannedActions,
    array $conversationHistory,
    array $systemContext
): string {
    if ($executedActions === [] && $plannedActions === []) {
        return '';
    }

    $sections = [];
    if ($executedActions !== []) {
        $lines = ['### Completed this step'];
        foreach ($executedActions as $index => $result) {
            $lines[] = ($index + 1) . '. ' . ai_chat_describe_executed_action($result);
        }
        $sections[] = implode("\n", $lines);
    } elseif ($plannedActions !== []) {
        $lines = ['### Planned this step'];
        foreach ($plannedActions as $index => $action) {
            $lines[] = ($index + 1) . '. ' . ai_chat_describe_planned_action($action);
        }
        $sections[] = implode("\n", $lines);
    }

    $state = ai_chat_site_build_state($conversationHistory, $systemContext);
    if ($executedActions !== []) {
        $state = ai_chat_state_after_execution($state, $executedActions);
    }
    if ($state['site_slugs'] !== []) {
        $sections[] = ai_chat_format_site_build_plan_status($state);
        $sections[] = '### What\'s next' . "\n" . ai_chat_site_build_next_step_hint($state);
    }

    return implode("\n\n", $sections);
}

/**
 * Human-readable site-build progress for proceed messages with no remaining automated steps.
 *
 * @param array<string,mixed> $systemContext
 */
function ai_chat_site_build_progress_summary(array $conversationHistory, array $systemContext): string
{
    $state = ai_chat_site_build_state($conversationHistory, $systemContext);
    if ($state['site_slugs'] === []) {
        return '';
    }

    return ai_chat_format_site_build_plan_status($state)
        . "\n\n### What's next\n"
        . ai_chat_site_build_next_step_hint($state);
}

/**
 * Build create_entry payload using collection schema field names when available.
 *
 * @param array<string,mixed> $coll
 * @return array<string,mixed>
 */
function ai_chat_build_entry_payload_for_collection(array $coll, string $slug): array
{
    $name = (string) ($coll['name'] ?? ucfirst($slug));
    $payload = [
        'collection' => $slug,
        'title' => $name . ' — welcome',
        'status' => 'published',
    ];
    $bodyField = 'text';
    foreach ($coll['fields'] ?? [] as $field) {
        if (!is_array($field)) {
            continue;
        }
        $fieldName = (string) ($field['name'] ?? '');
        $fieldType = (string) ($field['type'] ?? 'text');
        if ($fieldName === '' || in_array($fieldName, ['title', 'slug'], true)) {
            continue;
        }
        if (in_array($fieldType, ['textarea', 'markdown', 'richtext', 'text', 'wysiwyg'], true)) {
            $bodyField = $fieldName;
            break;
        }
    }
    $payload[$bodyField] = 'Sample content for ' . $name . '. Edit in admin.';
    return $payload;
}

/**
 * Deterministic next actions when model returns prose on site-build "proceed".
 *
 * @param array<string,mixed> $systemContext
 * @return list<array<string,mixed>>
 */
function ai_chat_site_proceed_fallback_actions(array $conversationHistory, array $systemContext): array
{
    $done = ai_chat_parse_session_notes_from_history($conversationHistory);
    $collectionMap = [];
    foreach ($systemContext['collections'] ?? [] as $coll) {
        if (!is_array($coll)) {
            continue;
        }
        $slug = (string) ($coll['slug'] ?? '');
        if ($slug !== '') {
            $collectionMap[$slug] = $coll;
        }
    }
    if ($collectionMap === []) {
        return [];
    }

    $haystack = strtolower(implode("\n", array_column(array_slice($conversationHistory, -10), 'content')));
    $siteSlugs = array_values(array_filter(
        ['about', 'artworks', 'contact', 'gallery', 'projects', 'portfolio'],
        static fn (string $s): bool => isset($collectionMap[$s])
    ));
    if ($siteSlugs === []) {
        $siteSlugs = array_slice(array_keys($collectionMap), 0, 4);
    }

    $hasProjectIndex = in_array('index.html', $done['project_files'], true)
        || in_array('project/index.html', $done['project_files'], true);

    $allSiteEntriesDone = true;
    foreach ($siteSlugs as $slug) {
        if (!in_array($slug, $done['entries'], true)) {
            $allSiteEntriesDone = false;
            break;
        }
    }

    // Phase 1: seed entries for site collections not yet touched
    $entryActions = [];
    foreach ($siteSlugs as $slug) {
        if (in_array($slug, $done['entries'], true)) {
            continue;
        }
        $entryActions[] = [
            'type' => 'create_entry',
            'data' => ai_chat_build_entry_payload_for_collection($collectionMap[$slug], $slug),
            'description' => 'Seed published entry in ' . $slug,
        ];
        if (count($entryActions) >= 3) {
            break;
        }
    }
    if ($entryActions !== []) {
        return $entryActions;
    }

    // Phase 2: project shell (collections configured and entries seeded)
    if (!$hasProjectIndex && ($allSiteEntriesDone || $entryActions === [])) {
        $artSlug = isset($collectionMap['artworks']) ? 'artworks' : ($siteSlugs[0] ?? 'projects');
        $indexHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gallery</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="/birkly-client.js"></script>
</head>
<body>
  <header><h1>Gallery</h1></header>
  <main>
    {for each entry in '{$artSlug}'}
      <article>
        <h2>{title}</h2>
        <div>{'description'}</div>
      </article>
    {endfor}
  </main>
</body>
</html>
HTML;
        return [
            [
                'type' => 'write_project_file',
                'data' => ['path' => 'index.html', 'content' => $indexHtml],
                'description' => 'Create gallery index.html in project/',
            ],
            [
                'type' => 'write_project_file',
                'data' => [
                    'path' => 'css/style.css',
                    'content' => "body { font-family: system-ui, sans-serif; margin: 0; padding: 2rem; }\nmain { display: grid; gap: 1.5rem; max-width: 960px; margin: 0 auto; }\narticle { padding: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; }\n",
                ],
                'description' => 'Create gallery stylesheet in project/css/',
            ],
        ];
    }

    // Phase 3: about/contact pages when index exists
    if ($hasProjectIndex) {
        $pageActions = [];
        $pageDefs = [
            'about' => [
                'path' => 'about.html',
                'title' => 'About',
                'collection' => 'about',
            ],
            'contact' => [
                'path' => 'contact.html',
                'title' => 'Contact',
                'collection' => 'contact',
            ],
        ];
        foreach ($pageDefs as $def) {
            $path = $def['path'];
            if (in_array($path, $done['project_files'], true)) {
                continue;
            }
            if (!isset($collectionMap[$def['collection']])) {
                continue;
            }
            $collSlug = $def['collection'];
            $pageTitle = $def['title'];
            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$pageTitle}</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="/birkly-client.js"></script>
</head>
<body>
  <header><a href="index.html">Gallery</a> · <h1>{$pageTitle}</h1></header>
  <main>
    {for each entry in '{$collSlug}'}
      <article>
        <h2>{title}</h2>
        <div>{'description'}</div>
      </article>
    {endfor}
  </main>
</body>
</html>
HTML;
            $pageActions[] = [
                'type' => 'write_project_file',
                'data' => ['path' => $path, 'content' => $html],
                'description' => 'Create ' . $path . ' in project/',
            ];
        }
        if ($pageActions !== []) {
            return $pageActions;
        }
    }

    return [];
}
