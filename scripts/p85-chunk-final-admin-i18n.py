#!/usr/bin/env python3
"""P85 tier-1 final admin i18n: remaining collection/settings/entries/help/account keys."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    # Collection editor
    "col_editor_remove_sub_collection": ("Remove sub-collection", "Unterkollektion entfernen"),
    "col_editor_move_field_up": ("Move field up", "Feld nach oben"),
    "col_editor_move_field_down": ("Move field down", "Feld nach unten"),
    "col_editor_generate_api_key_title": ("Generate new API key", "Neuen API-Schlüssel erzeugen"),
    "col_editor_copy_api_key_title": ("Copy API key", "API-Schlüssel kopieren"),
    "col_editor_slug_label": ("Slug", "Slug"),
    "col_editor_icon_key_label": ("Icon key", "Symbol-Schlüssel"),
    "col_editor_new_field_default": ("New Field", "Neues Feld"),
    "col_editor_entry_method_label": ("Entry Method", "Erfassungsmethode"),
    "col_editor_templating_intro_before": (
        "For a detailed overview about templating, please visit ",
        "Für eine ausführliche Übersicht zur Vorlagensprache besuchen Sie ",
    ),
    "col_editor_templating_doc_link": ("the documentation site", "die Dokumentation"),
    # Settings — project / team
    "settings_project_overview_card": ("Overview", "Übersicht"),
    "settings_repository_title": ("Repository", "Repository"),
    "settings_project_new_file_btn": ("New file", "Neue Datei"),
    "settings_project_activity_filter_all": ("All", "Alle"),
    "settings_project_activity_filter_files": ("Files", "Dateien"),
    "settings_project_activity_filter_git": ("Git", "Git"),
    "settings_project_activity_filter_settings": ("Settings", "Einstellungen"),
    "settings_team_ai_connections_label": ("AI connections", "KI-Verbindungen"),
    "settings_team_ai_connections_none": ("None active", "Keine aktiv"),
    "settings_connection_meta_outbound": ("outbound · since {{connected}}", "Ausgehend · seit {{connected}}"),
    "settings_connection_meta_mcp": ("MCP · since {{connected}}", "MCP · seit {{connected}}"),
    "settings_db_user_label": ("Database User", "Datenbankbenutzer"),
    "settings_db_table_prefix_label": ("Table Prefix", "Tabellenpräfix"),
    "settings_storage_read_only": ("Read Only", "Nur lesen"),
    "settings_role_option_viewer": ("Viewer", "Betrachter"),
    "settings_role_option_editor": ("Editor", "Redakteur"),
    "settings_role_option_admin": ("Admin", "Administrator"),
    # Account
    "account_display_spacing_title": ("Display & spacing", "Anzeige & Abstände"),
    "account_display_spacing_description": (
        "Adjust text size and spacing across the admin interface.",
        "Textgröße und Abstände in der Admin-Oberfläche anpassen.",
    ),
    # Entries / entry detail dropdowns
    "entries_edit_this_collection": ("Edit this collection", "Diese Kollektion bearbeiten"),
    "entries_create_sub_collection": ("Create sub-collection", "Unterkollektion erstellen"),
    "entries_back_to_active": ("Back to active entries", "Zurück zu aktiven Einträgen"),
    "entries_view_trash": ("View trash", "Papierkorb anzeigen"),
    "entry_detail_edit_sub_collection": ("Edit this sub-collection", "Diese Unterkollektion bearbeiten"),
    "entry_detail_add_sub_collection_under": (
        "Add a new sub-collection under",
        "Neue Unterkollektion hinzufügen unter",
    ),
    # Help
    "help_loading_documentation": ("Loading documentation…", "Dokumentation wird geladen…"),
    # role_management DE fix (en stays)
    "role_management": ("Role Management", "Rollenverwaltung"),
}


def merge_lang() -> None:
    for loc, idx in [("en.json", 0), ("de.json", 1)]:
        path = LANG / loc
        data = json.loads(path.read_text(encoding="utf-8"))
        admin = data["admin"]
        for k, pair in STRINGS.items():
            admin[k] = pair[idx]
        data["admin"] = dict(sorted(admin.items()))
        path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")


if __name__ == "__main__":
    merge_lang()
    print(f"Merged {len(STRINGS)} admin keys")
