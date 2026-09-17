#!/usr/bin/env bash
# One-shot: sync + machine-translate remaining English copies to Japanese (core + Wave 1 plugins).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PYTHONUNBUFFERED=1
DELAY="${BIRKLY_JA_TRANSLATE_DELAY:-0.65}"
BATCH="${BIRKLY_JA_TRANSLATE_BATCH:-15}"
BL="$ROOT/scripts/build-locale-from-en.py"

echo "=== Core lang/ja.json (fill-identical) ==="
php "$ROOT/scripts/i18n-sync-locale.php" ja
python3 "$BL" --locale ja --fill-identical --delay "$DELAY" --batch-size "$BATCH"

PLUGINS=(
  "/agent/repos/Birkly-Plugin-Commerce/lang"
  "/agent/repos/Birkly-Plugin-User/lang"
  "/agent/repos/Birkly-Plugin-Automation/lang"
  "/agent/repos/Birkly-Plugin-Events/lang"
  "/agent/repos/Birkly-Plugin-Studio/lang"
)

for dir in "${PLUGINS[@]}"; do
  if [[ ! -d "$dir" ]]; then
    echo "SKIP missing $dir"
    continue
  fi
  echo "=== Plugin $(basename "$(dirname "$dir")") ==="
  python3 "$BL" --lang-dir "$dir" --locale ja --fill-identical --delay "$DELAY" --batch-size "$BATCH"
done

echo "=== Audits ==="
php "$ROOT/scripts/i18n-audit.php" --locale=ja --strict
php "$ROOT/scripts/i18n-audit.php" --locale=ja --check-untranslated --warn-only || true

for pair in "user_management:/agent/repos/Birkly-Plugin-User" "commerce:/agent/repos/Birkly-Plugin-Commerce" "automation:/agent/repos/Birkly-Plugin-Automation" "events:/agent/repos/Birkly-Plugin-Events" "studio:/agent/repos/Birkly-Plugin-Studio"; do
  pid="${pair%%:*}"
  proot="${pair#*:}"
  php "$ROOT/scripts/i18n-audit-plugin.php" "$proot" "$pid" --locale=ja --strict
done

echo "DONE p85-ja-full-pass"
