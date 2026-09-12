#!/usr/bin/env python3
"""P85: account 2FA status/setup copy + collection editor CAPTCHA/languages."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "account_js_2fa_backup_codes_remaining": (
        "You have {count} backup codes remaining.",
        "Sie haben noch {count} Backup-Codes.",
    ),
    "account_js_2fa_enabled_badge": ("Enabled", "Aktiviert"),
    "account_js_2fa_enabled_on": ("Enabled on {date}", "Aktiviert am {date}"),
    "account_js_2fa_load_error": ("Error loading 2FA status", "Fehler beim Laden des 2FA-Status"),
    "account_js_2fa_manual_entry": ("Manual entry", "Manuelle Eingabe"),
    "account_js_2fa_not_enabled": (
        "Two-factor authentication is not enabled for your account.",
        "Zwei-Faktor-Authentifizierung ist für Ihr Konto nicht aktiviert.",
    ),
    "account_js_2fa_regenerate_codes_intro": (
        "Here are your new backup codes. Store them in a safe place. Each code can be used once.",
        "Ihre neuen Backup-Codes. Bewahren Sie sie sicher auf. Jeder Code ist einmal verwendbar.",
    ),
    "account_js_2fa_step1_help": (
        "Scan this QR code with your authenticator app.",
        "Scannen Sie diesen QR-Code mit Ihrer Authenticator-App.",
    ),
    "account_js_2fa_step1_title": ("Step 1: Scan QR Code", "Schritt 1: QR-Code scannen"),
    "account_js_2fa_step2_help": (
        "Enter the 6-digit code from your authenticator app:",
        "Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein:",
    ),
    "account_js_2fa_step2_title": ("Step 2: Verify Code", "Schritt 2: Code bestätigen"),
    "account_js_2fa_step3_help": (
        "Save these backup codes in a safe place. Each code can be used once.",
        "Bewahren Sie diese Backup-Codes sicher auf. Jeder Code ist einmal verwendbar.",
    ),
    "account_js_2fa_step3_title": ("Step 3: Save Backup Codes", "Schritt 3: Backup-Codes speichern"),
    "account_js_initializing_2fa": ("Initializing 2FA setup...", "2FA-Einrichtung wird gestartet …"),
    "col_editor_captcha_hcaptcha": ("hCaptcha", "hCaptcha"),
    "col_editor_captcha_none_honeypot": ("None (use honeypot only)", "Keine (nur Honeypot)"),
    "col_editor_captcha_optional_help": (
        "Optional: Add CAPTCHA protection (requires configuration)",
        "Optional: CAPTCHA-Schutz (Konfiguration erforderlich)",
    ),
    "col_editor_captcha_provider_label": ("CAPTCHA Provider", "CAPTCHA-Anbieter"),
    "col_editor_captcha_recaptcha_v3": ("Google reCAPTCHA v3", "Google reCAPTCHA v3"),
    "col_editor_captcha_secret_key_label": ("CAPTCHA Secret Key", "CAPTCHA-Geheimschlüssel"),
    "col_editor_captcha_secret_key_ph": ("Your CAPTCHA secret key", "Ihr CAPTCHA-Geheimschlüssel"),
    "col_editor_captcha_site_key_label": ("CAPTCHA Site Key", "CAPTCHA-Site-Schlüssel"),
    "col_editor_captcha_site_key_ph": ("Your CAPTCHA site key", "Ihr CAPTCHA-Site-Schlüssel"),
    "col_editor_entry_method_admin_only": ("Admin Only", "Nur Admin"),
    "col_editor_entry_method_public_api": ("Public API", "Öffentliche API"),
    "col_editor_language_input_help": (
        "Type a language code (e.g., en, de, fr) or name to see suggestions. Press Enter to add.",
        "Sprachcode (z. B. en, de, fr) oder Name eingeben. Enter zum Hinzufügen.",
    ),
    "col_editor_language_input_ph": (
        "Type language code or name...",
        "Sprachcode oder Name eingeben …",
    ),
    "col_editor_no_fields_slugs_yet": (
        "No fields defined yet. Add fields to see their slugs.",
        "Noch keine Felder. Felder hinzufügen, um Slugs zu sehen.",
    ),
    "col_editor_remove_language_aria": ("Remove language", "Sprache entfernen"),
    "col_editor_select_type": ("Select Type", "Typ wählen"),
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
