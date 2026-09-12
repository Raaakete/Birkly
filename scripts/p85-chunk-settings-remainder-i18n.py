#!/usr/bin/env python3
"""P85: settings.html hardcoded remainder (en/de/ja)."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str, str]] = {
    "settings_timezone_eastern": ("Eastern Time", "Eastern Time (US)", "米国東部時間"),
    "settings_timezone_central": ("Central Time", "Central Time (US)", "米国中部時間"),
    "settings_timezone_mountain": ("Mountain Time", "Mountain Time (US)", "米国山地時間"),
    "settings_timezone_pacific": ("Pacific Time", "Pacific Time (US)", "米国太平洋時間"),
    "settings_project_visit_public_btn": ("Visit public site", "Öffentliche Site öffnen", "公開サイトを開く"),
    "settings_project_activity_filter_aria": (
        "Filter project activity",
        "Projektaktivität filtern",
        "プロジェクトのアクティビティを絞り込む",
    ),
    "settings_users_cms_help_prefix": (
        "CMS users here manage the admin panel. Site visitors and members are managed separately in ",
        "CMS-Benutzer verwalten hier das Admin-Panel. Site-Besucher und Mitglieder werden separat unter ",
        "ここでのCMSユーザーは管理画面を操作します。サイト訪問者とメンバーは ",
    ),
    "settings_site_users_label": ("Site Users", "Site-Benutzer", "サイトユーザー"),
    "settings_users_cms_help_suffix": (
        " (User Management plugin).",
        " verwaltet (User-Management-Plugin).",
        " で別管理です（User Managementプラグイン）。",
    ),
    "settings_invite_user_btn": ("Invite User", "Benutzer einladen", "ユーザーを招待"),
    "settings_add_user_btn": ("Add User", "Benutzer hinzufügen", "ユーザーを追加"),
    "settings_require_strong_password": ("Require Strong Password", "Starkes Passwort verlangen", "強力なパスワードを必須にする"),
    "settings_storage_flatfile": ("Flat File Storage (Default)", "Flatfile-Speicher (Standard)", "フラットファイルストレージ（既定）"),
    "settings_storage_mysql": ("MySQL/MariaDB", "MySQL/MariaDB", "MySQL/MariaDB"),
    "settings_storage_postgresql": ("PostgreSQL", "PostgreSQL", "PostgreSQL"),
    "settings_storage_sqlite_soon": ("SQLite (coming soon)", "SQLite (demnächst)", "SQLite（近日公開）"),
    "settings_plugin_access_label": ("Plugin access", "Plugin-Zugriff", "プラグインアクセス"),
    "settings_edit_user_aria": ("Edit user", "Benutzer bearbeiten", "ユーザーを編集"),
    "settings_delete_user_aria": ("Delete user", "Benutzer löschen", "ユーザーを削除"),
    "settings_edit_role_aria": ("Edit role", "Rolle bearbeiten", "ロールを編集"),
    "settings_delete_role_aria": ("Delete role", "Rolle löschen", "ロールを削除"),
    "delete_role": ("Delete Role", "Rolle löschen", "ロールを削除"),
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
