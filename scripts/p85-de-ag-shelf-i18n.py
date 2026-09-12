#!/usr/bin/env python3
"""P85: German translations for Agent Guide shelf (ag_*) keys still identical to en."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "ag_about_guide_body": ("Agent Guide is the public fact sheet about your business for AI. You edit it here; visitors’ AIs can read it from your website.", "Der Agent Guide ist das öffentliche Fact Sheet über Ihr Unternehmen für KI. Sie bearbeiten es hier; die KIs der Besucher können es von Ihrer Website lesen."),
    "ag_about_guide_title": ("What AI knows", "Was KI weiß"),
    "ag_about_guide_where": ("Lives on your website as a public file AIs can read.", "Liegt auf Ihrer Website als öffentliche Datei, die KIs lesen können."),
    "ag_about_mcp_body": ("Birkly MCP is how AI apps (Claude, Cursor, ChatGPT, …) connect to your Birkly admin/CMS to help you build and manage content.", "Birkly MCP ist der Weg, wie KI-Apps (Claude, Cursor, ChatGPT, …) sich mit Ihrem Birkly-Admin/CMS verbinden, um Inhalte zu erstellen und zu verwalten."),
    "ag_about_mcp_manage_link": ("Manage in Settings → AI", "In Einstellungen → KI verwalten"),
    "ag_about_mcp_title": ("How AI apps connect", "Wie KI-Apps sich verbinden"),
    "ag_about_mcp_where": ("Always lives on your Birkly address — not on an external website host.", "Liegt immer auf Ihrer Birkly-Adresse — nicht auf einem externen Website-Host."),
    "ag_about_webmcp_body": ("WebMCP lets AI helpers in the visitor’s browser use clear actions on your live website (like submitting a contact form or adding to cart) instead of guessing from the page. It is off until you turn it on.", "WebMCP ermöglicht KI-Helfern im Browser des Besuchers klare Aktionen auf Ihrer Live-Website (z. B. Kontaktformular absenden oder in den Warenkorb legen) statt zu raten. Standardmäßig aus, bis Sie es aktivieren."),
    "ag_about_webmcp_title": ("What AI can do on your site", "Was KI auf Ihrer Site tun kann"),
    "ag_about_webmcp_where": ("Runs in the visitor’s browser, on the page they’re viewing.", "Läuft im Browser des Besuchers auf der gerade angezeigten Seite."),
    "ag_badge_live": ("Live · v{version}", "Live · v{version}"),
    "ag_confirm_unpublish": ("Unpublish the Agent Guide? Public endpoints will stop serving your guide.", "Agent Guide zurückziehen? Öffentliche Endpunkte liefern Ihren Guide dann nicht mehr aus."),
    "ag_connections_about_body": ("AI apps like Claude, Cursor and ChatGPT connect to your Birkly CMS — not to your website host. Set up a personal connector or shared service agents below.", "KI-Apps wie Claude, Cursor und ChatGPT verbinden sich mit Ihrem Birkly-CMS — nicht mit Ihrem Website-Host. Richten Sie unten einen persönlichen Connector oder gemeinsame Service-Agenten ein."),
    "ag_connections_about_title": ("How AI apps connect to Birkly", "Wie KI-Apps sich mit Birkly verbinden"),
    "ag_discovery_crawlers": ("Allow AI crawlers in robots.txt", "KI-Crawler in robots.txt erlauben"),
    "ag_discovery_crawlers_help": ("Explicit Allow rules for GPTBot, PerplexityBot, ClaudeBot, and others on guide URLs.", "Explizite Allow-Regeln für GPTBot, PerplexityBot, ClaudeBot und andere auf Guide-URLs."),
    "ag_discovery_footer_line": ("Footer one-liner", "Footer-Einzeiler"),
    "ag_discovery_footer_line_help": ("Optional crawlable sentence in the footer with a link to your Agent Guide.", "Optionaler crawlbarer Satz im Footer mit Link zu Ihrem Agent Guide."),
    "ag_discovery_footer_line_placeholder": ("Official AI facts for chat and search assistants:", "Offizielle KI-Fakten für Chat- und Suchassistenten:"),
    "ag_discovery_footer_link": ("Footer “For agents” link", "Footer-Link „Für Agenten“"),
    "ag_discovery_footer_link_help": ("Adds a small footer link to /for-agents when the page footer is detected (default on).", "Fügt einen kleinen Footer-Link zu /for-agents hinzu, wenn ein Seitenfooter erkannt wird (Standard: an)."),
    "ag_discovery_help": ("Checklist for how chat and search AIs can find your published guide via indexed pages, sitemap, and robots.txt.", "Checkliste, wie Chat- und Such-KIs Ihren veröffentlichten Guide über indexierte Seiten, Sitemap und robots.txt finden."),
    "ag_discovery_indexnow": ("Ping search engines on publish (IndexNow)", "Suchmaschinen beim Veröffentlichen anpingen (IndexNow)"),
    "ag_discovery_indexnow_help": ("Notifies Bing and partners when you publish or republish.", "Benachrichtigt Bing und Partner bei Veröffentlichung oder Neuveröffentlichung."),
    "ag_discovery_refresh": ("Refresh checklist", "Checkliste aktualisieren"),
    "ag_discovery_sitemap": ("Include in sitemap.xml", "In sitemap.xml aufnehmen"),
    "ag_discovery_sitemap_help": ("Lists project pages and /for-agents in /sitemap.xml; adds Sitemap: to robots.txt.", "Listet Projektseiten und /for-agents in /sitemap.xml; fügt Sitemap: in robots.txt hinzu."),
    "ag_discovery_status_fail": ("Action needed", "Aktion erforderlich"),
    "ag_discovery_status_neutral": ("Optional", "Optional"),
    "ag_discovery_status_pass": ("OK", "OK"),
    "ag_discovery_status_warn": ("Review", "Prüfen"),
    "ag_discovery_template_help": ("Add crawlable Agent Guide text on any project page — not injected automatically.", "Crawlbarer Agent-Guide-Text auf beliebigen Projektseiten — nicht automatisch eingefügt."),
    "ag_discovery_template_title": ("Template tag (opt-in)", "Template-Tag (opt-in)"),
    "ag_discovery_title": ("AI discovery (search & chat)", "KI-Entdeckung (Suche & Chat)"),
    "ag_hosted_external": ("External", "Extern"),
    "ag_hosted_none": ("None", "Keine"),
    "ag_hosted_project": ("On Birkly", "Auf Birkly"),
    "ag_hub_manage_in_publish": ("Manage in Publish", "In Veröffentlichen verwalten"),
    "ag_hub_spine_aria": ("AI status overview", "KI-Statusübersicht"),
    "ag_hub_spine_connections": ("AI app connections", "KI-App-Verbindungen"),
    "ag_hub_spine_guide": ("Guide", "Leitfaden"),
    "ag_hub_spine_hosted": ("Where it’s hosted", "Wo es gehostet ist"),
    "ag_hub_spine_tools": ("Website tools", "Website-Werkzeuge"),
    "ag_hub_tab_connections": ("Connections", "Verbindungen"),
    "ag_hub_tab_guide": ("Guide", "Leitfaden"),
    "ag_hub_tab_publish": ("Publish", "Veröffentlichen"),
    "ag_hub_tab_tools": ("Tools", "Werkzeuge"),
    "ag_nav_advanced": ("Advanced", "Erweitert"),
    "ag_nav_essentials": ("Essentials", "Grundlagen"),
    "ag_nav_offerings": ("Offerings & claims", "Angebote & Claims"),
    "ag_publish_cms_url": ("Birkly URL (always works)", "Birkly-URL (funktioniert immer)"),
    "ag_publish_download_btn": ("Download agent-guide.json", "agent-guide.json herunterladen"),
    "ag_publish_download_label": ("Or download the guide file and upload it to your host’s /.well-known/ folder", "Oder Guide-Datei herunterladen und in /.well-known/ Ihres Hosts hochladen"),
    "ag_publish_external_intro": ("Your site is hosted outside Birkly. Add the Birkly client snippet below to every page — it automatically exposes your published Agent Guide (JSON, llms.txt, /for-agents, and discovery links) from your Birkly URL. No file uploads or server config required.", "Ihre Site wird außerhalb von Birkly gehostet. Fügen Sie das Birkly-Client-Snippet unten auf jeder Seite ein — es stellt Ihren veröffentlichten Agent Guide (JSON, llms.txt, /for-agents und Discovery-Links) automatisch von Ihrer Birkly-URL bereit. Keine Datei-Uploads oder Server-Konfiguration nötig."),
    "ag_publish_external_no_copy": ("Your Birkly URL above always serves the live guide. The client snippet is the zero-config path for external sites; proxy and download are optional.", "Ihre Birkly-URL oben liefert immer den Live-Guide. Das Client-Snippet ist der Zero-Config-Weg für externe Sites; Proxy und Download sind optional."),
    "ag_publish_guide_help": ("Your public AI fact sheet and where AIs can read it.", "Ihr öffentliches KI-Fact Sheet und wo KIs es lesen können."),
    "ag_publish_guide_none_note": ("No site delivery is set up yet. Choose how your site is delivered in Settings → Site delivery, then come back to publish.", "Noch keine Site-Auslieferung eingerichtet. Wählen Sie in Einstellungen → Site-Auslieferung, wie Ihre Site ausgeliefert wird, und kehren Sie dann zum Veröffentlichen zurück."),
    "ag_publish_guide_project_note": ("Published automatically on your public site — /.well-known/agent-guide.json, /llms.txt, /for-agents, plus a For agents link in your page footer. No download or upload needed.", "Automatisch auf Ihrer öffentlichen Site veröffentlicht — /.well-known/agent-guide.json, /llms.txt, /for-agents sowie ein Link „Für Agenten“ im Seitenfooter. Kein Download oder Upload nötig."),
    "ag_publish_guide_title": ("Guide", "Leitfaden"),
    "ag_publish_lede": ("Where AIs read your guide, whether website tools are on, and how AI apps connect — tailored to how your site is hosted.", "Wo KIs Ihren Guide lesen, ob Website-Werkzeuge aktiv sind und wie KI-Apps sich verbinden — abhängig davon, wie Ihre Site gehostet wird."),
    "ag_publish_mcp_help": ("AI apps connect to Birkly at this address — never on your website host.", "KI-Apps verbinden sich mit Birkly unter dieser Adresse — nie auf Ihrem Website-Host."),
    "ag_publish_mcp_manage": ("Manage in Settings → AI", "In Einstellungen → KI verwalten"),
    "ag_publish_mcp_title": ("AI app connections (Birkly MCP)", "KI-App-Verbindungen (Birkly MCP)"),
    "ag_publish_mcp_url_label": ("AI apps use this Birkly MCP URL", "KI-Apps nutzen diese Birkly-MCP-URL"),
    "ag_publish_option1_help": ("Paste the snippet on every page. It loads templates and automatically publishes Agent Guide discovery for chat/search AIs at your Birkly URL — /.well-known/agent-guide.json, /llms.txt, and /for-agents.", "Snippet auf jeder Seite einfügen. Es lädt Templates und veröffentlicht Agent-Guide-Discovery für Chat-/Such-KIs an Ihrer Birkly-URL — /.well-known/agent-guide.json, /llms.txt und /for-agents."),
    "ag_publish_option1_title": ("1. Birkly client snippet (recommended)", "1. Birkly-Client-Snippet (empfohlen)"),
    "ag_publish_option2_help": ("Optional: serve the same paths from your own domain by proxying to Birkly. Only needed if you want guide URLs on your marketing domain instead of your Birkly host.", "Optional: dieselben Pfade von Ihrer Domain aus per Proxy zu Birkly bereitstellen. Nur nötig, wenn Guide-URLs auf Ihrer Marketing-Domain statt auf dem Birkly-Host liegen sollen."),
    "ag_publish_option2_title": ("2. Reverse proxy (optional, advanced)", "2. Reverse-Proxy (optional, erweitert)"),
    "ag_publish_option3_help": ("Optional snapshot download for hosts that cannot run JavaScript. Re-download after each republish.", "Optionaler Snapshot-Download für Hosts ohne JavaScript. Nach jeder Neuveröffentlichung erneut herunterladen."),
    "ag_publish_option3_title": ("3. Download the file (optional, manual)", "3. Datei herunterladen (optional, manuell)"),
    "ag_publish_proxy_label": ("Or add this reverse-proxy rule", "Oder diese Reverse-Proxy-Regel hinzufügen"),
    "ag_publish_public_url": ("Public URL", "Öffentliche URL"),
    "ag_publish_snippet_label": ("Add this snippet to every page (delivers Agent Guide automatically)", "Dieses Snippet auf jeder Seite einfügen (stellt Agent Guide automatisch bereit)"),
    "ag_publish_webmcp_external_no_copy": ("On an external host you must add these script tags yourself. They are not automatic and are not synced via Git unless you deploy the scripts yourself.", "Auf einem externen Host müssen Sie diese Script-Tags selbst einfügen. Sie sind nicht automatisch und werden nicht per Git synchronisiert, es sei denn, Sie deployen die Skripte selbst."),
    "ag_publish_webmcp_external_on": ("Add the script tags below to every page.", "Fügen Sie die Script-Tags unten auf jeder Seite ein."),
    "ag_publish_webmcp_help": ("Whether AI helpers can act on your live site. Manage in the Tools tab.", "Ob KI-Helfer auf Ihrer Live-Site handeln können. Im Tab Werkzeuge verwalten."),
    "ag_publish_webmcp_manage": ("Manage in Tools", "In Werkzeuge verwalten"),
    "ag_publish_webmcp_off": ("Off. Turn on in the Tools tab.", "Aus. Im Tab Werkzeuge aktivieren."),
    "ag_publish_webmcp_project_on": ("Automatically active on your Birkly site.", "Automatisch aktiv auf Ihrer Birkly-Site."),
    "ag_publish_webmcp_snippet_label": ("Add these script tags to every page", "Diese Script-Tags auf jeder Seite einfügen"),
    "ag_publish_webmcp_title": ("Website tools (WebMCP)", "Website-Werkzeuge (WebMCP)"),
    "ag_status_connected": ("Connected", "Verbunden"),
    "ag_status_draft": ("Draft", "Entwurf"),
    "ag_status_not_set_up": ("Not set up", "Nicht eingerichtet"),
    "ag_status_off": ("Off", "Aus"),
    "ag_status_published": ("Published", "Veröffentlicht"),
    "ag_tools_allowlist_empty": ("No public forms found yet. Public forms come from collections set to accept public submissions.", "Noch keine öffentlichen Formulare. Diese stammen aus Kollektionen mit öffentlichen Einsendungen."),
    "ag_tools_allowlist_help": ("Only forms you list here can be submitted by an AI helper — and only after the visitor confirms.", "Nur hier gelistete Formulare können von einem KI-Helfer abgesendet werden — und nur nach Bestätigung durch den Besucher."),
    "ag_tools_allowlist_title": ("Which forms can be submitted", "Welche Formulare abgesendet werden dürfen"),
    "ag_tools_allowlist_warn": ("Form submitting is enabled but no forms are allowed yet — pick at least one above.", "Formular-Absenden ist aktiv, aber noch keine Formulare erlaubt — wählen Sie mindestens eines oben."),
    "ag_tools_browser_note": ("Works in browsers that support website tools. Other visitors see your site exactly as today.", "Funktioniert in Browsern mit Website-Werkzeugen. Andere Besucher sehen Ihre Site wie bisher."),
    "ag_tools_catalog_help": ("Choose which actions AI helpers may use. Disabled tools are invisible to the visitor’s AI. Shop and event tools appear only when those features are on your pages.", "Wählen Sie, welche Aktionen KI-Helfer nutzen dürfen. Deaktivierte Werkzeuge sind für die KI des Besuchers unsichtbar. Shop- und Event-Werkzeuge erscheinen nur, wenn diese Features auf Ihren Seiten aktiv sind."),
    "ag_tools_catalog_title": ("Tools", "Werkzeuge"),
    "ag_tools_consent_confirm": ("Asks the visitor to confirm.", "Fragt den Besucher zur Bestätigung."),
    "ag_tools_consent_login": ("Highest consent — asks the visitor to confirm before signing in.", "Höchste Einwilligung — fragt den Besucher vor der Anmeldung."),
    "ag_tools_consent_pay": ("Highest consent — asks the visitor to confirm before paying.", "Höchste Einwilligung — fragt den Besucher vor der Zahlung."),
    "ag_tools_lede": ("Website tools let AI helpers in a visitor’s browser use clear actions on your live site instead of guessing. Your site works the same for everyone else.", "Website-Werkzeuge ermöglichen KI-Helfern im Browser klare Aktionen auf Ihrer Live-Site statt Raten. Für alle anderen bleibt die Site unverändert."),
    "ag_tools_master_hosting": ("On a Birkly-hosted site the tools script is added automatically when this is on. On an external host you add a script tag yourself — see Publish.", "Auf einer Birkly-gehosteten Site wird das Werkzeug-Skript bei Aktivierung automatisch eingefügt. Auf einem externen Host fügen Sie selbst ein Script-Tag ein — siehe Veröffentlichen."),
    "ag_tools_master_title": ("Website tools", "Website-Werkzeuge"),
    "ag_tools_on_count": ("On ({n})", "An ({n})"),
    "ag_tools_pagespeed_body": ("PageSpeed Insights marks WebMCP audits “Not evaluated” when its lab Chrome has no navigator.modelContext (Lighthouse only treats WebMCP as supported if that API exists). That is a lab-browser limit — not a Birkly failure. Birkly still injects the tools script, stamps toolname/tooldescription on allowlisted forms, and registers tools in browsers that do support WebMCP. Verify locally with the steps below. The separate llms.txt check needs /llms.txt on your site (served automatically from a published Agent Guide).", "PageSpeed Insights markiert WebMCP-Audits als „Nicht bewertet“, wenn der Lab-Chrome kein navigator.modelContext hat (Lighthouse behandelt WebMCP nur als unterstützt, wenn diese API existiert). Das ist eine Lab-Browser-Grenze — kein Birkly-Fehler. Birkly injiziert trotzdem das Werkzeug-Skript, setzt toolname/tooldescription auf erlaubte Formulare und registriert Werkzeuge in unterstützten Browsern. Lokal mit den Schritten unten prüfen. Die separate llms.txt-Prüfung benötigt /llms.txt auf Ihrer Site (automatisch bei veröffentlichtem Agent Guide)."),
    "ag_tools_pagespeed_link": ("Lighthouse: registered WebMCP tools", "Lighthouse: registrierte WebMCP-Werkzeuge"),
    "ag_tools_pagespeed_title": ("About PageSpeed Insights", "Über PageSpeed Insights"),
    "ag_tools_preview_empty": ("No tools would be exposed yet — enable tools above.", "Noch keine Werkzeuge sichtbar — aktivieren Sie Werkzeuge oben."),
    "ag_tools_preview_help": ("A plain-language preview of the actions a visitor’s AI helper would see with your current settings.", "Eine verständliche Vorschau der Aktionen, die ein KI-Helfer des Besuchers mit Ihren aktuellen Einstellungen sehen würde."),
    "ag_tools_preview_title": ("Tools this page would expose", "Werkzeuge, die diese Seite bereitstellen würde"),
    "ag_tools_saved": ("Website tools updated", "Website-Werkzeuge aktualisiert"),
    "ag_tools_standard_body": ("Website tools are built on the open WebMCP browser standard, so AI browsers and agents can use your site safely — the same actions, with the visitor in control.", "Website-Werkzeuge basieren auf dem offenen WebMCP-Browser-Standard, damit KI-Browser und Agenten Ihre Site sicher nutzen können — dieselben Aktionen, der Besucher behält die Kontrolle."),
    "ag_tools_standard_link": ("Read Google’s WebMCP explainer", "Googles WebMCP-Erklärung lesen"),
    "ag_tools_support_body": ("Website tools work in browsers that support them. When a browser doesn’t, your page behaves exactly as it does today — nothing breaks.", "Website-Werkzeuge funktionieren in unterstützten Browsern. Wenn nicht, verhält sich Ihre Seite wie heute — nichts bricht."),
    "ag_tools_support_title": ("Browser support", "Browser-Unterstützung"),
    "ag_tools_verify_body": ("On a supported Chromium build (146+), enable the #enable-webmcp-testing flag and open a page with tools on. The registered tools appear in the browser’s model-context inspector.", "In einem unterstützten Chromium-Build (146+) Flag #enable-webmcp-testing aktivieren und eine Seite mit Werkzeugen öffnen. Registrierte Werkzeuge erscheinen im Model-Context-Inspector des Browsers."),
    "ag_tools_verify_step1": ("Use Chrome 146+ and open chrome://flags/#enable-webmcp-testing to enable WebMCP testing.", "Chrome 146+ nutzen und chrome://flags/#enable-webmcp-testing für WebMCP-Tests aktivieren."),
    "ag_tools_verify_step2": ("Open a page on your site that has tools turned on. Confirm forms show toolname/tooldescription in Inspect.", "Eine Seite Ihrer Site mit aktiven Werkzeugen öffnen. In Inspizieren toolname/tooldescription an Formularen prüfen."),
    "ag_tools_verify_step3": ("In DevTools → Lighthouse, run Agentic Browsing (or check the model-context inspector) to see the registered tools.", "In DevTools → Lighthouse „Agentic Browsing“ ausführen (oder Model-Context-Inspector), um registrierte Werkzeuge zu sehen."),
    "ag_tools_verify_title": ("Verify with a browser tool inspector (technical)", "Mit Browser-Werkzeug-Inspector prüfen (technisch)"),
    "ag_tools_why_off": ("Off to start because it lets AI helpers do things on your live site. Turn it on when ready, choose exactly which actions to allow, and turn it off any time. Sensitive actions — like paying or signing in — always ask the visitor to confirm.", "Standardmäßig aus, weil KI-Helfer auf Ihrer Live-Site handeln können. Aktivieren Sie es, wenn bereit, wählen Sie genau erlaubte Aktionen und schalten Sie es jederzeit ab. Sensible Aktionen — z. B. zahlen oder anmelden — fragen den Besucher immer."),
    "ag_unpublish_toast": ("Agent Guide unpublished", "Agent Guide zurückgezogen"),
    "ag_version_label": ("Version {n}", "Version {n}"),
    "ag_webmcp_off": ("Off", "Aus"),
    "ag_webmcp_on": ("On", "An"),
    "ag_wizard_optional": ("optional", "optional"),
}


def merge_de() -> None:
    en = json.loads((LANG / "en.json").read_text(encoding="utf-8"))["admin"]
    data = json.loads((LANG / "de.json").read_text(encoding="utf-8"))
    de = data["admin"]
    updated = 0
    for key, (_, de_val) in STRINGS.items():
        if key not in en:
            continue
        if de.get(key) == en.get(key):
            de[key] = de_val
            updated += 1
    data["admin"] = dict(sorted(de.items()))
    (LANG / "de.json").write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
    print(f"Updated {updated} ag_* DE strings (en unchanged)")


if __name__ == "__main__":
    merge_de()
