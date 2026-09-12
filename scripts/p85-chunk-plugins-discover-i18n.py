#!/usr/bin/env python3
"""P85: Hub Discover / plugins-page.js strings missing from admin catalogs (en/de/ja)."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "lang"

STRINGS: dict[str, tuple[str, str, str]] = {
    "plugins_request_failed": ("Request failed", "Anfrage fehlgeschlagen", "リクエストに失敗しました"),
    "marketplace_empty_response": (
        "Hub request failed (empty server response). Try again or check server logs.",
        "Hub-Anfrage fehlgeschlagen (leere Serverantwort). Erneut versuchen oder Server-Logs prüfen.",
        "Hubリクエストが失敗しました（サーバー応答が空です）。再試行するかサーバーログを確認してください。",
    ),
    "marketplace_invalid_response": (
        "Hub request failed (invalid server response). Try again or check server logs.",
        "Hub-Anfrage fehlgeschlagen (ungültige Serverantwort). Erneut versuchen oder Server-Logs prüfen.",
        "Hubリクエストが失敗しました（無効なサーバー応答）。再試行するかサーバーログを確認してください。",
    ),
    "marketplace_request_failed": ("Hub request failed.", "Hub-Anfrage fehlgeschlagen.", "Hubリクエストに失敗しました。"),
    "marketplace_request_network": (
        "Could not reach the Hub service. The install may still be running — wait a moment, then try again.",
        "Hub-Dienst nicht erreichbar. Die Installation läuft möglicherweise noch — kurz warten und erneut versuchen.",
        "Hubサービスに接続できませんでした。インストールがまだ実行中の可能性があります。しばらく待ってから再試行してください。",
    ),
    "marketplace_request_timeout": (
        "Hub request timed out. Check your network connection and try again.",
        "Hub-Anfrage-Zeitüberschreitung. Netzwerk prüfen und erneut versuchen.",
        "Hubリクエストがタイムアウトしました。ネットワークを確認して再試行してください。",
    ),
    "plugins_empty_response": (
        "Plugin request failed (empty server response). Try again or check server logs.",
        "Plugin-Anfrage fehlgeschlagen (leere Serverantwort). Erneut versuchen oder Server-Logs prüfen.",
        "プラグインリクエストが失敗しました（サーバー応答が空です）。再試行するかサーバーログを確認してください。",
    ),
    "plugins_invalid_response": (
        "Plugin request failed (invalid server response). Try again or check server logs.",
        "Plugin-Anfrage fehlgeschlagen (ungültige Serverantwort). Erneut versuchen oder Server-Logs prüfen.",
        "プラグインリクエストが失敗しました（無効なサーバー応答）。再試行するかサーバーログを確認してください。",
    ),
    "hub_plugin_updated": ("Plugin updated successfully", "Plugin erfolgreich aktualisiert", "プラグインを更新しました"),
    "hub_install_failed": ("Install failed", "Installation fehlgeschlagen", "インストールに失敗しました"),
    "hub_update_failed": ("Update failed", "Update fehlgeschlagen", "更新に失敗しました"),
    "hub_confirm_plugin_update": (
        "Update this plugin to the latest Hub version?",
        "Dieses Plugin auf die neueste Hub-Version aktualisieren?",
        "このプラグインを最新のHubバージョンに更新しますか？",
    ),
    "hub_entitlement_held": (
        "This entitlement is on hold. Update payment or contact the marketplace provider.",
        "Diese Berechtigung ist gesperrt. Zahlung aktualisieren oder den Marketplace-Anbieter kontaktieren.",
        "この権利は保留中です。支払いを更新するか、マーケットプレイス提供者に連絡してください。",
    ),
    "hub_entitlement_expired": (
        "This entitlement has expired. Renew it before installing or updating.",
        "Diese Berechtigung ist abgelaufen. Erneuern Sie sie vor Installation oder Update.",
        "この権利の有効期限が切れています。インストールまたは更新の前に更新してください。",
    ),
    "hub_entitlement_seat_limit": (
        "This license has reached its activation or seat limit.",
        "Diese Lizenz hat das Aktivierungs- oder Sitzplatzlimit erreicht.",
        "このライセンスはアクティベーションまたはシート上限に達しています。",
    ),
    "hub_entitlement_wrong_offer": (
        "This license belongs to a different Hub offer.",
        "Diese Lizenz gehört zu einem anderen Hub-Angebot.",
        "このライセンスは別のHubオファーに属しています。",
    ),
    "hub_entitlement_inactive": (
        "This entitlement is inactive. Activate a valid license or complete checkout.",
        "Diese Berechtigung ist inaktiv. Gültige Lizenz aktivieren oder Checkout abschließen.",
        "この権利は無効です。有効なライセンスを有効化するか、チェックアウトを完了してください。",
    ),
    "hub_activate_license_key": ("Activate key", "Schlüssel aktivieren", "キーを有効化"),
    "marketplace_checkout": ("Checkout", "Zur Kasse", "チェックアウト"),
    "hub_no_plugin_settings": ("No settings available.", "Keine Einstellungen verfügbar.", "利用可能な設定はありません。"),
    "hub_license_ref": ("License ref", "Lizenz-Ref.", "ライセンス参照"),
    "hub_plugin_update_badge": ("Update: v{version}", "Update: v{version}", "更新: v{version}"),
    "col_editor_fields_schema": ("Fields Schema", "Feldschema", "フィールドスキーマ"),
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
