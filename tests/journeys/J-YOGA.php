<?php
/**
 * J-YOGA — Events visitor (events plugin + yoga reference project).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-YOGA',
    'name' => 'Yoga events visitor',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        journey_install_dev_plugins();
        journey_activate_plugins('events');
        journey_install_reference_project('yoga');

        $prefix = '/reference-projects/yoga/website';
        $index = journey_http($base, $prefix . '/index.html');
        journey_assert('yoga index HTTP 200', $index['code'] === 200);
        journey_assert('yoga index has birkly-client.js', strpos($index['body'], 'birkly-client.js') !== false);
        journey_assert('yoga index references upcoming_events', strpos($index['body'], 'upcoming_events') !== false);

        $schedule = journey_http($base, $prefix . '/schedule.html');
        journey_assert('schedule HTTP 200', $schedule['code'] === 200);

        $event = journey_http($base, $prefix . '/event.html?slug=community-meetup');
        journey_assert('event detail HTTP 200', $event['code'] === 200);
        journey_assert('event detail has birkly-client.js', strpos($event['body'], 'birkly-client.js') !== false);

        $bundle = journey_page_bundle($base, [
            ['type' => 'template_function', 'into' => 'upcoming', 'name' => 'upcoming_events', 'args' => [6]],
        ]);
        journey_assert('page_bundle upcoming_events ok', ($bundle['status'] ?? '') === 'ok');
        $upcoming = $bundle['data']['upcoming'] ?? [];
        journey_assert('upcoming events returned', is_array($upcoming) && count($upcoming) > 0);
    },
];
