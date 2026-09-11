#!/usr/bin/env python3
"""P85: account 2FA, entry editor version/review, media empty state, onboarding starters."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "account_js_err_disabling_2fa": ("Error disabling 2FA: {message}", "Fehler beim Deaktivieren von 2FA: {message}"),
    "account_js_err_enabling_2fa": ("Error enabling 2FA: {message}", "Fehler beim Aktivieren von 2FA: {message}"),
    "account_js_err_regenerating_backup": (
        "Error regenerating backup codes: {message}",
        "Fehler beim Erneuern der Backup-Codes: {message}",
    ),
    "account_js_err_remove_avatar": ("Error removing avatar", "Fehler beim Entfernen des Avatars"),
    "account_js_2fa_code_invalid": ("Please enter a valid 6-digit code", "Bitte einen gültigen 6-stelligen Code eingeben"),
    "account_js_setting_up_2fa": ("Setting up 2FA...", "2FA wird eingerichtet …"),
    "entry_editor_js_confirm_restore_version": (
        "Are you sure you want to restore this version? Current version will be saved as a new version.",
        "Diese Version wirklich wiederherstellen? Die aktuelle Version wird zuerst im Verlauf gespeichert.",
    ),
    "entry_editor_js_err_history_missing_context": (
        "Cannot view history: missing entry or collection information",
        "Verlauf nicht verfügbar: Eintrags- oder Kollektionsinformationen fehlen",
    ),
    "entry_editor_js_err_load_diff": ("Error loading diff: {message}", "Fehler beim Laden des Vergleichs: {message}"),
    "entry_editor_js_err_load_history": (
        "Error loading version history: {message}",
        "Fehler beim Laden des Versionsverlaufs: {message}",
    ),
    "entry_editor_js_err_restore_version": ("Error restoring version: {message}", "Fehler beim Wiederherstellen: {message}"),
    "entry_editor_js_err_review_missing_context": (
        "Cannot submit review: missing entry or collection information",
        "Review nicht möglich: Eintrags- oder Kollektionsinformationen fehlen",
    ),
    "entry_editor_js_err_review_submit": ("Error submitting review: {message}", "Fehler beim Senden der Review: {message}"),
    "entry_editor_js_err_save_entry": ("Error saving entry: {message}", "Fehler beim Speichern des Eintrags: {message}"),
    "entry_editor_js_err_unexpected_response": ("Unexpected response format", "Unerwartetes Antwortformat"),
    "entry_editor_js_review_submitted_reload": (
        "Review action submitted successfully. Reloading page...",
        "Review-Aktion gesendet. Seite wird neu geladen …",
    ),
    "entry_editor_js_select_different_versions": ("Please select two different versions", "Bitte zwei verschiedene Versionen wählen"),
    "entry_editor_js_select_two_versions": ("Please select both versions to compare", "Bitte beide Versionen zum Vergleich wählen"),
    "entry_editor_js_version_restored_reload": (
        "Version restored successfully. Reloading page...",
        "Version wiederhergestellt. Seite wird neu geladen …",
    ),
    "entry_editor_sidebar_js_restore_confirm": (
        "Restore version {version}? Your current draft will be saved to history first.",
        "Version {version} wiederherstellen? Der aktuelle Entwurf wird zuerst im Verlauf gespeichert.",
    ),
    "media_js_empty_hint": ("Upload files or change the current filters.", "Dateien hochladen oder Filter anpassen."),
    "media_js_empty_title": ("No media found", "Keine Medien gefunden"),
    "media_js_folder_name_placeholder": ("Folder name", "Ordnername"),
    "media_js_new_folder_title": ("New folder", "Neuer Ordner"),
    "media_js_new_subfolder_title": ("New subfolder", "Neuer Unterordner"),
    "media_js_prompt_create_label": ("Create", "Erstellen"),
    "media_js_rename_folder_message": ("Enter a new name for this folder.", "Neuen Namen für diesen Ordner eingeben."),
    "media_js_subfolder_name_placeholder": ("Subfolder name", "Unterordnername"),
    "media_js_upload_failed": ("Upload failed.", "Upload fehlgeschlagen."),
    "onboarding_starter_blank_hint": ("No demo content", "Keine Demo-Inhalte"),
    "onboarding_starter_blank_label": ("Start blank", "Leer starten"),
    "onboarding_starter_marsk_hint": ("Slow living journal + demo site", "Slow-Living-Journal + Demo-Website"),
    "onboarding_starter_marsk_label": ("Marsk", "Marsk"),
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
