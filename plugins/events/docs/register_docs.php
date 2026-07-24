<?php
if (!function_exists('register_plugin_docs') || (function_exists('get_plugin_docs') && get_plugin_docs('events') !== null)) {
    return;
}
if (!function_exists('events_plugin_active')) {
    require_once dirname(__DIR__) . '/init.php';
}
if (!events_plugin_active()) {
    return;
}
register_plugin_docs('events', [
    'title' => 'Events',
    'summary' => 'Event fieldtype on any collection, EventIndexer with recurrence, iCal/webcal feeds, Commerce register fulfillment, legacy events collection coexistence.',
    'badge' => 'Extension',
    'docs' => [
        'ai' => __DIR__ . '/ai-context.md',
        'help' => __DIR__ . '/help-overview.md',
        'help_topics' => [
            ['id' => 'events-rsvp', 'title' => 'RSVP & capacity', 'file' => __DIR__ . '/help-rsvp.md', 'keywords' => ['rsvp', 'capacity', 'registration']],
            ['id' => 'events-tickets', 'title' => 'Paid tickets', 'file' => __DIR__ . '/help-tickets.md', 'keywords' => ['ticket', 'paid', 'payment']],
            ['id' => 'events-fieldtype', 'title' => 'Event fieldtype', 'file' => __DIR__ . '/help-fieldtype.md', 'keywords' => ['fieldtype', 'recurrence', 'calendar', 'ics']],
        ],
        'public_path' => '06-extensibility/plugins.html',
    ],
    'mcp_topic_slug' => 'events',
    'keywords' => ['events', 'rsvp', 'ticket', 'registration', 'capacity'],
]);
