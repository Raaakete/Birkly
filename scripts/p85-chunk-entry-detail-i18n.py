#!/usr/bin/env python3
"""P85: entry-detail page hardcoded admin strings (en/de/ja)."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

# key -> (en, de, ja)
STRINGS: dict[str, tuple[str, str, str]] = {
    "entry_detail_main_entry": (
        "Main Entry",
        "Haupteintrag",
        "メインエントリ",
    ),
    "entry_detail_parent_entry": (
        "Parent Entry",
        "Übergeordneter Eintrag",
        "親エントリ",
    ),
    "entry_detail_parent_sub_entry": (
        "Parent Sub-Entry",
        "Übergeordneter Untereintrag",
        "親サブエントリ",
    ),
    "entry_detail_sub_entry_fallback": (
        "Sub-Entry",
        "Untereintrag",
        "サブエントリ",
    ),
    "entry_detail_sub_collection_actions_aria": (
        "Sub-collection actions",
        "Aktionen für Unter-Collection",
        "サブコレクションの操作",
    ),
    "entry_detail_add_sub_collection_aria": (
        "Add a new sub-collection",
        "Neue Unter-Collection hinzufügen",
        "新しいサブコレクションを追加",
    ),
    "smart_table_component_not_found": (
        "Smart table component not found.",
        "Smart-Table-Komponente nicht gefunden.",
        "スマートテーブルコンポーネントが見つかりません。",
    ),
    "smart_table_component_not_found_at": (
        "Smart table component not found at: {path}",
        "Smart-Table-Komponente nicht gefunden unter: {path}",
        "スマートテーブルコンポーネントが見つかりません: {path}",
    ),
}


def merge_lang() -> None:
    for loc, idx in [("en.json", 0), ("de.json", 1), ("ja.json", 2)]:
        path = LANG / loc
        data = json.loads(path.read_text(encoding="utf-8"))
        admin = data["admin"]
        for k, triple in STRINGS.items():
            admin[k] = triple[idx]
        data["admin"] = dict(sorted(admin.items()))
        path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
    print(f"Merged {len(STRINGS)} keys into en/de/ja")


if __name__ == "__main__":
    merge_lang()
