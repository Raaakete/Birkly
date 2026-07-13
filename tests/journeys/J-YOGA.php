<?php
/**
 * J-YOGA — Events visitor: upcoming list → event detail → RSVP registration.
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-YOGA',
    'name' => 'Yoga events visitor',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        journey_prepare_reference_demo('yoga');

        $prefix = '/reference-projects/yoga/website';
        $index = journey_http($base, $prefix . '/index.html');
        journey_assert('yoga index HTTP 200', $index['code'] === 200);
        journey_assert('yoga index has birkly-client.js', strpos($index['body'], 'birkly-client.js') !== false);
        journey_assert('yoga index references upcoming_events', strpos($index['body'], 'upcoming_events') !== false);

        $bundle = journey_page_bundle($base, [
            ['type' => 'template_function', 'into' => 'upcoming', 'name' => 'upcoming_events', 'args' => [6]],
        ]);
        journey_assert('page_bundle upcoming_events ok', ($bundle['status'] ?? '') === 'ok');
        $upcoming = $bundle['data']['upcoming'] ?? [];
        journey_assert('upcoming events returned', is_array($upcoming) && count($upcoming) > 0);
        $firstSlug = (string) ($upcoming[0]['slug'] ?? 'sunrise-vinyasa');
        journey_assert('upcoming includes sunrise-vinyasa', in_array('sunrise-vinyasa', array_column($upcoming, 'slug'), true));

        $event = journey_http($base, $prefix . '/event.html?slug=' . urlencode($firstSlug));
        journey_assert('event detail HTTP 200', $event['code'] === 200);
        journey_assert('event detail has RSVP form', strpos($event['body'], 'data-birkly-action="rsvp"') !== false);

        $eventId = (string) ($upcoming[0]['slug'] ?? 'sunrise-vinyasa');
        $rsvp = journey_http($base, '/api/index.php?route=events&action=rsvp', [
            'method' => 'POST',
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body' => http_build_query([
                'event_id' => $eventId,
                'name' => 'Journey Yoga Guest',
                'email' => 'journey-yoga-' . getmypid() . '@birkly.test',
                'ticket_type' => 'free',
            ]),
        ]);
        $rsvpData = birkly_test_json_decode($rsvp['body']);
        journey_assert('event RSVP HTTP 200', $rsvp['code'] === 200, $rsvp['body']);
        journey_assert('event RSVP status ok', ($rsvpData['status'] ?? '') === 'ok', $rsvp['body']);
        journey_assert('event RSVP registration returned', !empty($rsvpData['data']['registration'] ?? null));
    },
];
