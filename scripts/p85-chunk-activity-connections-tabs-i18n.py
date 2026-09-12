#!/usr/bin/env python3
"""P85: activity empty, connections banners, settings tabs, cleanup orphan keys."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

ORPHAN_KEYS = ("settings_domains_rate_limit_none",)

STRINGS: dict[str, tuple[str, str]] = {
    "activity_js_no_activity_found": ("No activity found", "Keine Aktivität gefunden"),
    "connections_js_connection_test_success": ("Connection test succeeded.", "Verbindungstest erfolgreich."),
    "connections_js_default_email_body": (
        "Sending via {provider} from {from_email}. Form notifications and plugins use this connection.",
        "Versand über {provider} von {from_email}. Formularbenachrichtigungen und Plugins nutzen diese Verbindung.",
    ),
    "connections_js_default_email_title": ("Default email: {name}", "Standard-E-Mail: {name}"),
    "connections_js_emails_logged_body": (
        "Your default connection ({name}) uses Log only. Switch to SMTP or an email service before going live.",
        "Die Standardverbindung ({name}) nutzt „Nur Protokoll“. Vor dem Livegang auf SMTP oder einen E-Mail-Dienst wechseln.",
    ),
    "connections_js_emails_logged_title": ("Emails are logged, not sent", "E-Mails werden protokolliert, nicht versendet"),
    "connections_js_err_load_credentials": ("Could not load credentials.", "Anmeldedaten konnten nicht geladen werden."),
    "connections_js_err_load_email_connections": (
        "Could not load email connections. Please refresh the page.",
        "E-Mail-Verbindungen konnten nicht geladen werden. Bitte Seite neu laden.",
    ),
    "connections_js_no_email_connection_body": (
        "Form submissions and plugin emails will not reach a real inbox until you set up email below.",
        "Formularübermittlungen und Plugin-E-Mails erreichen kein Postfach, bis E-Mail unten eingerichtet ist.",
    ),
    "connections_js_no_email_connection_title": ("No email connection yet", "Noch keine E-Mail-Verbindung"),
    "connections_js_no_email_setup_hint": (
        "Click Set up email above and follow the steps. It only takes a minute.",
        "Oben „E-Mail einrichten“ wählen und den Schritten folgen — dauert nur eine Minute.",
    ),
    "connections_js_no_email_setup_title": ("No email set up yet", "E-Mail noch nicht eingerichtet"),
    "connections_js_no_extra_provider_settings": ("No extra settings needed for this method.", "Für diese Methode sind keine weiteren Einstellungen nötig."),
    "connections_js_test_email_prompt": ("Send a test email to:", "Test-E-Mail senden an:"),
    "connections_js_test_email_sent_inbox": (
        "Test email sent. Check your inbox (and spam folder).",
        "Test-E-Mail gesendet. Posteingang (und Spam) prüfen.",
    ),
    "email_picker_no_connection": ("No email connection set up yet", "Noch keine E-Mail-Verbindung eingerichtet"),
    "entry_editor_js_submit_review": ("Submit", "Senden"),
    "settings_tab_analytics": ("Analytics", "Analytics"),
    "settings_tab_connections": ("Connections", "Verbindungen"),
    "settings_tab_integrations": ("Integrations", "Integrationen"),
    "settings_tab_project": ("Project", "Projekt"),
    "settings_tab_security": ("Security", "Sicherheit"),
    "settings_tab_site_delivery": ("Site delivery", "Site-Auslieferung"),
    "settings_tab_team_access": ("Team access", "Team & Zugriff"),
    "settings_view_activity": ("View activity", "Aktivität anzeigen"),
}


def merge_lang():
    for loc, idx in [("en.json", 0), ("de.json", 1)]:
        path = LANG / loc
        data = json.loads(path.read_text())
        admin = data["admin"]
        for k in ORPHAN_KEYS:
            admin.pop(k, None)
        for k, pair in STRINGS.items():
            admin[k] = pair[idx]
        data["admin"] = dict(sorted(admin.items()))
        path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n")


if __name__ == "__main__":
    merge_lang()
    print(f"Merged {len(STRINGS)} keys, removed {len(ORPHAN_KEYS)} orphan keys")
