#!/usr/bin/env python3
"""One-pass: translate keys where locale still equals English."""
from __future__ import annotations

import importlib.util
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("ble", ROOT / "scripts/build-locale-from-en.py")
ble = importlib.util.module_from_spec(spec)
assert spec.loader
spec.loader.exec_module(ble)

# Only skip strings that should stay Latin product tokens (not keys containing "api"/"mcp").
KEEP_LATIN = frozenset(
    {
        "api",
        "csv",
        "json",
        "html",
        "css",
        "js",
        "url",
        "stripe",
        "github",
        "mcp",
        "birkly",
        "hub",
    }
)


def keep_english_value(en_val: str) -> bool:
    v = en_val.strip()
    if len(v) <= 2:
        return True
    lower = v.lower()
    if lower in KEEP_LATIN:
        return True
    # Short acronyms / codes
    if len(v) <= 5 and v.isupper():
        return True
    return False


def translate_one(text: str) -> str:
    protected, tokens = ble.protect(text)
    try:
        out = ble.GoogleTranslator(source="en", target="ja").translate(protected)
        if out and out.strip():
            return ble.restore(out, tokens)
    except Exception:
        pass
    try:
        from deep_translator import MyMemoryTranslator

        out = MyMemoryTranslator(source="en-US", target="ja-JP").translate(protected)
        if out and out.strip():
            return ble.restore(out, tokens)
    except Exception:
        pass
    return text


def fix_file(lang_dir: Path, locale: str, source: str = "en.json") -> int:
    en_data = json.loads((lang_dir / source).read_text(encoding="utf-8"))
    loc_path = lang_dir / f"{locale}.json"
    if not loc_path.is_file():
        data = json.loads(json.dumps(en_data))
        data["_meta"] = {
            "name": "Japanese",
            "native_name": "日本語",
            "flag": "🇯🇵",
        }
    else:
        data = json.loads(loc_path.read_text(encoding="utf-8"))

    pairs = ble.collect_strings(en_data)
    todo: list[tuple[tuple[str, ...], str]] = []
    for path, en_val in pairs:
        existing = ble.get_at(data, path)
        if not isinstance(en_val, str) or keep_english_value(en_val):
            continue
        if not isinstance(existing, str) or existing != en_val:
            continue
        todo.append((path, en_val))

    print(f"{loc_path}: {len(todo)} identical strings")
    for i, (path, en_val) in enumerate(todo, 1):
        ble.set_at(data, path, translate_one(en_val))
        if i % 40 == 0:
            loc_path.write_text(
                json.dumps(ble.sort_nested(data), ensure_ascii=False, indent=4) + "\n",
                encoding="utf-8",
            )

    if locale == "ja":
        data.setdefault("_meta", {}).update(
            {"name": "Japanese", "native_name": "日本語", "flag": "🇯🇵"}
        )
    loc_path.write_text(
        json.dumps(ble.sort_nested(data), ensure_ascii=False, indent=4) + "\n",
        encoding="utf-8",
    )
    return len(todo)


def main() -> None:
    n = fix_file(ROOT / "lang", "ja")
    sys.exit(0 if n >= 0 else 1)


if __name__ == "__main__":
    main()
