<?php
/**
 * AI playbooks — structured workflows for complex multi-step tasks.
 * Playbooks are loaded on demand via read_birkly_docs; summaries are injected into the system prompt.
 */

/** @return array<string, array{title: string, topic: string, summary: string, triggers: string}> */
function birkly_ai_playbook_catalog(): array
{
    return [
        'site-building' => [
            'title' => 'Website & portfolio builder (project/)',
            'topic' => 'site-building',
            'summary' => 'End-to-end flow: plan content → schema → entries → HTML/CSS in project/ with Birkly templating.',
            'triggers' => 'website, portfolio, project folder, build a site, gallery site, landing page',
        ],
        'sub-collections' => [
            'title' => 'Sub-collections & nested content',
            'topic' => 'sub-collections',
            'summary' => 'Parent/child collection patterns, sub-entry tools, admin URLs.',
            'triggers' => 'sub-collection, sub-entry, nested entries, comments on entries',
        ],
        'templating' => [
            'title' => 'Client templating language (full spec)',
            'topic' => 'templating',
            'summary' => 'Complete template syntax: loops, conditionals, {from…get}, field access.',
            'triggers' => 'template syntax, {for each entry}, display collection on site',
        ],
    ];
}

/**
 * Short playbook index for every chat system prompt (L1).
 */
function birkly_ai_playbooks_prompt_section(): string
{
    $lines = [
        'PLAYBOOKS (read before complex tasks — use read_birkly_docs with topic slug):',
        'When the user asks for a website, portfolio, or multi-step build, call read_birkly_docs topic "site-building" FIRST, then execute — do not loop on get_project_info.',
    ];
    foreach (birkly_ai_playbook_catalog() as $def) {
        $lines[] = '- ' . $def['topic'] . ': ' . $def['title'] . ' — ' . $def['summary'];
    }
    $lines[] = '';
    $lines[] = 'After reading a playbook: follow its step order. Do not repeat discovery reads you already have in this turn.';
    return implode("\n", $lines);
}

/**
 * Detect website / project-folder build intent from message + recent history.
 */
function ai_chat_is_project_site_intent(string $message, array $conversationHistory = []): bool
{
    $haystack = strtolower($message);
    $recent = array_slice($conversationHistory, -6);
    foreach ($recent as $msg) {
        $haystack .= ' ' . strtolower((string) ($msg['content'] ?? ''));
    }
    return (bool) preg_match(
        '/\b(website|web site|portfolio|project\/|project folder|index\.html|write_project|create_project|build\s+(?:a\s+)?(?:site|website)|site builder|gallery site|landing page)\b/i',
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

    // Project site reads (check before generic "entries" — list_project_files used to collide)
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
