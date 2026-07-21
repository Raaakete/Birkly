<?php
/**
 * Events plugin bootstrap — fieldtype, indexer, registrations, calendar feeds.
 */

require_once __DIR__ . '/init.php';

if (events_plugin_active()) {
    events_bootstrap();
}

if (function_exists('register_api_endpoint')) {
    register_api_endpoint('events', __DIR__ . '/api/events.php');
}

if (function_exists('register_admin_page')) {
    register_admin_page('events', __DIR__ . '/admin/events.html');
}

if (function_exists('register_plugin_fieldtype') && events_plugin_active()) {
    events_register_fieldtype();
}

if (function_exists('register_plugin_admin_meta')) {
    register_plugin_admin_meta('events', [
        'title' => 'Events',
        'root_page' => 'events',
        'permission' => 'manage_events',
        'page_slugs' => ['events'],
        'sidebar' => [
            'show' => true,
            'label' => 'Events',
            'order' => 30,
            'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
        ],
    ]);
}

if (function_exists('register_hook')) {
    register_hook('plugin_activated', function ($plugin_id) {
        if ($plugin_id !== 'events') {
            return;
        }
        events_seed_collection();
        if (function_exists('grant_plugin_capability')) {
            grant_plugin_capability('events', 'access_payment_shelf', 'global');
        }
        events_bootstrap();
        $indexer = new EventIndexer();
        $indexer->reindexAll();
    });

    register_hook('automation_blocks_register', function () {
        if (events_plugin_active()) {
            events_init_automation_triggers();
        }
    });

    register_hook('after_entry_save', function ($entry, $collection, $oldEntry = null) {
        if (!events_plugin_active()) {
            return;
        }
        events_bootstrap();
        $indexer = new EventIndexer();
        $collectionSlug = is_string($collection) ? $collection : ($collection['slug'] ?? '');
        $indexer->indexEntry(is_array($entry) ? $entry : [], $collectionSlug);
    });

    register_hook('after_entry_delete', function ($entry, $collection) {
        if (!events_plugin_active()) {
            return;
        }
        events_bootstrap();
        $indexer = new EventIndexer();
        $entryId = is_array($entry) ? ($entry['id'] ?? '') : (string) $entry;
        $collectionSlug = is_string($collection) ? $collection : ($collection['slug'] ?? '');
        $indexer->removeEntry($entryId, $collectionSlug);
    });
}

if (function_exists('automation_bootstrap') && automation_bootstrap()) {
    events_init_automation_triggers();
} elseif (function_exists('register_automation_block') && events_plugin_active()) {
    events_init_automation_triggers();
}

if (function_exists('register_template_function') && events_plugin_active()) {
    register_template_function('upcoming_events', function ($limit = 10) {
        events_bootstrap();
        return events_upcoming((int) $limit);
    });

    register_template_function('event_register_url', function ($eventId = '') {
        $base = function_exists('birkly_url')
            ? birkly_url('/api/index.php?route=events&action=rsvp')
            : '/api/index.php?route=events&action=rsvp';
        return $eventId !== '' ? $base . '&event_id=' . urlencode($eventId) : $base;
    });

    register_template_function('event_ticket_checkout_url', function ($eventId = '', $ticketType = '') {
        $base = function_exists('birkly_url')
            ? birkly_url('/api/index.php?route=events&action=paid_register')
            : '/api/index.php?route=events&action=paid_register';
        if ($eventId !== '') {
            $base .= '&event_id=' . urlencode($eventId);
        }
        if ($ticketType !== '') {
            $base .= '&ticket_type=' . urlencode($ticketType);
        }
        return $base;
    });

    register_template_function('events_calendar_feed_url', function () {
        return events_calendar_feed_url();
    });

    register_template_function('event_ics_url', function ($collectionSlug = '', $entryId = '', $occurrenceIndex = '') {
        if ($collectionSlug === '' || $entryId === '') {
            return '';
        }
        $idx = $occurrenceIndex !== '' ? (int) $occurrenceIndex : null;
        return events_ics_url((string) $collectionSlug, (string) $entryId, $idx);
    });
}

require __DIR__ . '/docs/register_docs.php';

global $birkly_plugins;
if (isset($birkly_plugins['events'])) {
    $birkly_plugins['events']['dashboard_widgets_callback'] = 'events_dashboard_widgets';
}
