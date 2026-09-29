#!/usr/bin/env bash
# Deprecated. Do not rebuild locales with phrase maps. Use scripts/i18n-new-locale.php
# and translate from lang/en.json. Check with scripts/i18n-audit.php --selectable.
echo "p85-es-full-pass.sh is deprecated. Translate from lang/en.json and run scripts/i18n-audit.php --selectable." >&2
exit 1
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "=== Core lang/es.json ==="
php "$ROOT/scripts/i18n-sync-locale.php" es
php "$ROOT/scripts/p85-es-rebuild-from-en.php" core

PLUGINS=(
  "user_management:/agent/repos/Birkly-Plugin-User"
  "commerce:/agent/repos/Birkly-Plugin-Commerce"
  "automation:/agent/repos/Birkly-Plugin-Automation"
  "events:/agent/repos/Birkly-Plugin-Events"
  "studio:/agent/repos/Birkly-Plugin-Studio"
)

for pair in "${PLUGINS[@]}"; do
  pid="${pair%%:*}"
  proot="${pair#*:}"
  if [[ ! -d "$proot/lang" ]]; then
    echo "SKIP missing $proot"
    continue
  fi
  echo "=== Plugin $pid ==="
  php "$ROOT/scripts/i18n-sync-plugin-locale.php" "$proot" "$pid" es
  php "$ROOT/scripts/p85-es-rebuild-from-en.php" plugin "$proot" "$pid"
done

echo "=== Audits (es strict) ==="
php "$ROOT/scripts/i18n-audit.php" --locale=es --strict
for pair in "${PLUGINS[@]}"; do
  pid="${pair%%:*}"
  proot="${pair#*:}"
  php "$ROOT/scripts/i18n-audit-plugin.php" "$proot" "$pid" --locale=es --strict
done

echo "DONE p85-es-full-pass"
