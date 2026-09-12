#!/usr/bin/env python3
"""P85: plugins hub consent + review modal strings (en/de/ja)."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str, str]] = {
    "marketplace_consent_title": (
        "Plugin permissions",
        "Plugin-Berechtigungen",
        "プラグインの権限",
    ),
    "marketplace_write_review": (
        "Write a review",
        "Rezension schreiben",
        "レビューを書く",
    ),
    "marketplace_reviews_site_bound": (
        "Reviews are tied to this Birkly site installation. Only people who installed and use the plugin here can submit a review.",
        "Rezensionen gelten für diese Birkly-Installation. Nur wer das Plugin hier installiert und nutzt, kann bewerten.",
        "レビューはこのBirklyサイトインストールに紐づきます。ここでプラグインをインストールして利用している人のみ投稿できます。",
    ),
    "marketplace_review_stars_display": (
        "{rating} stars",
        "{rating} Sterne",
        "{rating}つ星",
    ),
    "marketplace_review_missing_plugin": (
        "Missing plugin information",
        "Plugin-Informationen fehlen",
        "プラグイン情報がありません",
    ),
    "marketplace_review_body_required": (
        "Please write a review",
        "Bitte schreiben Sie eine Rezension",
        "レビューを入力してください",
    ),
    "marketplace_review_submitted": (
        "Review submitted",
        "Rezension eingereicht",
        "レビューを送信しました",
    ),
    "marketplace_review_rating_legend": (
        "Rating",
        "Bewertung",
        "評価",
    ),
    "marketplace_review_your_body_label": (
        "Your review",
        "Ihre Rezension",
        "レビュー",
    ),
    "marketplace_review_body_placeholder": (
        "Share your experience using this plugin on your site.",
        "Teilen Sie Ihre Erfahrung mit diesem Plugin auf Ihrer Site.",
        "このプラグインをサイトで使った感想を共有してください。",
    ),
    "marketplace_review_submit_btn": (
        "Submit review",
        "Rezension absenden",
        "レビューを送信",
    ),
    "marketplace_review_stars_5": ("5 stars", "5 Sterne", "5つ星"),
    "marketplace_review_stars_4": ("4 stars", "4 Sterne", "4つ星"),
    "marketplace_review_stars_3": ("3 stars", "3 Sterne", "3つ星"),
    "marketplace_review_stars_2": ("2 stars", "2 Sterne", "2つ星"),
    "marketplace_review_stars_1": ("1 star", "1 Stern", "1つ星"),
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
    print(f"Merged {len(STRINGS)} keys")


if __name__ == "__main__":
    merge_lang()
