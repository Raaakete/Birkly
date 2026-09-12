#!/usr/bin/env python3
"""P85 tier-1 remainder: collection editor + settings delivery/project/domain copy."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    # —— Collection editor ——
    "col_editor_allow_anonymous_help": (
        "Off by default. When enabled, anyone can submit to this collection without a domain license or API key.",
        "Standardmäßig aus. Wenn aktiviert, kann jeder ohne Domain-Lizenz oder API-Schlüssel einsenden.",
    ),
    "col_editor_allow_anonymous_submissions": (
        "Allow anonymous submissions",
        "Anonyme Einsendungen erlauben",
    ),
    "col_editor_allow_public_read": (
        "Allow public read access",
        "Öffentlichen Lesezugriff erlauben",
    ),
    "col_editor_allow_public_read_help": (
        "When off, this collection can only be read by pages rendered by Birkly itself. Turn on only if an external site needs to fetch it. Anything readable this way should be treated as public.",
        "Wenn aus, ist die Kollektion nur für von Birkly gerenderte Seiten lesbar. Nur aktivieren, wenn eine externe Website Daten abrufen muss. Alles Lesbare gilt dann als öffentlich.",
    ),
    "col_editor_auto_generate_slug": (
        "Auto-generate slug from title",
        "Slug automatisch aus dem Titel erzeugen",
    ),
    "col_editor_breadcrumb_collection_fallback": ("Collection", "Kollektion"),
    "col_editor_collection_fields_title": ("Collection Fields", "Kollektionsfelder"),
    "col_editor_copy_to_clipboard": ("Copy to clipboard", "In Zwischenablage kopieren"),
    "col_editor_default_sort_order": ("Default Sort Order", "Standard-Sortierung"),
    "col_editor_describe_collection_ph": (
        "Describe what this collection is for",
        "Beschreiben Sie den Zweck dieser Kollektion",
    ),
    "col_editor_describe_sub_ph": (
        "Describe what this sub-collection is for",
        "Beschreiben Sie den Zweck dieser Unterkollektion",
    ),
    "col_editor_display_name": ("Display Name", "Anzeigename"),
    "col_editor_embeddable_form_code": ("Embeddable Form Code", "Einbettbarer Formularcode"),
    "col_editor_entry_method_help_collection": (
        "Choose how entries can be created for this collection",
        "Legen Sie fest, wie Einträge für diese Kollektion erstellt werden können",
    ),
    "col_editor_entry_method_help_sub": (
        "Choose how entries can be created for this sub-collection",
        "Legen Sie fest, wie Einträge für diese Unterkollektion erstellt werden können",
    ),
    "col_editor_example_collection_level": ("Collection Level Example", "Beispiel auf Kollektionsebene"),
    "col_editor_example_entry_level": ("Entry Level Example", "Beispiel auf Eintragsebene"),
    "col_editor_example_sub_entry_level": ("Sub-Entry Level Example", "Beispiel auf Untereintragsebene"),
    "col_editor_field_options_label": (
        "Options (one per line, format: label|value)",
        "Optionen (eine pro Zeile, Format: Bezeichnung|Wert)",
    ),
    "col_editor_field_slugs_title": ("Field slugs", "Feld-Slugs"),
    "col_editor_field_type": ("Field Type", "Feldtyp"),
    "col_editor_api_name": ("API Name", "API-Name"),
    "col_editor_auto_publish_draft_help": (
        "If enabled, entries will be published automatically. Otherwise, they'll be saved as drafts for review.",
        "Wenn aktiviert, werden Einträge automatisch veröffentlicht. Sonst werden sie als Entwürfe zur Prüfung gespeichert.",
    ),
    "col_editor_email_connection_picker_help": (
        "Which email connection should send these notifications?",
        "Welche E-Mail-Verbindung soll diese Benachrichtigungen senden?",
    ),
    "col_editor_form_code_help": (
        "Copy and paste this code into your website to create a form that submits to this collection.",
        "Kopieren Sie diesen Code in Ihre Website, um ein Formular zu erstellen, das an diese Kollektion sendet.",
    ),
    "col_editor_icon_emoji_label": ("Icon (emoji)", "Symbol (Emoji)"),
    "col_editor_last_update": ("Last Update", "Letzte Aktualisierung"),
    "col_editor_mandatory_field": ("Mandatory Field", "Pflichtfeld"),
    "col_editor_multilang_enable_collection": (
        "Enable multi-language support for this collection",
        "Mehrsprachigkeit für diese Kollektion aktivieren",
    ),
    "col_editor_multilang_enable_sub": (
        "Enable multi-language support for this sub-collection",
        "Mehrsprachigkeit für diese Unterkollektion aktivieren",
    ),
    "col_editor_multilang_when_enabled_help": (
        "When enabled, entries can have content in multiple languages",
        "Wenn aktiviert, können Einträge Inhalte in mehreren Sprachen haben",
    ),
    "col_editor_notification_email": ("Notification Email", "Benachrichtigungs-E-Mail"),
    "col_editor_notification_email_help": (
        "Who should receive an email when someone submits this form?",
        "Wer soll eine E-Mail erhalten, wenn jemand dieses Formular absendet?",
    ),
    "col_editor_notification_email_ph": ("email@example.com", "email@beispiel.de"),
    "col_editor_parent_collection_label": ("Parent Collection:", "Übergeordnete Kollektion:"),
    "col_editor_rate_limit_help": (
        "Maximum number of submissions allowed per hour per API key",
        "Maximale Einsendungen pro Stunde und API-Schlüssel",
    ),
    "col_editor_rate_limit_per_hour": ("Rate Limit (per hour)", "Ratenlimit (pro Stunde)"),
    "col_editor_section_desc_collection_fields": (
        "Define the fields that entries in this collection will have.",
        "Definieren Sie die Felder, die Einträge in dieser Kollektion haben.",
    ),
    "col_editor_section_desc_nested_sub_collections": (
        'Configure nested sub-collections that can be attached to entries in this sub-collection. For example, a "Comments" sub-collection might have "Replies" as a nested sub-collection.',
        'Verschachtelte Unterkollektionen konfigurieren, die an Einträge in dieser Unterkollektion angehängt werden können. Beispiel: „Kommentare“ mit „Antworten“ als Unterkollektion.',
    ),
    "col_editor_section_desc_sub_collections": (
        'Configure sub-collections that can be attached to entries in this collection. For example, a blog post collection might have "Comments" and "Ratings" as sub-collections.',
        'Unterkollektionen konfigurieren, die an Einträge in dieser Kollektion angehängt werden können. Beispiel: Blogbeiträge mit „Kommentare“ und „Bewertungen“.',
    ),
    "col_editor_section_desc_sub_fields": (
        "Define the fields that sub-entries in this sub-collection will have.",
        "Definieren Sie die Felder, die Untereinträge in dieser Unterkollektion haben.",
    ),
    "col_editor_select_field_type_modal": ("Select Field Type", "Feldtyp auswählen"),
    "col_editor_select_type_ph": ("Select type...", "Typ auswählen …"),
    "col_editor_slug_auto_ph": ("auto-generated", "automatisch erzeugt"),
    "col_editor_sort_newest": ("Newest First", "Neueste zuerst"),
    "col_editor_sort_oldest": ("Oldest First", "Älteste zuerst"),
    "col_editor_sort_recently_updated": ("Recently Updated", "Zuletzt aktualisiert"),
    "col_editor_sub_belongs_help": (
        "This sub-collection belongs to the parent collection above and will be attached to entries in that collection.",
        "Diese Unterkollektion gehört zur übergeordneten Kollektion oben und wird an Einträge dieser Kollektion angehängt.",
    ),
    # —— Settings: delivery & project ——
    "settings_cache_ttl_help": (
        "How long pre-rendered pages are cached. <code>0</code> disables caching (every request re-renders). Max 86400 (24h).",
        "Wie lange vorgerenderte Seiten gecacht werden. <code>0</code> deaktiviert den Cache (jede Anfrage rendert neu). Max. 86400 (24 h).",
    ),
    "settings_delivery_alias_redirect_help": (
        "When enabled, visitors on aliases such as <code>birkly.io</code> are sent to the primary public URL (e.g. <code>birkly.cloud</code>). Uncheck to keep each hostname in the browser address bar.",
        "Wenn aktiviert, werden Besucher von Alias-Hostnamen wie <code>birkly.io</code> zur primären öffentlichen URL weitergeleitet (z. B. <code>birkly.cloud</code>). Deaktivieren, um den Hostnamen in der Adressleiste zu behalten.",
    ),
    "settings_delivery_alias_redirect_label": (
        "Redirect alias hostnames to primary URL (301)",
        "Alias-Hostnamen auf primäre URL umleiten (301)",
    ),
    "settings_delivery_domains_desc": (
        "Every hostname registered for CORS and how it is routed for visitors and admin.",
        "Jeder für CORS registrierte Hostname und wie er für Besucher und Admin geroutet wird.",
    ),
    "settings_delivery_domains_title": ("Domains & delivery", "Domains & Auslieferung"),
    "settings_domain_api_read_write_help": (
        "Read Only: Can fetch content. Read & Write: Can fetch and submit content.",
        "Nur Lesen: Inhalte abrufen. Lesen & Schreiben: Inhalte abrufen und einsenden.",
    ),
    "settings_domain_delivery_role_help": (
        "Choose how this hostname is used for site delivery. Registering <code>birkly.io</code> also enables <code>www.birkly.io</code> for CORS and routing.",
        "Legen Sie fest, wie dieser Hostname für die Auslieferung genutzt wird. Registrierung von <code>birkly.io</code> aktiviert auch <code>www.birkly.io</code> für CORS und Routing.",
    ),
    "settings_domain_name_help": (
        "Enter the domain name without http:// or https://",
        "Domainnamen ohne http:// oder https:// eingeben",
    ),
    "settings_git_sync_help": (
        "Sync the files under <code>project/</code> with a GitHub, GitLab, or generic git remote. CMS content, media, users, and plugins are never included.",
        "Dateien unter <code>project/</code> mit GitHub, GitLab oder einem Git-Remote synchronisieren. CMS-Inhalte, Medien, Benutzer und Plugins sind nie enthalten.",
    ),
    "settings_hosting_type_help_after": ("", ""),
    "settings_hosting_type_help_before": (
        'Controlled by Settings → Site delivery. SSR is only available when mode is “In Birkly (project/)”. ',
        "Gesteuert unter Einstellungen → Site delivery. SSR ist nur verfügbar, wenn der Modus „In Birkly (project/)“ ist. ",
    ),
    "settings_hosting_type_help_link": ("Open Site delivery", "Site delivery öffnen"),
    "settings_hosting_where_served": (
        "Where the CMS and public site are served from.",
        "Wo CMS und öffentliche Website ausgeliefert werden.",
    ),
    "settings_integrations_api_doc_help": (
        "For detailed API documentation, including available endpoints, request/response formats, and authentication methods, please refer to the Birkly documentation or contact your system administrator.",
        "Ausführliche API-Dokumentation (Endpunkte, Formate, Authentifizierung) finden Sie in der Birkly-Dokumentation oder bei Ihrem Administrator.",
    ),
    "settings_project_activity_help": (
        "Recent project file changes, settings updates, and git events, with who made each change.",
        "Aktuelle Projektdatei-Änderungen, Einstellungsupdates und Git-Ereignisse — mit Angabe, wer die Änderung vorgenommen hat.",
    ),
    "settings_project_ai_permission_help": (
        "AI connections need the <code>manage_project_site</code> permission (Admin role by default) to read or write project files.",
        "KI-Verbindungen benötigen die Berechtigung <code>manage_project_site</code> (standardmäßig Admin-Rolle), um Projektdateien zu lesen oder zu schreiben.",
    ),
    "settings_project_files_help": (
        "Browse, edit, and upload the HTML, CSS, JS, and assets served from <code>project/</code>.",
        "HTML, CSS, JS und Assets unter <code>project/</code> durchsuchen, bearbeiten und hochladen.",
    ),
    "settings_project_manage_files_after": (".", "."),
    "settings_project_manage_files_before": ("Manage files in ", "Dateien verwalten unter "),
    "settings_project_manage_files_link": ("Settings → Project", "Einstellungen → Projekt"),
    "settings_project_overview_title": ("Project overview", "Projektübersicht"),
    "settings_project_visit_header_help": (
        "Opens the project site in a new tab. Choose the URL style below if one works better on your host.",
        "Öffnet die Projektwebsite in einem neuen Tab. Wählen Sie unten den URL-Stil, der auf Ihrem Host besser funktioniert.",
    ),
    "settings_project_visit_header_label": (
        'Show “Visit project” in admin header',
        "„Projekt besuchen“ in der Admin-Kopfzeile anzeigen",
    ),
    "settings_project_visit_url_help": (
        "Auto uses site root for SSR, subdirectory installs, and clean public URLs; otherwise index.html when present. Marsk and similar templates usually need site root.",
        "Auto nutzt die Site-Root für SSR, Unterverzeichnis-Installationen und saubere URLs; sonst index.html wenn vorhanden. Marsk und ähnliche Templates brauchen meist die Site-Root.",
    ),
    "settings_project_visit_url_label": ("Visit project URL", "URL „Projekt besuchen“"),
    "settings_project_activity_title": ("Activity", "Aktivität"),
    "settings_project_files_title": ("Files", "Dateien"),
    "settings_project_git_sync_title": ("Git sync", "Git-Synchronisation"),
    "settings_project_rendering_title": ("Rendering", "Rendering"),
    "settings_project_server_rendering_title": ("Server rendering", "Server-Rendering"),
    "settings_project_workspace_help": (
        "The bundled website workspace on this installation. CMS content and media stay in Birkly and are read at runtime.",
        "Der gebündelte Website-Arbeitsbereich dieser Installation. CMS-Inhalte und Medien bleiben in Birkly und werden zur Laufzeit gelesen.",
    ),
    "settings_render_mode_help": (
        "Server pre-renders HTML with the built-in PHP engine — recommended and default for Birkly-hosted projects. Auto uses the server when possible and falls back to the client. Client renders in the browser only.",
        "Der Server rendert HTML mit der eingebauten PHP-Engine — empfohlen und Standard für Birkly-gehostete Projekte. Auto nutzt den Server wenn möglich, sonst den Client. Client rendert nur im Browser.",
    ),
    "settings_rendering_autosave_help": (
        "Changes here save automatically — no separate save button needed.",
        "Änderungen hier werden automatisch gespeichert — kein separater Speichern-Button nötig.",
    ),
    "settings_rendering_external_note": (
        'Server rendering is disabled when Site delivery is not “In Birkly (project/)”. Change the mode under Settings → Site delivery to enable SSR.',
        "Server-Rendering ist deaktiviert, wenn Site delivery nicht „In Birkly (project/)“ ist. Modus unter Einstellungen → Site delivery ändern, um SSR zu aktivieren.",
    ),
    "settings_rendering_server_desc": (
        "Pre-render bundled project pages on the server for faster first paint. Server-side rendering is the default for Birkly-hosted project sites and runs on the built-in PHP engine — no Node.js required. External sites always render on the client.",
        "Projektseiten serverseitig vorrendern für schnelleren First Paint. SSR ist Standard für Birkly-gehostete Projekte und läuft auf der eingebauten PHP-Engine — kein Node.js nötig. Externe Sites rendern immer im Client.",
    ),
    "settings_rendering_section_help": (
        "How project pages are rendered and cached.",
        "Wie Projektseiten gerendert und gecacht werden.",
    ),
    "settings_analytics_legal_disclaimer": (
        "Not legal advice — you remain responsible for privacy law in your region.",
        "Keine Rechtsberatung — Sie bleiben für Datenschutz in Ihrer Region verantwortlich.",
    ),
    "settings_analytics_preset_help": (
        "Choose how much visitor data Birkly collects. Use Save Settings in the top bar to apply. Minimal+ enables tracking for your site (including localhost preview).",
        "Legen Sie fest, wie viele Besucherdaten Birkly erfasst. Mit „Einstellungen speichern“ oben anwenden. Minimal+ aktiviert Tracking für Ihre Site (inkl. localhost-Vorschau).",
    ),
    "settings_analytics_advanced_help": (
        "Retention days and consent banner text apply when you use Standard or Advanced presets.",
        "Aufbewahrungstage und Einwilligungstext gelten bei den Voreinstellungen Standard oder Erweitert.",
    ),
    "settings_analytics_report_help": (
        "Combined snapshot across all enabled domains. Depth follows your preset.",
        "Kombinierte Übersicht über alle aktivierten Domains. Tiefe folgt Ihrer Voreinstellung.",
    ),
    # —— Account 2FA copy (fix DE) ——
    "disable_2fa_instructions": (
        "Enter your password and, if prompted, a code from your authenticator app to disable two-factor authentication for your account.",
        "Geben Sie Ihr Passwort und ggf. einen Code aus Ihrer Authenticator-App ein, um die Zwei-Faktor-Authentifizierung für Ihr Konto zu deaktivieren.",
    ),
    "regenerate_backup_codes_warning": (
        "Generating new backup codes invalidates all previous codes. Store the new codes in a safe place — each code works only once.",
        "Neue Backup-Codes machen alle bisherigen ungültig. Bewahren Sie die neuen Codes sicher auf — jeder Code ist nur einmal verwendbar.",
    ),
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
