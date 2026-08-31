#!/usr/bin/env bash
# OSS M2 — verify git history is clean before public release.
# Exit 0 = no sensitive paths found in history; 1 = audit failed.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FAIL=0
check_empty() {
  local label="$1"
  shift
  local count
  count="$(git log --all --oneline -- "$@" 2>/dev/null | wc -l | tr -d ' ')"
  if [[ "$count" != "0" ]]; then
    echo "FAIL: $label still has $count commit(s) in history"
    FAIL=1
  else
    echo "OK:  $label — no history"
  fi
}

echo "[oss-history-audit] Repository: $ROOT"
echo "[oss-history-audit] HEAD: $(git rev-parse --short HEAD)"

check_empty "users/" users/
check_empty "data/ai_conversations" data/ai_conversations
check_empty "data/ai_approvals" data/ai_approvals
check_empty "api/update.php" api/update.php
check_empty "docs/reviews" docs/reviews
check_empty "docs/roadmap" docs/roadmap

if git log --all -S 'sk_live_' --oneline 2>/dev/null | grep -q .; then
  echo "WARN: sk_live_ string found in history (review manually — may be test fixtures)"
fi

if [[ "$FAIL" -eq 0 ]]; then
  echo "[oss-history-audit] PASS"
  exit 0
fi

echo "[oss-history-audit] FAIL"
exit 1
