#!/usr/bin/env bash
# P85 tier-1: sync + rebuild Spanish catalogs for core + Wave 1 plugins (no external MT).
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
