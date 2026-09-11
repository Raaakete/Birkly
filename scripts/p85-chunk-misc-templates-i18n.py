#!/usr/bin/env python3
"""P85: remaining admin templates (dashboard, onboarding, entry-editor, media, content, entries)."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str]] = {
    "content_group_icon_help": ("Browse or paste an emoji for this group", "Emoji für diese Gruppe wählen oder einfügen"),
    "dashboard_customize_intro": (
        "Drag widgets to reorder them and use the checkboxes to show or hide widgets on your dashboard.",
        "Widgets per Drag & Drop sortieren; Kontrollkästchen blenden Widgets ein oder aus.",
    ),
    "dashboard_starter_site_help": (
        "Open the starter template site that was installed with your CMS.",
        "Starter-Vorlagen-Site öffnen, die mit Ihrer CMS-Installation mitgeliefert wurde.",
    ),
    "entries_view_trash": ("View trash ({{count}})", "Papierkorb anzeigen ({{count}})"),
    "entry_editor_discovery_seo_btn": ("Search listing in Discovery", "Eintrag in Discovery suchen"),
    "entry_editor_discovery_seo_help": (
        "Edit how this entry appears in Google and social networks.",
        "Darstellung in Google und sozialen Netzwerken bearbeiten.",
    ),
    "entry_editor_permalink_preview": ("Permalink preview", "Permalink-Vorschau"),
    "entry_editor_review_actions_title": ("Review actions", "Review-Aktionen"),
    "entry_editor_site_url_preview_help_after": (" for a full preview.", " für eine vollständige Vorschau."),
    "entry_editor_site_url_preview_help_before": (
        "Set your site URL in ",
        "Site-URL unter ",
    ),
    "entry_editor_site_url_preview_help_link": ("Settings", "Einstellungen"),
    "entries_create_sub_collection": ("Create sub-collection", "Unterkollektion erstellen"),
    "entry_detail_add_sub_under": (
        "Add a new sub-collection under ({{slug}})",
        "Neue Unterkollektion unter ({{slug}}) hinzufügen",
    ),
    "media_upload_current_folder": (
        "Files upload into the current folder.",
        "Dateien werden in den aktuellen Ordner hochgeladen.",
    ),
    "onboarding_choose_template_subtitle": (
        "Optional sample collections and a demo website. Blank always works. Marsk installs a demo journal site.",
        "Optionale Beispielkollektionen und Demo-Website. Leer starten ist möglich. Marsk installiert eine Demo-Journal-Site.",
    ),
    "onboarding_choose_template_title": ("Choose a starter template", "Starter-Vorlage wählen"),
    "onboarding_welcome_subtitle": (
        "Birkly is installed and running. A few quick steps to finish setup.",
        "Birkly ist installiert. Wenige Schritte zum Abschluss des Setups.",
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
