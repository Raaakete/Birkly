#!/usr/bin/env python3
"""P85: collection editor embeddable form snippet + plugin health labels (en/de/ja)."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str, str]] = {
    "col_editor_form_select_placeholder": ("Select...", "Auswählen…", "選択…"),
    "col_editor_form_submit": ("Submit", "Absenden", "送信"),
    "col_editor_form_submitting": ("Submitting...", "Wird gesendet…", "送信中…"),
    "col_editor_form_success": (
        "Thank you! Your submission has been received.",
        "Vielen Dank! Ihre Eingabe wurde empfangen.",
        "ありがとうございます。送信を受け付けました。",
    ),
    "col_editor_form_error_prefix": ("Error: ", "Fehler: ", "エラー: "),
    "col_editor_form_error_generic": ("Something went wrong", "Etwas ist schiefgelaufen", "問題が発生しました"),
    "col_editor_form_error_submit": (
        "Error submitting form. Please try again.",
        "Fehler beim Senden des Formulars. Bitte erneut versuchen.",
        "フォームの送信エラーです。もう一度お試しください。",
    ),
    "hub_health_status_healthy": ("Healthy", "In Ordnung", "正常"),
    "hub_health_status_warning": ("Warning", "Warnung", "警告"),
    "hub_health_status_error": ("Error", "Fehler", "エラー"),
    "hub_health_status_unknown": ("Unknown", "Unbekannt", "不明"),
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
