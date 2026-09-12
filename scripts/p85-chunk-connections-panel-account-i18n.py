#!/usr/bin/env python3
"""P85: connections panel copy, account 2FA setup, hub offers, col editor placeholders."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "account_js_2fa_code_placeholder": ("000000", "000000"),
    "account_js_2fa_done": ("Done", "Fertig"),
    "account_js_2fa_enable_confirm_btn": ("Enable 2FA", "2FA aktivieren"),
    "account_js_2fa_qr_alt": ("2FA QR Code", "2FA-QR-Code"),
    "account_js_2fa_scanned_confirm": ("I have scanned the code", "Code gescannt"),
    "account_js_2fa_verification_code_label": ("Verification Code", "Bestätigungscode"),
    "account_js_disable_2fa_btn": ("Disable 2FA", "2FA deaktivieren"),
    "account_js_enable_2fa_btn": ("Enable 2FA", "2FA aktivieren"),
    "account_js_regenerate_backup_btn": ("Regenerate Backup Codes", "Backup-Codes erneuern"),
    "col_editor_field_label_placeholder": ("Field label", "Feldbezeichnung"),
    "col_editor_field_name_placeholder": ("Field name", "Feldname"),
    "col_editor_optional_description_placeholder": ("Optional description", "Optionale Beschreibung"),
    "connections_panel_add_credential_btn": ("Add credential", "Anmeldedaten hinzufügen"),
    "connections_panel_advanced_options": ("Advanced options", "Erweiterte Optionen"),
    "connections_panel_alias_help": (
        "Leave blank to auto-generate. Plugins use this to pick a specific connection.",
        "Leer lassen für automatische Erzeugung. Plugins wählen damit eine Verbindung.",
    ),
    "connections_panel_alias_label": ("Internal reference name", "Interner Referenzname"),
    "connections_panel_alias_ph": ("auto-generated", "automatisch erzeugt"),
    "connections_panel_connection_enabled": ("Connection is enabled", "Verbindung ist aktiv"),
    "connections_panel_connection_name_help": (
        "A label you will recognize in the list — only you see this.",
        "Bezeichnung in der Liste — nur für Sie sichtbar.",
    ),
    "connections_panel_connection_name_label": ("Connection name", "Verbindungsname"),
    "connections_panel_connection_name_ph": ("e.g. My website email", "z. B. Website-E-Mail"),
    "connections_panel_default_checkbox_after": (
        " email connection for form notifications, Commerce, and other features",
        "-E-Mail-Verbindung für Formulare, Commerce und andere Funktionen",
    ),
    "connections_panel_default_checkbox_before": ("Use this as the ", "Als "),
    "connections_panel_default_checkbox_strong": ("default", "Standard"),
    "connections_panel_default_help_later": (
        "You can change the default later from the connection list.",
        "Standard später in der Verbindungsliste änderbar.",
    ),
    "connections_panel_email_help": (
        "Set up how Birkly sends email — form notifications, order receipts, password resets, and more. You only need to do this once.",
        "Einrichten, wie Birkly E-Mails sendet — Formulare, Bestellungen, Passwort-Reset usw. Einmalig nötig.",
    ),
    "connections_panel_email_title": ("Email", "E-Mail"),
    "connections_panel_from_email_help": ('Recipients see this as the "From" address.', "Empfänger sehen dies als Absenderadresse."),
    "connections_panel_from_email_label": ("Sender email address", "Absender-E-Mail"),
    "connections_panel_from_email_ph": ("hello@yourdomain.com", "hello@ihredomain.de"),
    "connections_panel_from_name_label": ("Sender name", "Absendername"),
    "connections_panel_from_name_ph": ("Your site name", "Name Ihrer Website"),
    "connections_panel_http_alias_help": (
        "Lowercase letters, numbers, dots, dashes and underscores.",
        "Kleinbuchstaben, Ziffern, Punkte, Bindestriche und Unterstriche.",
    ),
    "connections_panel_http_alias_label": ("Reference name", "Referenzname"),
    "connections_panel_http_alias_ph": ("e.g. shipping-api", "z. B. shipping-api"),
    "connections_panel_http_credential_type": ("Credential type", "Anmeldedatentyp"),
    "connections_panel_http_enabled": ("Enabled", "Aktiv"),
    "connections_panel_http_help": (
        "Saved login details for plugins that call external APIs (shipping, CRM, webhooks).",
        "Gespeicherte Zugangsdaten für Plugins mit externen APIs (Versand, CRM, Webhooks).",
    ),
    "connections_panel_http_name_ph": ("e.g. Shipping API", "z. B. Versand-API"),
    "connections_panel_http_title": ("HTTP credentials", "HTTP-Anmeldedaten"),
    "connections_panel_loading_credentials": ("Loading credentials…", "Anmeldedaten werden geladen …"),
    "connections_panel_loading_email": ("Loading email connections…", "E-Mail-Verbindungen werden geladen …"),
    "connections_panel_send_test_btn": ("Send test", "Test senden"),
    "connections_panel_send_test_label": ("Send test email to", "Test-E-Mail senden an"),
    "connections_panel_setup_email_btn": ("Set up email", "E-Mail einrichten"),
    "connections_panel_step_choose": ("1. Choose method", "1. Methode wählen"),
    "connections_panel_step_details": ("2. Enter details", "2. Details eingeben"),
    "connections_panel_step_test": ("3. Test & save", "3. Testen & speichern"),
    "connections_panel_test_email_ph": ("you@example.com", "sie@beispiel.de"),
    "connections_panel_wizard_intro_choose": (
        "How would you like Birkly to send email? Pick the option that matches your situation.",
        "Wie soll Birkly E-Mails senden? Option passend zu Ihrer Situation wählen.",
    ),
    "connections_panel_wizard_intro_test": (
        "Send yourself a quick test to make sure everything works.",
        "Kurzen Test an sich selbst senden, um die Einrichtung zu prüfen.",
    ),
    "connections_panel_wizard_pick_help_after": (" for production sites.", " für Produktivsites."),
    "connections_panel_wizard_pick_help_before": (
        "Not sure? Use Log only on your computer to try things out, or Resend / Postmark",
        "Unsicher? „Nur Protokoll“ lokal zum Testen oder Resend / Postmark",
    ),
    "connections_panel_wizard_steps_aria": ("Email setup steps", "Schritte der E-Mail-Einrichtung"),
    "connections_panel_wizard_title": ("Set up email", "E-Mail einrichten"),
    "marketplace_no_offers_found": ("No offers found.", "Keine Angebote gefunden."),
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
