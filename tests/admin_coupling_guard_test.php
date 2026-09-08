<?php
/**
 * P83 — Admin coupling guard (dashboard mounts + notification literals).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function guard_fail(string $label): void
{
    global $failures;
    $failures[] = $label;
    fwrite(STDERR, "FAIL: $label\n");
}

function guard_file(string $relativePath, array $forbiddenPatterns): void
{
    global $root;
    $path = $root . '/' . ltrim($relativePath, '/');
    if (!is_file($path)) {
        guard_fail("missing file $relativePath");
        return;
    }
    $contents = (string) file_get_contents($path);
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $contents)) {
            guard_fail("$relativePath must not match $pattern");
        }
    }
}

guard_file('admin/js/dashboard.js', [
    '/commerceStats/',
    '/eventsUpcoming/',
    '/studioPublishing/',
    '/automationRuns/',
    '/function renderCommerce/',
    '/function renderEvents/',
    '/function renderStudio/',
    '/function renderAutomation/',
]);

guard_file('core/notification_broadcast.php', [
    "/'commerce'/",
    "/'events'/",
    "/'automation'/",
    "/'studio'/",
    "/'user_management'/",
    "/'commerce_'/",
    "/'events_'/",
]);

guard_file('api/notifications.php', [
    "/case 'commerce_order_created'/",
    "/case 'commerce_order_paid'/",
    "/case 'commerce_subscription_created'/",
]);

guard_file('birkly-webmcp.js', [
    '/registerCommercePack/',
    '/registerEventsPack/',
    '/registerUserPack/',
]);

guard_file('admin/js/ai-presence.js', [
    '/WEBMCP_TOOL_META/',
    '/WEBMCP_PACK_META/',
    '/birkly_commerce_get_cart/',
    '/birkly_events_list/',
    '/birkly_user_session/',
]);

guard_file('admin/index.php', [
    "/'automation'/",
    '/"automation"/',
    '/\$page === \'automation\'/',
    '/\$page !== \'automation\'/',
]);

guard_file('api/plugin_activity.php', [
    "/\\['plugin'\\].*'commerce'/",
    '/\\$input\\[\'plugin\'\\].*\'commerce\'/',
]);

if ($failures !== []) {
    exit(1);
}

echo "admin_coupling_guard_test: OK\n";
