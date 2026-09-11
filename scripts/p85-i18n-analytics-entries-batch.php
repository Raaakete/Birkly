<?php
/**
 * P85 slice 6 — legacy analytics.js, entries list toasts/progress, hosting wizard gaps (EN/DE).
 * Usage: php scripts/p85-i18n-analytics-entries-batch.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);

$newEn = [
    'analytics_legacy_loading' => 'Loading…',
    'analytics_legacy_load_error' => 'Error loading tracking configurations',
    'analytics_legacy_empty' => 'No websites are being tracked yet.',
    'analytics_legacy_add_first' => 'Add your first website',
    'analytics_legacy_enabled' => 'Enabled',
    'analytics_legacy_disabled' => 'Disabled',
    'analytics_legacy_tracking_code' => 'Tracking code',
    'analytics_legacy_copy_code' => 'Copy code',
    'analytics_legacy_snippet_help' => 'Add this code to your website before the closing </body> tag',
    'analytics_legacy_disable' => 'Disable',
    'analytics_legacy_enable' => 'Enable',
    'analytics_legacy_delete' => 'Delete',
    'analytics_legacy_delete_confirm' => 'Are you sure you want to delete this tracking configuration? Analytics will stop for this website.',
    'analytics_legacy_update_failed' => 'Failed to update tracking',
    'analytics_legacy_update_error' => 'Error updating tracking configuration',
    'analytics_legacy_delete_failed' => 'Failed to delete tracking',
    'analytics_legacy_delete_error' => 'Error deleting tracking configuration',
    'analytics_legacy_create_failed' => 'Failed to create tracking',
    'analytics_legacy_create_error' => 'Error creating tracking configuration',
    'analytics_legacy_fill_fields' => 'Please fill in all fields',
    'analytics_legacy_error_prefix' => 'Error: {message}',
    'entries_error_prefix' => 'Error: {message}',
    'entries_invalid_json' => 'Invalid JSON response',
    'entries_bulk_updating_fields' => 'Updating field values…',
    'entries_bulk_field_updated' => 'Field updated successfully.',
    'entries_moving_to_trash' => 'Moving entry to trash…',
    'entries_restoring_entry' => 'Restoring entry…',
    'entries_duplicating_entry' => 'Duplicating entry…',
    'entries_bulk_publish_confirm' => 'Publish the selected entries?',
    'entries_bulk_publish_progress' => 'Publishing entries…',
    'entries_bulk_published' => 'Published %count% entries.',
    'entries_bulk_published_one' => 'Published 1 entry.',
    'entries_bulk_unpublish_confirm' => 'Unpublish the selected entries?',
    'entries_bulk_unpublish_progress' => 'Unpublishing entries…',
    'entries_bulk_unpublished' => 'Unpublished %count% entries.',
    'entries_bulk_unpublished_one' => 'Unpublished 1 entry.',
    'entries_bulk_trash_confirm' => 'Move the selected entries to trash?',
    'entries_bulk_trash_progress' => 'Moving entries to trash…',
    'entries_bulk_trashed' => 'Moved %count% entries to trash.',
    'entries_bulk_trashed_one' => 'Moved 1 entry to trash.',
    'entries_bulk_restore_confirm' => 'Restore the selected entries?',
    'entries_bulk_restore_progress' => 'Restoring entries…',
    'entries_bulk_restored' => 'Restored %count% entries.',
    'entries_bulk_restored_one' => 'Restored 1 entry.',
    'entries_publishing_entry' => 'Publishing entry…',
    'entries_unpublishing_entry' => 'Unpublishing entry…',
    'entries_publish_confirm' => 'Publish this entry?',
    'entries_unpublish_confirm' => 'Unpublish this entry?',
    'entries_load_failed' => 'Failed to load entries',
    'entries_save_order_failed' => 'Error saving entry order: {message}',
    'entries_assigned_to' => 'Assigned to: {name}',
    'entries_reviewed_by' => 'Reviewed by: {name}',
    'entries_sub_entries_count' => '{count} sub-entries',
    'hosting_wizard_proposal_title' => 'Suggested delivery setup',
    'hosting_wizard_proposal_desc' => 'Review detected settings before applying. Nothing is saved until you confirm.',
    'hosting_wizard_apply_proposal' => 'Apply suggestion',
    'hosting_wizard_dismiss_proposal' => 'Dismiss',
    'hosting_wizard_proposal_applied' => 'Suggested delivery setup applied',
    'hosting_wizard_proposal_apply_failed' => 'Failed to apply suggestion',
    'hosting_wizard_load_failed' => 'Failed to load hosting',
    'hosting_wizard_public_url_no_project' => 'Public URL must not include /project — use the hostname only.',
    'hosting_wizard_public_url_help_extra' => 'Do not include /project — use the hostname only (e.g. https://example.com).',
    'hosting_wizard_checklist_done' => 'Done: {item}',
    'hosting_wizard_checklist_todo' => 'Todo: {item}',
];

$newDe = [
    'analytics_legacy_loading' => 'Wird geladen…',
    'analytics_legacy_load_error' => 'Tracking-Konfigurationen konnten nicht geladen werden',
    'analytics_legacy_empty' => 'Es werden noch keine Websites getrackt.',
    'analytics_legacy_add_first' => 'Erste Website hinzufügen',
    'analytics_legacy_enabled' => 'Aktiv',
    'analytics_legacy_disabled' => 'Inaktiv',
    'analytics_legacy_tracking_code' => 'Tracking-Code',
    'analytics_legacy_copy_code' => 'Code kopieren',
    'analytics_legacy_snippet_help' => 'Fügen Sie diesen Code vor dem schließenden </body>-Tag Ihrer Website ein',
    'analytics_legacy_disable' => 'Deaktivieren',
    'analytics_legacy_enable' => 'Aktivieren',
    'analytics_legacy_delete' => 'Löschen',
    'analytics_legacy_delete_confirm' => 'Diese Tracking-Konfiguration wirklich löschen? Für diese Website werden keine Analytics mehr erfasst.',
    'analytics_legacy_update_failed' => 'Tracking konnte nicht aktualisiert werden',
    'analytics_legacy_update_error' => 'Fehler beim Aktualisieren der Tracking-Konfiguration',
    'analytics_legacy_delete_failed' => 'Tracking konnte nicht gelöscht werden',
    'analytics_legacy_delete_error' => 'Fehler beim Löschen der Tracking-Konfiguration',
    'analytics_legacy_create_failed' => 'Tracking konnte nicht angelegt werden',
    'analytics_legacy_create_error' => 'Fehler beim Anlegen der Tracking-Konfiguration',
    'analytics_legacy_fill_fields' => 'Bitte füllen Sie alle Felder aus',
    'analytics_legacy_error_prefix' => 'Fehler: {message}',
    'entries_error_prefix' => 'Fehler: {message}',
    'entries_invalid_json' => 'Ungültige JSON-Antwort',
    'entries_bulk_updating_fields' => 'Feldwerte werden aktualisiert…',
    'entries_bulk_field_updated' => 'Feld erfolgreich aktualisiert.',
    'entries_moving_to_trash' => 'Eintrag wird in den Papierkorb verschoben…',
    'entries_restoring_entry' => 'Eintrag wird wiederhergestellt…',
    'entries_duplicating_entry' => 'Eintrag wird dupliziert…',
    'entries_bulk_publish_confirm' => 'Ausgewählte Einträge veröffentlichen?',
    'entries_bulk_publish_progress' => 'Einträge werden veröffentlicht…',
    'entries_bulk_published' => '%count% Einträge veröffentlicht.',
    'entries_bulk_published_one' => '1 Eintrag veröffentlicht.',
    'entries_bulk_unpublish_confirm' => 'Veröffentlichung der ausgewählten Einträge zurücknehmen?',
    'entries_bulk_unpublish_progress' => 'Einträge werden depubliziert…',
    'entries_bulk_unpublished' => 'Veröffentlichung von %count% Einträgen zurückgenommen.',
    'entries_bulk_unpublished_one' => 'Veröffentlichung von 1 Eintrag zurückgenommen.',
    'entries_bulk_trash_confirm' => 'Ausgewählte Einträge in den Papierkorb verschieben?',
    'entries_bulk_trash_progress' => 'Einträge werden in den Papierkorb verschoben…',
    'entries_bulk_trashed' => '%count% Einträge in den Papierkorb verschoben.',
    'entries_bulk_trashed_one' => '1 Eintrag in den Papierkorb verschoben.',
    'entries_bulk_restore_confirm' => 'Ausgewählte Einträge wiederherstellen?',
    'entries_bulk_restore_progress' => 'Einträge werden wiederhergestellt…',
    'entries_bulk_restored' => '%count% Einträge wiederhergestellt.',
    'entries_bulk_restored_one' => '1 Eintrag wiederhergestellt.',
    'entries_publishing_entry' => 'Eintrag wird veröffentlicht…',
    'entries_unpublishing_entry' => 'Veröffentlichung wird zurückgenommen…',
    'entries_publish_confirm' => 'Diesen Eintrag veröffentlichen?',
    'entries_unpublish_confirm' => 'Veröffentlichung dieses Eintrags zurücknehmen?',
    'entries_load_failed' => 'Einträge konnten nicht geladen werden',
    'entries_save_order_failed' => 'Fehler beim Speichern der Reihenfolge: {message}',
    'entries_assigned_to' => 'Zugewiesen an: {name}',
    'entries_reviewed_by' => 'Geprüft von: {name}',
    'entries_sub_entries_count' => '{count} Untereinträge',
    'hosting_wizard_proposal_title' => 'Vorgeschlagenes Delivery-Setup',
    'hosting_wizard_proposal_desc' => 'Erkannte Einstellungen prüfen, bevor Sie sie übernehmen. Erst nach Bestätigung wird gespeichert.',
    'hosting_wizard_apply_proposal' => 'Vorschlag übernehmen',
    'hosting_wizard_dismiss_proposal' => 'Verwerfen',
    'hosting_wizard_proposal_applied' => 'Vorgeschlagenes Delivery-Setup übernommen',
    'hosting_wizard_proposal_apply_failed' => 'Vorschlag konnte nicht übernommen werden',
    'hosting_wizard_load_failed' => 'Hosting-Einstellungen konnten nicht geladen werden',
    'hosting_wizard_public_url_no_project' => 'Die öffentliche URL darf kein /project enthalten — nur Hostname angeben.',
    'hosting_wizard_public_url_help_extra' => 'Kein /project angeben — nur Hostname (z. B. https://beispiel.de).',
    'hosting_wizard_checklist_done' => 'Erledigt: {item}',
    'hosting_wizard_checklist_todo' => 'Offen: {item}',
];

foreach (['en.json', 'de.json'] as $file) {
    $path = $root . '/lang/' . $file;
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data['admin'] ?? null)) {
        fwrite(STDERR, "Invalid {$file}\n");
        exit(1);
    }
    $batch = $file === 'en.json' ? $newEn : $newDe;
    foreach ($batch as $key => $value) {
        $data['admin'][$key] = $value;
    }
    ksort($data['admin']);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents($path, $json);
    fwrite(STDOUT, "Updated lang/{$file} (" . count($batch) . " keys)\n");
}
