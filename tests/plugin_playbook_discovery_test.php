<?php
/**
 * P100-M8 — plugin playbooks are discovered from the docs shelf.
 *
 * Inactive plugins do not resolve. Active plugins keep the same topic ids.
 */

declare(strict_types=1);

$GLOBALS['haru_plugins_loaded'] = true;
$GLOBALS['birkly_plugin_playbooks'] = [];

$repos = dirname(__DIR__, 2);
$plugins = [
    'commerce' => $repos . '/Birkly-Plugin-Commerce',
    'user_management' => $repos . '/Birkly-Plugin-User',
    'events' => $repos . '/Birkly-Plugin-Events',
    'automation' => $repos . '/Birkly-Plugin-Automation',
];

foreach ($plugins as $id => $path) {
    if (!is_file($path . '/docs/playbooks.php')) {
        fwrite(STDERR, "missing playbook registration for {$id}\n");
        exit(1);
    }
    $GLOBALS['birkly_plugins'][$id] = [
        'id' => $id,
        'active' => false,
        'path' => $path,
    ];
}

require_once dirname(__DIR__) . '/core/plugins/docs_shelf.php';
require_once dirname(__DIR__) . '/core/ai_playbooks.php';
require_once dirname(__DIR__) . '/api/ai_helpers.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
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

$coreTopics = birkly_ai_core_docs_topics();
$coreCatalog = birkly_ai_core_playbook_catalog();
foreach (['commerce', 'commerce-store-kit', 'automation', 'events', 'user-management', 'user_management'] as $topic) {
    assert_true("core docs omit {$topic}", !isset($coreTopics[$topic]));
    assert_true("core catalog omits {$topic}", !isset($coreCatalog[$topic]));
}

$cmsPlaybooks = dirname(__DIR__) . '/docs/ai/playbooks';
foreach ([
    'commerce-playbook.md',
    'commerce-store-kit-playbook.md',
    'automation-playbook.md',
    'events-playbook.md',
    'user-management-playbook.md',
] as $file) {
    assert_true("cms playbook file left core: {$file}", !is_file($cmsPlaybooks . '/' . $file));
}

$inactive = readBirklyDocsTopic('commerce');
assert_true('inactive commerce playbook is absent', $inactive === null);
assert_true('inactive catalog omits commerce', !isset(birkly_ai_playbook_catalog()['commerce']));

foreach ($GLOBALS['birkly_plugins'] as $id => $plugin) {
    $GLOBALS['birkly_plugins'][$id]['active'] = true;
}

foreach ($plugins as $path) {
    require_once $path . '/docs/playbooks.php';
}

$commerce = readBirklyDocsTopic('commerce');
assert_true(
    'active commerce topic id resolves',
    is_array($commerce)
        && ($commerce['slug'] ?? '') === 'commerce'
        && ($commerce['source'] ?? '') === 'plugin_playbook'
        && str_contains((string) ($commerce['text'] ?? ''), 'Commerce plugin installed and active')
);

$storeKit = readBirklyDocsTopic('commerce-store-kit');
assert_true(
    'active commerce-store-kit resolves',
    is_array($storeKit)
        && str_contains((string) ($storeKit['text'] ?? ''), 'weight_grams')
        && str_contains((string) ($storeKit['text'] ?? ''), 'commerce-storefront.js')
);

$events = readBirklyDocsTopic('events');
assert_true(
    'active events playbook has failure modes',
    is_array($events) && str_contains((string) ($events['text'] ?? ''), 'Common failure modes')
);

$user = readBirklyDocsTopic('user-management');
$userAlias = readBirklyDocsTopic('user_management');
assert_true(
    'active user-management resolves',
    is_array($user) && str_contains((string) ($user['text'] ?? ''), 'Admin users vs site users')
);
assert_true(
    'user_management alias resolves to the same playbook',
    is_array($userAlias) && ($userAlias['text'] ?? '') === ($user['text'] ?? '') && ($user['text'] ?? '') !== ''
);

$automation = readBirklyDocsTopic('automation');
assert_true(
    'active automation topic id resolves',
    is_array($automation) && str_contains((string) ($automation['text'] ?? ''), 'Automation plugin installed and active')
);

$catalog = birkly_ai_playbook_catalog();
assert_true('catalog discovers commerce', isset($catalog['commerce']) && ($catalog['commerce']['topic'] ?? '') === 'commerce');
assert_true('catalog discovers commerce-store-kit', isset($catalog['commerce-store-kit']));
assert_true('catalog discovers events', isset($catalog['events']));
assert_true('catalog discovers automation', isset($catalog['automation']));
assert_true('catalog discovers user-management', isset($catalog['user-management']));
assert_true('catalog keeps templating', isset($catalog['templating']));

$GLOBALS['birkly_plugins']['commerce']['active'] = false;
assert_true('deactivated commerce playbook is absent', birkly_plugin_playbook_for_topic('commerce') === null);
assert_true('deactivated catalog omits commerce', !isset(birkly_ai_playbook_catalog()['commerce']));
assert_true('events stay while commerce is inactive', birkly_plugin_playbook_for_topic('events') !== null);

$commerceSrc = (string) file_get_contents($plugins['commerce'] . '/docs/playbooks.php');
assert_true('Commerce registers commerce', str_contains($commerceSrc, "register_plugin_playbook('commerce', 'commerce'"));
assert_true('Commerce registers commerce-store-kit', str_contains($commerceSrc, "register_plugin_playbook('commerce', 'commerce-store-kit'"));
$userSrc = (string) file_get_contents($plugins['user_management'] . '/docs/playbooks.php');
assert_true('User registers user_management alias', str_contains($userSrc, "'user_management'"));

if ($failures > 0) {
    fwrite(STDERR, "plugin_playbook_discovery_test: {$failures} failure(s)\n");
    exit(1);
}

echo "plugin_playbook_discovery_test: PASS\n";
exit(0);
