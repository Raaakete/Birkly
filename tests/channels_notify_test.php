<?php
/**
 * Notify channel tests (Phase 65 M6): Slack, Discord, Twilio SMS.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-channels-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

require_once dirname(__DIR__) . '/core/connections/registry.php';
require_once dirname(__DIR__) . '/core/mail/channels.php';
require_once dirname(__DIR__) . '/core/mail/notify.php';

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

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}

$captured = [];
$GLOBALS['__birkly_http_transport'] = function ($url, $method, $body, $headers, $timeout) use (&$captured) {
    $captured[] = ['url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers, 'timeout' => $timeout];
    return ['status' => 'accepted', 'http_code' => 200, 'response' => 'ok', 'error' => null];
};

birkly_connections_save(['connections' => []]);

$slack = birkly_connection_create([
    'type' => 'channel',
    'provider' => 'slack',
    'alias' => 'team_alerts',
    'name' => 'Team Alerts',
    'config' => ['channel' => '#alerts'],
    'secrets' => ['webhook_url' => 'https://hooks.slack.com/services/XXX/YYY/ZZZ'],
]);
assert_true('slack connection created', $slack['ok'] === true);

$result = birkly_channel_dispatch(['subject' => 'Alert', 'text' => 'Something happened.'], birkly_connection_get_by_alias('team_alerts', true));
assert_true('slack dispatch accepted', $result['status'] === 'accepted');
assert_true('slack request captured', count($captured) === 1);
$slackBody = json_decode($captured[0]['body'], true);
assert_true('slack body contains text', is_array($slackBody) && ($slackBody['text'] ?? '') === 'Something happened.');
assert_true('slack body contains channel', ($slackBody['channel'] ?? '') === '#alerts');

$discord = birkly_connection_create([
    'type' => 'channel',
    'provider' => 'discord',
    'alias' => 'discord_alerts',
    'name' => 'Discord Alerts',
    'config' => [],
    'secrets' => ['webhook_url' => 'https://discord.com/api/webhooks/123/token'],
]);
assert_true('discord connection created', $discord['ok'] === true);

$captured = [];
$result = birkly_channel_dispatch(['subject' => 'Discord alert', 'html' => '<p>Test</p>'], birkly_connection_get_by_alias('discord_alerts', true));
assert_true('discord dispatch accepted', $result['status'] === 'accepted');
$discordBody = json_decode($captured[0]['body'], true);
assert_true('discord body contains content', is_array($discordBody) && strpos(($discordBody['content'] ?? ''), 'Discord alert') !== false);

$twilio = birkly_connection_create([
    'type' => 'channel',
    'provider' => 'twilio_sms',
    'alias' => 'sms_alerts',
    'name' => 'SMS Alerts',
    'config' => ['from_number' => '+15551234567'],
    'secrets' => ['account_sid' => 'AC123', 'auth_token' => 'token'],
]);
assert_true('twilio connection created', $twilio['ok'] === true);

$captured = [];
$result = birkly_channel_dispatch(['to_number' => '+15557654321', 'text' => 'Hello'], birkly_connection_get_by_alias('sms_alerts', true));
assert_true('twilio dispatch accepted', $result['status'] === 'accepted');
assert_true('twilio url contains account sid', strpos($captured[0]['url'], 'AC123') !== false);
assert_true('twilio authorization basic', count(array_filter($captured[0]['headers'], fn($h) => stripos($h, 'Authorization: Basic') === 0)) === 1);
parse_str($captured[0]['body'], $twilioForm);
assert_true('twilio form has To', ($twilioForm['To'] ?? '') === '+15557654321');
assert_true('twilio form has Body', ($twilioForm['Body'] ?? '') === 'Hello');

// birkly_notify fan-out includes channel.
$captured = [];
$results = birkly_notify('team_alerts', ['subject' => 'Notify fan-out', 'text' => 'via notify']);
assert_true('notify fan-out includes slack result', isset($results['team_alerts']));
assert_true('notify slack accepted', $results['team_alerts']['status'] === 'accepted');
assert_true('notify captured slack request', count($captured) === 1);

rrmdir($testRoot);

echo "\nchannels_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
