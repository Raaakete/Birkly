<?php
declare(strict_types=1);

$sourceRoot = dirname(__DIR__);
$cmsRoot = sys_get_temp_dir() . '/birkly_automation_plugin_test_' . getmypid();
$pluginRoot = $cmsRoot . '/plugins/automation';
$GLOBALS['automation_test_cms_root'] = $cmsRoot;

cleanup_dir($cmsRoot);
mkdir($pluginRoot, 0775, true);
copy_tree($sourceRoot, $pluginRoot, ['.git']);
write_core_stubs($cmsRoot);

$_SESSION = [
    'user' => [
        'id' => 'test-user',
        'role' => 'admin',
        'capabilities' => ['manage_content'],
    ],
];

require_once $pluginRoot . '/init.php';
require_once $pluginRoot . '/includes/cron_auth.php';

$tests = [
    'trigger status filters are honored' => 'test_status_trigger_filters',
    'webhook nested filters are honored' => 'test_webhook_nested_filters',
    'test mode simulates mutating actions' => 'test_mode_simulates_actions',
    'action failures return false' => 'test_action_failure_status',
    'future retry jobs remain pending' => 'test_future_retry_stays_pending',
    'outbound URL validation blocks private targets' => 'test_outbound_url_validation',
    'secret redaction masks sensitive fields' => 'test_secret_redaction',
    'cron token matching is enforced' => 'test_cron_token_matching',
    'admin cron URLs are masked by default' => 'test_admin_cron_urls_masked_by_default',
    'admin CSS uses automation accent tokens' => 'test_admin_css_uses_accent_tokens',
    'graph branching follows if/else edges' => 'test_graph_branching_execution',
    'editor page is registered for dedicated canvas' => 'test_editor_page_files_exist',
    'package builder verifies source artifact contents' => 'test_package_builder_contents',
    'enqueue processes queue inline' => 'test_enqueue_processes_inline',
    'setup health and mail settings work' => 'test_setup_health_and_mail_settings',
    'dry run describes side effects clearly' => 'test_dry_run_describes_side_effects',
    'force queue processing ignores retry backoff' => 'test_force_queue_ignores_retry',
    'duplicate email side effects are deduped' => 'test_email_side_effect_dedupe',
];

$failures = 0;
foreach ($tests as $label => $fn) {
    reset_settings($cmsRoot);
    try {
        $fn();
        echo "PASS {$label}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "FAIL {$label}: {$e->getMessage()}\n";
    }
}

cleanup_dir($cmsRoot);

if ($failures > 0) {
    exit(1);
}

echo 'All automation plugin tests passed (' . count($tests) . ").\n";

function test_status_trigger_filters(): void
{
    create_automation([
        'id' => 'publish-notify',
        'name' => 'Publish notify',
        'enabled' => true,
        'trigger' => ['type' => 'status_changed', 'config' => ['from' => 'draft', 'to' => 'published']],
        'actions' => [['type' => 'log', 'config' => ['message' => 'Published {{entry.title}}']]],
    ]);
    create_automation([
        'id' => 'archive-notify',
        'name' => 'Archive notify',
        'enabled' => true,
        'trigger' => ['type' => 'status_changed', 'config' => ['from' => 'draft', 'to' => 'archived']],
        'actions' => [['type' => 'log', 'config' => ['message' => 'Archived {{entry.title}}']]],
    ]);

    $executed = execute_automations('status_changed', [
        'from_status' => 'draft',
        'to_status' => 'published',
        'collection' => 'blog',
        'entry' => ['title' => 'Hello', 'collection' => 'blog'],
        '_test_mode' => true,
    ]);

    assert_same(1, $executed, 'only matching status trigger should run');
}

function test_webhook_nested_filters(): void
{
    create_automation([
        'id' => 'webhook-order',
        'name' => 'Webhook order',
        'enabled' => true,
        'trigger' => [
            'type' => 'webhook_inbound',
            'config' => [
                'webhook_id' => 'orders',
                'filter_field' => 'payload.event',
                'filter_value' => 'order.created',
            ],
        ],
        'actions' => [['type' => 'log', 'config' => ['message' => 'Order webhook']]],
    ]);

    $matched = execute_automations('webhook_inbound', [
        'webhook_id' => 'orders',
        'payload' => ['event' => 'order.created'],
        '_test_mode' => true,
    ]);
    $missed = execute_automations('webhook_inbound', [
        'webhook_id' => 'orders',
        'payload' => ['event' => 'order.cancelled'],
        '_test_mode' => true,
    ]);

    assert_same(1, $matched, 'matching webhook payload should run');
    assert_same(0, $missed, 'non-matching webhook payload should skip');
}

function test_mode_simulates_actions(): void
{
    $ok = execute_automation([
        'id' => 'mutating-test',
        'name' => 'Mutating test',
        'actions' => [[
            'type' => 'set_field',
            'config' => ['field' => 'title', 'value' => 'Changed'],
        ]],
    ], [
        'entry' => ['id' => 'missing', 'collection' => 'blog'],
        'collection' => 'blog',
        '_test_mode' => true,
    ]);

    assert_true($ok, 'test mode should simulate mutating action');
}

function test_action_failure_status(): void
{
    $ok = execute_automation([
        'id' => 'bad-webhook',
        'name' => 'Bad webhook',
        'actions' => [[
            'type' => 'webhook',
            'config' => ['url' => 'http://127.0.0.1/hook', 'method' => 'POST', 'payload' => '{}'],
        ]],
    ], [
        'entry' => ['title' => 'Hello'],
    ]);

    assert_false($ok, 'failed action should return false');
}

function test_future_retry_stays_pending(): void
{
    $storage = StorageFactory::createStorage($GLOBALS['automation_test_cms_root'] . '/settings');
    $storage->write('automation_queue.json', [[
        'id' => 'job_future',
        'trigger_type' => 'entry_created',
        'context' => [],
        'created_at' => date('c'),
        'attempts' => 1,
        'status' => 'pending',
        'retry_after' => date('c', time() + 3600),
    ]]);

    $processed = process_automation_queue(10);
    $status = get_automation_queue_status();

    assert_same(0, $processed, 'future retry should not process');
    assert_same(1, $status['pending'], 'future retry should remain pending');
    assert_same(0, $status['processing'], 'future retry should not become processing');
}

function test_outbound_url_validation(): void
{
    assert_false(automation_validate_outbound_url('http://127.0.0.1/hook')['ok'], 'loopback URL blocked');
    assert_false(automation_validate_outbound_url('http://localhost/hook')['ok'], 'localhost URL blocked');
    assert_true(automation_validate_outbound_url('https://93.184.216.34/hook')['ok'], 'public https URL allowed');
}

function test_secret_redaction(): void
{
    $redacted = automation_redact_sensitive_data([
        'webhook_url' => 'https://hooks.example.com/services/abcdef123456',
        'nested' => ['api_key' => 'abcdef123456'],
    ]);

    assert_true($redacted['webhook_url'] !== 'https://hooks.example.com/services/abcdef123456', 'webhook URL masked');
    assert_true($redacted['nested']['api_key'] !== 'abcdef123456', 'API key masked');
}

function test_cron_token_matching(): void
{
    $token = automation_get_cron_token();

    assert_true($token !== '', 'cron token should be generated');
    assert_true(automation_cron_token_matches($token), 'generated cron token should match');
    assert_false(automation_cron_token_matches('wrong-token'), 'wrong cron token should not match');
}

function test_admin_cron_urls_masked_by_default(): void
{
    $root = dirname(__DIR__);
    $html = (string) file_get_contents($root . '/admin/automation.html');
    $js = (string) file_get_contents($root . '/admin/automation.js');

    assert_true(str_contains($html, 'automation-cron-toggle'), 'setup panel should include reveal toggle');
    assert_true(str_contains($html, 'data-automation-view="setup"'), 'admin should expose a Setup tab for cron guidance');
    assert_true(str_contains($html, 'id="automation-cron-queue-url"'), 'setup panel should include queue URL container');
    assert_true(str_contains($html, 'id="automation-setup-checklist"'), 'setup panel should include readiness checklist');
    assert_true(str_contains($html, 'id="automation-mail-settings-form"'), 'setup panel should include email From settings');
    assert_true(str_contains($html, 'id="automation-mail-method"'), 'setup panel should let users choose mail transport');
    assert_true(str_contains($html, 'data-automation-action="process-queue-now"'), 'setup panel should allow processing queue now');
    assert_true(str_contains($html, 'data-automation-action="send-test-email"'), 'setup panel should offer send test email');
    assert_true(str_contains($html, 'make automations actually deliver'), 'setup panel should explain its purpose');
    assert_true(str_contains($html, 'id="automation-recent-jobs"'), 'setup panel should show recent queue jobs');
    assert_true(is_file($root . '/cms_paths.php'), 'cms_paths helper should exist for data-volume installs');
    $register = (string) file_get_contents($root . '/docs/register_docs.php');
    assert_true(str_contains($register, 'help-getting-started.md'), 'docs should register getting started topic');
    assert_true(str_contains($register, 'help-email.md'), 'docs should register email topic');
    assert_true(str_contains($register, 'help-troubleshooting.md'), 'docs should register troubleshooting topic');
    assert_true(is_file($root . '/docs/help-examples.md'), 'examples doc should exist');
    assert_true(is_file($root . '/includes/mailer.php'), 'SMTP/log mailer should exist');
    assert_true(!preg_match('/token=[A-Za-z0-9_-]{8,}/', $html), 'HTML must not embed a raw cron token');
    assert_true(str_contains($js, 'function maskCronUrl'), 'admin JS should include URL masking helper');
    assert_true(str_contains($js, 'token=)[^&]+'), 'mask helper should redact token query values');
    assert_true(str_contains($js, 'aria-pressed'), 'reveal toggle should expose pressed state');
    assert_true(str_contains($js, 'initAutomationActionDelegation'), 'admin JS should bind CSP-safe click handlers');
    assert_true(str_contains($js, 'Dry Run — nothing sent'), 'dry-run UI should state nothing was sent');
    assert_true(!str_contains($html, 'onclick='), 'admin HTML must not use inline onclick under admin CSP');
}

function test_enqueue_processes_inline(): void
{
    create_automation([
        'id' => 'inline-queue',
        'name' => 'Inline queue',
        'enabled' => true,
        'trigger' => ['type' => 'entry_created', 'config' => []],
        'actions' => [['type' => 'log', 'config' => ['message' => 'queued {{entry.title}}']]],
    ]);

    $ok = enqueue_automation('entry_created', [
        'entry' => ['title' => 'Queued Entry', 'collection' => 'blog'],
        'collection' => 'blog',
        '_test_mode' => true,
    ]);
    assert_true($ok, 'enqueue should succeed');

    $status = get_automation_queue_status();
    assert_true(($status['completed'] ?? 0) >= 1, 'inline processing should complete the job');
    assert_same(0, (int) ($status['pending'] ?? 0), 'pending queue should be drained after inline process');

    $runtime = StorageFactory::createStorage($GLOBALS['automation_test_cms_root'] . '/settings')
        ->read('automation_runtime.json') ?? [];
    assert_true(!empty($runtime['last_queue_run_at']), 'queue heartbeat should be recorded');
}

function test_setup_health_and_mail_settings(): void
{
    $saved = automation_save_mail_settings([
        'method' => 'log',
        'from_email' => 'alerts@example.com',
        'from_name' => 'Alerts',
    ]);
    assert_true(is_array($saved), 'mail settings should save');
    assert_same('alerts@example.com', $saved['from_email'], 'from email persisted');
    assert_same('log', $saved['method'], 'log transport persisted');
    assert_true(!isset($saved['smtp_password']), 'public mail settings should not expose password');

    $mail = automation_get_mail_settings();
    assert_same('alerts@example.com', $mail['from_email'], 'mail settings should load');

    $send = automation_send_mail('user@example.com', 'Hello', '<p>Body</p>');
    assert_true(!empty($send['ok']), 'log transport should accept mail');
    assert_same('log', $send['method'], 'send should use log method');

    create_automation([
        'id' => 'email-health',
        'name' => 'Email health',
        'enabled' => true,
        'trigger' => ['type' => 'entry_created', 'config' => []],
        'actions' => [['type' => 'email', 'config' => [
            'to' => 'user@example.com',
            'subject' => 'Hi',
            'body' => 'Body',
        ]]],
    ]);

    $health = automation_setup_health();
    assert_true(isset($health['checks']) && is_array($health['checks']), 'health should include checks');
    assert_true(isset($health['mail']['from_email']), 'health should expose mail settings');
    assert_true(isset($health['guide']['steps']), 'health should include setup guide copy');
    assert_true(($health['plugin_version'] ?? '') !== '', 'health should expose plugin version');
    assert_true(version_compare((string) $health['plugin_version'], '1.1.4', '>='), 'plugin version should be at least 1.1.4');

    $lock1 = automation_acquire_test_email_lock('dedupe@example.com', 30);
    $lock2 = automation_acquire_test_email_lock('dedupe@example.com', 30);
    assert_true(!empty($lock1['acquired']), 'first test-email lock should acquire');
    assert_true(empty($lock2['acquired']), 'second test-email lock should dedupe');
    automation_release_test_email_lock('dedupe@example.com');
    assert_same(1, (int) ($health['action_usage']['email'] ?? 0), 'email action usage should be counted');
    assert_true(count($health['sent_mail'] ?? []) >= 1, 'recent sends should include logged mail');
}

function test_dry_run_describes_side_effects(): void
{
    $detail = automation_describe_dry_run_action('email', [
        'to' => 'user@example.com',
        'subject' => 'Hello',
        'body' => 'Body',
    ], []);

    assert_true(str_contains($detail, 'not sent'), 'email dry-run detail must say not sent');
    assert_true(str_contains($detail, 'user@example.com'), 'email dry-run detail should include recipient');

    $webhook = automation_describe_dry_run_action('webhook', [
        'url' => 'https://example.com/hook',
        'method' => 'POST',
    ], []);
    assert_true(str_contains($webhook, 'not sent'), 'webhook dry-run detail must say not sent');
}

function test_email_side_effect_dedupe(): void
{
    automation_save_mail_settings([
        'method' => 'log',
        'from_email' => 'alerts@example.com',
        'from_name' => 'Alerts',
    ]);

    create_automation([
        'id' => 'dedupe-email',
        'name' => 'Dedupe email',
        'enabled' => true,
        'trigger' => ['type' => 'entry_created', 'config' => []],
        'actions' => [[
            'type' => 'email',
            'config' => [
                'to' => 'user@example.com',
                'subject' => 'Hello',
                'body' => 'Body',
            ],
        ]],
    ]);

    $context = [
        'entry' => ['id' => 'entry_1', 'title' => 'One', 'collection' => 'blog'],
        'collection' => 'blog',
        'origin_automation' => 'dedupe-email',
    ];

    $block = get_automation_block('action', 'email');
    $handler = $block['handler'];
    $first = call_user_func($handler, [
        'to' => 'user@example.com',
        'subject' => 'Hello',
        'body' => 'Body',
    ], $context);
    $second = call_user_func($handler, [
        'to' => 'user@example.com',
        'subject' => 'Hello',
        'body' => 'Body',
    ], $context);

    assert_true(!empty($first['sent']), 'first email action should send');
    assert_true(empty($first['deduped']), 'first email action should not be deduped');
    assert_true(!empty($second['deduped']), 'second identical email action should be deduped');

    $ok1 = enqueue_automation('entry_created', [
        'entry' => ['id' => 'entry_dup', 'title' => 'Dup', 'collection' => 'blog'],
        'collection' => 'blog',
        '_test_mode' => true,
    ]);
    $before = get_automation_queue_status();
    $ok2 = enqueue_automation('entry_created', [
        'entry' => ['id' => 'entry_dup', 'title' => 'Dup', 'collection' => 'blog'],
        'collection' => 'blog',
        '_test_mode' => true,
    ]);
    $after = get_automation_queue_status();
    assert_true($ok1 && $ok2, 'enqueue dedupe should still report success');
    assert_true(
        (int) ($after['total'] ?? 0) <= (int) ($before['total'] ?? 0) + 0,
        'second enqueue for same entry should not add another job'
    );
}

function test_force_queue_ignores_retry(): void
{
    create_automation([
        'id' => 'force-queue',
        'name' => 'Force queue',
        'enabled' => true,
        'trigger' => ['type' => 'entry_created', 'config' => []],
        'actions' => [['type' => 'log', 'config' => ['message' => 'forced']]],
    ]);

    $storage = automation_storage();
    $storage->write('automation_queue.json', [[
        'id' => 'job_retry',
        'trigger_type' => 'entry_created',
        'context' => [
            'entry' => ['title' => 'Retry me', 'collection' => 'blog'],
            'collection' => 'blog',
            '_test_mode' => true,
        ],
        'created_at' => date('c'),
        'attempts' => 1,
        'status' => 'pending',
        'retry_after' => date('c', time() + 3600),
        'error' => 'previous failure',
    ]]);

    $skipped = process_automation_queue(10, ['force' => false]);
    assert_same(0, $skipped, 'normal process should honor retry_after');

    $forced = process_automation_queue(10, ['force' => true]);
    assert_same(1, $forced, 'force process should ignore retry_after');

    $status = get_automation_queue_status();
    assert_same(0, (int) ($status['pending'] ?? 0), 'forced job should leave pending empty');
    assert_true((int) ($status['completed'] ?? 0) >= 1, 'forced job should complete');
}

function test_admin_css_uses_accent_tokens(): void
{
    $css = (string) file_get_contents(dirname(__DIR__) . '/admin/automation.css');

    assert_true(str_contains($css, '--automation-accent:'), 'automation CSS should define an accent token alias');
    assert_false(str_contains($css, '--primary-'), 'automation CSS should not rely on orphan primary token fallbacks');
    assert_false(str_contains($css, '#1d4ed8') || str_contains($css, '#2563eb') || str_contains($css, '#3b82f6'), 'automation CSS should not keep old blue fallbacks');
    assert_true(str_contains($css, '.canvas-port'), 'canvas ports should be styled for connections');
    assert_true(str_contains($css, '.automation-editor-shell'), 'dedicated editor shell styles should exist');
}

function test_editor_page_files_exist(): void
{
    $root = dirname(__DIR__);
    $plugin = (string) file_get_contents($root . '/plugin.php');
    $html = (string) file_get_contents($root . '/admin/automation-editor.html');
    $js = (string) file_get_contents($root . '/admin/automation-editor.js');
    $editor = (string) file_get_contents($root . '/admin/automation_node_editor.js');

    assert_true(str_contains($plugin, "register_admin_page('automation-editor'"), 'plugin should register automation-editor page');
    assert_true(str_contains($plugin, "'automation-editor'"), 'plugin meta should include editor sub-page');
    assert_true(str_contains($html, 'id="node-canvas"'), 'editor page should include canvas');
    assert_true(str_contains($js, 'automation-editor'), 'editor page script should target editor route');
    assert_true(str_contains($editor, '_startConnect'), 'node editor should support port connections');
    assert_true(str_contains($editor, 'data-port="true"'), 'condition nodes should expose If-true ports');
    assert_true(str_contains($editor, '_buildSearchableCollectionPicker'), 'inspector should offer searchable collection picker');
    assert_true(str_contains($editor, '_nodeSummary'), 'nodes should render setting summaries');
    assert_true(str_contains($editor, 'node-palette-tabs'), 'palette should expose category tabs');
}

function test_graph_branching_execution(): void
{
    $truePath = [];
    $falsePath = [];

    // Register temporary condition/actions via direct automation with graph + real blocks.
    $automation = [
        'id' => 'auto_branch_test',
        'name' => 'Branch test',
        'enabled' => true,
        'trigger' => ['type' => 'entry_created', 'config' => []],
        'conditions' => [],
        'actions' => [],
        'graph' => [
            'nodes' => [
                ['id' => 't1', 'kind' => 'trigger', 'type' => 'entry_created', 'config' => [], 'x' => 0, 'y' => 0],
                ['id' => 'c1', 'kind' => 'condition', 'type' => 'status_check', 'config' => ['status' => 'published'], 'x' => 0, 'y' => 0],
                ['id' => 'a_true', 'kind' => 'action', 'type' => 'log', 'config' => ['message' => 'true-path'], 'x' => 0, 'y' => 0],
                ['id' => 'a_false', 'kind' => 'action', 'type' => 'log', 'config' => ['message' => 'false-path'], 'x' => 0, 'y' => 0],
            ],
            'edges' => [
                ['from' => 't1', 'to' => 'c1', 'branch' => 'default'],
                ['from' => 'c1', 'to' => 'a_true', 'branch' => 'true'],
                ['from' => 'c1', 'to' => 'a_false', 'branch' => 'false'],
            ],
        ],
    ];

    $okTrue = execute_automation($automation, [
        '_test_mode' => true,
        'entry' => ['status' => 'published', 'title' => 'Pub'],
    ]);
    assert_true($okTrue, 'true branch should execute');

    $okFalse = execute_automation($automation, [
        '_test_mode' => true,
        'entry' => ['status' => 'draft', 'title' => 'Draft'],
    ]);
    assert_true($okFalse, 'false branch should still execute its else action');

    // Persist graph via create/update sanitize path.
    $created = create_automation([
        'name' => 'Persisted graph',
        'trigger' => ['type' => 'entry_created', 'config' => []],
        'actions' => [['type' => 'log', 'config' => ['message' => 'x']]],
        'graph' => $automation['graph'],
    ]);
    assert_true(is_array($created) && is_array($created['graph'] ?? null), 'create should persist graph');
    assert_true(count($created['graph']['edges']) === 3, 'sanitized graph should keep branch edges');
}

function test_package_builder_contents(): void
{
    if (!class_exists('ZipArchive')) {
        $output = [];
        $code = 0;
        exec(PHP_BINARY . ' ' . escapeshellarg(dirname(__DIR__) . '/scripts/build-plugin-zip.php') . ' 2>&1', $output, $code);
        assert_true($code !== 0 && str_contains(implode("\n", $output), 'zip extension'), 'package builder should report missing zip extension');
        return;
    }

    $tmp = sys_get_temp_dir() . '/birkly_automation_package_test_' . getmypid();
    cleanup_dir($tmp);
    mkdir($tmp, 0775, true);
    $zipPath = $tmp . '/birkly-automation-test.zip';
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' ' . escapeshellarg(dirname(__DIR__) . '/scripts/build-plugin-zip.php') . ' ' . escapeshellarg($zipPath), $output, $code);

    assert_same(0, $code, 'package builder should exit cleanly');
    $metadata = json_decode(implode("\n", $output), true);
    assert_true(is_array($metadata) && ($metadata['status'] ?? '') === 'ok', 'package builder should emit ok metadata');
    assert_true(is_file($zipPath), 'package zip should be written');
    assert_true(is_file($zipPath . '.sha256'), 'package checksum should be written');

    $zip = new ZipArchive();
    $names = [];
    if ($zip->open($zipPath) === true) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
    }

    assert_true(in_array('automation/plugin.json', $names, true), 'zip should include plugin manifest under automation/');
    assert_true(in_array('automation/admin/automation.html', $names, true), 'zip should include admin page');
    assert_true(in_array('automation/cli/process_queue.php', $names, true), 'zip should include CLI runner');
    assert_false(in_array('automation/tests/run.php', $names, true), 'zip should exclude tests');
    assert_false(in_array('automation/docs/RELEASE-READINESS-PROGRESS.md', $names, true), 'zip should exclude internal progress doc');
    assert_false(in_array('automation/docs/WAVE3-RELEASE-EVIDENCE.md', $names, true), 'zip should exclude Wave 3 evidence doc');

    cleanup_dir($tmp);
}

function reset_settings(string $cmsRoot): void
{
    cleanup_dir($cmsRoot . '/settings');
    mkdir($cmsRoot . '/settings', 0775, true);
}

function write_core_stubs(string $cmsRoot): void
{
    mkdir($cmsRoot . '/core/storage', 0775, true);
    mkdir($cmsRoot . '/core/plugins', 0775, true);

    file_put_contents($cmsRoot . '/core/storage/StorageFactory.php', <<<'PHP'
<?php
class StorageFactory {
    public static function createStorage($base) {
        return new JsonStorage($base);
    }
}
class JsonStorage {
    private string $base;
    public function __construct($base) {
        $this->base = $base;
        if (!is_dir($this->base)) {
            mkdir($this->base, 0775, true);
        }
    }
    public function read($path) {
        $file = $this->base . '/' . ltrim($path, '/');
        if (!is_file($file)) {
            return null;
        }
        $json = file_get_contents($file);
        return $json === '' ? null : json_decode($json, true);
    }
    public function write($path, $data) {
        $file = $this->base . '/' . ltrim($path, '/');
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    }
}
PHP);

    file_put_contents($cmsRoot . '/core/plugins.php', <<<'PHP'
<?php
$GLOBALS['birkly_test_hooks'] = [];
function register_hook($hook, $callback) {
    $GLOBALS['birkly_test_hooks'][$hook][] = $callback;
}
function do_action($hook, ...$args) {
    foreach ($GLOBALS['birkly_test_hooks'][$hook] ?? [] as $callback) {
        $callback(...$args);
    }
}
function apply_filters($hook, $value) {
    return $value;
}
PHP);

    file_put_contents($cmsRoot . '/core/cache.php', <<<'PHP'
<?php
class Cache {
    private static array $items = [];
    public function get($key) { return self::$items[$key] ?? null; }
    public function set($key, $value, $ttl = null) { self::$items[$key] = $value; return true; }
    public function delete($key) { unset(self::$items[$key]); return true; }
}
PHP);
}

function copy_tree(string $source, string $dest, array $excludeNames = []): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $name = $item->getFilename();
        if (in_array($name, $excludeNames, true)) {
            continue;
        }

        $target = $dest . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0775, true);
            }
        } else {
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0775, true);
            }
            copy($item->getPathname(), $target);
        }
    }
}

function cleanup_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
    }
}

function assert_true($value, string $message): void
{
    if ($value !== true) {
        throw new RuntimeException($message);
    }
}

function assert_false($value, string $message): void
{
    if ($value !== false) {
        throw new RuntimeException($message);
    }
}
