#!/usr/bin/env python3
"""P85: email provider cards, connections panel chrome, modal cancel labels."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

PROVIDERS = {
    "log": {
        "title": ("Log only", "Nur Protokoll"),
        "subtitle": (
            "Save emails to a file on this server — nothing is sent. Great for trying Birkly on your computer.",
            "E-Mails werden auf diesem Server in eine Datei geschrieben — es wird nichts versendet. Ideal zum Ausprobieren.",
        ),
        "badge": ("Local / testing", "Lokal / Test"),
        "help": (
            "Emails are written to logs/mail.jsonl. Use this while developing; switch to a real provider before going live.",
            "E-Mails landen in logs/mail.jsonl. Für Entwicklung geeignet; vor Livegang echten Anbieter wählen.",
        ),
    },
    "smtp": {
        "title": ("Email server (SMTP)", "E-Mail-Server (SMTP)"),
        "subtitle": (
            "Use SMTP from your hosting company, Google Workspace, Microsoft 365, or any mail server.",
            "SMTP Ihres Hosters, Google Workspace, Microsoft 365 oder eines beliebigen Mailservers.",
        ),
        "badge": ("Most common", "Am häufigsten"),
        "help": (
            "You will need the SMTP host, port, username, and password from your email or hosting provider.",
            "SMTP-Host, Port, Benutzername und Passwort vom E-Mail- oder Hosting-Anbieter erforderlich.",
        ),
    },
    "resend": {
        "title": ("Resend", "Resend"),
        "subtitle": (
            "Modern email API — sign up at resend.com, verify your domain, and paste your API key.",
            "Moderne E-Mail-API — bei resend.com registrieren, Domain verifizieren, API-Schlüssel einfügen.",
        ),
        "badge": ("Recommended", "Empfohlen"),
        "help": (
            "Create an API key in the Resend dashboard. Your sender address must use a domain you verified there.",
            "API-Schlüssel im Resend-Dashboard erstellen. Absenderadresse muss eine dort verifizierte Domain nutzen.",
        ),
    },
    "postmark": {
        "title": ("Postmark", "Postmark"),
        "subtitle": (
            "Reliable transactional email — paste your Server API token from Postmark.",
            "Zuverlässige Transaktions-E-Mails — Server-API-Token von Postmark einfügen.",
        ),
        "badge": ("Transactional", "Transaktional"),
        "help": (
            "In Postmark, open your server → API tokens → copy the Server API token.",
            "In Postmark: Server → API-Tokens → Server-API-Token kopieren.",
        ),
    },
    "ses": {
        "title": ("Amazon SES", "Amazon SES"),
        "subtitle": (
            "Send through AWS Simple Email Service with your access keys and region.",
            "Versand über AWS Simple Email Service mit Zugangsschlüsseln und Region.",
        ),
        "badge": ("AWS", "AWS"),
        "help": (
            "Create IAM credentials with SES send permissions. Use the region where your SES identity is verified.",
            "IAM-Zugangsdaten mit SES-Senderecht anlegen. Region wählen, in der die SES-Identität verifiziert ist.",
        ),
    },
    "mailgun": {
        "title": ("Mailgun", "Mailgun"),
        "subtitle": (
            "Send via Mailgun — enter your domain and private API key.",
            "Versand über Mailgun — Domain und privaten API-Schlüssel eingeben.",
        ),
        "badge": ("API", "API"),
        "help": (
            "Find your API key and sending domain in the Mailgun control panel.",
            "API-Schlüssel und Sende-Domain im Mailgun-Panel finden.",
        ),
    },
    "mail": {
        "title": ("PHP mail()", "PHP mail()"),
        "subtitle": (
            "Use the server built-in mail function. Often unreliable — only use if your host requires it.",
            "PHP-eigene mail()-Funktion. Oft unzuverlässig — nur wenn der Hoster es verlangt.",
        ),
        "badge": ("Advanced", "Erweitert"),
        "help": (
            "Many shared hosts block or limit PHP mail(). Prefer SMTP or an email service for production.",
            "Viele Shared Hosts blockieren PHP mail(). Für Produktion SMTP oder E-Mail-Dienst bevorzugen.",
        ),
    },
}

STRINGS: dict[str, tuple[str, str]] = {
    "connections_panel_add_http_credential": ("Add HTTP credential", "HTTP-Anmeldedaten hinzufügen"),
    "connections_panel_save_connection": ("Save connection", "Verbindung speichern"),
    "connections_panel_wizard_back": ("Back", "Zurück"),
    "connections_panel_wizard_continue": ("Continue", "Weiter"),
    "connections_js_test_failed_unknown": ("Test failed: {message}", "Test fehlgeschlagen: {message}"),
}

for prov, fields in PROVIDERS.items():
    for field, pair in fields.items():
        STRINGS[f"connections_provider_{prov}_{field}"] = pair


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
