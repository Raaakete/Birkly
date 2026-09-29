<?php
/**
 * AI quality eval harness — scripted prompt scenarios, playbook section checks,
 * help-manifest ai_topic wiring, and site-build checklist coverage.
 *
 * Run: php tests/ai_quality_eval_test.php
 */

declare(strict_types=1);

$GLOBALS['haru_plugins_loaded'] = true;
$GLOBALS['birkly_plugin_playbooks'] = [];
$pluginRepos = dirname(__DIR__, 2);
$playbookSettingsDir = sys_get_temp_dir() . '/birkly-playbook-eval-' . getmypid();
mkdir($playbookSettingsDir);
$playbookStates = [];
foreach (['commerce', 'user_management', 'events', 'automation'] as $playbookPluginId) {
    $playbookStates[$playbookPluginId] = ['active' => true, 'settings' => []];
}
file_put_contents($playbookSettingsDir . '/plugins.json', json_encode($playbookStates));
$GLOBALS['__birkly_plugin_state_settings_dir'] = $playbookSettingsDir;
$GLOBALS['birkly_plugins'] = [
    'commerce' => ['active' => true, 'path' => $pluginRepos . '/Birkly-Plugin-Commerce'],
    'user_management' => ['active' => true, 'path' => $pluginRepos . '/Birkly-Plugin-User'],
    'events' => ['active' => true, 'path' => $pluginRepos . '/Birkly-Plugin-Events'],
    'automation' => ['active' => true, 'path' => $pluginRepos . '/Birkly-Plugin-Automation'],
];

require_once dirname(__DIR__) . '/api/ai_helpers.php';
require_once dirname(__DIR__) . '/core/ai_playbooks.php';
require_once dirname(__DIR__) . '/core/help_docs.php';
require_once dirname(__DIR__) . '/core/plugins/docs_shelf.php';
foreach ($GLOBALS['birkly_plugins'] as $id => $plugin) {
    $GLOBALS['birkly_plugins'][$id]['active'] = true;
    if (!isset($GLOBALS['birkly_plugins'][$id]['path'])) {
        $GLOBALS['birkly_plugins'][$id]['path'] = $pluginRepos . '/Birkly-Plugin-' . $id;
    }
}
foreach ([
    'commerce' => $pluginRepos . '/Birkly-Plugin-Commerce/docs/playbooks.php',
    'user_management' => $pluginRepos . '/Birkly-Plugin-User/docs/playbooks.php',
    'events' => $pluginRepos . '/Birkly-Plugin-Events/docs/playbooks.php',
    'automation' => $pluginRepos . '/Birkly-Plugin-Automation/docs/playbooks.php',
] as $id => $playbooks) {
    $GLOBALS['birkly_plugins'][$id]['active'] = true;
    $GLOBALS['birkly_plugins'][$id]['path'] = dirname($playbooks, 2);
    if (is_file($playbooks)) {
        require_once $playbooks;
    }
}

$failures = 0;

function eval_assert(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

/** @param list<string> $needles */
function text_contains_all(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!str_contains($haystack, $needle)) {
            return false;
        }
    }
    return true;
}

// --- Prompt scenarios → expected auto-injected topics ---

$scenarios = [
    ['build me a bakery website', ['site-building', 'web-project-patterns', 'content-model', 'templating']],
    ['fix my contact form validation failed', ['public-forms']],
    ['connect Cursor via MCP', ['mcp-setup']],
    ['install commerce plugin for my shop', ['plugins-overview', 'commerce']],
    ['create editor role without delete permission', ['settings-roles']],
    ['add member registration and login', ['user-management']],
    ['RSVP events with paid tickets', ['events', 'commerce']],
    ['organize collections in sidebar groups', ['collection-groups']],
    ['why is my detail page blank best practice', ['ai-quality-guide']],
];

foreach ($scenarios as [$prompt, $expectedTopics]) {
    $detected = birkly_ai_detect_playbook_topics($prompt, []);
    $missing = array_values(array_diff($expectedTopics, $detected));
    eval_assert(
        'scenario topics: ' . substr($prompt, 0, 40),
        $missing === [],
        $missing !== [] ? 'missing: ' . implode(', ', $missing) . ' got: ' . implode(', ', $detected) : ''
    );
}

// --- Playbook depth: expanded playbooks must have key sections ---

$expandedPlaybooks = [
    'events' => ['Setup order', 'Common failure modes', 'Admin cross-reference'],
    'user-management' => ['Admin users vs site users', 'user_submit', 'Admin cross-reference'],
    'settings-roles' => ['Default CMS roles', 'cms_create_user', 'Admin cross-reference'],
    'cms-pages' => ['Pages vs project site', 'Anti-patterns', 'Admin cross-reference'],
    'plugins-overview' => ['Dependency order', 'list_plugins', 'Admin cross-reference'],
];

foreach ($expandedPlaybooks as $topic => $sections) {
    $doc = readBirklyDocsTopic($topic);
    $text = is_array($doc) ? (string) ($doc['text'] ?? '') : '';
    eval_assert(
        "playbook sections: {$topic}",
        $text !== '' && text_contains_all($text, $sections),
        'expected sections: ' . implode(', ', $sections)
    );
}

// --- Regression docs: known bad patterns ---

$publicForms = readBirklyDocsTopic('public-forms');
eval_assert(
    'public-forms has known bad patterns',
    is_array($publicForms) && str_contains((string) ($publicForms['text'] ?? ''), 'Known bad patterns')
);

$patterns = readBirklyDocsTopic('web-project-patterns');
eval_assert(
    'web-project-patterns has known bad patterns',
    is_array($patterns) && str_contains((string) ($patterns['text'] ?? ''), 'Known bad patterns')
);

// --- site-building lives under playbooks/ ---

$siteBuilding = readBirklyDocsTopic('site-building');
eval_assert(
    'site-building resolves from playbooks folder',
    is_array($siteBuilding) && str_contains((string) ($siteBuilding['text'] ?? ''), 'Build order')
);

// --- ai-quality-guide checklist items used by eval ---

$quality = readBirklyDocsTopic('ai-quality-guide');
$checklistItems = [
    'birkly-client.js',
    'read_collection',
    'public-forms',
    'fieldtypes',
];
eval_assert(
    'ai-quality-guide checklist coverage',
    is_array($quality) && text_contains_all((string) ($quality['text'] ?? ''), $checklistItems)
);

// --- help-manifest ai_topic → valid playbook topics ---

$manifest = birkly_load_core_help_manifest();
$topics = is_array($manifest['topics'] ?? null) ? $manifest['topics'] : [];
$withAiTopic = 0;
$invalidAiTopic = [];

foreach ($topics as $topic) {
    if (!is_array($topic) || empty($topic['ai_topic'])) {
        continue;
    }
    $withAiTopic++;
    $slug = (string) $topic['ai_topic'];
    $doc = readBirklyDocsTopic($slug);
    if ($doc === null) {
        $invalidAiTopic[] = ($topic['id'] ?? '?') . '→' . $slug;
    }
}

eval_assert('help-manifest has ai_topic entries', $withAiTopic >= 10);
eval_assert(
    'help-manifest ai_topic slugs resolve',
    $invalidAiTopic === [],
    $invalidAiTopic !== [] ? implode('; ', $invalidAiTopic) : ''
);

// --- MCP initialize includes quality + plugin guidance ---

$mcpInit = birkly_ai_mcp_initialize_instructions();
eval_assert('MCP init mentions ai-quality-guide', str_contains($mcpInit, 'ai-quality-guide'));
eval_assert('MCP init mentions list_plugins', str_contains($mcpInit, 'list_plugins'));
eval_assert('MCP init mentions known bad patterns / entry for', str_contains($mcpInit, '{entry for}'));

// --- Auto-inject gate ---

eval_assert(
    'auto-inject settings-roles without site intent',
    birkly_ai_should_auto_inject_playbooks('create custom editor role permissions', [])
);

exit($failures > 0 ? 1 : 0);
