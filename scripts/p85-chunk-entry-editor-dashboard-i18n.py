#!/usr/bin/env python3
"""P85: entry editor sidebar + dashboard hardcoded admin strings (en/de/ja)."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

# key -> (en, de, ja)
STRINGS: dict[str, tuple[str, str, str]] = {
    "dashboard_customize_title": (
        "Customize dashboard",
        "Dashboard anpassen",
        "ダッシュボードをカスタマイズ",
    ),
    "dashboard_reset_to_defaults": (
        "Reset to defaults",
        "Auf Standard zurücksetzen",
        "デフォルトにリセット",
    ),
    "dashboard_open_demo_site": (
        "Open demo site",
        "Demo-Site öffnen",
        "デモサイトを開く",
    ),
    "dashboard_view_demo_website": (
        "View demo website",
        "Demo-Website ansehen",
        "デモサイトを表示",
    ),
    "hide_checklist": (
        "Hide checklist",
        "Checkliste ausblenden",
        "チェックリストを非表示",
    ),
    "checklist_dismiss": (
        "Dismiss",
        "Ausblenden",
        "閉じる",
    ),
    "modal_done": (
        "Done",
        "Fertig",
        "完了",
    ),
    "entry_editor_copy_all_bindings": (
        "Copy all",
        "Alle kopieren",
        "すべてコピー",
    ),
    "entry_editor_copy_permalink": (
        "Copy permalink",
        "Permalink kopieren",
        "パーマリンクをコピー",
    ),
    "entry_editor_copy_meta_snippet": (
        "Copy meta snippet",
        "Meta-Snippet kopieren",
        "メタスニペットをコピー",
    ),
    "entry_editor_slug_help": (
        "URL-friendly identifier (ASCII letters, numbers, and hyphens). Titles support any language; non-Latin titles need a manual slug for readable URLs.",
        "URL-tauglicher Bezeichner (ASCII-Buchstaben, Ziffern, Bindestriche). Titel können jede Sprache nutzen; nicht-latinische Titel brauchen ggf. einen manuellen Slug.",
        "URL向けの識別子（ASCIIの英数字とハイフン）。タイトルは任意の言語に対応しますが、非ラテン文字のタイトルでは読みやすいURLのために手動スラッグが必要な場合があります。",
    ),
    "entry_editor_slug_unicode_hint": (
        "This title cannot produce an ASCII slug. Enter a manual slug (e.g. romanized text or <code>post-2026</code>) or the entry ID will be used.",
        "Aus diesem Titel lässt sich kein ASCII-Slug erzeugen. Geben Sie einen manuellen Slug ein (z. B. romanisiert oder <code>post-2026</code>) oder es wird die Eintrags-ID verwendet.",
        "このタイトルからASCIIスラッグは生成できません。手動でスラッグ（ローマ字など、または <code>post-2026</code>）を入力するか、エントリIDが使われます。",
    ),
    "entry_editor_status_clarity_unpublished": (
        "Hidden from your live site and public API. Only visible in the admin.",
        "Auf der Live-Site und in der Public API ausgeblendet. Nur im Admin sichtbar.",
        "公開サイトとパブリックAPIでは非表示です。管理画面でのみ表示されます。",
    ),
    "entry_editor_status_clarity_published": (
        "Visible on your site and included in the public API.",
        "Auf Ihrer Site sichtbar und in der Public API enthalten.",
        "サイトに表示され、パブリックAPIに含まれます。",
    ),
    "entry_editor_status_clarity_scheduled": (
        "Will publish automatically at the date you set. Hidden until then.",
        "Wird zum gesetzten Datum automatisch veröffentlicht. Bis dahin ausgeblendet.",
        "設定した日時に自動公開されます。それまでは非表示です。",
    ),
    "entry_editor_status_clarity_pending_review": (
        "Waiting for reviewer approval. Not visible on the live site.",
        "Wartet auf Freigabe durch den Prüfer. Auf der Live-Site nicht sichtbar.",
        "レビュアーの承認待ちです。公開サイトでは表示されません。",
    ),
    "entry_editor_publish_at": (
        "Publish At",
        "Veröffentlichen am",
        "公開日時",
    ),
    "entry_editor_unpublish_at": (
        "Unpublish At",
        "Depublizieren am",
        "非公開日時",
    ),
    "entry_editor_publish_at_help": (
        "The entry will be automatically published at this date and time",
        "Der Eintrag wird zu diesem Datum und dieser Uhrzeit automatisch veröffentlicht",
        "この日時にエントリが自動的に公開されます",
    ),
    "entry_editor_unpublish_at_help": (
        "Optional: The entry will be automatically unpublished at this date and time",
        "Optional: Der Eintrag wird zu diesem Datum und dieser Uhrzeit automatisch depubliziert",
        "任意: この日時にエントリが自動的に非公開になります",
    ),
    "entry_editor_notify_reviewer": (
        "Notify Reviewer",
        "Prüfer benachrichtigen",
        "レビュアーに通知",
    ),
    "entry_editor_select_reviewer": (
        "Select a reviewer...",
        "Prüfer auswählen…",
        "レビュアーを選択…",
    ),
    "entry_editor_reviewer_help": (
        "Select a user to notify when this entry is submitted for review",
        "Benutzer auswählen, der bei Einreichung zur Prüfung benachrichtigt wird",
        "レビュー提出時に通知するユーザーを選択",
    ),
    "entry_editor_entry_id": (
        "Entry ID",
        "Eintrags-ID",
        "エントリID",
    ),
    "entry_editor_created_by": (
        "Created By",
        "Erstellt von",
        "作成者",
    ),
    "entry_editor_updated_by": (
        "Updated By",
        "Aktualisiert von",
        "更新者",
    ),
    "entry_editor_revisions": (
        "Revisions",
        "Revisionen",
        "リビジョン",
    ),
    "entry_editor_revisions_help": (
        "Review previous saved versions and restore an older revision when needed.",
        "Frühere gespeicherte Versionen ansehen und bei Bedarf wiederherstellen.",
        "保存済みの以前のバージョンを確認し、必要に応じて復元します。",
    ),
    "entry_editor_templating_intro_before": (
        "For a detailed overview about templating, please visit ",
        "Eine ausführliche Übersicht zur Templating finden Sie auf ",
        "テンプレートの詳細は ",
    ),
    "entry_editor_templating_intro_link": (
        "the documentation site",
        "der Dokumentationsseite",
        "ドキュメントサイト",
    ),
    "entry_editor_templating_intro_after": (
        ".",
        ".",
        " をご覧ください。",
    ),
    "entry_editor_collection_template": (
        "Collection template",
        "Collection-Vorlage",
        "コレクションテンプレート",
    ),
    "entry_editor_meta_seo_snippet": (
        "Meta & SEO snippet",
        "Meta- & SEO-Snippet",
        "メタ・SEOスニペット",
    ),
    "entry_editor_entry_level_example": (
        "Entry Level Example",
        "Beispiel auf Eintragsebene",
        "エントリレベルの例",
    ),
    "entry_editor_collection_level_example": (
        "Collection Level Example",
        "Beispiel auf Collection-Ebene",
        "コレクションレベルの例",
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
