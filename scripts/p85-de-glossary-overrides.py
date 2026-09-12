#!/usr/bin/env python3
"""Apply glossary DE overrides for admin keys where de value still equals en."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

# Exact EN admin string value → preferred DE (applied when de[key] == en[key])
GLOSSARY: dict[str, str] = {
    "Add": "Hinzufügen",
    "Remove": "Entfernen",
    "Close": "Schließen",
    "Optional": "Optional",
    "Guide": "Leitfaden",
    "Status": "Status",
    "Publish": "Veröffentlichen",
    "Tools": "Werkzeuge",
    "Off": "Aus",
    "On": "An",
    "Collection": "Kollektion",
    "Plugins": "Plugins",
    "Pricing": "Preise",
    "Email": "E-Mail",
    "Analytics": "Analytics",
    "Viewer": "Betrachter",
    "Editor": "Redakteur",
    "Admin": "Administrator",
    "Read Only": "Nur lesen",
    "Upload": "Hochladen",
    "Preview": "Vorschau",
    "Repository": "Repository",
    "Settings": "Einstellungen",
    "Files": "Dateien",
    "Git": "Git",
    "All": "Alle",
    "Name": "Name",
    "Description": "Beschreibung",
    "Cancel": "Abbrechen",
    "Delete": "Löschen",
    "Save": "Speichern",
    "Search": "Suchen",
    "Filter": "Filter",
    "Active": "Aktiv",
    "Inactive": "Inaktiv",
    "Enabled": "Aktiviert",
    "Disabled": "Deaktiviert",
    "Unknown": "Unbekannt",
    "Never": "Nie",
    "Created": "Erstellt",
    "Updated": "Aktualisiert",
    "Role Management": "Rollenverwaltung",
    "Domains": "Domains",
    "Connected": "Verbunden",
    "Draft": "Entwurf",
    "Published": "Veröffentlicht",
    "External": "Extern",
    "None": "Keine",
    "Advanced": "Erweitert",
    "Essentials": "Grundlagen",
    "Connections": "Verbindungen",
    "Review": "Prüfen",
    "OK": "OK",
    "optional": "optional",
    "Version {n}": "Version {n}",
}


def main() -> None:
    en = json.loads((LANG / "en.json").read_text(encoding="utf-8"))["admin"]
    data = json.loads((LANG / "de.json").read_text(encoding="utf-8"))
    de = data["admin"]
    updated = 0
    for key, en_val in en.items():
        if not isinstance(en_val, str):
            continue
        de_val = de.get(key)
        if de_val != en_val:
            continue
        if en_val in GLOSSARY:
            de[key] = GLOSSARY[en_val]
            updated += 1
    data["admin"] = dict(sorted(de.items()))
    (LANG / "de.json").write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
    print(f"Glossary overrides applied: {updated} keys")


if __name__ == "__main__":
    main()
