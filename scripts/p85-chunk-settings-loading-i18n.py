#!/usr/bin/env python3
"""P85: settings loading placeholders, onboarding/settings JS, content-groups toasts."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "loading_analytics_report": ("Loading analytics report...", "Analytics-Bericht wird geladen …"),
    "loading_delivery_matrix": ("Loading delivery matrix...", "Delivery-Matrix wird geladen …"),
    "loading_denylist": ("Loading denylist...", "Denylist wird geladen …"),
    "loading_git_availability": ("Checking git availability...", "Git-Verfügbarkeit wird geprüft …"),
    "loading_presets": ("Loading presets…", "Voreinstellungen werden geladen …"),
    "loading_project_info": ("Loading project info...", "Projektinformationen werden geladen …"),
    "loading_system_info": ("Loading system info…", "Systeminformationen werden geladen …"),
    "entry_detail_js_bulk_delete_confirm": ("Delete {count} sub-entries?", "{count} Untereinträge löschen?"),
    "entry_detail_js_collection_not_found": (
        "Collection not found. Please refresh the page.",
        "Kollektion nicht gefunden. Bitte Seite neu laden.",
    ),
    "entry_detail_no_sub_entries": ("No sub-entries found.", "Keine Untereinträge gefunden."),
    "roles_empty_custom": ("No custom roles found", "Keine benutzerdefinierten Rollen gefunden"),
    "roles_add_first": ("Add first role", "Erste Rolle hinzufügen"),
    "roles_no_description": ("No description", "Keine Beschreibung"),
    "onboarding_js_install_template_failed": (
        "Failed to install starter template.",
        "Starter-Vorlage konnte nicht installiert werden.",
    ),
    "onboarding_js_save_project_failed": (
        "Failed to save project information.",
        "Projektinformationen konnten nicht gespeichert werden.",
    ),
    "onboarding_js_save_failed": ("Failed to save: {message}", "Speichern fehlgeschlagen: {message}"),
    "onboarding_js_complete_failed": (
        "Failed to complete onboarding: {message}",
        "Setup konnte nicht abgeschlossen werden: {message}",
    ),
    "settings_js_err_loading_delivery_matrix": (
        "Error loading delivery matrix: {message}",
        "Fehler beim Laden der Delivery-Matrix: {message}",
    ),
    "settings_js_no_pending_changes": ("No pending changes to save", "Keine ausstehenden Änderungen zum Speichern"),
    "settings_js_changes_save_per_action": (
        "Changes on this tab are saved when you confirm each action",
        "Änderungen auf diesem Tab werden beim Bestätigen der jeweiligen Aktion gespeichert",
    ),
    "settings_js_analytics_settings_saved": ("Analytics settings saved", "Analytics-Einstellungen gespeichert"),
    "content_js_err_collection_id_delete": (
        "Collection ID not found. Cannot delete collection.",
        "Kollektions-ID nicht gefunden. Kollektion kann nicht gelöscht werden.",
    ),
    "content_js_err_collection_id_duplicate": (
        "Collection ID not found. Cannot duplicate collection.",
        "Kollektions-ID nicht gefunden. Kollektion kann nicht dupliziert werden.",
    ),
    "content_js_err_delete_retry": (
        "Error deleting collection. Please try again.",
        "Fehler beim Löschen der Kollektion. Bitte erneut versuchen.",
    ),
    "content_js_err_duplicate_retry": (
        "Error duplicating collection. Please try again.",
        "Fehler beim Duplizieren der Kollektion. Bitte erneut versuchen.",
    ),
    "content_js_err_save_groups_retry": (
        "Error saving groups. Please try again.",
        "Fehler beim Speichern der Gruppen. Bitte erneut versuchen.",
    ),
    "content_js_failed_delete_collection": (
        "Failed to delete collection: {message}",
        "Kollektion konnte nicht gelöscht werden: {message}",
    ),
    "content_js_failed_duplicate_collection": (
        "Failed to duplicate collection: {message}",
        "Kollektion konnte nicht dupliziert werden: {message}",
    ),
    "content_js_failed_save_groups": (
        "Failed to save groups: {message}",
        "Gruppen konnten nicht gespeichert werden: {message}",
    ),
    "content_js_group_name_required": ("Please enter a group name", "Bitte einen Gruppennamen eingeben"),
}


def merge_lang():
    for loc, idx in [("en.json", 0), ("de.json", 1)]:
        path = LANG / loc
        data = json.loads(path.read_text())
        admin = data["admin"]
        for k, pair in STRINGS.items():
            admin[k] = pair[idx]
        data["admin"] = dict(sorted(admin.items()))
        path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n")


if __name__ == "__main__":
    merge_lang()
    print(f"Merged {len(STRINGS)} admin keys")
