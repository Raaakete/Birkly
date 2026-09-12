#!/usr/bin/env python3
"""P85: media success toasts, entry revisions, settings empty states."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "entry_editor_js_loading_revisions": ("Loading revisions…", "Revisionen werden geladen …"),
    "entry_editor_js_no_revisions_yet": ("No saved revisions yet.", "Noch keine gespeicherten Revisionen."),
    "entry_editor_js_no_version_diff": (
        "No differences found between these versions.",
        "Keine Unterschiede zwischen diesen Versionen.",
    ),
    "entry_editor_js_review_notes_placeholder": ("Enter notes...", "Notizen eingeben …"),
    "entry_editor_no_fields_slugs": (
        "No fields defined yet. Add fields to see their slugs.",
        "Noch keine Felder definiert. Felder hinzufügen, um Slugs anzuzeigen.",
    ),
    "loading_collections": ("Loading collections…", "Kollektionen werden geladen …"),
    "media_js_alt_text_placeholder": (
        "Describe the image for screen readers",
        "Bild für Screenreader beschreiben",
    ),
    "media_js_folder_created_success": ("Folder created successfully.", "Ordner erfolgreich erstellt."),
    "media_js_folder_renamed_success": ("Folder renamed successfully.", "Ordner erfolgreich umbenannt."),
    "media_js_media_deleted_success": ("Media deleted successfully.", "Medium erfolgreich gelöscht."),
    "media_js_media_details_saved": ("Media details saved successfully.", "Mediendetails gespeichert."),
    "media_js_selected_media_deleted_success": (
        "Selected media deleted successfully.",
        "Ausgewählte Medien gelöscht.",
    ),
    "settings_analytics_select_preset_hint": (
        "Select a preset above and save, then visit your site to see reports.",
        "Oben eine Voreinstellung wählen und speichern, dann die Website für Berichte öffnen.",
    ),
    "settings_domains_rate_limit_after": (
        " tab to enable API access controls.",
        "-Tab, um API-Zugriffskontrollen zu aktivieren.",
    ),
    "settings_domains_rate_limit_before": (
        "No domains registered — per-domain rate limits are not configured. Register a domain in the ",
        "Keine Domains registriert — Ratenlimits pro Domain sind nicht konfiguriert. Domain im ",
    ),
    "settings_role_description_placeholder": ("Describe what this role can do", "Beschreiben Sie, was diese Rolle darf"),
    "settings_search_settings": ("Search settings", "Einstellungen durchsuchen"),
    "settings_js_no_active_plugins": ("No active plugins", "Keine aktiven Plugins"),
    "settings_js_no_delivery_hostnames": (
        "No delivery hostnames yet. Register a domain or configure hosting URLs in the wizard below.",
        "Noch keine Delivery-Hostnamen. Domain registrieren oder Hosting-URLs im Assistenten unten konfigurieren.",
    ),
    "settings_js_no_domains_add_hint": (
        'No domains yet. Click "Add domain" to connect one.',
        "Noch keine Domains. „Domain hinzufügen“ klicken, um eine zu verbinden.",
    ),
    "settings_js_no_domains_registered": ("No domains registered yet.", "Noch keine Domains registriert."),
    "settings_js_no_log_entries": ("No log entries found", "Keine Protokolleinträge gefunden"),
    "settings_js_no_protected_resources": (
        "No protected resources. All collections are accessible to plugins (subject to capability checks).",
        "Keine geschützten Ressourcen. Alle Kollektionen sind für Plugins zugänglich (vorbehaltlich Berechtigungsprüfungen).",
    ),
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
