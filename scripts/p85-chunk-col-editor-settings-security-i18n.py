#!/usr/bin/env python3
"""P85: collection editor sub-collections/public API + settings security help."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "col_editor_add_sub_collection": ("Add Sub-Collection", "Unterkollektion hinzufügen"),
    "col_editor_auto_publish": ("Auto-publish", "Automatisch veröffentlichen"),
    "col_editor_auto_publish_if_public_api": (
        "Auto-publish submissions (if Public API)",
        "Einsendungen automatisch veröffentlichen (bei öffentlicher API)",
    ),
    "col_editor_auto_publish_submissions": ("Auto-publish submissions", "Einsendungen automatisch veröffentlichen"),
    "col_editor_create_sub_collection": ("Create Sub-Collection", "Unterkollektion erstellen"),
    "col_editor_edit_sub_collection": ("Edit Sub-Collection", "Unterkollektion bearbeiten"),
    "col_editor_icon_key_ph": ("e.g. document-text", "z. B. document-text"),
    "col_editor_icon_ph": ("e.g. 📝", "z. B. 📝"),
    "col_editor_name_ph_blog": ("e.g., Blog Posts", "z. B. Blogbeiträge"),
    "col_editor_name_ph_comments": ("e.g., Comments", "z. B. Kommentare"),
    "col_editor_name_ph_replies": ("e.g., Replies", "z. B. Antworten"),
    "col_editor_nested_sub_collection": ("Nested Sub-Collection", "Verschachtelte Unterkollektion"),
    "col_editor_new_sub_collection": ("New Sub-Collection", "Neue Unterkollektion"),
    "col_editor_new_sub_collection_default": ("New Sub-Collection", "Neue Unterkollektion"),
    "col_editor_parent_sub_collection": ("Parent Sub-Collection:", "Übergeordnete Unterkollektion:"),
    "col_editor_public_api_settings": ("Public API Settings", "Einstellungen öffentliche API"),
    "col_editor_save_sub_collection": ("Save Sub-Collection", "Unterkollektion speichern"),
    "col_editor_slug_ph_comments": ("e.g., comments", "z. B. comments"),
    "col_editor_slug_ph_replies": ("e.g., replies", "z. B. replies"),
    "col_editor_sub_collection": ("Sub-Collection", "Unterkollektion"),
    "col_editor_sub_collection_fields": ("Sub-Collection Fields", "Felder der Unterkollektion"),
    "col_editor_sub_collection_name": ("Sub-Collection Name", "Name der Unterkollektion"),
    "col_editor_sub_collections": ("Sub-Collections", "Unterkollektionen"),
    "settings_default_site_language_help": (
        "Default language used for your public site content and settings.",
        "Standardsprache für Website-Inhalte und Einstellungen.",
    ),
    "settings_role_plugin_visibility_help": (
        "Unchecked plugins are hidden from this role in the sidebar and blocked from plugin admin pages.",
        "Abgewählte Plugins sind in der Seitenleiste ausgeblendet und deren Admin-Bereiche gesperrt.",
    ),
    "settings_security_2fa_grace_help": (
        "Days users have to enable 2FA after the policy is turned on (default 30).",
        "Tage zum Aktivieren von 2FA nach Policy-Start (Standard 30).",
    ),
    "settings_security_2fa_grace_label": ("Grace period (days)", "Karenzzeit (Tage)"),
    "settings_security_2fa_policy_help": (
        "When enabled, all admin users must set up two-factor authentication. Users without 2FA receive a configurable grace period before login is blocked.",
        "Alle Admin-Benutzer müssen 2FA einrichten. Ohne 2FA gilt eine konfigurierbare Karenzzeit vor Sperre.",
    ),
    "settings_security_2fa_policy_title": ("Two-factor authentication policy", "Richtlinie für Zwei-Faktor-Authentifizierung"),
    "settings_security_ai_mcp_help": (
        "Site-wide hard limit on AI tool access. Overrides per-connection approval settings — tools are removed from MCP and admin AI chat when restricted.",
        "Site-weites Limit für KI-Tools. Überschreibt Freigaben pro Verbindung — Tools werden aus MCP und Admin-Chat entfernt.",
    ),
    "settings_security_ai_mcp_title": ("AI & MCP tools", "KI- & MCP-Tools"),
    "settings_security_ai_mode_disabled_after": (
        " — block all tool execution; MCP ",
        " — blockiert alle Tools; MCP ",
    ),
    "settings_security_ai_mode_disabled_strong": ("Disabled", "Deaktiviert"),
    "settings_security_ai_mode_disabled_after_code": (" is empty", " ist leer"),
    "settings_security_ai_mode_normal_after": (" — tools follow role permissions (default)", " — Tools folgen Rollenberechtigungen (Standard)"),
    "settings_security_ai_mode_normal_strong": ("Normal", "Normal"),
    "settings_security_ai_mode_readonly_after": (
        " — built-in AI chat works; no MCP/tools exposed or executed",
        " — integrierter KI-Chat; keine MCP-Tools",
    ),
    "settings_security_ai_mode_readonly_strong": ("Read-only chat", "Nur-Lese-Chat"),
    "settings_security_denylist_add_collection": ("Add collection", "Kollektion hinzufügen"),
    "settings_security_denylist_add_custom_pattern": ("Add custom pattern", "Eigenes Muster hinzufügen"),
    "settings_security_denylist_add_plugin": ("Add plugin", "Plugin hinzufügen"),
    "settings_security_denylist_deny_plugin_space": ("Deny plugin admin space", "Plugin-Admin-Bereich sperren"),
    "settings_security_denylist_help": (
        "Resources in the denylist are completely off-limits to all plugins, regardless of their granted capabilities. This is a hard security barrier that cannot be overridden.",
        "Denylist-Ressourcen sind für alle Plugins tabu — unabhängig von Berechtigungen.",
    ),
    "settings_security_denylist_protect_collection": ("Protect collection", "Kollektion schützen"),
    "settings_security_denylist_spaces_help": (
        "Deny plugin admin spaces (e.g. commerce, automation) to block sidebar injection and space-scoped hooks for that plugin.",
        "Plugin-Admin-Bereiche sperren (z. B. Commerce), um Seitenleiste und Hooks zu blockieren.",
    ),
    "settings_security_require_2fa_label": ("Require 2FA for all admin users", "2FA für alle Admin-Benutzer verlangen"),
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
