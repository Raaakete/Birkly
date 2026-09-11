#!/usr/bin/env python3
"""P85: ai-chat-button.js, ai-presence.js toasts/UI, plugins-page.js leftovers."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

CHAT_HELPER = """
    function chatT(key, fallback, params) {
        let text = (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')
            ? Birkly.i18n.t('admin.' + key, fallback)
            : (fallback != null ? fallback : key);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }

"""

# key -> (en, de)
STRINGS: dict[str, tuple[str, str]] = {
    # — ai chat FAB & chrome —
    "ai_chat_js_assist": ("Assist", "Assistenz"),
    "ai_chat_js_open_assist_menu": ("Open assist menu", "Assistenzmenü öffnen"),
    "ai_chat_js_open_assistant": ("Open AI Assistant", "KI-Assistent öffnen"),
    "ai_chat_js_close_assistant": ("Close AI Assistant", "KI-Assistent schließen"),
    "ai_chat_js_close": ("Close", "Schließen"),
    "ai_chat_js_send_feedback": ("Send feedback", "Feedback senden"),
    "ai_chat_js_aria_close_pending": ("Close AI Assistant ({count} pending)", "KI-Assistent schließen ({count} ausstehend)"),
    "ai_chat_js_aria_open_pending": ("Open AI Assistant ({count} pending)", "KI-Assistent öffnen ({count} ausstehend)"),
    "ai_chat_js_history": ("History", "Verlauf"),
    "ai_chat_js_console": ("Console", "Konsole"),
    "ai_chat_js_approvals": ("Approvals", "Freigaben"),
    "ai_chat_js_new_chat": ("New chat", "Neuer Chat"),
    "ai_chat_js_fullscreen": ("Fullscreen", "Vollbild"),
    "ai_chat_js_toggle_fullscreen": ("Toggle fullscreen", "Vollbild umschalten"),
    "ai_chat_js_exit_fullscreen": ("Exit fullscreen", "Vollbild beenden"),
    "ai_chat_js_enter_fullscreen": ("Enter fullscreen", "Vollbild aktivieren"),
    "ai_chat_js_close_panel": ("Close panel", "Panel schließen"),
    "ai_chat_js_back_to_chat": ("Back to chat", "Zurück zum Chat"),
    "ai_chat_js_ai_connection": ("AI connection", "KI-Verbindung"),
    "ai_chat_js_choose_connection": ("Choose AI connection", "KI-Verbindung wählen"),
    "ai_chat_js_loading_connections": ("Loading connections...", "Verbindungen werden geladen …"),
    "ai_chat_js_welcome": (
        "Select an AI connection to start chatting. The AI can help you create and edit content.",
        "Wählen Sie eine KI-Verbindung, um zu chatten. Die KI kann Inhalte erstellen und bearbeiten.",
    ),
    "ai_chat_js_console_hint": (
        "Action results and errors from this session",
        "Aktionsergebnisse und Fehler dieser Sitzung",
    ),
    "ai_chat_js_clear": ("Clear", "Leeren"),
    "ai_chat_js_loading_approvals": ("Loading approvals…", "Freigaben werden geladen …"),
    "ai_chat_js_waiting_ai": ("Waiting for AI…", "Warte auf KI …"),
    "ai_chat_js_ai_thinking": ("AI is thinking…", "KI denkt nach …"),
    "ai_chat_js_ai_working": ("AI is working…", "KI arbeitet …"),
    "ai_chat_js_still_working": ("Still working…", "Arbeitet noch …"),
    "ai_chat_js_composer_placeholder": (
        "Ask the AI to create or edit content… Paste or drop images and files here.",
        "Bitten Sie die KI, Inhalte zu erstellen oder zu bearbeiten … Bilder und Dateien hier einfügen oder ablegen.",
    ),
    "ai_chat_js_add_image": ("Add image", "Bild hinzufügen"),
    "ai_chat_js_add_document": ("Add document", "Dokument hinzufügen"),
    "ai_chat_js_choose_model": ("Choose model", "Modell wählen"),
    "ai_chat_js_model": ("Model", "Modell"),
    "ai_chat_js_send_message": ("Send message", "Nachricht senden"),
    "ai_chat_js_cancel_response": ("Cancel response", "Antwort abbrechen"),
    "ai_chat_js_send": ("Send", "Senden"),
    "ai_chat_js_cancel": ("Cancel", "Abbrechen"),
    "ai_chat_js_search_models": ("Search models…", "Modelle suchen …"),
    "ai_chat_js_remove_attachment": ("Remove", "Entfernen"),
    "ai_chat_js_add_image_hint": (
        "Add image — or paste / drop into the chat box",
        "Bild hinzufügen — oder in die Chatbox einfügen/ablegen",
    ),
    "ai_chat_js_images_not_supported": (
        "Images not supported for this provider",
        "Bilder werden von diesem Anbieter nicht unterstützt",
    ),
    "ai_chat_js_add_doc_hint": (
        "Add document — or paste / drop into the chat box",
        "Dokument hinzufügen — oder in die Chatbox einfügen/ablegen",
    ),
    "ai_chat_js_docs_not_supported": (
        "Documents not supported for this provider",
        "Dokumente werden von diesem Anbieter nicht unterstützt",
    ),
    "ai_chat_js_learn_more": ("Learn more", "Mehr erfahren"),
    "ai_chat_js_no_description": ("No description", "Keine Beschreibung"),
    "ai_chat_js_server_error": ("Server error", "Serverfehler"),
    "ai_chat_js_session_expired": (
        "Your session expired. Please refresh the page and log in again.",
        "Ihre Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und melden Sie sich erneut an.",
    ),
    "ai_chat_js_timeout_504": (
        'The request timed out (504). Large site builds can take a minute — try again, use a faster model, or ask for fewer files at once (e.g. "write index.html first").',
        'Zeitüberschreitung (504). Große Site-Builds können eine Minute dauern — erneut versuchen, schnelleres Modell wählen oder weniger Dateien auf einmal anfordern (z. B. „zuerst index.html schreiben").',
    ),
    "ai_chat_js_http_error": ("HTTP Error {status}: {message}", "HTTP-Fehler {status}: {message}"),
    "ai_chat_js_unknown_error": ("Unknown error", "Unbekannter Fehler"),
    "ai_chat_js_confirm_switch_connection": (
        "Switch AI connection? This starts a new chat.",
        "KI-Verbindung wechseln? Dies startet einen neuen Chat.",
    ),
    "ai_chat_js_confirm_new_chat": (
        "Start a new chat? This will clear the current conversation.",
        "Neuen Chat starten? Die aktuelle Unterhaltung wird gelöscht.",
    ),
    "ai_chat_js_confirm_approve_action": ("Approve this action?", "Diese Aktion freigeben?"),
    "ai_chat_js_attach_vision_required": (
        "Image paste is not supported for this connection. Use a vision-capable provider.",
        "Einfügen von Bildern wird für diese Verbindung nicht unterstützt. Nutzen Sie einen vision-fähigen Anbieter.",
    ),
    "ai_chat_js_attach_images_only": ("Only image files can be attached as images.", "Als Bilder können nur Bilddateien angehängt werden."),
    "ai_chat_js_attach_image_too_large": ("Image is too large (max 4 MB).", "Bild ist zu groß (max. 4 MB)."),
    "ai_chat_js_attach_max": ("Maximum {max} attachments per message.", "Maximal {max} Anhänge pro Nachricht."),
    "ai_chat_js_attach_read_image_failed": ("Could not read image: {message}", "Bild konnte nicht gelesen werden: {message}"),
    "ai_chat_js_attach_docs_not_supported": (
        "Document attachments are not supported for this connection.",
        "Dokumentanhänge werden für diese Verbindung nicht unterstützt.",
    ),
    "ai_chat_js_attach_text_docs_only": (
        "Only text-based documents are supported (.txt, .md, .json, .csv, etc.).",
        "Nur textbasierte Dokumente werden unterstützt (.txt, .md, .json, .csv usw.).",
    ),
    "ai_chat_js_attach_read_doc_failed": ("Could not read document: {message}", "Dokument konnte nicht gelesen werden: {message}"),
    "ai_chat_js_attach_unsupported_file": (
        "Unsupported file: {name}. Paste images or text documents.",
        "Nicht unterstützte Datei: {name}. Bilder oder Textdokumente einfügen.",
    ),
    "ai_chat_js_attached_prefix": ("Attached: ", "Angehängt: "),
    "ai_chat_js_failed_load_connection": ("Failed to load connection", "Verbindung konnte nicht geladen werden"),
    "ai_chat_js_failed_load_connection_msg": ("Failed to load connection: {message}", "Verbindung konnte nicht geladen werden: {message}"),
    "ai_chat_js_no_connection": ("No connection selected", "Keine Verbindung ausgewählt"),
    "ai_chat_js_select_connection_first": ("Select an AI connection first", "Zuerst eine KI-Verbindung auswählen"),
    "ai_chat_js_request_cancelled": ("Request cancelled.", "Anfrage abgebrochen."),
    "ai_chat_js_stream_ended": ("Stream ended without a response.", "Stream endete ohne Antwort."),
    "ai_chat_js_invalid_response": ("Invalid response from server.", "Ungültige Antwort vom Server."),
    "ai_chat_js_prepared_actions": ("Prepared actions for your review (see below).", "Aktionen zur Prüfung vorbereitet (siehe unten)."),
    "ai_chat_js_prepared_actions_short": ("Prepared actions for your review.", "Aktionen zur Prüfung vorbereitet."),
    "ai_chat_js_actions_failed": (
        "Warning: {count} action(s) failed. See the Console tab for details.",
        "Warnung: {count} Aktion(en) fehlgeschlagen. Details im Tab Konsole.",
    ),
    "ai_chat_js_actions_pending": (
        "{count} action(s) pending approval. Open the Approvals tab to review.",
        "{count} Aktion(en) warten auf Freigabe. Tab Freigaben öffnen zum Prüfen.",
    ),
    "ai_chat_js_actions_executed": ("{count} action(s) executed successfully.", "{count} Aktion(en) erfolgreich ausgeführt."),
    "ai_chat_js_action_approved": ("Action approved and executed successfully.", "Aktion freigegeben und erfolgreich ausgeführt."),
    "ai_chat_js_failed_approve": ("Failed to approve: {message}", "Freigabe fehlgeschlagen: {message}"),
    "ai_chat_js_error_approving": ("Error approving action", "Fehler bei der Freigabe"),
    "ai_chat_js_action_rejected": ("Action rejected.", "Aktion abgelehnt."),
    "ai_chat_js_failed_reject": ("Failed to reject: {message}", "Ablehnung fehlgeschlagen: {message}"),
    "ai_chat_js_error_rejecting": ("Error rejecting action", "Fehler beim Ablehnen"),
    "ai_chat_js_loading": ("Loading", "Laden"),
    "ai_chat_js_chat_title": ("Chat", "Chat"),
    "ai_chat_js_review_attached": ("Please review the attached file(s).", "Bitte prüfen Sie die angehängten Datei(en)."),
    # activity tips
    "ai_chat_js_tip_storage": (
        "Did you know Birkly can run with MySQL or PostgreSQL instead of flat files?",
        "Wussten Sie schon? Birkly kann mit MySQL oder PostgreSQL statt Flatfiles laufen.",
    ),
    "ai_chat_js_tip_project_files": (
        "The AI can read and edit files in your project/ folder for site builds.",
        "Die KI kann Dateien in Ihrem project/-Ordner für Site-Builds lesen und bearbeiten.",
    ),
    "ai_chat_js_tip_attachments": (
        "You can attach images and documents to chat messages for context.",
        "Sie können Bilder und Dokumente für Kontext an Chat-Nachrichten anhängen.",
    ),
    "ai_chat_js_tip_approvals": (
        "Use the Approvals tab to review AI-proposed changes before they run.",
        "Nutzen Sie den Tab Freigaben, um KI-Vorschläge vor der Ausführung zu prüfen.",
    ),
    "ai_chat_js_tip_backup": (
        "Collections and entries live under content/ — back them up regularly.",
        "Collections und Einträge liegen unter content/ — sichern Sie sie regelmäßig.",
    ),
    "ai_chat_js_tip_models": (
        "Switch models from the sparkle menu in the composer for faster or smarter replies.",
        "Wechseln Sie im Composer über das Funkeln-Menü das Modell für schnellere oder bessere Antworten.",
    ),
    "ai_chat_js_tip_templating": (
        "Birkly templates use {for each entry in 'collection'} syntax for dynamic pages.",
        "Birkly-Vorlagen nutzen {for each entry in 'collection'}-Syntax für dynamische Seiten.",
    ),
    # — Agent Guide / presence JS —
    "ag_js_upload_failed": ("Upload failed", "Upload fehlgeschlagen"),
    "ag_js_uploading": ("Uploading {name}…", "Lade {name} hoch …"),
    "ag_js_doc_added_source": ("Document added as source of truth", "Dokument als maßgebliche Quelle hinzugefügt"),
    "ag_js_select_collection_entry": ("Select collection and entry for CMS link", "Collection und Eintrag für CMS-Link wählen"),
    "ag_js_enter_label_or_url": ("Enter a label or URL", "Bezeichnung oder URL eingeben"),
    "ag_js_link_check_complete": ("Link check complete", "Linkprüfung abgeschlossen"),
    "ag_js_link_suggestions_ready": ("Link suggestions ready — review in AI Assist", "Linkvorschläge bereit — in KI-Assist prüfen"),
    "ag_js_ai_cancelled": ("AI request cancelled", "KI-Anfrage abgebrochen"),
    "ag_js_invalid_json": ("Invalid JSON in {path}", "Ungültiges JSON in {path}"),
    "ag_js_draft_saved": ("Draft saved", "Entwurf gespeichert"),
    "ag_js_select_connection_or_fill_gaps": (
        "Select an AI connection for this action, or use Fill gaps for basic suggestions",
        "KI-Verbindung für diese Aktion wählen oder „Lücken füllen“ für einfache Vorschläge nutzen",
    ),
    "ag_js_no_suggestions_hint": (
        "No suggestions — try linking CMS content or running Auto-fill",
        "Keine Vorschläge — CMS-Inhalte verknüpfen oder Auto-Ausfüllen ausführen",
    ),
    "ag_js_suggestion_accepted": ("Suggestion accepted", "Vorschlag übernommen"),
    "ag_js_draft_proposal_applied": ("Draft proposal applied", "Entwurfsvorschlag angewendet"),
    "ag_js_all_suggestions_accepted": ("All suggestions accepted", "Alle Vorschläge übernommen"),
    "ag_js_select_connection_sidebar": ("Select an AI connection in the sidebar first", "Zuerst eine KI-Verbindung in der Seitenleiste wählen"),
    "ag_js_no_suggestions_field": ("No suggestions returned for this field", "Keine Vorschläge für dieses Feld"),
    "ag_js_field_updated": ("Field updated", "Feld aktualisiert"),
    "ag_js_no_collections": ("No CMS collections found — create content collections first", "Keine CMS-Collections — zuerst Content-Collections anlegen"),
    "ag_js_no_proposals": ("No proposals generated", "Keine Vorschläge erzeugt"),
    "ag_js_select_one_collection": ("Select at least one collection", "Mindestens eine Collection wählen"),
    "ag_js_select_connection_cms": ("Select an AI connection for CMS generation", "KI-Verbindung für CMS-Generierung wählen"),
    "ag_js_proposals_ready": ("{n} proposal(s) ready to review", "{n} Vorschlag/Vorschläge zur Prüfung bereit"),
    "ag_js_suggestions_count": ("{n} suggestion(s) to review", "{n} Vorschlag/Vorschläge zur Prüfung"),
    "ag_js_proposals_profile": (
        "{n} proposal(s) for a complete profile — review and accept",
        "{n} Vorschlag/Vorschläge für ein vollständiges Profil — prüfen und übernehmen",
    ),
    "ag_js_no_proposals_context": (
        "No proposals generated — try Fill gaps or add more context first",
        "Keine Vorschläge — zuerst Lücken füllen oder mehr Kontext hinzufügen",
    ),
    "ag_js_media_picker_unavailable": ("Media picker unavailable — reload the page", "Medienauswahl nicht verfügbar — Seite neu laden"),
    "ag_js_product_saved": ("Product saved — remember to save draft", "Produkt gespeichert — Entwurf speichern nicht vergessen"),
    "ag_js_connect_cms_first": ("Connect CMS entries first (Context tab)", "Zuerst CMS-Einträge verbinden (Tab Kontext)"),
    "ag_js_source_added": ("Source added", "Quelle hinzugefügt"),
    "ag_js_pick_media_first": ("Pick a media file first", "Zuerst eine Mediendatei wählen"),
    "ag_js_confirm_delete_product": ("Delete this product/service?", "Dieses Produkt/diese Dienstleistung löschen?"),
    "ag_js_confirm_remove_media": ("Remove this media context?", "Diesen Medienkontext entfernen?"),
    "ag_js_select_claim": ("Select a claim to view details.", "Behauptung wählen, um Details anzuzeigen."),
    "ag_js_claim_not_found": ("Claim not found.", "Behauptung nicht gefunden."),
    "ag_js_no_collections_match": ("No collections match your search", "Keine Collections passen zur Suche"),
    "ag_js_failed_load_tools": ("Failed to load tools config", "Werkzeug-Konfiguration konnte nicht geladen werden"),
    "ag_js_failed_load_presence": ("Failed to load AI Presence", "AI Presence konnte nicht geladen werden"),
    # — plugins hub —
    "hub_js_reviews_site_hint": (
        "Reviews are tied to this Birkly site installation. Only people who installed and use the plugin here can submit a review.",
        "Bewertungen gelten für diese Birkly-Installation. Nur Nutzer, die das Plugin hier installiert haben, können bewerten.",
    ),
    "hub_js_missing_plugin_info": ("Missing plugin information", "Plugin-Informationen fehlen"),
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


def patch_presence():
    path = ROOT / "admin/js/ai-presence.js"
    text = path.read_text()
    if "function t(key, fallback, params)" not in text:
        text = text.replace(
            "    function t(key, fallback) {\n"
            "        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {\n"
            "            return global.Birkly.i18n.t('admin.' + key, fallback);\n"
            "        }\n"
            "        return fallback || key;\n"
            "    }",
            "    function t(key, fallback, params) {\n"
            "        var text;\n"
            "        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {\n"
            "            text = global.Birkly.i18n.t('admin.' + key, fallback);\n"
            "        } else {\n"
            "            text = fallback || key;\n"
            "        }\n"
            "        if (params && typeof params === 'object') {\n"
            "            Object.keys(params).forEach(function (name) {\n"
            "                text = text.split('{' + name + '}').join(String(params[name]));\n"
            "            });\n"
            "        }\n"
            "        return text;\n"
            "    }",
        )
    repl = [
        ("statusEl.textContent = 'Uploading ' + file.name + '…';",
         "statusEl.textContent = t('ag_js_uploading', 'Uploading {name}…', { name: file.name });"),
        ("throw new Error(result.message || 'Upload failed');",
         "throw new Error(result.message || t('ag_js_upload_failed', 'Upload failed'));"),
        ("toast('Document added as source of truth', 'success');",
         "toast(t('ag_js_doc_added_source', 'Document added as source of truth'), 'success');"),
        ("toast(err.message || 'Upload failed', 'error');",
         "toast(err.message || t('ag_js_upload_failed', 'Upload failed'), 'error');"),
        ("toast('Select collection and entry for CMS link', 'warning');",
         "toast(t('ag_js_select_collection_entry', 'Select collection and entry for CMS link'), 'warning');"),
        ("toast('Enter a label or URL', 'warning');",
         "toast(t('ag_js_enter_label_or_url', 'Enter a label or URL'), 'warning');"),
        ("toast('Link check complete', 'success');",
         "toast(t('ag_js_link_check_complete', 'Link check complete'), 'success');"),
        ("toast('Link suggestions ready — review in AI Assist', 'success');",
         "toast(t('ag_js_link_suggestions_ready', 'Link suggestions ready — review in AI Assist'), 'success');"),
        ("toast('AI request cancelled', 'info');",
         "toast(t('ag_js_ai_cancelled', 'AI request cancelled'), 'info');"),
        ("toast('Invalid JSON in ' + path, 'error');",
         "toast(t('ag_js_invalid_json', 'Invalid JSON in {path}', { path: path }), 'error');"),
        ("toast('Draft saved', 'success');",
         "toast(t('ag_js_draft_saved', 'Draft saved'), 'success');"),
        ("toast('Select an AI connection for this action, or use Fill gaps for basic suggestions', 'warning');",
         "toast(t('ag_js_select_connection_or_fill_gaps', 'Select an AI connection for this action, or use Fill gaps for basic suggestions'), 'warning');"),
        ("toast('No suggestions — try linking CMS content or running Auto-fill', 'info');",
         "toast(t('ag_js_no_suggestions_hint', 'No suggestions — try linking CMS content or running Auto-fill'), 'info');"),
        ("toast('Suggestion accepted', 'success');",
         "toast(t('ag_js_suggestion_accepted', 'Suggestion accepted'), 'success');"),
        ("toast('Draft proposal applied', 'success');",
         "toast(t('ag_js_draft_proposal_applied', 'Draft proposal applied'), 'success');"),
        ("toast('All suggestions accepted', 'success');",
         "toast(t('ag_js_all_suggestions_accepted', 'All suggestions accepted'), 'success');"),
        ("toast('Select an AI connection in the sidebar first', 'warning');",
         "toast(t('ag_js_select_connection_sidebar', 'Select an AI connection in the sidebar first'), 'warning');"),
        ("toast('No suggestions returned for this field', 'info');",
         "toast(t('ag_js_no_suggestions_field', 'No suggestions returned for this field'), 'info');"),
        ("toast('Field updated', 'success');",
         "toast(t('ag_js_field_updated', 'Field updated'), 'success');"),
        ("toast('No CMS collections found — create content collections first', 'warning');",
         "toast(t('ag_js_no_collections', 'No CMS collections found — create content collections first'), 'warning');"),
        ("toast('No proposals generated', 'info');",
         "toast(t('ag_js_no_proposals', 'No proposals generated'), 'info');"),
        ("toast('Select at least one collection', 'warning');",
         "toast(t('ag_js_select_one_collection', 'Select at least one collection'), 'warning');"),
        ("toast('Select an AI connection for CMS generation', 'warning');",
         "toast(t('ag_js_select_connection_cms', 'Select an AI connection for CMS generation'), 'warning');"),
        ("toast(state.suggestions.length + ' suggestion(s) to review', 'info');",
         "toast(t('ag_js_suggestions_count', '{n} suggestion(s) to review', { n: state.suggestions.length }), 'info');"),
        ("toast(state.suggestions.length + ' proposal(s) ready to review', 'success');",
         "toast(t('ag_js_proposals_ready', '{n} proposal(s) ready to review', { n: state.suggestions.length }), 'success');"),
        ("toast(state.suggestions.length + ' proposal(s) for a complete profile — review and accept', 'success');",
         "toast(t('ag_js_proposals_profile', '{n} proposal(s) for a complete profile — review and accept', { n: state.suggestions.length }), 'success');"),
        ("toast('No proposals generated — try Fill gaps or add more context first', 'info');",
         "toast(t('ag_js_no_proposals_context', 'No proposals generated — try Fill gaps or add more context first'), 'info');"),
        ("toast('Media picker unavailable — reload the page', 'error');",
         "toast(t('ag_js_media_picker_unavailable', 'Media picker unavailable — reload the page'), 'error');"),
        ("toast('Product saved — remember to save draft', 'success');",
         "toast(t('ag_js_product_saved', 'Product saved — remember to save draft'), 'success');"),
        ("toast('Connect CMS entries first (Context tab)', 'warning');",
         "toast(t('ag_js_connect_cms_first', 'Connect CMS entries first (Context tab)'), 'warning');"),
        ("toast('Source added', 'success');",
         "toast(t('ag_js_source_added', 'Source added'), 'success');"),
        ("toast('Pick a media file first', 'warning');",
         "toast(t('ag_js_pick_media_first', 'Pick a media file first'), 'warning');"),
        ("confirmAsync('Delete this product/service?').then(function (ok) {",
         "confirmAsync(t('ag_js_confirm_delete_product', 'Delete this product/service?')).then(function (ok) {"),
        ("confirmAsync('Remove this media context?').then(function (ok) {",
         "confirmAsync(t('ag_js_confirm_remove_media', 'Remove this media context?')).then(function (ok) {"),
        ("el.textContent = 'Select a claim to view details.';",
         "el.textContent = t('ag_js_select_claim', 'Select a claim to view details.');"),
        ("el.textContent = 'Claim not found.';",
         "el.textContent = t('ag_js_claim_not_found', 'Claim not found.');"),
        ("hint.textContent = 'No collections match your search';",
         "hint.textContent = t('ag_js_no_collections_match', 'No collections match your search');"),
        ("if (res.status !== 'ok') throw new Error(res.message || 'Failed to load tools config');",
         "if (res.status !== 'ok') throw new Error(res.message || t('ag_js_failed_load_tools', 'Failed to load tools config'));"),
        ("toast(err.message || 'Failed to load tools config', 'error');",
         "toast(err.message || t('ag_js_failed_load_tools', 'Failed to load tools config'), 'error');"),
        ("toast(e.message || 'Failed to load AI Presence', 'error');",
         "toast(e.message || t('ag_js_failed_load_presence', 'Failed to load AI Presence'), 'error');"),
    ]
    for old, new in repl:
        if old not in text:
            print("WARN presence missing:", old[:60])
        else:
            text = text.replace(old, new)
    path.write_text(text)


def patch_plugins():
    path = ROOT / "admin/js/plugins-page.js"
    text = path.read_text()
    repl = [
        ("summaryEl.textContent = 'Reviews are tied to this Birkly site installation. Only people who installed and use the plugin here can submit a review.';",
         "summaryEl.textContent = t('hub_js_reviews_site_hint', 'Reviews are tied to this Birkly site installation. Only people who installed and use the plugin here can submit a review.');"),
        ("showMessage('Missing plugin information', 'error');",
         "showMessage(t('hub_js_missing_plugin_info', 'Missing plugin information'), 'error');"),
        ("if (!(await BirklyFeedback.confirm('Are you sure you want to delete this plugin? This action cannot be undone.'))) {",
         "if (!(await BirklyFeedback.confirm(t('settings_js_confirm_delete_plugin', 'Are you sure you want to delete this plugin? This action cannot be undone.')))) {"),
    ]
    text = text.replace(
        "'<button type=\"button\" class=\"btn btn-sm btn-danger\" data-plugin-action=\"delete\" data-plugin-id=\"' + escapeHtml(plugin.id) + '\">Delete</button>') +",
        "'<button type=\"button\" class=\"btn btn-sm btn-danger\" data-plugin-action=\"delete\" data-plugin-id=\"' + escapeHtml(plugin.id) + '\">' + escapeHtml(t('delete', 'Delete')) + '</button>') +",
    )
    for old, new in repl[:3]:
        text = text.replace(old, new)
    path.write_text(text)


def patch_chat():
    path = ROOT / "admin/js/ai-chat-button.js"
    text = path.read_text()
    if "function chatT" not in text:
        text = text.replace("    'use strict';\n\n", "    'use strict';\n" + CHAT_HELPER + "\n")
    repl = [
        ("button.setAttribute('aria-label', 'Open assist menu');", "button.setAttribute('aria-label', chatT('ai_chat_js_open_assist_menu', 'Open assist menu'));"),
        ("button.title = 'Assist';", "button.title = chatT('ai_chat_js_assist', 'Assist');"),
        ("button.setAttribute('aria-label', 'Open AI Assistant');", "button.setAttribute('aria-label', chatT('ai_chat_js_open_assistant', 'Open AI Assistant'));"),
        ("button.title = 'AI Assistant';", "button.title = chatT('ai_chat_js_open_assistant', 'Open AI Assistant');"),
        ("button.setAttribute('aria-label', 'Close AI Assistant');", "button.setAttribute('aria-label', chatT('ai_chat_js_close_assistant', 'Close AI Assistant'));"),
        ("button.title = 'Close';", "button.title = chatT('ai_chat_js_close', 'Close');"),
        ("let message = bodyText || 'Server error';", "let message = bodyText || chatT('ai_chat_js_server_error', 'Server error');"),
        ("return 'Your session expired. Please refresh the page and log in again.';",
         "return chatT('ai_chat_js_session_expired', 'Your session expired. Please refresh the page and log in again.');"),
        ("return 'The request timed out (504). Large site builds can take a minute — try again, use a faster model, or ask for fewer files at once (e.g. \"write index.html first\").';",
         "return chatT('ai_chat_js_timeout_504', 'The request timed out (504). Large site builds can take a minute — try again, use a faster model, or ask for fewer files at once (e.g. \"write index.html first\").');"),
        ("return 'HTTP Error ' + status + ': ' + message;",
         "return chatT('ai_chat_js_http_error', 'HTTP Error {status}: {message}', { status: status, message: message });"),
        ("result?.message || result?.error || result?.data?.message || 'Unknown error'",
         "result?.message || result?.error || result?.data?.message || chatT('ai_chat_js_unknown_error', 'Unknown error')"),
        ("button.setAttribute('aria-label', `Close AI Assistant, ${count} pending approval${count === 1 ? '' : 's'}`);",
         "button.setAttribute('aria-label', chatT('ai_chat_js_aria_close_pending', 'Close AI Assistant ({count} pending)', { count: count }));"),
        ("button.setAttribute('aria-label', `Open AI Assistant, ${count} pending approval${count === 1 ? '' : 's'}`);",
         "button.setAttribute('aria-label', chatT('ai_chat_js_aria_open_pending', 'Open AI Assistant ({count} pending)', { count: count }));"),
        ("approval.description || 'No description')",
         "approval.description || chatT('ai_chat_js_no_description', 'No description'))"),
        ("const titles = { history: 'History', console: 'Console', approvals: 'Approvals' };",
         "const titles = { history: chatT('ai_chat_js_history', 'History'), console: chatT('ai_chat_js_console', 'Console'), approvals: chatT('ai_chat_js_approvals', 'Approvals') };"),
        ("await BirklyFeedback.confirm('Switch AI connection? This starts a new chat.')",
         "await BirklyFeedback.confirm(chatT('ai_chat_js_confirm_switch_connection', 'Switch AI connection? This starts a new chat.'))"),
        ("await BirklyFeedback.confirm('Start a new chat? This will clear the current conversation.')",
         "await BirklyFeedback.confirm(chatT('ai_chat_js_confirm_new_chat', 'Start a new chat? This will clear the current conversation.'))"),
        ("notifyAttachmentIssue('Image paste is not supported for this connection. Use a vision-capable provider.');",
         "notifyAttachmentIssue(chatT('ai_chat_js_attach_vision_required', 'Image paste is not supported for this connection. Use a vision-capable provider.'));"),
        ("notifyAttachmentIssue('Only image files can be attached as images.');",
         "notifyAttachmentIssue(chatT('ai_chat_js_attach_images_only', 'Only image files can be attached as images.'));"),
        ("notifyAttachmentIssue('Image is too large (max 4 MB).');",
         "notifyAttachmentIssue(chatT('ai_chat_js_attach_image_too_large', 'Image is too large (max 4 MB).'));"),
        ("notifyAttachmentIssue('Maximum ' + MAX_CHAT_ATTACHMENTS + ' attachments per message.');",
         "notifyAttachmentIssue(chatT('ai_chat_js_attach_max', 'Maximum {max} attachments per message.', { max: MAX_CHAT_ATTACHMENTS }));"),
        ("notifyAttachmentIssue('Could not read image: ' + error.message);",
         "notifyAttachmentIssue(chatT('ai_chat_js_attach_read_image_failed', 'Could not read image: {message}', { message: error.message }));"),
        ("notifyAttachmentIssue('Document attachments are not supported for this connection.');",
         "notifyAttachmentIssue(chatT('ai_chat_js_attach_docs_not_supported', 'Document attachments are not supported for this connection.'));"),
        ("notifyAttachmentIssue('Only text-based documents are supported (.txt, .md, .json, .csv, etc.).');",
         "notifyAttachmentIssue(chatT('ai_chat_js_attach_text_docs_only', 'Only text-based documents are supported (.txt, .md, .json, .csv, etc.).'));"),
        ("notifyAttachmentIssue('Could not read document: ' + error.message);",
         "notifyAttachmentIssue(chatT('ai_chat_js_attach_read_doc_failed', 'Could not read document: {message}', { message: error.message }));"),
        ("notifyAttachmentIssue('Unsupported file: ' + (file.name || 'unknown') + '. Paste images or text documents.');",
         "notifyAttachmentIssue(chatT('ai_chat_js_attach_unsupported_file', 'Unsupported file: {name}. Paste images or text documents.', { name: (file.name || 'unknown') }));"),
        ("return 'Attached: ' + pendingAttachments.map(function (a) { return a.name; }).join(', ');",
         "return chatT('ai_chat_js_attached_prefix', 'Attached: ') + pendingAttachments.map(function (a) { return a.name; }).join(', ');"),
        ("showError('Failed to load connection: ' + (result.message || 'Unknown error'));",
         "showError(chatT('ai_chat_js_failed_load_connection_msg', 'Failed to load connection: {message}', { message: (result.message || chatT('ai_chat_js_unknown_error', 'Unknown error')) }));"),
        ("showError('Failed to load connection');",
         "showError(chatT('ai_chat_js_failed_load_connection', 'Failed to load connection'));"),
        ("btn.setAttribute('aria-label', streaming ? 'Cancel response' : 'Send message');",
         "btn.setAttribute('aria-label', streaming ? chatT('ai_chat_js_cancel_response', 'Cancel response') : chatT('ai_chat_js_send_message', 'Send message'));"),
        ("btn.title = streaming ? 'Cancel' : 'Send';",
         "btn.title = streaming ? chatT('ai_chat_js_cancel', 'Cancel') : chatT('ai_chat_js_send', 'Send');"),
        ("addChatMessage('system', 'Request cancelled.');",
         "addChatMessage('system', chatT('ai_chat_js_request_cancelled', 'Request cancelled.'));"),
        ("updateStreamingMessage(assistantMessageId, 'Prepared actions for your review (see below).', true);",
         "updateStreamingMessage(assistantMessageId, chatT('ai_chat_js_prepared_actions', 'Prepared actions for your review (see below).'), true);"),
        ("chatMessages.push({ role: 'assistant', content: 'Prepared actions for your review.' });",
         "chatMessages.push({ role: 'assistant', content: chatT('ai_chat_js_prepared_actions_short', 'Prepared actions for your review.') });"),
        ("showError('No connection selected');",
         "showError(chatT('ai_chat_js_no_connection', 'No connection selected'));"),
        ("showChatActivity('Waiting for AI…');",
         "showChatActivity(chatT('ai_chat_js_waiting_ai', 'Waiting for AI…'));"),
        ("addChatMessageWithRetry('error', 'Stream ended without a response.');",
         "addChatMessageWithRetry('error', chatT('ai_chat_js_stream_ended', 'Stream ended without a response.'));"),
        ("addChatMessageWithRetry('error', 'Invalid response from server.');",
         "addChatMessageWithRetry('error', chatT('ai_chat_js_invalid_response', 'Invalid response from server.'));"),
        ("showError('Select an AI connection first');",
         "showError(chatT('ai_chat_js_select_connection_first', 'Select an AI connection first'));"),
        ("if (!(await BirklyFeedback.confirm('Approve this action?'))) return;",
         "if (!(await BirklyFeedback.confirm(chatT('ai_chat_js_confirm_approve_action', 'Approve this action?')))) return;"),
        ("addChatMessage('system', 'Action approved and executed successfully.');",
         "addChatMessage('system', chatT('ai_chat_js_action_approved', 'Action approved and executed successfully.'));"),
        ("addChatMessage('error', 'Failed to approve: ' + (result.message || 'Unknown error'));",
         "addChatMessage('error', chatT('ai_chat_js_failed_approve', 'Failed to approve: {message}', { message: (result.message || chatT('ai_chat_js_unknown_error', 'Unknown error')) }));"),
        ("addChatMessage('error', 'Error approving action');",
         "addChatMessage('error', chatT('ai_chat_js_error_approving', 'Error approving action'));"),
        ("addChatMessage('system', 'Action rejected.');",
         "addChatMessage('system', chatT('ai_chat_js_action_rejected', 'Action rejected.'));"),
        ("addChatMessage('error', 'Failed to reject: ' + (result.message || 'Unknown error'));",
         "addChatMessage('error', chatT('ai_chat_js_failed_reject', 'Failed to reject: {message}', { message: (result.message || chatT('ai_chat_js_unknown_error', 'Unknown error')) }));"),
        ("addChatMessage('error', 'Error rejecting action');",
         "addChatMessage('error', chatT('ai_chat_js_error_rejecting', 'Error rejecting action'));"),
        ("? '<span class=\"spinner\" role=\"status\" aria-label=\"Loading\"></span>'",
         "? '<span class=\"spinner\" role=\"status\" aria-label=\"' + escapeHtml(chatT('ai_chat_js_loading', 'Loading')) + '\"></span>'"),
        ("fullscreenBtn.title = isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen';",
         "fullscreenBtn.title = isFullscreen ? chatT('ai_chat_js_exit_fullscreen', 'Exit fullscreen') : chatT('ai_chat_js_enter_fullscreen', 'Enter fullscreen');"),
        ("const message = buildMessageWithAttachments(rawMessage || 'Please review the attached file(s).');",
         "const message = buildMessageWithAttachments(rawMessage || chatT('ai_chat_js_review_attached', 'Please review the attached file(s).'));"),
        ("const title = firstUser ? String(firstUser.content).slice(0, 72) : 'Chat';",
         "const title = firstUser ? String(firstUser.content).slice(0, 72) : chatT('ai_chat_js_chat_title', 'Chat');"),
        ("const description = approval.description || 'No description';",
         "const description = approval.description || chatT('ai_chat_js_no_description', 'No description');"),
        ("imageBtn.title = caps.vision ? 'Add image — or paste / drop into the chat box' : 'Images not supported for this provider';",
         "imageBtn.title = caps.vision ? chatT('ai_chat_js_add_image_hint', 'Add image — or paste / drop into the chat box') : chatT('ai_chat_js_images_not_supported', 'Images not supported for this provider');"),
        ("docBtn.title = caps.documents ? 'Add document — or paste / drop into the chat box' : 'Documents not supported for this provider';",
         "docBtn.title = caps.documents ? chatT('ai_chat_js_add_doc_hint', 'Add document — or paste / drop into the chat box') : chatT('ai_chat_js_docs_not_supported', 'Documents not supported for this provider');"),
        ("const label = tip.label ? escapeHtml(tip.label) : 'Learn more';",
         "const label = tip.label ? escapeHtml(tip.label) : chatT('ai_chat_js_learn_more', 'Learn more');"),
    ]
    for old, new in repl:
        if old not in text:
            print("WARN chat missing:", old[:70])
        else:
            text = text.replace(old, new)

    # CHAT_ACTIVITY_STATUS_CYCLE
    text = text.replace(
        "    const CHAT_ACTIVITY_STATUS_CYCLE = [\n"
        "        'Waiting for AI…',\n"
        "        'AI is thinking…',\n"
        "        'AI is working…',\n"
        "        'Still working…',\n"
        "    ];",
        "    function chatActivityStatusCycle() {\n"
        "        return [\n"
        "            chatT('ai_chat_js_waiting_ai', 'Waiting for AI…'),\n"
        "            chatT('ai_chat_js_ai_thinking', 'AI is thinking…'),\n"
        "            chatT('ai_chat_js_ai_working', 'AI is working…'),\n"
        "            chatT('ai_chat_js_still_working', 'Still working…'),\n"
        "        ];\n"
        "    }",
    )
    text = text.replace("CHAT_ACTIVITY_STATUS_CYCLE[", "chatActivityStatusCycle()[")
    text = text.replace("CHAT_ACTIVITY_STATUS_CYCLE.length", "chatActivityStatusCycle().length")

    # Tips — replace const with function building localized tips
    text = text.replace(
        "    const CHAT_ACTIVITY_TIPS = [\n"
        "        {\n"
        "            text: 'Did you know Birkly can run with MySQL or PostgreSQL instead of flat files?',\n"
        "            href: 'https://docs.birkly.com/content/01-admin/settings.html#storage-backends',\n"
        "            label: 'Learn more',\n"
        "        },\n"
        "        {\n"
        "            text: 'The AI can read and edit files in your project/ folder for site builds.',\n"
        "            href: 'https://docs.birkly.com/content/03-ai/ai-assistant.html',\n"
        "            label: 'Learn more',\n"
        "        },\n"
        "        {\n"
        "            text: 'You can attach images and documents to chat messages for context.',\n"
        "        },\n"
        "        {\n"
        "            text: 'Use the Approvals tab to review AI-proposed changes before they run.',\n"
        "            href: 'https://docs.birkly.com/content/03-ai/approvals.html',\n"
        "            label: 'Learn more',\n"
        "        },\n"
        "        {\n"
        "            text: 'Collections and entries live under content/ — back them up regularly.',\n"
        "            href: 'https://docs.birkly.com/content/07-operations/backup-and-restore.html',\n"
        "            label: 'Learn more',\n"
        "        },\n"
        "        {\n"
        "            text: 'Switch models from the sparkle menu in the composer for faster or smarter replies.',\n"
        "        },\n"
        "        {\n"
        "            text: 'Birkly templates use {for each entry in \\'collection\\'} syntax for dynamic pages.',\n"
        "            href: 'https://docs.birkly.com/content/02-content/templating.html',\n"
        "            label: 'Learn more',\n"
        "        },\n"
        "    ];",
        "    function chatActivityTips() {\n"
        "        var learn = chatT('ai_chat_js_learn_more', 'Learn more');\n"
        "        return [\n"
        "            { text: chatT('ai_chat_js_tip_storage', 'Did you know Birkly can run with MySQL or PostgreSQL instead of flat files?'), href: 'https://docs.birkly.com/content/01-admin/settings.html#storage-backends', label: learn },\n"
        "            { text: chatT('ai_chat_js_tip_project_files', 'The AI can read and edit files in your project/ folder for site builds.'), href: 'https://docs.birkly.com/content/03-ai/ai-assistant.html', label: learn },\n"
        "            { text: chatT('ai_chat_js_tip_attachments', 'You can attach images and documents to chat messages for context.') },\n"
        "            { text: chatT('ai_chat_js_tip_approvals', 'Use the Approvals tab to review AI-proposed changes before they run.'), href: 'https://docs.birkly.com/content/03-ai/approvals.html', label: learn },\n"
        "            { text: chatT('ai_chat_js_tip_backup', 'Collections and entries live under content/ — back them up regularly.'), href: 'https://docs.birkly.com/content/07-operations/backup-and-restore.html', label: learn },\n"
        "            { text: chatT('ai_chat_js_tip_models', 'Switch models from the sparkle menu in the composer for faster or smarter replies.') },\n"
        "            { text: chatT('ai_chat_js_tip_templating', \"Birkly templates use {for each entry in 'collection'} syntax for dynamic pages.\"), href: 'https://docs.birkly.com/content/02-content/templating.html', label: learn },\n"
        "        ];\n"
        "    }",
    )
    text = text.replace("CHAT_ACTIVITY_TIPS[", "chatActivityTips()[")
    text = text.replace("CHAT_ACTIVITY_TIPS.length", "chatActivityTips().length")

    # System messages with dynamic counts — need manual patterns
    text = text.replace(
        "addChatMessage('system', 'Warning: ' + failedActions.length + ' action(s) failed. See the Console tab for details.');",
        "addChatMessage('system', chatT('ai_chat_js_actions_failed', 'Warning: {count} action(s) failed. See the Console tab for details.', { count: failedActions.length }));",
    )
    text = text.replace(
        "addChatMessage('system', result.data.actions_created + ' action(s) pending approval. Open the Approvals tab to review.');",
        "addChatMessage('system', chatT('ai_chat_js_actions_pending', '{count} action(s) pending approval. Open the Approvals tab to review.', { count: result.data.actions_created }));",
    )
    text = text.replace(
        "addChatMessage('system', successCount + ' action(s) executed successfully.');",
        "addChatMessage('system', chatT('ai_chat_js_actions_executed', '{count} action(s) executed successfully.', { count: successCount }));",
    )

    # createChatWindow template strings
    tmpl_repl = [
        ('title="Send feedback" aria-label="Send feedback"', 'title="${chatT(\'ai_chat_js_send_feedback\', \'Send feedback\')}" aria-label="${chatT(\'ai_chat_js_send_feedback\', \'Send feedback\')}"'),
        ('data-tooltip="History" aria-label="History"', 'data-tooltip="${chatT(\'ai_chat_js_history\', \'History\')}" aria-label="${chatT(\'ai_chat_js_history\', \'History\')}"'),
        ('data-tooltip="Console" aria-label="Console"', 'data-tooltip="${chatT(\'ai_chat_js_console\', \'Console\')}" aria-label="${chatT(\'ai_chat_js_console\', \'Console\')}"'),
        ('data-tooltip="Approvals" aria-label="Approvals"', 'data-tooltip="${chatT(\'ai_chat_js_approvals\', \'Approvals\')}" aria-label="${chatT(\'ai_chat_js_approvals\', \'Approvals\')}"'),
        ('data-tooltip="New chat" aria-label="New chat"', 'data-tooltip="${chatT(\'ai_chat_js_new_chat\', \'New chat\')}" aria-label="${chatT(\'ai_chat_js_new_chat\', \'New chat\')}"'),
        ('data-tooltip="Fullscreen" aria-label="Toggle fullscreen"', 'data-tooltip="${chatT(\'ai_chat_js_fullscreen\', \'Fullscreen\')}" aria-label="${chatT(\'ai_chat_js_toggle_fullscreen\', \'Toggle fullscreen\')}"'),
        ('class="u-sr-only">AI connection</label>', 'class="u-sr-only">${chatT(\'ai_chat_js_ai_connection\', \'AI connection\')}</label>'),
        ('title="Choose AI connection"', 'title="${chatT(\'ai_chat_js_choose_connection\', \'Choose AI connection\')}"'),
        ('<option value="">Loading connections...</option>', '<option value="">${chatT(\'ai_chat_js_loading_connections\', \'Loading connections...\')}</option>'),
        ('<p>Select an AI connection to start chatting. The AI can help you create and edit content.</p>',
         '<p>${chatT(\'ai_chat_js_welcome\', \'Select an AI connection to start chatting. The AI can help you create and edit content.\')}</p>'),
        ('aria-label="Close panel"', 'aria-label="${chatT(\'ai_chat_js_close_panel\', \'Close panel\')}"'),
        ('<span id="ai-chat-side-panel-title">History</span>', '<span id="ai-chat-side-panel-title">${chatT(\'ai_chat_js_history\', \'History\')}</span>'),
        ('data-tooltip="Back to chat" aria-label="Back to chat"', 'data-tooltip="${chatT(\'ai_chat_js_back_to_chat\', \'Back to chat\')}" aria-label="${chatT(\'ai_chat_js_back_to_chat\', \'Back to chat\')}"'),
        ('Action results and errors from this session', '${chatT(\'ai_chat_js_console_hint\', \'Action results and errors from this session\')}'),
        ('id="ai-chat-console-clear">Clear</button>', 'id="ai-chat-console-clear">${chatT(\'ai_chat_js_clear\', \'Clear\')}</button>'),
        ('<div class="loading-state">Loading approvals…</div>', '<div class="loading-state">${chatT(\'ai_chat_js_loading_approvals\', \'Loading approvals…\')}</div>'),
        ('id="ai-chat-activity-status">Waiting for AI…</span>', 'id="ai-chat-activity-status">${chatT(\'ai_chat_js_waiting_ai\', \'Waiting for AI…\')}</span>'),
        ('placeholder="Ask the AI to create or edit content… Paste or drop images and files here."',
         'placeholder="${chatT(\'ai_chat_js_composer_placeholder\', \'Ask the AI to create or edit content… Paste or drop images and files here.\')}"'),
        ('data-tooltip="Add image" aria-label="Add image"', 'data-tooltip="${chatT(\'ai_chat_js_add_image\', \'Add image\')}" aria-label="${chatT(\'ai_chat_js_add_image\', \'Add image\')}"'),
        ('data-tooltip="Add document" aria-label="Add document"', 'data-tooltip="${chatT(\'ai_chat_js_add_document\', \'Add document\')}" aria-label="${chatT(\'ai_chat_js_add_document\', \'Add document\')}"'),
        ('data-tooltip="Choose model"', 'data-tooltip="${chatT(\'ai_chat_js_choose_model\', \'Choose model\')}"'),
        ('<span id="ai-chat-model-label">Model</span>', '<span id="ai-chat-model-label">${chatT(\'ai_chat_js_model\', \'Model\')}</span>'),
        ('aria-label="Send message"', 'aria-label="${chatT(\'ai_chat_js_send_message\', \'Send message\')}"'),
        ('<div class="loading-state">Loading connections…</div>', '<div class="loading-state">${chatT(\'ai_chat_js_loading_connections\', \'Loading connections...\')}</div>'),
    ]
    for old, new in tmpl_repl:
        if old not in text:
            print("WARN chat tmpl missing:", old[:50])
        else:
            text = text.replace(old, new)

    path.write_text(text)


def main():
    merge_lang()
    patch_presence()
    patch_plugins()
    patch_chat()
    print("P85 chat/presence chunk:", len(STRINGS), "keys")


if __name__ == "__main__":
    main()
