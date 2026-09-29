<?php
/**
 * P87 — Admin Guide companion (checklist, tips, per-user progress).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * @param array<string, string> $params
 */
function birkly_guide_admin_page_url(string $page, array $params = []): string
{
    $query = array_merge(['page' => $page], $params);
    return birkly_url('/admin/index.php?' . http_build_query($query));
}

/**
 * @return array<string, mixed>
 */
function birkly_guide_default_prefs(): array
{
    return [
        'first_admin_visit_ack_at' => null,
        'tips_seen' => [],
        'attention_dismissed' => [],
        'journeys_completed' => [],
        'disable_attention' => false,
        'tips_snoozed_until' => null,
        'guide_auto_open_pending' => false,
        'post_onboarding_nudge_pending' => false,
        'post_onboarding_nudge_dismissed_at' => null,
        'reset_at' => null,
    ];
}

function birkly_guide_is_tips_snoozed(array $guidePrefs): bool
{
    $until = $guidePrefs['tips_snoozed_until'] ?? null;
    if ($until === null || $until === '') {
        return false;
    }
    $ts = strtotime((string) $until);
    return $ts !== false && $ts > time();
}

/**
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function birkly_guide_prefs_from_user(array $user): array
{
    $prefs = is_array($user['ui_preferences'] ?? null) ? $user['ui_preferences'] : [];
    $guide = is_array($prefs['guide'] ?? null) ? $prefs['guide'] : [];
    return array_merge(birkly_guide_default_prefs(), $guide);
}

/**
 * @param array<string, mixed> $user
 * @param array<string, mixed> $guidePatch
 * @return array<string, mixed>|null Updated user record or null on failure
 */
function birkly_guide_merge_prefs(array $user, array $guidePatch): ?array
{
    $prefs = is_array($user['ui_preferences'] ?? null) ? $user['ui_preferences'] : [];
    $guide = birkly_guide_prefs_from_user($user);
    foreach ($guidePatch as $key => $value) {
        if (array_key_exists($key, birkly_guide_default_prefs()) || in_array($key, ['tips_seen', 'attention_dismissed', 'journeys_completed', 'guide_auto_open_pending'], true)) {
            $guide[$key] = $value;
        }
    }
    $prefs['guide'] = $guide;
    $user['ui_preferences'] = $prefs;
    if (!birkly_save_user_account($user)) {
        return null;
    }
    return $user;
}

function birkly_guide_count_collections(): int
{
    $root = dirname(__DIR__) . '/content/collections';
    if (!is_dir($root)) {
        return 0;
    }
    $count = 0;
    foreach (scandir($root) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $root . '/' . $entry;
        if (is_dir($path) && is_file($path . '/collection.json')) {
            $count++;
        }
    }
    return $count;
}

function birkly_guide_has_published_entry(): bool
{
    $root = dirname(__DIR__) . '/content/collections';
    if (!is_dir($root)) {
        return false;
    }
    foreach (scandir($root) ?: [] as $slug) {
        if ($slug === '.' || $slug === '..') {
            continue;
        }
        $entriesDir = $root . '/' . $slug . '/entries';
        if (!is_dir($entriesDir)) {
            continue;
        }
        foreach (scandir($entriesDir) ?: [] as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $data = json_decode((string) file_get_contents($entriesDir . '/' . $file), true);
            if (is_array($data) && ($data['status'] ?? '') === 'published') {
                return true;
            }
        }
    }
    return false;
}

/**
 * Wave-1 alpha official plugin ids (Hub).
 *
 * @return list<string>
 */
function birkly_guide_alpha_plugin_ids(): array
{
    $manifest = birkly_guide_load_manifest();
    $ids = $manifest['alpha_plugin_ids'] ?? [];
    if (!is_array($ids)) {
        return [];
    }
    $out = [];
    foreach ($ids as $id) {
        $id = strtolower(trim((string) $id));
        if ($id !== '' && preg_match('/^[a-z0-9_-]+$/', $id)) {
            $out[] = $id;
        }
    }
    return array_values(array_unique($out));
}

function birkly_guide_has_alpha_plugin_active(): bool
{
    if (!function_exists('get_active_plugins')) {
        require_once dirname(__DIR__) . '/core/plugins.php';
    }
    $active = get_active_plugins();
    $ids = array_map(static function ($p) {
        return is_array($p) ? (string) ($p['id'] ?? '') : '';
    }, $active);
    foreach (birkly_guide_alpha_plugin_ids() as $wanted) {
        if (in_array($wanted, $ids, true)) {
            return true;
        }
    }
    return false;
}

function birkly_guide_site_previewable(): bool
{
    $projectIndex = birkly_project_root() . '/project/index.html';
    return is_file($projectIndex);
}

/**
 * Map admin page (+ optional tab) to guide area id.
 */
function birkly_guide_area_from_request(string $page, string $tab = ''): string
{
    $page = trim($page);
    $tab = trim($tab);
    if ($page === 'settings' && $tab !== '') {
        if ($tab === 'site-delivery' || $tab === 'delivery') {
            return 'settings-delivery';
        }
        return 'settings-' . preg_replace('/[^a-z0-9_-]/', '', strtolower($tab));
    }
    if ($page === 'content') {
        return 'content';
    }
    if ($page === 'discovery' || $page === 'agent-guide' || $page === 'ai-presence') {
        return 'discovery';
    }
    if ($page === 'plugins') {
        return 'hub';
    }
    if ($page === 'entries' || $page === 'entry-editor') {
        return 'entries';
    }
    if ($page === 'media') {
        return 'media';
    }
    return $page !== '' ? $page : 'dashboard';
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_guide_area_tips(): array
{
    return [
        [
            'area' => 'dashboard',
            'title_key' => 'guide_tip_dashboard_title',
            'body_key' => 'guide_tip_dashboard_body',
            'help_topic' => 'getting-started',
        ],
        [
            'area' => 'content',
            'title_key' => 'guide_tip_content_title',
            'body_key' => 'guide_tip_content_body',
            'help_topic' => 'collections',
        ],
        [
            'area' => 'hub',
            'title_key' => 'guide_tip_hub_title',
            'body_key' => 'guide_tip_hub_body',
            'help_topic' => 'marketplace',
        ],
        [
            'area' => 'entries',
            'title_key' => 'guide_tip_entries_title',
            'body_key' => 'guide_tip_entries_body',
            'help_topic' => 'entries',
        ],
        [
            'area' => 'settings-delivery',
            'title_key' => 'guide_tip_delivery_title',
            'body_key' => 'guide_tip_delivery_body',
            'help_topic' => 'getting-started',
        ],
        [
            'area' => 'media',
            'title_key' => 'guide_tip_media_title',
            'body_key' => 'guide_tip_media_body',
            'help_topic' => 'media-library',
        ],
        [
            'area' => 'discovery',
            'title_key' => 'guide_tip_discovery_title',
            'body_key' => 'guide_tip_discovery_body',
            'help_topic' => 'discovery',
        ],
        [
            'area' => 'settings-project',
            'title_key' => 'guide_tip_project_title',
            'body_key' => 'guide_tip_project_body',
            'help_topic' => 'getting-started',
        ],
        [
            'area' => 'settings-ai',
            'title_key' => 'guide_tip_ai_title',
            'body_key' => 'guide_tip_ai_body',
            'help_topic' => 'getting-started',
        ],
    ];
}

function birkly_guide_user_has_ai_connection(?array $user): bool
{
    if (!is_array($user)) {
        return false;
    }
    if (!function_exists('birkly_list_accessible_ai_connections_for_session_user')) {
        require_once dirname(__DIR__) . '/api/ai_helpers.php';
    }
    $connections = birkly_list_accessible_ai_connections_for_session_user($user);
    return is_array($connections) && count($connections) > 0;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_guide_tip_for_area(string $area): ?array
{
    foreach (birkly_guide_area_tips() as $tip) {
        if (($tip['area'] ?? '') === $area) {
            return $tip;
        }
    }
    return null;
}

/**
 * @param array<string, mixed> $user Session or account user row
 */
function birkly_guide_checklist_item_visible(string $itemId, array $user): bool
{
    $permissionByItem = [
        'has_collection' => 'manage_content',
        'has_published_entry' => 'manage_content',
        'site_previewable' => 'manage_settings',
        'ai_connection_configured' => 'manage_settings',
        'hub_plugin_installed' => 'manage_plugins',
    ];
    $needed = $permissionByItem[$itemId] ?? null;
    if ($needed === null) {
        return true;
    }
    if (!function_exists('checkPermission')) {
        require_once dirname(__DIR__) . '/api/auth.php';
    }
    return checkPermission($user, $needed);
}

/**
 * @param array<string, mixed>|null $user
 * @return list<array<string, mixed>>
 */
function birkly_guide_checklist_items_for_user(?array $user): array
{
    $items = birkly_guide_checklist_items();
    if ($user === null) {
        return $items;
    }
    return array_values(array_filter($items, static function (array $item) use ($user): bool {
        return birkly_guide_checklist_item_visible((string) ($item['id'] ?? ''), $user);
    }));
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_guide_checklist_items(?array $user = null): array
{
    $hasCollection = birkly_guide_count_collections() > 0;
    $hasPublished = birkly_guide_has_published_entry();
    $hasPlugin = birkly_guide_has_alpha_plugin_active();
    $previewable = birkly_guide_site_previewable();
    $hasAi = birkly_guide_user_has_ai_connection($user);

    require_once __DIR__ . '/project_site.php';
    $visitHref = function_exists('birkly_public_site_visit_url')
        ? birkly_public_site_visit_url()
        : birkly_guide_admin_page_url('settings', ['tab' => 'site-delivery']);

    return [
        [
            'id' => 'has_collection',
            'done' => $hasCollection,
            'href' => birkly_guide_admin_page_url('collection-editor'),
            'help_topic' => 'collections',
            'journey_id' => 'first-content',
        ],
        [
            'id' => 'has_published_entry',
            'done' => $hasPublished,
            'href' => birkly_guide_admin_page_url('content'),
            'help_topic' => 'entries',
            'journey_id' => 'first-content',
        ],
        [
            'id' => 'site_previewable',
            'done' => $previewable,
            'href' => $visitHref !== '' ? $visitHref : birkly_guide_admin_page_url('settings', ['tab' => 'site-delivery']),
            'help_topic' => 'getting-started',
            'journey_id' => 'first-content',
        ],
        [
            'id' => 'ai_connection_configured',
            'done' => $hasAi,
            'href' => birkly_guide_admin_page_url('settings', ['tab' => 'ai']),
            'help_topic' => 'getting-started',
            'journey_id' => 'first-content',
        ],
        [
            'id' => 'hub_plugin_installed',
            'done' => $hasPlugin,
            'href' => birkly_guide_admin_page_url('plugins'),
            'help_topic' => 'marketplace',
            'journey_id' => 'hub-first-plugin',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function birkly_guide_load_manifest(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }
    $path = dirname(__DIR__) . '/admin/guide/guide-manifest.json';
    if (!is_file($path)) {
        $cached = ['journeys' => [], 'concepts' => []];
        return $cached;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    $cached = is_array($decoded) ? $decoded : ['journeys' => [], 'concepts' => []];
    return $cached;
}

/**
 * @param array<string, mixed> $guidePrefs
 * @return list<array<string, mixed>>
 */
function birkly_guide_resolve_journeys(array $guidePrefs): array
{
    $manifest = birkly_guide_load_manifest();
    $completed = is_array($guidePrefs['journeys_completed'] ?? null) ? $guidePrefs['journeys_completed'] : [];
    $out = [];
    foreach (($manifest['journeys'] ?? []) as $journey) {
        if (!is_array($journey)) {
            continue;
        }
        $id = (string) ($journey['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $steps = [];
        foreach (($journey['steps'] ?? []) as $step) {
            if (!is_array($step)) {
                continue;
            }
            $page = (string) ($step['href_page'] ?? '');
            $tab = (string) ($step['href_tab'] ?? '');
            $params = $tab !== '' ? ['tab' => $tab] : [];
            $href = '';
            if ($page === 'visit-project') {
                require_once __DIR__ . '/project_site.php';
                $href = function_exists('birkly_public_site_visit_url') ? birkly_public_site_visit_url() : '';
            } elseif ($page !== '') {
                $href = birkly_guide_admin_page_url($page, $params);
            }
            $steps[] = [
                'teach' => __('admin.' . (string) ($step['teach_key'] ?? '')),
                'href' => $href,
                'help_topic' => (string) ($step['help_topic'] ?? 'getting-started'),
                'open_in_new_tab' => $page === 'visit-project',
            ];
        }
        $out[] = [
            'id' => $id,
            'title' => __('admin.' . (string) ($journey['title_key'] ?? $id)),
            'completed' => in_array($id, $completed, true),
            'steps' => $steps,
        ];
    }
    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_guide_resolve_concepts(): array
{
    $manifest = birkly_guide_load_manifest();
    $out = [];
    foreach (($manifest['concepts'] ?? []) as $concept) {
        if (!is_array($concept)) {
            continue;
        }
        $page = (string) ($concept['href_page'] ?? '');
        $tab = (string) ($concept['href_tab'] ?? '');
        $params = $tab !== '' ? ['tab' => $tab] : [];
        $out[] = [
            'id' => (string) ($concept['id'] ?? ''),
            'title' => __('admin.' . (string) ($concept['title_key'] ?? '')),
            'body' => __('admin.' . (string) ($concept['body_key'] ?? '')),
            'help_topic' => (string) ($concept['help_topic'] ?? 'getting-started'),
            'href' => $page !== '' ? birkly_guide_admin_page_url($page, $params) : '',
        ];
    }
    return $out;
}

/**
 * @param array<string, mixed> $guidePrefs
 * @param array<string, mixed>|null $user
 * @return array<string, mixed>
 */
function birkly_guide_build_progress_payload(array $guidePrefs, string $area, ?array $user = null): array
{
    $tipsSeen = is_array($guidePrefs['tips_seen'] ?? null) ? $guidePrefs['tips_seen'] : [];
    $attentionDismissed = is_array($guidePrefs['attention_dismissed'] ?? null) ? $guidePrefs['attention_dismissed'] : [];
    $tip = birkly_guide_tip_for_area($area);
    $attention = false;
    $snoozed = birkly_guide_is_tips_snoozed($guidePrefs);
    if ($tip && empty($guidePrefs['disable_attention']) && !$snoozed) {
        $areaId = (string) ($tip['area'] ?? '');
        if ($areaId !== '' && !in_array($areaId, $tipsSeen, true) && !in_array($areaId, $attentionDismissed, true)) {
            $attention = true;
        }
    }

    $checklist = birkly_guide_checklist_items_for_user($user);
    foreach ($checklist as &$item) {
        $item['label'] = __('admin.guide_checklist_' . ($item['id'] ?? ''));
    }
    unset($item);

    $shouldAutoOpen = ($guidePrefs['first_admin_visit_ack_at'] ?? null) === null
        || $guidePrefs['first_admin_visit_ack_at'] === ''
        || !empty($guidePrefs['guide_auto_open_pending']);

    $checklistDone = count(array_filter($checklist, static fn ($item) => !empty($item['done'])));

    return [
        'area' => $area,
        'prefs' => $guidePrefs,
        'checklist' => $checklist,
        'checklist_done_count' => $checklistDone,
        'checklist_total' => count($checklist),
        'tip' => $tip,
        'attention' => $attention,
        'should_auto_open_panel' => $shouldAutoOpen,
        'guide_auto_open_pending' => !empty($guidePrefs['guide_auto_open_pending']),
        'show_post_onboarding_nudge' => false,
        'tips_snoozed' => $snoozed,
        'journeys' => birkly_guide_resolve_journeys($guidePrefs),
        'concepts' => birkly_guide_resolve_concepts(),
        'show_ai_connection_nudge' => !birkly_guide_user_has_ai_connection($user),
        'ai_connection_href' => birkly_guide_admin_page_url('settings', ['tab' => 'ai']),
    ];
}
