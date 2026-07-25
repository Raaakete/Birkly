#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
failed=0

for f in "$ROOT"/admin/js/*.js; do
  if ! node --check "$f" >/dev/null 2>&1; then
    echo "Syntax error: ${f#$ROOT/}"
    node --check "$f" 2>&1 || true
    failed=1
  fi
done

if [[ "$failed" -ne 0 ]]; then
  exit 1
fi

echo "admin JS syntax check passed ($(ls -1 "$ROOT"/admin/js/*.js | wc -l) files)"
