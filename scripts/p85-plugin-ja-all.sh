#!/usr/bin/env bash
# One-shot plugin JA catalogs (sequential, no retry loops). Usage: from Birkly repo root.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PYTHONUNBUFFERED=1
PLUGINS=(
  "Events:/agent/repos/Birkly-Plugin-Events"
  "Automation:/agent/repos/Birkly-Plugin-Automation"
  "User:/agent/repos/Birkly-Plugin-User"
  "Studio:/agent/repos/Birkly-Plugin-Studio"
)
for entry in "${PLUGINS[@]}"; do
  name="${entry%%:*}"
  dir="${entry#*:}/lang"
  echo "=== Plugin $name ==="
  python3 "$ROOT/scripts/build-locale-from-en.py" \
    --lang-dir "$dir" --locale ja --force --delay 0.35 --batch-size 20
done
echo "All plugin JA catalogs written."
