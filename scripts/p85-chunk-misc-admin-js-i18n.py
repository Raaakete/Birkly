#!/usr/bin/env python3
"""P85: updates, analytics, media confirms, onboarding skip, account confirms."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "updates_js_invalid_response": ("Invalid response from update server", "Ungültige Antwort vom Update-Server"),
    "updates_js_request_failed": ("Request failed", "Anfrage fehlgeschlagen"),
    "updates_js_update_service_unreachable": (
        "Could not reach the update service.",
        "Update-Dienst nicht erreichbar.",
    ),
    "updates_js_check_failed": ("Could not check for updates", "Updates konnten nicht geprüft werden"),
    "updates_js_check_failed_msg": ("Failed to check for updates", "Prüfung auf Updates fehlgeschlagen"),
    "updates_js_no_update_download": ("No update available to download.", "Kein Update zum Herunterladen verfügbar."),
    "updates_js_download_failed": ("Download failed", "Download fehlgeschlagen"),
    "updates_js_download_success_hint": (
        "Update downloaded ({size} KB). Click Install Update to apply.",
        "Update heruntergeladen ({size} KB). Klicken Sie auf Update installieren.",
    ),
    "updates_js_download_update_btn": ("Download Update", "Update herunterladen"),
    "updates_js_download_first": ("Download an update first.", "Laden Sie zuerst ein Update herunter."),
    "updates_js_confirm_install": (
        "Install Birkly {version}? A backup will be created first.",
        "Birkly {version} installieren? Zuerst wird ein Backup erstellt.",
    ),
    "updates_js_install_success_reload": (
        "Update installed successfully. The page will reload.",
        "Update erfolgreich installiert. Die Seite wird neu geladen.",
    ),
    "updates_js_install_failed": ("Installation failed", "Installation fehlgeschlagen"),
    "updates_js_checking": ("Checking for updates...", "Suche nach Updates …"),
    "updates_js_check_failed_badge": ("Update check failed", "Update-Prüfung fehlgeschlagen"),
    "updates_js_remote_unavailable": ("Remote check unavailable", "Remote-Prüfung nicht verfügbar"),
    "updates_js_up_to_date_locally": ("You are up to date locally", "Lokal ist alles aktuell"),
    "updates_js_no_release_notes": ("No release notes available.", "Keine Release Notes verfügbar."),
    "updates_js_update_available_badge": ("Update available", "Update verfügbar"),
    "updates_js_version_available": ("Version {version} is available", "Version {version} ist verfügbar"),
    "updates_js_running_version_you": ("You are running version {version}.", "Sie nutzen Version {version}."),
    "updates_js_published_at": ("Published: {date}", "Veröffentlicht: {date}"),
    "updates_js_up_to_date_badge": ("Up to date", "Aktuell"),
    "updates_js_up_to_date_title": ("You are up to date", "Sie sind auf dem neuesten Stand"),
    "updates_js_running_version": ("Running version {version}.", "Version {version} läuft."),
    "analytics_js_confirm_delete_tracking": (
        "Are you sure you want to delete this tracking configuration? This will stop collecting analytics for this website.",
        "Tracking-Konfiguration wirklich löschen? Die Analytics-Erfassung für diese Website wird beendet.",
    ),
    "analytics_js_failed_update_tracking": ("Failed to update tracking", "Tracking konnte nicht aktualisiert werden"),
    "analytics_js_err_update_tracking": ("Error updating tracking configuration", "Fehler beim Aktualisieren der Tracking-Konfiguration"),
    "analytics_js_failed_delete_tracking": ("Failed to delete tracking", "Tracking konnte nicht gelöscht werden"),
    "analytics_js_err_delete_tracking": ("Error deleting tracking configuration", "Fehler beim Löschen der Tracking-Konfiguration"),
    "analytics_js_fill_all_fields": ("Please fill in all fields", "Bitte alle Felder ausfüllen"),
    "analytics_js_failed_create_tracking": ("Failed to create tracking", "Tracking konnte nicht erstellt werden"),
    "analytics_js_err_create_tracking": ("Error creating tracking configuration", "Fehler beim Erstellen der Tracking-Konfiguration"),
    "analytics_js_empty_not_tracked": ("No websites are being tracked yet.", "Es werden noch keine Websites erfasst."),
    "analytics_js_add_first_website": ("Add Your First Website", "Erste Website hinzufügen"),
    "analytics_js_disabled": ("Disabled", "Deaktiviert"),
    "analytics_js_tracking_code_label": ("Tracking Code", "Tracking-Code"),
    "analytics_js_copy_code": ("Copy code", "Code kopieren"),
    "analytics_js_embed_hint": (
        "Add this code to your website before the closing </body> tag",
        "Fügen Sie diesen Code vor dem schließenden </body>-Tag Ihrer Website ein",
    ),
    "analytics_js_enable": ("Enable", "Aktivieren"),
    "analytics_js_disable": ("Disable", "Deaktivieren"),
    "media_js_confirm_folder_has_files": (
        'Folder "{name}" contains files.\n\nClick OK to move files to root, or Cancel to delete them with the folder.',
        'Ordner „{name}“ enthält Dateien.\n\nOK: Dateien ins Stammverzeichnis verschieben. Abbrechen: Dateien mit dem Ordner löschen.',
    ),
    "media_js_confirm_delete_folder": ('Delete folder "{name}"?', 'Ordner „{name}“ löschen?'),
    "media_js_confirm_delete_folder_and_files": (
        'Delete folder "{name}" and delete its files?',
        'Ordner „{name}“ und enthaltene Dateien löschen?',
    ),
    "media_js_confirm_delete_folder_move_files": (
        'Delete folder "{name}" and move its files to root?',
        'Ordner „{name}“ löschen und Dateien ins Stammverzeichnis verschieben?',
    ),
    "media_js_confirm_delete_selected": ("Delete the selected media items?", "Ausgewählte Medienelemente löschen?"),
    "media_js_confirm_delete_file": ("Delete this media file?", "Diese Mediendatei löschen?"),
    "onboarding_js_confirm_skip_setup": (
        "Skip setup? You can finish later from the dashboard.",
        "Setup überspringen? Sie können später im Dashboard fortfahren.",
    ),
    "account_js_confirm_unlink_cloud": (
        "Unlink your Birkly cloud account from this CMS installation?",
        "Birkly-Cloud-Konto von dieser CMS-Installation trennen?",
    ),
    "account_js_confirm_remove_avatar": ("Are you sure you want to remove your avatar?", "Avatar wirklich entfernen?"),
}


def merge_lang():
    for loc, key in [("en.json", 0), ("de.json", 1)]:
        path = LANG / loc
        data = json.loads(path.read_text())
        admin = data["admin"]
        for k, pair in STRINGS.items():
            admin[k] = pair[key]
        data["admin"] = dict(sorted(admin.items()))
        path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n")


def extend_helper(path: Path, fn: str, marker: str):
    text = path.read_text()
    if f"{fn}(key, fallback, params)" in text or "params.forEach" in text.split(f"function {fn}")[1][:400]:
        return
    old = f"""    function {fn}(key, fallback) {{
        if (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function') {{
            return Birkly.i18n.t('admin.' + key, fallback);
        }}
        return fallback != null ? fallback : key;
    }}"""
    new = f"""    function {fn}(key, fallback, params) {{
        let text = (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')
            ? Birkly.i18n.t('admin.' + key, fallback)
            : (fallback != null ? fallback : key);
        if (params && typeof params === 'object') {{
            Object.keys(params).forEach(function (name) {{
                text = text.split('{{' + name + '}}').join(String(params[name]));
            }});
        }}
        return text;
    }}"""
    if old in text:
        text = text.replace(old, new)
        path.write_text(text)


def patch_updates():
    path = ROOT / "admin/js/updates.js"
    extend_helper(path, "updT", "")
    text = path.read_text()
    repl = [
        ("throw new Error('Invalid response from update server');", "throw new Error(updT('updates_js_invalid_response', 'Invalid response from update server'));"),
        ("const msg = result.message || 'Request failed';", "const msg = result.message || updT('updates_js_request_failed', 'Request failed');"),
        ("message || 'Unknown error'", "message || updT('ai_conn_js_unknown_error', 'Unknown error')"),
        ("message || 'Could not reach the update service.')", "message || updT('updates_js_update_service_unreachable', 'Could not reach the update service.'))"),
        ("showError(result && result.message ? result.message : 'Could not check for updates');", "showError(result && result.message ? result.message : updT('updates_js_check_failed', 'Could not check for updates'));"),
        ("showError(result.message || 'Could not check for updates');", "showError(result.message || updT('updates_js_check_failed', 'Could not check for updates'));"),
        ("showError('Could not check for updates');", "showError(updT('updates_js_check_failed', 'Could not check for updates'));"),
        ("showError(error.message || 'Failed to check for updates');", "showError(error.message || updT('updates_js_check_failed_msg', 'Failed to check for updates'));"),
        ("BirklyFeedback.toast('No update available to download.', 'error')", "BirklyFeedback.toast(updT('updates_js_no_update_download', 'No update available to download.'), 'error')"),
        ("BirklyFeedback.toast(result.message || 'Download failed', 'error')", "BirklyFeedback.toast(result.message || updT('updates_js_download_failed', 'Download failed'), 'error')"),
        ("BirklyFeedback.toast(error.message || 'Download failed', 'error')", "BirklyFeedback.toast(error.message || updT('updates_js_download_failed', 'Download failed'), 'error')"),
        ("BirklyFeedback.toast('Download an update first.', 'error')", "BirklyFeedback.toast(updT('updates_js_download_first', 'Download an update first.'), 'error')"),
        (
            "if (!(await BirklyFeedback.confirm('Install Birkly ' + updateData.latest_version + '? A backup will be created first.'))) {",
            "if (!(await BirklyFeedback.confirm(updT('updates_js_confirm_install', 'Install Birkly {version}? A backup will be created first.', { version: updateData.latest_version })))) {",
        ),
        (
            "BirklyFeedback.toast('Update installed successfully. The page will reload.', 'success')",
            "BirklyFeedback.toast(updT('updates_js_install_success_reload', 'Update installed successfully. The page will reload.'), 'success')",
        ),
        ("BirklyFeedback.toast(result.message || 'Installation failed', 'error')", "BirklyFeedback.toast(result.message || updT('updates_js_install_failed', 'Installation failed'), 'error')"),
        ("BirklyFeedback.toast(error.message || 'Installation failed', 'error')", "BirklyFeedback.toast(error.message || updT('updates_js_install_failed', 'Installation failed'), 'error')"),
        ("'</svg> Download Update';", "'</svg> ' + updT('updates_js_download_update_btn', 'Download Update');"),
    ]
    old_hint = """                        '<p class="u-fg-success">Update downloaded (' +
                        Math.round((result.data.size || 0) / 1024) +
                        ' KB). Click <strong>Install Update</strong> to apply.</p>';"""
    new_hint = """                        '<p class="u-fg-success">' + escapeHtml(updT('updates_js_download_success_hint', 'Update downloaded ({size} KB). Click Install Update to apply.', { size: Math.round((result.data.size || 0) / 1024) })) + '</p>';"""
    for old, new in repl:
        text = text.replace(old, new)
    if old_hint in text:
        text = text.replace(old_hint, new_hint)
    elif "updates_js_download_success_hint" not in text:
        print("WARN updates: download success hint block")
    path.write_text(text)


def patch_analytics():
    path = ROOT / "admin/js/analytics.js"
    extend_helper(path, "anaT", "")
    text = path.read_text()
    repl = [
        (
            "if (await BirklyFeedback.confirm('Are you sure you want to delete this tracking configuration? This will stop collecting analytics for this website.')) {",
            "if (await BirklyFeedback.confirm(anaT('analytics_js_confirm_delete_tracking', 'Are you sure you want to delete this tracking configuration? This will stop collecting analytics for this website.'))) {",
        ),
        (
            "BirklyFeedback.toast('Error: ' + (data.message || 'Failed to update tracking'), 'error');",
            "BirklyFeedback.toast(anaT('feedback_error_prefix', 'Error:') + ' ' + (data.message || anaT('analytics_js_failed_update_tracking', 'Failed to update tracking')), 'error');",
        ),
        (
            "BirklyFeedback.toast('Error updating tracking configuration', 'error')",
            "BirklyFeedback.toast(anaT('analytics_js_err_update_tracking', 'Error updating tracking configuration'), 'error')",
        ),
        (
            "BirklyFeedback.toast('Error: ' + (data.message || 'Failed to delete tracking'), 'error');",
            "BirklyFeedback.toast(anaT('feedback_error_prefix', 'Error:') + ' ' + (data.message || anaT('analytics_js_failed_delete_tracking', 'Failed to delete tracking')), 'error');",
        ),
        (
            "BirklyFeedback.toast('Error deleting tracking configuration', 'error')",
            "BirklyFeedback.toast(anaT('analytics_js_err_delete_tracking', 'Error deleting tracking configuration'), 'error')",
        ),
        (
            "BirklyFeedback.toast('Please fill in all fields', 'warning')",
            "BirklyFeedback.toast(anaT('analytics_js_fill_all_fields', 'Please fill in all fields'), 'warning')",
        ),
        (
            "BirklyFeedback.toast('Error: ' + (data.message || 'Failed to create tracking'), 'error');",
            "BirklyFeedback.toast(anaT('feedback_error_prefix', 'Error:') + ' ' + (data.message || anaT('analytics_js_failed_create_tracking', 'Failed to create tracking')), 'error');",
        ),
        (
            "BirklyFeedback.toast('Error creating tracking configuration', 'error')",
            "BirklyFeedback.toast(anaT('analytics_js_err_create_tracking', 'Error creating tracking configuration'), 'error')",
        ),
    ]
    for old, new in repl:
        if old not in text:
            print("WARN analytics missing:", old[:50])
        else:
            text = text.replace(old, new)
    path.write_text(text)


def patch_media():
    path = ROOT / "admin/js/media-page.js"
    text = path.read_text()
    repl = [
        (
            "const choice = await BirklyFeedback.confirm('Folder \"' + folderName + '\" contains files.\\n\\nClick OK to move files to root, or Cancel to delete them with the folder.');",
            "const choice = await BirklyFeedback.confirm(mdT('media_js_confirm_folder_has_files', 'Folder \"{name}\" contains files.\\n\\nClick OK to move files to root, or Cancel to delete them with the folder.', { name: folderName }));",
        ),
        (
            "? 'Delete folder \"' + folderName + '\" and ' + (handleFiles === 'delete' ? 'delete its files' : 'move its files to root') + '?'",
            "? (handleFiles === 'delete' ? mdT('media_js_confirm_delete_folder_and_files', 'Delete folder \"{name}\" and delete its files?', { name: folderName }) : mdT('media_js_confirm_delete_folder_move_files', 'Delete folder \"{name}\" and move its files to root?', { name: folderName }))",
        ),
        (
            ": 'Delete folder \"' + folderName + '\"?';",
            ": mdT('media_js_confirm_delete_folder', 'Delete folder \"{name}\"?', { name: folderName });",
        ),
        (
            "if (!(await BirklyFeedback.confirm('Delete the selected media items?'))) {",
            "if (!(await BirklyFeedback.confirm(mdT('media_js_confirm_delete_selected', 'Delete the selected media items?')))) {",
        ),
        (
            "if (!(await BirklyFeedback.confirm('Delete this media file?'))) {",
            "if (!(await BirklyFeedback.confirm(mdT('media_js_confirm_delete_file', 'Delete this media file?')))) {",
        ),
    ]
    for old, new in repl:
        if old not in text:
            print("WARN media missing:", old[:50])
        else:
            text = text.replace(old, new)
    path.write_text(text)


def patch_onboarding():
    path = ROOT / "admin/js/onboarding.js"
    text = path.read_text()
    old = "if (!(await BirklyFeedback.confirm('Skip setup? You can finish later from the dashboard.'))) return;"
    new = "if (!(await BirklyFeedback.confirm(obT('onboarding_js_confirm_skip_setup', 'Skip setup? You can finish later from the dashboard.')))) return;"
    if old in text:
        text = text.replace(old, new)
    path.write_text(text)


def patch_account():
    path = ROOT / "admin/js/account-settings.js"
    text = path.read_text()
    repl = [
        (
            "if (!(await BirklyFeedback.confirm('Unlink your Birkly cloud account from this CMS installation?'))) return;",
            "if (!(await BirklyFeedback.confirm(accountT('account_js_confirm_unlink_cloud', 'Unlink your Birkly cloud account from this CMS installation?')))) return;",
        ),
        (
            "if (!(await BirklyFeedback.confirm('Are you sure you want to remove your avatar?'))) {",
            "if (!(await BirklyFeedback.confirm(accountT('account_js_confirm_remove_avatar', 'Are you sure you want to remove your avatar?')))) {",
        ),
    ]
    for old, new in repl:
        text = text.replace(old, new)
    path.write_text(text)


def main():
    merge_lang()
    patch_updates()
    patch_analytics()
    patch_media()
    patch_onboarding()
    patch_account()
    print("P85 misc admin JS i18n:", len(STRINGS), "keys")


if __name__ == "__main__":
    main()
