<?php
/**
 * P6.3 — Events RSVP + paid ticket shelf consumer tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Events shelf tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/events/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-events-shelf-' . getmypid();
$regsDir = $dataDir . '/registrations';
$contentDir = $dataDir . '/content';
@mkdir($regsDir, 0755, true);
@mkdir($contentDir . '/collections/events/entries', 0755, true);

file_put_contents($contentDir . '/collections/events/collection.json', json_encode([
    'id' => 'col-events', 'slug' => 'events', 'name' => 'Events',
], JSON_PRETTY_PRINT));
file_put_contents($contentDir . '/collections/events/entries/ev-1.json', json_encode([
    'id' => 'ev-1', 'title' => 'Test Event', 'slug' => 'test-event', 'capacity' => 2,
    'ticket_types' => json_encode([
        ['id' => 'free', 'name' => 'Free', 'price' => 0, 'currency' => 'usd', 'capacity' => 0],
        ['id' => 'vip', 'name' => 'VIP', 'price' => 25, 'currency' => 'usd', 'capacity' => 10],
    ]),
    'status' => 'published',
], JSON_PRETTY_PRINT));

require_once $root . '/plugins/events/includes/EventCatalog.php';
require_once $root . '/plugins/events/includes/RegistrationStore.php';
require_once $root . '/plugins/events/includes/CapacityService.php';
require_once $root . '/plugins/events/includes/RegistrationService.php';

class TestEventCatalog extends EventsCatalog
{
    private string $contentRoot;
    public function __construct(string $contentRoot) { $this->contentRoot = $contentRoot; }
    public function getEvent(string $eventId): ?array
    {
        require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__) . '/core/public_read.php';
        $storage = StorageFactory::createStorage($this->contentRoot);
        $result = birkly_public_get_entry($storage, 'events', '', $eventId);
        return ($result['status'] ?? '') === 'ok' ? ($result['data']['entry'] ?? null) : null;
    }
}

events_bootstrap();

$regs = new EventsRegistrationStore($regsDir);
$catalog = new TestEventCatalog($contentDir);
$capacity = new EventsCapacityService($regs);
$service = new EventsRegistrationService($catalog, $regs, $capacity);

$free = $service->registerFree('ev-1', 'Alice', 'alice@example.com');
test_assert('free RSVP ok', ($free['ok'] ?? false) === true);
test_assert('free registration confirmed', ($free['registration']['status'] ?? '') === 'confirmed');

$free2 = $service->registerFree('ev-1', 'Bob', 'bob@example.com');
test_assert('second RSVP ok', ($free2['ok'] ?? false) === true);

$full = $service->registerFree('ev-1', 'Charlie', 'charlie@example.com');
test_assert('capacity blocks third RSVP', ($full['ok'] ?? false) === false);

require_once $root . '/plugins/payments/includes/ProviderInterface.php';
require_once $root . '/plugins/payments/includes/AbstractProvider.php';
require_once $root . '/plugins/payments/includes/CredentialStore.php';
require_once $root . '/plugins/payments/includes/ActivityLog.php';
require_once $root . '/plugins/payments/includes/ProviderResolver.php';
require_once $root . '/plugins/payments/includes/PaymentShelf.php';
require_once $root . '/plugins/payments/includes/providers/StripeProvider.php';

$payDir = $dataDir . '/payments';
foreach (['/connections', '/activity', '/webhooks'] as $sub) {
    @mkdir($payDir . $sub, 0755, true);
}
file_put_contents($payDir . '/activity/log.json', '[]');
file_put_contents($payDir . '/encryption_key.txt', base64_encode(hash('sha256', 'test-key', true)));

class TestEventsPaymentsStore extends PaymentsCredentialStore
{
    public function __construct(string $testDir)
    {
        foreach (['/connections', '/activity', '/webhooks'] as $sub) {
            if (!is_dir($testDir . $sub)) {
                mkdir($testDir . $sub, 0755, true);
            }
        }
        if (!is_file($testDir . '/activity/log.json')) {
            file_put_contents($testDir . '/activity/log.json', '[]');
        }
        $keyFile = $testDir . '/encryption_key.txt';
        if (!is_file($keyFile)) {
            file_put_contents($keyFile, base64_encode(hash('sha256', 'test-key', true)));
        }
        $ref = new ReflectionClass(PaymentsCredentialStore::class);
        $baseProp = $ref->getProperty('baseDir');
        $baseProp->setAccessible(true);
        $baseProp->setValue($this, $testDir);
        $keyProp = $ref->getProperty('encryptionKey');
        $keyProp->setAccessible(true);
        $keyProp->setValue($this, trim(file_get_contents($keyFile)));
    }
}

$store = new TestEventsPaymentsStore($payDir);
$activityLog = new PaymentsActivityLog($payDir);
$resolver = new PaymentsProviderResolver($store);
$resolver->register(new StripeProvider());
$shelf = new PaymentShelf($resolver, $store, $activityLog);
$store->saveConnection([
    'provider' => 'stripe', 'label' => 'Mock', 'mode' => 'test', 'enabled' => true,
    'credentials' => ['mock' => true, 'secret_key' => 'sk_test'],
]);

require_once $root . '/core/plugins/services.php';
require_once $root . '/core/plugins/capabilities.php';

global $haru_plugin_services;
$haru_plugin_services['payment_shelf'] = [
    'service' => $shelf,
    'plugin_id' => 'payments',
    'version' => '1.0.0',
    'capability' => 'access_payment_shelf',
    'scope' => 'global',
];

require_once $root . '/core/plugins/permissions.php';
grant_plugin_capability('events', 'access_payment_shelf', 'global');

$contentDir2 = $dataDir . '/content2';
@mkdir($contentDir2 . '/collections/events/entries', 0755, true);
file_put_contents($contentDir2 . '/collections/events/collection.json', json_encode([
    'id' => 'col-events', 'slug' => 'events', 'name' => 'Events',
], JSON_PRETTY_PRINT));
file_put_contents($contentDir2 . '/collections/events/entries/ev-2.json', json_encode([
    'id' => 'ev-2', 'title' => 'Paid Event', 'capacity' => 0,
    'ticket_types' => json_encode([
        ['id' => 'vip', 'name' => 'VIP', 'price' => 25, 'currency' => 'usd', 'capacity' => 0],
    ]),
    'status' => 'published',
], JSON_PRETTY_PRINT));

$regs2 = new EventsRegistrationStore($dataDir . '/registrations2');
$catalog2 = new TestEventCatalog($contentDir2);
$service2 = new EventsRegistrationService($catalog2, $regs2, new EventsCapacityService($regs2));

$paid = $service2->startPaidRegistration('ev-2', 'vip', 'Dan', 'dan@example.com');
test_assert('paid registration started', ($paid['ok'] ?? false) === true, $paid['error'] ?? json_encode($paid));
test_assert('paid registration_id', !empty($paid['registration_id']));

$confirmed = $service2->confirmPaidByPaymentId($paid['payment_id']);
test_assert('registration marked paid', ($confirmed['status'] ?? '') === 'paid');

$log = $activityLog->list([], 50);
$foundEvents = false;
foreach ($log as $entry) {
    if (($entry['source_plugin'] ?? '') === 'events' && ($entry['source_ref'] ?? '') === $paid['registration_id']) {
        $foundEvents = true;
        break;
    }
}
test_assert('activity log source_plugin events', $foundEvents);

exit(test_finish('Events shelf tests'));
