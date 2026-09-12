#!/usr/bin/env python3
"""One-off P85 bulk patch for admin JS i18n (settings, media, content-groups, onboarding, connections)."""
from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

HELPER = {
    "settings-page.js": """
function stT(key, fallback, params) {
    let text = (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')
        ? Birkly.i18n.t('admin.' + key, fallback)
        : (fallback != null ? fallback : key);
    if (params && typeof params === 'object') {
        Object.keys(params).forEach(function (name) {
            text = text.split('{' + name + '}').join(String(params[name]));
        });
    }
    return text;
}
""",
    "media-page.js": """
  function mdT(key, fallback, params) {
    let text = (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')
      ? Birkly.i18n.t('admin.' + key, fallback)
      : (fallback != null ? fallback : key);
    if (params && typeof params === 'object') {
      Object.keys(params).forEach(function (name) {
        text = text.split('{' + name + '}').join(String(params[name]));
      });
    }
    return text;
  }
""",
    "content-groups.js": """
    function cgT(key, fallback, params) {
        let text = (window.Birkly && Birkly.i18n && typeof Birkly.i18n.t === 'function')
            ? Birkly.i18n.t('admin.' + key, fallback)
            : (fallback != null ? fallback : key);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }
""",
    "onboarding.js": """
function obT(key, fallback, params) {
    let text = (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')
        ? Birkly.i18n.t('admin.' + key, fallback)
        : (fallback != null ? fallback : key);
    if (params && typeof params === 'object') {
        Object.keys(params).forEach(function (name) {
            text = text.split('{' + name + '}').join(String(params[name]));
        });
    }
    return text;
}
""",
    "connections-settings.js": """
function connT(key, fallback, params) {
    let text = (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')
        ? Birkly.i18n.t('admin.' + key, fallback)
        : (fallback != null ? fallback : key);
    if (params && typeof params === 'object') {
        Object.keys(params).forEach(function (name) {
            text = text.split('{' + name + '}').join(String(params[name]));
        });
    }
    return text;
}
""",
}

# key -> (en, de)
STRINGS: dict[str, tuple[str, str]] = {
    # settings-page.js
    "settings_js_role_modal_missing": ("Role modal not found. Please refresh the page.", "Rollen-Dialog nicht gefunden. Bitte Seite neu laden."),
    "settings_js_edit_role_modal_missing": ("Edit role modal not found. Please refresh the page.", "Bearbeiten-Dialog nicht gefunden. Bitte Seite neu laden."),
    "settings_js_err_loading_role": ("Error loading role:", "Fehler beim Laden der Rolle:"),
    "settings_js_err_form_missing": ("Error: Form not found", "Fehler: Formular nicht gefunden"),
    "settings_js_err_role_name_required": ("Error: Role name is required", "Fehler: Rollenname ist erforderlich"),
    "settings_js_role_updated": ("Role updated successfully!", "Rolle erfolgreich aktualisiert!"),
    "settings_js_err_updating_role": ("Error updating role:", "Fehler beim Aktualisieren der Rolle:"),
    "settings_js_role_created": ("Role created successfully!", "Rolle erfolgreich erstellt!"),
    "settings_js_err_creating_role": ("Error creating role:", "Fehler beim Erstellen der Rolle:"),
    "settings_js_confirm_delete_role": ("Are you sure you want to delete this role? This action cannot be undone.", "Diese Rolle wirklich löschen? Das kann nicht rückgängig gemacht werden."),
    "settings_js_role_deleted": ("Role deleted successfully!", "Rolle erfolgreich gelöscht!"),
    "settings_js_err_deleting_role": ("Error deleting role:", "Fehler beim Löschen der Rolle:"),
    "settings_js_save_settings_btn": ("Save Settings", "Einstellungen speichern"),
    "settings_js_invite_email_connection_hint": (
        "Sending invitation emails requires an email connection under Settings → Connections. Without one, use “Create invite link” and share the URL yourself.",
        "Einladungs-E-Mails benötigen eine E-Mail-Verbindung unter Einstellungen → Verbindungen. Ohne diese „Einladungslink erstellen“ nutzen und die URL selbst teilen.",
    ),
    "settings_js_invitation_failed": ("Invitation failed", "Einladung fehlgeschlagen"),
    "settings_js_err_sending_invitation": ("Error sending invitation:", "Fehler beim Senden der Einladung:"),
    "settings_js_invite_link_copied": ("Invite link copied to clipboard.", "Einladungslink in die Zwischenablage kopiert."),
    "settings_js_copy_link_manual": ("Select the link field and copy it manually.", "Linkfeld markieren und manuell kopieren."),
    "settings_js_invitations_load_failed": ("Could not load invitations.", "Einladungen konnten nicht geladen werden."),
    "settings_js_invitation_resent": ("Invitation resent.", "Einladung erneut gesendet."),
    "settings_js_could_not_resend_email": ("Could not resend email.", "E-Mail konnte nicht erneut gesendet werden."),
    "settings_js_resend_copy_instead": (" Copy the link from the list instead.", " Kopieren Sie stattdessen den Link aus der Liste."),
    "settings_js_could_not_resend_invitation": ("Could not resend invitation.", "Einladung konnte nicht erneut gesendet werden."),
    "settings_js_err_resending_invitation": ("Error resending invitation:", "Fehler beim erneuten Senden:"),
    "settings_js_invitation_cancelled": ("Invitation cancelled.", "Einladung abgebrochen."),
    "settings_js_could_not_cancel_invitation": ("Could not cancel invitation.", "Einladung konnte nicht abgebrochen werden."),
    "settings_js_err_cancelling_invitation": ("Error cancelling invitation:", "Fehler beim Abbrechen der Einladung:"),
    "settings_js_user_created": ("User created successfully!", "Benutzer erfolgreich erstellt!"),
    "settings_js_err_creating_user": ("Error creating user:", "Fehler beim Erstellen des Benutzers:"),
    "settings_js_user_updated": ("User updated successfully!", "Benutzer erfolgreich aktualisiert!"),
    "settings_js_err_updating_user": ("Error updating user:", "Fehler beim Aktualisieren des Benutzers:"),
    "settings_js_confirm_delete_user": (
        'Are you sure you want to delete user "{email}"? This action cannot be undone.',
        'Benutzer „{email}“ wirklich löschen? Das kann nicht rückgängig gemacht werden.',
    ),
    "settings_js_user_deleted": ("User deleted successfully!", "Benutzer erfolgreich gelöscht!"),
    "settings_js_err_deleting_user": ("Error deleting user:", "Fehler beim Löschen des Benutzers:"),
    "settings_js_confirm_revoke_ai": (
        "Revoke all active AI app connections for {who}? They will need to sign in again in Claude, Cursor, or ChatGPT.",
        "Alle aktiven KI-App-Verbindungen für {who} widerrufen? Erneute Anmeldung in Claude, Cursor oder ChatGPT nötig.",
    ),
    "settings_js_ai_connections_revoked": ("AI connections revoked", "KI-Verbindungen widerrufen"),
    "settings_js_could_not_revoke_ai": ("Could not revoke AI access", "KI-Zugang konnte nicht widerrufen werden"),
    "settings_js_err_revoking_ai": ("Error revoking AI access:", "Fehler beim Widerrufen des KI-Zugangs:"),
    "settings_js_plugin_action_success": ("Plugin {action}d successfully", "Plugin erfolgreich {action}"),
    "settings_js_failed_plugin_action": ("Failed to {action} plugin", "Plugin konnte nicht {action} werden"),
    "settings_js_confirm_delete_plugin": ("Are you sure you want to delete this plugin? This action cannot be undone.", "Dieses Plugin wirklich löschen? Das kann nicht rückgängig gemacht werden."),
    "settings_js_plugin_deleted": ("Plugin deleted successfully", "Plugin erfolgreich gelöscht"),
    "settings_js_failed_delete_plugin": ("Failed to delete plugin", "Plugin konnte nicht gelöscht werden"),
    "settings_js_delivery_role_updated": ("Delivery role updated for {host}", "Delivery-Rolle für {host} aktualisiert"),
    "settings_js_confirm_primary_url": (
        "Make {host} the primary public URL? The current primary will become an alias.",
        "{host} als primäre öffentliche URL setzen? Die bisherige primäre URL wird ein Alias.",
    ),
    "settings_js_primary_url_updated": ("Primary public URL updated", "Primäre öffentliche URL aktualisiert"),
    "settings_js_alias_redirect_updated": ("Alias redirect updated", "Alias-Weiterleitung aktualisiert"),
    "settings_js_analytics_enabled": ("Analytics enabled for {domain}", "Analytics aktiviert für {domain}"),
    "settings_js_analytics_disabled": ("Analytics disabled for {domain}", "Analytics deaktiviert für {domain}"),
    "settings_js_err_domain_email_required": ("Error: Domain and customer email are required", "Fehler: Domain und Kunden-E-Mail sind erforderlich"),
    "settings_js_err_invalid_server_response": ("Error: Invalid response from server. Check console for details.", "Fehler: Ungültige Serverantwort. Konsole prüfen."),
    "settings_js_domain_registered_warning": (
        "Domain registered{role_msg}, but delivery role could not be applied: {warning}",
        "Domain registriert{role_msg}, Delivery-Rolle konnte nicht gesetzt werden: {warning}",
    ),
    "settings_js_domain_registered_success": ("Domain registered successfully!{role_msg}", "Domain erfolgreich registriert!{role_msg}"),
    "settings_js_err_registering_domain": ("Error registering domain:", "Fehler bei Domain-Registrierung:"),
    "settings_js_err_loading_domain": ("Error loading domain details:", "Fehler beim Laden der Domain-Details:"),
    "settings_js_domain_updated": ("Domain updated successfully!", "Domain erfolgreich aktualisiert!"),
    "settings_js_err_updating_domain": ("Error updating domain:", "Fehler beim Aktualisieren der Domain:"),
    "settings_js_confirm_delete_domain": (
        'Are you sure you want to delete domain "{name}"? This will revoke all access.',
        'Domain „{name}“ wirklich löschen? Der gesamte Zugriff wird widerrufen.',
    ),
    "settings_js_domain_deleted": ("Domain deleted successfully!", "Domain erfolgreich gelöscht!"),
    "settings_js_err_deleting_domain": ("Error deleting domain:", "Fehler beim Löschen der Domain:"),
    "settings_js_select_plugin_first": ("Select a plugin first", "Wählen Sie zuerst ein Plugin"),
    "settings_js_plugin_denylist_added": ("Plugin added to denylist", "Plugin zur Sperrliste hinzugefügt"),
    "settings_js_failed_add_plugin": ("Failed to add plugin", "Plugin konnte nicht hinzugefügt werden"),
    "settings_js_select_collection_first": ("Select a collection first", "Wählen Sie zuerst eine Sammlung"),
    "settings_js_collection_denylist_added": ("Collection added to denylist", "Sammlung zur Sperrliste hinzugefügt"),
    "settings_js_resource_denylist_added": ("Resource added to denylist", "Ressource zur Sperrliste hinzugefügt"),
    "settings_js_confirm_remove_denylist": ('Remove "{resource}" from the denylist?', '„{resource}“ von der Sperrliste entfernen?'),
    "settings_js_resource_denylist_removed": ("Resource removed from denylist", "Ressource von der Sperrliste entfernt"),
    "settings_js_confirm_migration_backup": (
        "This will migrate all data to the selected storage backend. Make sure you have a backup! Continue?",
        "Alle Daten werden in das gewählte Speicher-Backend migriert. Backup vorhanden? Fortfahren?",
    ),
    "settings_js_select_db_backend": ("Please select a database backend first", "Wählen Sie zuerst ein Datenbank-Backend"),
    "settings_js_confirm_migration_wait": ("Migration may take some time. Do not close this window. Continue?", "Migration kann dauern. Fenster nicht schließen. Fortfahren?"),
    "settings_js_migration_db_fields_required": ("Enter database host, name, and user before migrating", "Vor Migration Host, Datenbankname und Benutzer angeben"),
    "settings_js_migration_failed": ("Migration failed:", "Migration fehlgeschlagen:"),
    "settings_js_settings_saved": ("Settings saved successfully!", "Einstellungen erfolgreich gespeichert!"),
    "settings_js_err_uploading_logo": ("Error uploading logo:", "Fehler beim Hochladen des Logos:"),
    "settings_js_err_saving_settings": ("Error saving settings:", "Fehler beim Speichern der Einstellungen:"),
    "settings_js_confirm_remove_logo": (
        "Are you sure you want to remove the logo? The default Birkly icon will be used.",
        "Logo wirklich entfernen? Das Standard-Birkly-Symbol wird verwendet.",
    ),
    "settings_js_logo_removed": ("Logo removed successfully!", "Logo erfolgreich entfernt!"),
    "settings_js_err_removing_logo": ("Error removing logo:", "Fehler beim Entfernen des Logos:"),
    "settings_js_system_rebuilt": ("System rebuilt successfully", "System erfolgreich neu aufgebaut"),
    "settings_js_failed_rebuild": ("Failed to rebuild system", "System konnte nicht neu aufgebaut werden"),
    "settings_js_perm_collections": ("Collections", "Sammlungen"),
    "settings_js_perm_media": ("Media", "Medien"),
    "settings_js_perm_users": ("Users", "Benutzer"),
    "settings_js_perm_settings": ("Settings", "Einstellungen"),
    "settings_js_perm_view": ("View", "Ansehen"),
    "settings_js_perm_edit": ("Edit", "Bearbeiten"),
    "settings_js_perm_create": ("Create", "Erstellen"),
    "settings_js_perm_delete": ("Delete", "Löschen"),
    "settings_js_loading_table": ("Loading...", "Wird geladen…"),
    # media-page.js
    "media_js_err_load": ("Failed to load media:", "Medien konnten nicht geladen werden:"),
    "media_js_err_load_generic": ("Error loading media:", "Fehler beim Laden der Medien:"),
    "media_js_select_file_upload": ("Select a file to upload.", "Wählen Sie eine Datei zum Hochladen."),
    "media_js_err_create_folder": ("Failed to create folder:", "Ordner konnte nicht erstellt werden:"),
    "media_js_err_create_folder_generic": ("Error creating folder:", "Fehler beim Erstellen des Ordners:"),
    "media_js_err_rename_folder": ("Failed to rename folder:", "Ordner konnte nicht umbenannt werden:"),
    "media_js_err_rename_folder_generic": ("Error renaming folder:", "Fehler beim Umbenennen des Ordners:"),
    "media_js_err_delete_folder": ("Failed to delete folder:", "Ordner konnte nicht gelöscht werden:"),
    "media_js_err_delete_folder_generic": ("Error deleting folder:", "Fehler beim Löschen des Ordners:"),
    "media_js_item_not_found": ("Media item not found.", "Medienelement nicht gefunden."),
    "media_js_no_item_selected": ("No media item selected.", "Kein Medienelement ausgewählt."),
    "media_js_err_save_details": ("Failed to save media details:", "Mediendetails konnten nicht gespeichert werden:"),
    "media_js_err_save_details_generic": ("Error saving media details:", "Fehler beim Speichern der Mediendetails:"),
    "media_js_select_items_move": ("Select at least one media item to move.", "Mindestens ein Medium zum Verschieben auswählen."),
    "media_js_no_media_move": ("No media selected to move.", "Keine Medien zum Verschieben ausgewählt."),
    "media_js_no_media_selected": ("No media selected.", "Keine Medien ausgewählt."),
    "media_js_err_delete_media": ("Failed to delete media:", "Medium konnte nicht gelöscht werden:"),
    "media_js_alt_prefix": ("Alt:", "Alt:"),
    # content-groups.js
    "content_groups_drop_here": ("Drop collections here", "Sammlungen hier ablegen"),
    "content_groups_first_title": ("Create your first collection", "Erste Sammlung erstellen"),
    "content_groups_first_message": (
        "Collections hold your site content — blog posts, products, pages, and more. Start from scratch or install a starter template.",
        "Sammlungen enthalten Ihre Inhalte — Blog, Produkte, Seiten und mehr. Neu starten oder Starter-Vorlage installieren.",
    ),
    "content_groups_new_collection": ("New collection", "Neue Sammlung"),
    "content_groups_or_template": ("Or start with a template", "Oder mit einer Vorlage starten"),
    "content_groups_ungrouped_title": ("Ungrouped Collections", "Nicht gruppierte Sammlungen"),
    "content_groups_edit_group": ("Edit Group", "Gruppe bearbeiten"),
    "content_groups_update_group": ("Update Group", "Gruppe aktualisieren"),
    "content_groups_new_group": ("New Group", "Neue Gruppe"),
    "content_groups_create_group": ("Create Group", "Gruppe erstellen"),
    "content_groups_confirm_delete_group": (
        "Are you sure you want to delete this group? Collections will be moved to ungrouped.",
        "Diese Gruppe wirklich löschen? Sammlungen werden nicht gruppiert.",
    ),
    # onboarding.js
    "onboarding_js_marsk_network_note": (
        "Marsk needs an internet connection from this computer so Birkly can download the demo pack. You can continue with Blank.",
        "Marsk benötigt Internet von diesem Rechner, um das Demo-Paket zu laden. Sie können mit Leer fortfahren.",
    ),
    "onboarding_js_passwords_mismatch": ("Passwords do not match", "Passwörter stimmen nicht überein"),
    "onboarding_js_generic_error": ("An error occurred. Please try again.", "Ein Fehler ist aufgetreten. Bitte erneut versuchen."),
    "onboarding_js_installing": ("Installing…", "Installation läuft…"),
    "onboarding_js_continue": ("Continue", "Weiter"),
    "onboarding_js_saving": ("Saving...", "Speichern…"),
    # connections-settings.js
    "connections_js_confirm_delete": ('Delete "{label}"? This cannot be undone.', '„{label}“ löschen? Das kann nicht rückgängig gemacht werden.'),
    "connections_js_enter_email_first": ("Enter your email address first.", "Geben Sie zuerst Ihre E-Mail-Adresse ein."),
    "connections_js_sending_test": ("Sending test…", "Test wird gesendet…"),
    "connections_js_test_logged": ("Test logged successfully (Log only mode — check logs/mail.jsonl).", "Test protokolliert (Nur Log — logs/mail.jsonl prüfen)."),
    "connections_js_test_sent": ("Test sent! Check your inbox and spam folder.", "Test gesendet! Posteingang und Spam prüfen."),
    "connections_js_test_failed": ("Test failed:", "Test fehlgeschlagen:"),
    "connections_js_add_http_credential": ("Add HTTP credential", "HTTP-Zugangsdaten hinzufügen"),
    "connections_js_save_credential": ("Save credential", "Zugangsdaten speichern"),
    "connections_js_edit_http_credential": ("Edit HTTP credential", "HTTP-Zugangsdaten bearbeiten"),
    "connections_js_update_credential": ("Update credential", "Zugangsdaten aktualisieren"),
}


def merge_lang():
    for loc, key in [("en.json", 0), ("de.json", 1)]:
        path = LANG / loc
        data = json.loads(path.read_text())
        admin = data["admin"]
        for k, pair in STRINGS.items():
            admin[k] = pair[key]
        data["admin"] = dict(sorted(admin.items()))
        path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n")


def insert_helper(path: Path, marker: str, helper: str, fn_name: str):
    text = path.read_text()
    if fn_name in text:
        return
    idx = text.find(marker)
    if idx == -1:
        raise SystemExit(f"marker not found in {path}: {marker[:40]}")
    text = text[:idx] + helper + text[idx:]
    path.write_text(text)


def patch_settings():
    path = ROOT / "admin/js/settings-page.js"
    insert_helper(path, "\nfunction getSettingsUsers()", HELPER["settings-page.js"], "function stT")
    text = path.read_text()
    repl = [
        ("showMessage('Role modal not found. Please refresh the page.', 'error')",
         "showMessage(stT('settings_js_role_modal_missing', 'Role modal not found. Please refresh the page.'), 'error')"),
        ("showMessage('Edit role modal not found. Please refresh the page.', 'error')",
         "showMessage(stT('settings_js_edit_role_modal_missing', 'Edit role modal not found. Please refresh the page.'), 'error')"),
        ("showMessage('Error loading role: ' + (result.message || 'Unknown error'), 'error')",
         "showMessage(stT('settings_js_err_loading_role', 'Error loading role:') + ' ' + (result.message || stT('ai_conn_js_unknown_error', 'Unknown error')), 'error')"),
        ("showMessage('Error loading role: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_loading_role', 'Error loading role:') + ' ' + error.message, 'error')"),
        ("showMessage('Error: Form not found', 'error')",
         "showMessage(stT('settings_js_err_form_missing', 'Error: Form not found'), 'error')"),
        ("showMessage('Error: Role name is required', 'error')",
         "showMessage(stT('settings_js_err_role_name_required', 'Error: Role name is required'), 'error')"),
        ("showMessage('Role updated successfully!', 'success')",
         "showMessage(stT('settings_js_role_updated', 'Role updated successfully!'), 'success')"),
        ("showMessage('Error: ' + (result.message || 'Unknown error'), 'error')",
         "showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('ai_conn_js_unknown_error', 'Unknown error')), 'error')"),
        ("showMessage('Error updating role: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_updating_role', 'Error updating role:') + ' ' + error.message, 'error')"),
        ("showMessage('Role created successfully!', 'success')",
         "showMessage(stT('settings_js_role_created', 'Role created successfully!'), 'success')"),
        ("showMessage('Error creating role: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_creating_role', 'Error creating role:') + ' ' + error.message, 'error')"),
        ("BirklyFeedback.confirm('Are you sure you want to delete this role? This action cannot be undone.')",
         "BirklyFeedback.confirm(stT('settings_js_confirm_delete_role', 'Are you sure you want to delete this role? This action cannot be undone.'))"),
        ("showMessage('Role deleted successfully!', 'success')",
         "showMessage(stT('settings_js_role_deleted', 'Role deleted successfully!'), 'success')"),
        ("showMessage('Error: ' + result.message, 'error')",
         "showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error')"),
        ("showMessage('Error deleting role: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_deleting_role', 'Error deleting role:') + ' ' + error.message, 'error')"),
        ("saveBtn.textContent = 'Save Settings'",
         "saveBtn.textContent = stT('settings_js_save_settings_btn', 'Save Settings')"),
        ("copy.textContent = 'Sending invitation emails requires an email connection under Settings → Connections. Without one, use “Create invite link” and share the URL yourself.'",
         "copy.textContent = stT('settings_js_invite_email_connection_hint', 'Sending invitation emails requires an email connection under Settings → Connections. Without one, use “Create invite link” and share the URL yourself.')"),
        ("showMessage('Error: ' + (result.message || 'Invitation failed'), 'error')",
         "showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_invitation_failed', 'Invitation failed')), 'error')"),
        ("showMessage('Error sending invitation: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_sending_invitation', 'Error sending invitation:') + ' ' + error.message, 'error')"),
        ("showMessage('Invite link copied to clipboard.', 'success')",
         "showMessage(stT('settings_js_invite_link_copied', 'Invite link copied to clipboard.'), 'success')"),
        ("showMessage('Select the link field and copy it manually.', 'warning')",
         "showMessage(stT('settings_js_copy_link_manual', 'Select the link field and copy it manually.'), 'warning')"),
        ("emptyEl.querySelector('.empty-state__description').textContent = 'Could not load invitations.'",
         "emptyEl.querySelector('.empty-state__description').textContent = stT('settings_js_invitations_load_failed', 'Could not load invitations.')"),
        ("showMessage(result.message || 'Invitation resent.', 'success')",
         "showMessage(result.message || stT('settings_js_invitation_resent', 'Invitation resent.'), 'success')"),
        ("showMessage((result.message || 'Could not resend email.') + ' Copy the link from the list instead.', 'warning')",
         "showMessage((result.message || stT('settings_js_could_not_resend_email', 'Could not resend email.')) + stT('settings_js_resend_copy_instead', ' Copy the link from the list instead.'), 'warning')"),
        ("showMessage(result.message || 'Could not resend invitation.', 'error')",
         "showMessage(result.message || stT('settings_js_could_not_resend_invitation', 'Could not resend invitation.'), 'error')"),
        ("showMessage('Error resending invitation: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_resending_invitation', 'Error resending invitation:') + ' ' + error.message, 'error')"),
        ("showMessage(result.message || 'Invitation cancelled.', 'success')",
         "showMessage(result.message || stT('settings_js_invitation_cancelled', 'Invitation cancelled.'), 'success')"),
        ("showMessage(result.message || 'Could not cancel invitation.', 'error')",
         "showMessage(result.message || stT('settings_js_could_not_cancel_invitation', 'Could not cancel invitation.'), 'error')"),
        ("showMessage('Error cancelling invitation: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_cancelling_invitation', 'Error cancelling invitation:') + ' ' + error.message, 'error')"),
        ("showMessage('User created successfully!', 'success')",
         "showMessage(stT('settings_js_user_created', 'User created successfully!'), 'success')"),
        ("showMessage('Error creating user: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_creating_user', 'Error creating user:') + ' ' + error.message, 'error')"),
        ("showMessage('User updated successfully!', 'success')",
         "showMessage(stT('settings_js_user_updated', 'User updated successfully!'), 'success')"),
        ("showMessage('Error updating user: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_updating_user', 'Error updating user:') + ' ' + error.message, 'error')"),
        ("BirklyFeedback.confirm('Are you sure you want to delete user \"' + email + '\"? This action cannot be undone.')",
         "BirklyFeedback.confirm(stT('settings_js_confirm_delete_user', 'Are you sure you want to delete user \"{email}\"? This action cannot be undone.', { email: email }))"),
        ("showMessage('User deleted successfully!', 'success')",
         "showMessage(stT('settings_js_user_deleted', 'User deleted successfully!'), 'success')"),
        ("showMessage('Error deleting user: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_deleting_user', 'Error deleting user:') + ' ' + error.message, 'error')"),
        ("BirklyFeedback.confirm('Revoke all active AI app connections for ' + who + '? They will need to sign in again in Claude, Cursor, or ChatGPT.')",
         "BirklyFeedback.confirm(stT('settings_js_confirm_revoke_ai', 'Revoke all active AI app connections for {who}? They will need to sign in again in Claude, Cursor, or ChatGPT.', { who: who }))"),
        ("showMessage(result.message || 'AI connections revoked', 'success')",
         "showMessage(result.message || stT('settings_js_ai_connections_revoked', 'AI connections revoked'), 'success')"),
        ("showMessage('Error: ' + (result.message || 'Could not revoke AI access'), 'error')",
         "showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_could_not_revoke_ai', 'Could not revoke AI access')), 'error')"),
        ("showMessage('Error revoking AI access: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_revoking_ai', 'Error revoking AI access:') + ' ' + error.message, 'error')"),
        ("showMessage(result.message || 'Plugin ' + action + 'd successfully', 'success')",
         "showMessage(result.message || stT('settings_js_plugin_action_success', 'Plugin {action}d successfully', { action: action }), 'success')"),
        ("var errorMsg = 'Error: ' + (result.message || 'Failed to ' + action + ' plugin');",
         "var errorMsg = stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_failed_plugin_action', 'Failed to {action} plugin', { action: action }));"),
        ("showMessage('Error: ' + error.message, 'error')",
         "showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error')"),
        ("BirklyFeedback.confirm('Are you sure you want to delete this plugin? This action cannot be undone.')",
         "BirklyFeedback.confirm(stT('settings_js_confirm_delete_plugin', 'Are you sure you want to delete this plugin? This action cannot be undone.'))"),
        ("showMessage(result.message || 'Plugin deleted successfully', 'success')",
         "showMessage(result.message || stT('settings_js_plugin_deleted', 'Plugin deleted successfully'), 'success')"),
        ("showMessage('Error: ' + (result.message || 'Failed to delete plugin'), 'error')",
         "showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_failed_delete_plugin', 'Failed to delete plugin')), 'error')"),
        ("showMessage('Delivery role updated for ' + host, 'success')",
         "showMessage(stT('settings_js_delivery_role_updated', 'Delivery role updated for {host}', { host: host }), 'success')"),
        ("BirklyFeedback.confirm('Make ' + host + ' the primary public URL? The current primary will become an alias.')",
         "BirklyFeedback.confirm(stT('settings_js_confirm_primary_url', 'Make {host} the primary public URL? The current primary will become an alias.', { host: host }))"),
        ("showMessage(result.message || 'Primary public URL updated', 'success')",
         "showMessage(result.message || stT('settings_js_primary_url_updated', 'Primary public URL updated'), 'success')"),
        ("showMessage(result.message || 'Alias redirect updated', 'success')",
         "showMessage(result.message || stT('settings_js_alias_redirect_updated', 'Alias redirect updated'), 'success')"),
        ("showMessage('Error: Form not found', 'error')",
         "showMessage(stT('settings_js_err_form_missing', 'Error: Form not found'), 'error')"),
        ("showMessage('Error: Domain and customer email are required', 'error')",
         "showMessage(stT('settings_js_err_domain_email_required', 'Error: Domain and customer email are required'), 'error')"),
        ("showMessage('Error: Invalid response from server. Check console for details.', 'error')",
         "showMessage(stT('settings_js_err_invalid_server_response', 'Error: Invalid response from server. Check console for details.'), 'error')"),
        ("showMessage('Domain updated successfully!', 'success')",
         "showMessage(stT('settings_js_domain_updated', 'Domain updated successfully!'), 'success')"),
        ("showMessage('Error updating domain: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_updating_domain', 'Error updating domain:') + ' ' + error.message, 'error')"),
        ("showMessage('Domain deleted successfully!', 'success')",
         "showMessage(stT('settings_js_domain_deleted', 'Domain deleted successfully!'), 'success')"),
        ("showMessage('Error deleting domain: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_deleting_domain', 'Error deleting domain:') + ' ' + error.message, 'error')"),
        ("showMessage('Select a plugin first', 'error')",
         "showMessage(stT('settings_js_select_plugin_first', 'Select a plugin first'), 'error')"),
        ("showMessage('Plugin added to denylist', 'success')",
         "showMessage(stT('settings_js_plugin_denylist_added', 'Plugin added to denylist'), 'success')"),
        ("showMessage(result.message || 'Failed to add plugin', 'error')",
         "showMessage(result.message || stT('settings_js_failed_add_plugin', 'Failed to add plugin'), 'error')"),
        ("showMessage(error.message || 'Failed to add plugin', 'error')",
         "showMessage(error.message || stT('settings_js_failed_add_plugin', 'Failed to add plugin'), 'error')"),
        ("showMessage('Select a collection first', 'error')",
         "showMessage(stT('settings_js_select_collection_first', 'Select a collection first'), 'error')"),
        ("showMessage('Collection added to denylist', 'success')",
         "showMessage(stT('settings_js_collection_denylist_added', 'Collection added to denylist'), 'success')"),
        ("showMessage('Resource added to denylist', 'success')",
         "showMessage(stT('settings_js_resource_denylist_added', 'Resource added to denylist'), 'success')"),
        ("showMessage('Resource removed from denylist', 'success')",
         "showMessage(stT('settings_js_resource_denylist_removed', 'Resource removed from denylist'), 'success')"),
        ("showMessage('Please select a database backend first', 'error')",
         "showMessage(stT('settings_js_select_db_backend', 'Please select a database backend first'), 'error')"),
        ("showMessage('Enter database host, name, and user before migrating', 'error')",
         "showMessage(stT('settings_js_migration_db_fields_required', 'Enter database host, name, and user before migrating'), 'error')"),
        ("showMessage('Migration failed: ' + failureMessage, 'error')",
         "showMessage(stT('settings_js_migration_failed', 'Migration failed:') + ' ' + failureMessage, 'error')"),
        ("showMessage('Settings saved successfully!', 'success')",
         "showMessage(stT('settings_js_settings_saved', 'Settings saved successfully!'), 'success')"),
        ("window.showMessage('Settings saved successfully!', 'success')",
         "window.showMessage(stT('settings_js_settings_saved', 'Settings saved successfully!'), 'success')"),
        ("window.showMessage('Error: ' + msg, 'error')",
         "window.showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + msg, 'error')"),
        ("showMessage('Error uploading logo: ' + (uploadResult.message || 'Unknown error'), 'error')",
         "showMessage(stT('settings_js_err_uploading_logo', 'Error uploading logo:') + ' ' + (uploadResult.message || stT('ai_conn_js_unknown_error', 'Unknown error')), 'error')"),
        ("showMessage('Error uploading logo: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_uploading_logo', 'Error uploading logo:') + ' ' + error.message, 'error')"),
        ("showMessage('Error saving settings: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_saving_settings', 'Error saving settings:') + ' ' + error.message, 'error')"),
        ("BirklyFeedback.confirm('Are you sure you want to remove the logo? The default Birkly icon will be used.')",
         "BirklyFeedback.confirm(stT('settings_js_confirm_remove_logo', 'Are you sure you want to remove the logo? The default Birkly icon will be used.'))"),
        ("showMessage('Logo removed successfully!', 'success')",
         "showMessage(stT('settings_js_logo_removed', 'Logo removed successfully!'), 'success')"),
        ("showMessage('Error removing logo: ' + error.message, 'error')",
         "showMessage(stT('settings_js_err_removing_logo', 'Error removing logo:') + ' ' + error.message, 'error')"),
        ("showMessage(result.message || 'System rebuilt successfully', 'success')",
         "showMessage(result.message || stT('settings_js_system_rebuilt', 'System rebuilt successfully'), 'success')"),
        ("showMessage('Error: ' + (result.message || 'Failed to rebuild system'), 'error')",
         "showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_failed_rebuild', 'Failed to rebuild system')), 'error')"),
        ("tbody.innerHTML = '<tr><td colspan=\"6\"><div class=\"loading-state\"><p>Loading...</p></div></td></tr>'",
         "tbody.innerHTML = '<tr><td colspan=\"6\"><div class=\"loading-state\"><p>' + escapeHtml(stT('settings_js_loading_table', 'Loading...')) + '</p></div></td></tr>'"),
    ]
    for old, new in repl:
        if old not in text:
            continue
        text = text.replace(old, new)
    # permissions grid
    text = text.replace(
        "heading.textContent = resource;",
        "heading.textContent = stT('settings_js_perm_' + resource, resource);",
    )
    text = text.replace(
        "span.textContent = action.charAt(0).toUpperCase() + action.slice(1);",
        "span.textContent = stT('settings_js_perm_' + action, action.charAt(0).toUpperCase() + action.slice(1));",
    )
    # analytics toggle
    text = text.replace(
        "showMessage(enabled ? 'Analytics enabled for ' + domainName : 'Analytics disabled for ' + domainName, 'success');",
        "showMessage(enabled ? stT('settings_js_analytics_enabled', 'Analytics enabled for {domain}', { domain: domainName }) : stT('settings_js_analytics_disabled', 'Analytics disabled for {domain}', { domain: domainName }), 'success');",
    )
    text = text.replace(
        "if (!(await BirklyFeedback.confirm('Are you sure you want to delete domain \"' + domainName + '\"? This will revoke all access.'))) {",
        "if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_delete_domain', 'Are you sure you want to delete domain \"{name}\"? This will revoke all access.', { name: domainName })))) {",
    )
    text = text.replace(
        "if (!(await BirklyFeedback.confirm(`Remove \"${resource}\" from the denylist?`))) return;",
        "if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_remove_denylist', 'Remove \"{resource}\" from the denylist?', { resource: resource })))) return;",
    )
    text = text.replace(
        "if (!(await BirklyFeedback.confirm('This will migrate all data to the selected storage backend. Make sure you have a backup! Continue?'))) {",
        "if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_migration_backup', 'This will migrate all data to the selected storage backend. Make sure you have a backup! Continue?')))) {",
    )
    text = text.replace(
        "if (!(await BirklyFeedback.confirm('Migration may take some time. Do not close this window. Continue?'))) {",
        "if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_migration_wait', 'Migration may take some time. Do not close this window. Continue?')))) {",
    )
    path.write_text(text)


def patch_media():
    path = ROOT / "admin/js/media-page.js"
    insert_helper(path, "\n  function icon(name, opts) {", HELPER["media-page.js"], "function mdT")
    text = path.read_text()
    if "function showError" in text and "mdT(" not in text.split("function showError")[1][:200]:
        # patch showError to use prefix - find showError definition
        pass
    repl = [
        ("showError('Failed to load media: ' + getResultMessage(result, 'Unknown error'));",
         "showError(mdT('media_js_err_load', 'Failed to load media:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));"),
        ("showError('Error loading media: ' + error.message);",
         "showError(mdT('media_js_err_load_generic', 'Error loading media:') + ' ' + error.message);"),
        ("showError('Select a file to upload.');",
         "showError(mdT('media_js_select_file_upload', 'Select a file to upload.'));"),
        ("showError('Failed to create folder: ' + getResultMessage(result, 'Unknown error'));",
         "showError(mdT('media_js_err_create_folder', 'Failed to create folder:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));"),
        ("showError('Error creating folder: ' + error.message);",
         "showError(mdT('media_js_err_create_folder_generic', 'Error creating folder:') + ' ' + error.message);"),
        ("showError('Failed to rename folder: ' + getResultMessage(result, 'Unknown error'));",
         "showError(mdT('media_js_err_rename_folder', 'Failed to rename folder:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));"),
        ("showError('Error renaming folder: ' + error.message);",
         "showError(mdT('media_js_err_rename_folder_generic', 'Error renaming folder:') + ' ' + error.message);"),
        ("showError('Failed to delete folder: ' + getResultMessage(result, 'Unknown error'));",
         "showError(mdT('media_js_err_delete_folder', 'Failed to delete folder:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));"),
        ("showError('Error deleting folder: ' + error.message);",
         "showError(mdT('media_js_err_delete_folder_generic', 'Error deleting folder:') + ' ' + error.message);"),
        ("showError('Media item not found.');",
         "showError(mdT('media_js_item_not_found', 'Media item not found.'));"),
        ("showError('No media item selected.');",
         "showError(mdT('media_js_no_item_selected', 'No media item selected.'));"),
        ("showError('Failed to save media details: ' + getResultMessage(result, 'Unknown error'));",
         "showError(mdT('media_js_err_save_details', 'Failed to save media details:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));"),
        ("showError('Error saving media details: ' + error.message);",
         "showError(mdT('media_js_err_save_details_generic', 'Error saving media details:') + ' ' + error.message);"),
        ("showError('Select at least one media item to move.');",
         "showError(mdT('media_js_select_items_move', 'Select at least one media item to move.'));"),
        ("showError('No media selected to move.');",
         "showError(mdT('media_js_no_media_move', 'No media selected to move.'));"),
        ("showError('No media selected.');",
         "showError(mdT('media_js_no_media_selected', 'No media selected.'));"),
        ("showError('Failed to delete media: ' + getResultMessage(result, 'Unknown error'));",
         "showError(mdT('media_js_err_delete_media', 'Failed to delete media:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));"),
        ("altEl.textContent = 'Alt: ' + item.alt_text;",
         "altEl.textContent = mdT('media_js_alt_prefix', 'Alt:') + ' ' + item.alt_text;"),
    ]
    for old, new in repl:
        text = text.replace(old, new)
    path.write_text(text)


def patch_content_groups():
    path = ROOT / "admin/js/content-groups.js"
    insert_helper(path, "\n    function adminUrl(page, params) {", HELPER["content-groups.js"], "function cgT")
    text = path.read_text()
    repl = [
        ("emptyMsg.textContent = 'Drop collections here';", "emptyMsg.textContent = cgT('content_groups_drop_here', 'Drop collections here');"),
        ("title.textContent = 'Create your first collection';", "title.textContent = cgT('content_groups_first_title', 'Create your first collection');"),
        ("message.textContent = 'Collections hold your site content — blog posts, products, pages, and more. Start from scratch or install a starter template.';",
         "message.textContent = cgT('content_groups_first_message', 'Collections hold your site content — blog posts, products, pages, and more. Start from scratch or install a starter template.');"),
        ("createBtn.textContent = 'New collection';", "createBtn.textContent = cgT('content_groups_new_collection', 'New collection');"),
        ("templatesHeading.textContent = 'Or start with a template';", "templatesHeading.textContent = cgT('content_groups_or_template', 'Or start with a template');"),
        ("title.textContent = 'Ungrouped Collections';", "title.textContent = cgT('content_groups_ungrouped_title', 'Ungrouped Collections');"),
        ("title.textContent = 'Edit Group';", "title.textContent = cgT('content_groups_edit_group', 'Edit Group');"),
        ("submitBtn.textContent = 'Update Group';", "submitBtn.textContent = cgT('content_groups_update_group', 'Update Group');"),
        ("title.textContent = 'New Group';", "title.textContent = cgT('content_groups_new_group', 'New Group');"),
        ("submitBtn.textContent = 'Create Group';", "submitBtn.textContent = cgT('content_groups_create_group', 'Create Group');"),
        ("BirklyFeedback.confirm('Are you sure you want to delete this group? Collections will be moved to ungrouped.')",
         "BirklyFeedback.confirm(cgT('content_groups_confirm_delete_group', 'Are you sure you want to delete this group? Collections will be moved to ungrouped.'))"),
    ]
    for old, new in repl:
        text = text.replace(old, new)
    path.write_text(text)


def patch_onboarding():
    path = ROOT / "admin/js/onboarding.js"
    text = path.read_text()
    if "function obT" not in text:
        marker = "\nconst onboarding = {"
        text = text.replace(marker, HELPER["onboarding.js"] + marker)
    repl = [
        ("note.textContent = 'Marsk needs an internet connection from this computer so Birkly can download the demo pack. You can continue with Blank.';",
         "note.textContent = obT('onboarding_js_marsk_network_note', 'Marsk needs an internet connection from this computer so Birkly can download the demo pack. You can continue with Blank.');"),
        ("errorDiv.textContent = 'Passwords do not match';", "errorDiv.textContent = obT('onboarding_js_passwords_mismatch', 'Passwords do not match');"),
        ("errorDiv.textContent = 'An error occurred. Please try again.';", "errorDiv.textContent = obT('onboarding_js_generic_error', 'An error occurred. Please try again.');"),
        ("btn.textContent = 'Installing…';", "btn.textContent = obT('onboarding_js_installing', 'Installing…');"),
        ("btn.textContent = 'Continue';", "btn.textContent = obT('onboarding_js_continue', 'Continue');"),
        ("submitBtn.textContent = 'Saving...';", "submitBtn.textContent = obT('onboarding_js_saving', 'Saving...');"),
    ]
    for old, new in repl:
        text = text.replace(old, new)
    path.write_text(text)


def patch_connections():
    path = ROOT / "admin/js/connections-settings.js"
    text = path.read_text()
    if "function connT" not in text:
        marker = "\n    var EMAIL_PROVIDER_CARDS = {"
        text = text.replace(marker, HELPER["connections-settings.js"] + marker)
    repl = [
        ("BirklyFeedback.confirm('Delete \"' + label + '\"? This cannot be undone.')",
         "BirklyFeedback.confirm(connT('connections_js_confirm_delete', 'Delete \"{label}\"? This cannot be undone.', { label: label }))"),
        ("confirm('Delete \"' + label + '\"?')",
         "confirm(connT('connections_js_confirm_delete', 'Delete \"{label}\"? This cannot be undone.', { label: label }))"),
        ("statusEl.textContent = 'Enter your email address first.';",
         "statusEl.textContent = connT('connections_js_enter_email_first', 'Enter your email address first.');"),
        ("statusEl.textContent = 'Sending test…';",
         "statusEl.textContent = connT('connections_js_sending_test', 'Sending test…');"),
        ("statusEl.textContent = 'Test logged successfully (Log only mode — check logs/mail.jsonl).';",
         "statusEl.textContent = connT('connections_js_test_logged', 'Test logged successfully (Log only mode — check logs/mail.jsonl).');"),
        ("statusEl.textContent = 'Test sent! Check your inbox and spam folder.';",
         "statusEl.textContent = connT('connections_js_test_sent', 'Test sent! Check your inbox and spam folder.');"),
        ("statusEl.textContent = 'Test failed: ' + ((testResult.send_result && testResult.send_result.error) || testResult.message || 'unknown error');",
         "statusEl.textContent = connT('connections_js_test_failed', 'Test failed:') + ' ' + ((testResult.send_result && testResult.send_result.error) || testResult.message || connT('ai_conn_js_unknown_error', 'unknown error'));"),
        ("document.getElementById('sys-conn-http-modal-title').textContent = 'Add HTTP credential';",
         "document.getElementById('sys-conn-http-modal-title').textContent = connT('connections_js_add_http_credential', 'Add HTTP credential');"),
        ("document.getElementById('sys-conn-http-submit').textContent = 'Save credential';",
         "document.getElementById('sys-conn-http-submit').textContent = connT('connections_js_save_credential', 'Save credential');"),
        ("document.getElementById('sys-conn-http-modal-title').textContent = 'Edit HTTP credential';",
         "document.getElementById('sys-conn-http-modal-title').textContent = connT('connections_js_edit_http_credential', 'Edit HTTP credential');"),
        ("document.getElementById('sys-conn-http-submit').textContent = 'Update credential';",
         "document.getElementById('sys-conn-http-submit').textContent = connT('connections_js_update_credential', 'Update credential');"),
    ]
    for old, new in repl:
        text = text.replace(old, new)
    path.write_text(text)


def main():
    merge_lang()
    patch_settings()
    patch_media()
    patch_content_groups()
    patch_onboarding()
    patch_connections()
    print("P85 chunk patch complete:", len(STRINGS), "keys")


if __name__ == "__main__":
    main()
