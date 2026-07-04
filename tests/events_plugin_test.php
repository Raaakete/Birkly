<?php
/**
 * P6.3 — Events plugin scaffold tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Events plugin tests\n\n";

$root = dirname(__DIR__);
$pluginDir = $root . '/plugins/events';

test_assert('plugin.json exists', is_file($pluginDir . '/plugin.json'));
test_assert('plugin.php exists', is_file($pluginDir . '/plugin.php'));
test_assert('RegistrationService.php exists', is_file($pluginDir . '/includes/RegistrationService.php'));
test_assert('events admin html exists', is_file($pluginDir . '/admin/events.html'));
test_assert('events api exists', is_file($pluginDir . '/api/events.php'));
test_assert('events seed exists', is_file($pluginDir . '/collections/events/collection.json'));

$metadata = json_decode(file_get_contents($pluginDir . '/plugin.json'), true);
test_assert('plugin.json parses', is_array($metadata));
test_assert('navigation page is events', ($metadata['navigation']['page'] ?? '') === 'events');
test_assert('payments dependency optional', ($metadata['dependencies']['plugins'][0]['required'] ?? true) === false);

$pluginPhp = file_get_contents($pluginDir . '/plugin.php');
test_assert('registers events API route', strpos($pluginPhp, "register_api_endpoint('events'") !== false);
test_assert('upcoming_events template function', strpos($pluginPhp, "register_template_function('upcoming_events'") !== false);

$initPhp = file_get_contents($pluginDir . '/init.php');
test_assert('event_rsvp automation trigger', strpos($initPhp, "'event_rsvp'") !== false);
test_assert('payment event handlers registered', strpos($initPhp, 'registerPaymentEventHandlers') !== false);

exit(test_finish('Events plugin tests'));
