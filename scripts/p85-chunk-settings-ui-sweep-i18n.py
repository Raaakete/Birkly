#!/usr/bin/env python3
"""P85: remaining settings.html UI labels + collection remove-field chrome."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "col_editor_remove_field": ("Remove field", "Feld entfernen"),
    "settings_analytics_consent_label": ("Consent banner text (Standard+)", "Einwilligungstext (Standard+)"),
    "settings_analytics_export_csv": ("Export CSV", "CSV exportieren"),
    "settings_analytics_report_scope": ("Report scope", "Berichtsbereich"),
    "settings_analytics_report_title": ("Report", "Bericht"),
    "settings_analytics_retention_label": ("Retention days (optional override)", "Aufbewahrungstage (optional)"),
    "settings_analytics_retention_ph": ("Preset default", "Voreinstellung"),
    "settings_analytics_scope_all_domains": ("All enabled domains", "Alle aktiven Domains"),
    "settings_analytics_section_title": ("Analytics", "Analytics"),
    "settings_api_auth_label": ("Authentication", "Authentifizierung"),
    "settings_api_auth_registered_suffix": ("registered", "registriert"),
    "settings_api_auth_text_before": (
        "Admin API requests use session cookies after login. Public content API access requires registered domains",
        "Admin-API-Anfragen nutzen Sitzungs-Cookies nach Anmeldung. Öffentlicher API-Zugriff erfordert registrierte Domains",
    ),
    "settings_api_auth_text_link": ("Manage domains", "Domains verwalten"),
    "settings_api_endpoint_label": ("API endpoint", "API-Endpunkt"),
    "settings_api_endpoint_text": ("The API is available at:", "Die API ist erreichbar unter:"),
    "settings_api_rate_limit_label": ("Rate limiting", "Ratenbegrenzung"),
    "settings_api_rate_limit_text_after": (" tab.", "-Tab."),
    "settings_api_rate_limit_text_before": (
        "Per-domain rate limits apply to registered API domains. Configure limits in the ",
        "Ratenlimits gelten pro registrierter Domain. Limits im ",
    ),
    "settings_api_rate_limit_text_link": ("Domains", "Domains"),
    "settings_create_role_btn": ("Create Role", "Rolle erstellen"),
    "settings_create_user_btn": ("Create User", "Benutzer erstellen"),
    "settings_danger_zone_desc_before": (
        "Permanently delete all files in ",
        "Alle Dateien in ",
    ),
    "settings_danger_zone_desc_after": (
        ". This cannot be undone. Download a ZIP first if you need a backup.",
        " endgültig löschen. Nicht rückgängig. Vorher ZIP sichern.",
    ),
    "settings_danger_zone_title": ("Danger zone", "Gefahrenzone"),
    "settings_delete_project_btn": ("Delete project", "Projekt löschen"),
    "settings_developer_export_btn": ("Export template pack", "Vorlagenpaket exportieren"),
    "settings_developer_export_include": (
        "Include content collections in template export (never users or secrets)",
        "Inhaltskollektionen im Vorlagenexport (nie Benutzer oder Geheimnisse)",
    ),
    "settings_developer_mode_help": (
        "Off by default. Unlocks export and diagnostic tools for advanced installs.",
        "Standardmäßig aus. Export- und Diagnose-Tools für erweiterte Installationen.",
    ),
    "settings_developer_mode_title": ("Developer mode", "Entwicklermodus"),
    "settings_developer_smoke_btn": ("API / MCP smoke", "API-/MCP-Schnelltest"),
    "settings_domain_analytics_track": (
        "Track visitor statistics for this domain.",
        "Besucherstatistik für diese Domain erfassen.",
    ),
    "settings_domain_analytics_track_long": (
        "Track visitor statistics for this domain. Zero-dependency, self-hosted analytics with complete privacy.",
        "Besucherstatistik für diese Domain. Self-hosted Analytics ohne externe Abhängigkeiten.",
    ),
    "settings_domain_cms_alias_option": (
        "CMS alias — admin, API, and birkly-client.js on this hostname",
        "CMS-Alias — Admin, API und birkly-client.js auf diesem Hostnamen",
    ),
    "settings_domain_delivery_role_label": ("Delivery role", "Delivery-Rolle"),
    "settings_domain_public_alias_option": (
        "Public alias — visitors see the site on this hostname",
        "Öffentlicher Alias — Besucher sehen die Site auf diesem Hostnamen",
    ),
    "settings_domain_read_write": ("Read & Write", "Lesen & Schreiben"),
    "settings_edit_user_email": ("Email Address", "E-Mail-Adresse"),
    "settings_invite_expires_label": ("Invitation Expires In", "Einladung läuft ab in"),
    "settings_project_activity_card_title": ("Recent activity", "Letzte Aktivitäten"),
    "settings_project_ai_connections_btn": ("AI connections", "KI-Verbindungen"),
    "settings_project_browse_files_title": ("Browse & edit files", "Dateien durchsuchen & bearbeiten"),
    "settings_project_cache_ttl_label": ("Cache TTL (seconds)", "Cache-TTL (Sekunden)"),
    "settings_project_download_btn": ("Download project", "Projekt herunterladen"),
    "settings_project_file_placeholder": ("Select a file to preview or edit", "Datei zum Anzeigen oder Bearbeiten wählen"),
    "settings_project_git_refresh_btn": ("Refresh status", "Status aktualisieren"),
    "settings_project_hosting_birkly": ("Birkly hosted (project/)", "Birkly-gehostet (project/)"),
    "settings_project_hosting_external": ("External (hosted elsewhere)", "Extern (woanders gehostet)"),
    "settings_project_hosting_from_delivery": ("Hosting (from Site delivery)", "Hosting (aus Site delivery)"),
    "settings_project_hosting_urls_btn": ("Hosting URLs", "Hosting-URLs"),
    "settings_project_invalidate_ssr": ("Invalidate SSR cache", "SSR-Cache leeren"),
    "settings_project_render_auto": ("Auto (recommended)", "Auto (empfohlen)"),
    "settings_project_render_client": ("Client-side", "Clientseitig"),
    "settings_project_render_mode_label": ("Render mode", "Render-Modus"),
    "settings_project_render_auto_short": ("Auto", "Auto"),
    "settings_project_render_server_default": ("Server-side (default)", "Serverseitig (Standard)"),
    "settings_project_visit_directory": ("Site root (no index.html)", "Site-Root (ohne index.html)"),
    "settings_project_visit_index": ("Include index.html", "index.html einbeziehen"),
    "settings_security_lockout_label": ("Lockout Time (minutes)", "Sperrzeit (Minuten)"),
    "settings_site_language_label": ("Site language", "Site-Sprache"),
    "settings_update_user_btn": ("Update User", "Benutzer aktualisieren"),
    "settings_delete_user_btn": ("Delete User", "Benutzer löschen"),
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
