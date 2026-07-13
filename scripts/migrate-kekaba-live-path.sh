#!/usr/bin/env bash
# P28-M9 — Delegate kekaba live-path migration to sibling kekaba repo when present.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KEKABA_REPO="${KEKABA_REPO:-$(dirname "$ROOT")/kekaba}"
SCRIPT="$KEKABA_REPO/scripts/migrate-kekaba-live-path.sh"

if [[ ! -x "$SCRIPT" ]]; then
  if [[ -f "$SCRIPT" ]]; then
    chmod +x "$SCRIPT"
  fi
fi

if [[ -x "$SCRIPT" ]]; then
  echo "[migrate-kekaba-live-path] delegating to $SCRIPT"
  exec "$SCRIPT" "$@"
fi

echo "[migrate-kekaba-live-path] FAIL: kekaba repo not found at $KEKABA_REPO" >&2
echo "Clone kekaba as a sibling of Birkly or set KEKABA_REPO=/path/to/kekaba" >&2
exit 1
