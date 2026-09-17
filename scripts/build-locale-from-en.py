#!/usr/bin/env python3
"""Build a locale JSON file from English canonical strings (machine-assisted).

Preserves placeholders: {name}, {{count}}, HTML tags, and @i18n: references.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import time
from pathlib import Path
from typing import Any

from deep_translator import GoogleTranslator

PLACEHOLDER_RE = re.compile(
    r"(\{\{[a-zA-Z0-9_]+\}\}|\{[a-zA-Z0-9_.]+\}|%[sd]|@[a-zA-Z0-9_.:-]+|<[^>]+>)"
)


def protect(text: str) -> tuple[str, list[str]]:
    tokens: list[str] = []

    def repl(m: re.Match[str]) -> str:
        tokens.append(m.group(0))
        return f"__BIRKLYPH{len(tokens) - 1}__"

    return PLACEHOLDER_RE.sub(repl, text), tokens


def restore(text: str, tokens: list[str]) -> str:
    for i, tok in enumerate(tokens):
        text = text.replace(f"__BIRKLYPH{i}__", tok)
    return text


def should_skip_translate(
    en: str,
    existing: str | None,
    force: bool,
    fill_identical: bool,
) -> bool:
    if force:
        return False
    if existing is None:
        return False
    if existing != en:
        return True
    if fill_identical:
        return False
    return True


def collect_strings(obj: Any, path: tuple[str, ...] = ()) -> list[tuple[tuple[str, ...], str]]:
    out: list[tuple[tuple[str, ...], str]] = []
    if isinstance(obj, dict):
        for k, v in obj.items():
            if k == "_meta":
                continue
            out.extend(collect_strings(v, path + (k,)))
    elif isinstance(obj, str):
        out.append((path, obj))
    return out


def get_at(data: dict, path: tuple[str, ...]) -> Any:
    cur: Any = data
    for p in path:
        if not isinstance(cur, dict) or p not in cur:
            return None
        cur = cur[p]
    return cur


def set_at(data: dict, path: tuple[str, ...], value: str) -> None:
    cur = data
    for p in path[:-1]:
        cur = cur.setdefault(p, {})
    cur[path[-1]] = value


def sort_nested(obj: Any) -> Any:
    if isinstance(obj, dict):
        return {k: sort_nested(obj[k]) for k in sorted(obj.keys())}
    return obj


def translate_one_string(text: str, translator: GoogleTranslator, delay: float) -> str:
    protected, tokens = protect(text)
    target = getattr(translator, "target", "ja")
    if target == "ja":
        try:
            from deep_translator import MyMemoryTranslator

            ja = MyMemoryTranslator(source="en-US", target="ja-JP").translate(protected)
            if ja and ja.strip():
                time.sleep(delay)
                return restore(ja, tokens)
        except Exception:
            pass
    try:
        ja = translator.translate(protected)
        if ja and ja.strip():
            time.sleep(delay)
            return restore(ja, tokens)
    except Exception:
        pass
    time.sleep(delay)
    return text


def translate_batch(
    translator: GoogleTranslator,
    items: list[str],
    *,
    delay: float,
    retries: int = 1,
) -> list[str]:
    del retries
    return [translate_one_string(text, translator, delay) for text in items]


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    ap.add_argument("--locale", default="ja")
    ap.add_argument("--source", default="en.json", help="Source file name under lang/")
    ap.add_argument("--lang-dir", type=Path, default=None, help="Override lang directory")
    ap.add_argument("--force", action="store_true", help="Retranslate all strings from English")
    ap.add_argument(
        "--fill-identical",
        action="store_true",
        help="Retranslate locale values that still match English",
    )
    ap.add_argument("--batch-size", type=int, default=40)
    ap.add_argument("--delay", type=float, default=0.08)
    ap.add_argument("--meta-name", default=None)
    ap.add_argument("--meta-native", default=None)
    args = ap.parse_args()

    lang_dir = args.lang_dir or (args.root / "lang")
    en_path = lang_dir / args.source
    loc_path = lang_dir / f"{args.locale}.json"

    en_data = json.loads(en_path.read_text(encoding="utf-8"))
    if loc_path.is_file():
        loc_data = json.loads(loc_path.read_text(encoding="utf-8"))
    else:
        loc_data = {"_meta": en_data.get("_meta", {}).copy(), **{k: {} for k in en_data if k != "_meta"}}

    meta = loc_data.setdefault("_meta", {})
    if args.meta_name:
        meta["name"] = args.meta_name
    if args.meta_native:
        meta["native_name"] = args.meta_native
    if args.locale == "ja":
        meta.setdefault("name", "Japanese")
        meta.setdefault("native_name", "日本語")
        meta.setdefault("flag", "🇯🇵")

    pairs = collect_strings(en_data)
    todo: list[tuple[tuple[str, ...], str]] = []
    for path, en_val in pairs:
        existing = get_at(loc_data, path)
        if isinstance(existing, str) and should_skip_translate(
            en_val, existing, args.force, args.fill_identical
        ):
            continue
        todo.append((path, en_val))

    print(f"Translating {len(todo)} strings → {args.locale} ({len(pairs)} total in source)")

    target = "ja" if args.locale == "ja" else args.locale
    translator = GoogleTranslator(source="en", target=target)
    i = 0
    while i < len(todo):
        chunk = todo[i : i + args.batch_size]
        texts = [t for _, t in chunk]
        translated = translate_batch(translator, texts, delay=args.delay)
        for (path, _), ja in zip(chunk, translated):
            set_at(loc_data, path, ja)
        i += args.batch_size
        if i % 200 == 0 or i >= len(todo):
            print(f"  progress {min(i, len(todo))}/{len(todo)}")
            loc_path.write_text(
                json.dumps(sort_nested(loc_data), ensure_ascii=False, indent=4) + "\n",
                encoding="utf-8",
            )

    loc_path.write_text(
        json.dumps(sort_nested(loc_data), ensure_ascii=False, indent=4) + "\n",
        encoding="utf-8",
    )
    print(f"Wrote {loc_path}")


if __name__ == "__main__":
    main()
