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

    return [];
}
