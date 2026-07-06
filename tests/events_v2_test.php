<?php
/**
 * P7-M5 — Events v2: fieldtype, indexer, recurrence, iCal, API.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Events v2 tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/events/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-events-v2-' . getmypid();
@mkdir($dataDir, 0755, true);

require_once $root . '/plugins/events/includes/EventSchema.php';
require_once $root . '/plugins/events/includes/EventRecurrence.php';
require_once $root . '/plugins/events/includes/EventIndexer.php';
require_once $root . '/plugins/events/includes/IcsGenerator.php';
require_once $root . '/plugins/events/includes/AttendeeService.php';
require_once $root . '/plugins/events/includes/RegistrationStore.php';

$defaults = EventSchema::defaultValue();
test_assert('EventSchema default has starts_at', array_key_exists('starts_at', $defaults));
test_assert('EventSchema default recurrence disabled', ($defaults['recurrence']['enabled'] ?? true) === false);

$normalized = event_normalize_value([
    'starts_at' => '2026-07-10T18:00:00',
    'ends_at' => '2026-07-10T20:00:00',
    'recurrence' => ['enabled' => true, 'frequency' => 'weekly', 'interval' => 1, 'count' => 4],
]);
test_assert('normalize recurrence count', ($normalized['recurrence']['count'] ?? 0) === 4);

$invalid = EventSchema::validate(['starts_at' => '2026-07-10', 'ends_at' => '2026-07-09'], true);
test_assert('validate end before start fails', ($invalid['valid'] ?? true) === false);

$recInvalid = EventSchema::validate([
    'starts_at' => '2026-07-10T18:00:00',
    'recurrence' => ['enabled' => true, 'frequency' => 'daily', 'interval' => 1],
], true);
test_assert('recurrence without until/count fails', ($recInvalid['valid'] ?? true) === false);

$single = EventRecurrence::expandOccurrences([
    'starts_at' => '2026-07-10T18:00:00',
    'ends_at' => '2026-07-10T20:00:00',
    'timezone' => 'UTC',
    'recurrence' => ['enabled' => false],
], 'workshops:evt-1');
test_assert('single occurrence when recurrence off', count($single) === 1);

$series = EventRecurrence::expandOccurrences([
    'starts_at' => '2026-07-01T10:00:00',
    'ends_at' => '2026-07-01T11:00:00',
    'timezone' => 'UTC',
    'recurrence' => [
        'enabled' => true,
        'frequency' => 'weekly',
        'interval' => 1,
        'count' => 3,
        'skip_dates' => ['2026-07-08'],
    ],
], 'workshops:evt-2');
test_assert('weekly series produces occurrences', count($series) === 2);
test_assert('skip_dates respected', strpos(json_encode($series), '2026-07-08') === false);

$indexer = new EventIndexer($dataDir);
$entry = [
    'id' => 'evt-100',
    'slug' => 'summer-workshop',
    'title' => 'Summer Workshop',
    'status' => 'published',
    'event' => [
        'starts_at' => '2026-08-01T14:00:00',
        'ends_at' => '2026-08-01T16:00:00',
        'timezone' => 'UTC',
        'venue' => 'Main Hall',
        'recurrence' => ['enabled' => false],
    ],
];
$indexer->indexEntry($entry, 'workshops');
$index = $indexer->loadIndex();
test_assert('indexer stores entry', isset($index['entries']['workshops:evt-100']));
test_assert('indexer occurrences', count($index['entries']['workshops:evt-100']['occurrences'] ?? []) >= 1);

$indexer->removeEntry('evt-100', 'workshops');
test_assert('indexer removes entry', !isset($indexer->loadIndex()['entries']['workshops:evt-100']));

$indexer->indexEntry($entry, 'workshops');
test_assert('listOccurrences finds August event', count($indexer->listOccurrences('2026-08-01', '2026-08-31')) >= 1);

$icsEvent = IcsGenerator::buildEvent(
    ['entry_title' => 'Test', 'venue' => 'Hall A'],
    ['occurrence_id' => 'workshops:evt-100:0', 'starts_at' => '2026-08-01T14:00:00+00:00', 'ends_at' => '2026-08-01T16:00:00+00:00']
);
test_assert('ICS contains VEVENT', strpos($icsEvent, 'BEGIN:VEVENT') !== false);
test_assert('ICS contains SUMMARY', strpos($icsEvent, 'SUMMARY:Test') !== false);

$legacyRegs = new EventsRegistrationStore($dataDir . '/legacy_regs');
$legacyRegs->create([
    'id' => 'reg-1', 'event_id' => 'ev-1', 'name' => 'Alice', 'email' => 'a@example.com',
    'ticket_type' => 'free', 'status' => 'confirmed', 'amount_cents' => 0,
]);
$merged = (new AttendeeService($legacyRegs))->listAll();
test_assert('AttendeeService includes legacy', count(array_filter($merged, fn ($r) => ($r['source'] ?? '') === 'legacy')) >= 1);

test_assert('Event fieldtype class exists', is_file($root . '/plugins/events/fieldtypes/Event/Event.php'));

$pluginPhp = file_get_contents($root . '/plugins/events/plugin.php');
test_assert('plugin registers fieldtype hook', strpos($pluginPhp, 'events_register_fieldtype') !== false);
test_assert('plugin after_entry_save hook', strpos($pluginPhp, 'after_entry_save') !== false);
test_assert('events_calendar_feed_url template', strpos($pluginPhp, 'events_calendar_feed_url') !== false);

$initPhp = file_get_contents($root . '/plugins/events/init.php');
test_assert('events_message_attendees trigger', strpos($initPhp, 'events_message_attendees') !== false);

$apiPhp = file_get_contents($root . '/plugins/events/api/events.php');
test_assert('API ics action', strpos($apiPhp, "case 'ics'") !== false);
test_assert('API webcal action', strpos($apiPhp, "case 'webcal'") !== false);
test_assert('API list_occurrences', strpos($apiPhp, "case 'list_occurrences'") !== false);

$adminHtml = file_get_contents($root . '/plugins/events/admin/events.html');
test_assert('admin Overview tab', strpos($adminHtml, 'data-tab="overview"') !== false);
test_assert('admin Calendar tab', strpos($adminHtml, 'data-tab="calendar"') !== false);
test_assert('admin Attendees tab', strpos($adminHtml, 'data-tab="attendees"') !== false);
test_assert('admin Messages tab', strpos($adminHtml, 'data-tab="messages"') !== false);
test_assert('admin uses plugin_page_header', strpos($adminHtml, '{plugin_page_header}') !== false);

@unlink($dataDir . '/events_index.json');
@unlink($dataDir . '/legacy_regs/reg-1.json');

exit(test_finish('Events v2 tests'));
