#!/usr/bin/env bash
# Remove sensitive / internal paths from ALL git history (P36 / P86-M3).
# Requires: git-filter-repo (pip install git-filter-repo OR apt install git-filter-repo)
#
# WARNING: Rewrites history. After this you MUST force-push main and tell collaborators to re-clone.
# Run only from a fresh backup clone if unsure.
#
# Usage:
#   ./scripts/scrub-oss-history.sh --dry-run   # show what would be removed
#   ./scripts/scrub-oss-history.sh --execute   # destructive; then verify with oss-history-audit.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PATHS=(
  data/ai_conversations
  data/ai_approvals
  data/invitations
  api/update.php
  docs/reviews
  docs/roadmap
)

MODE="${1:-}"
if [[ "$MODE" != "--dry-run" && "$MODE" != "--execute" ]]; then
  echo "Usage: $0 --dry-run | --execute" >&2
  exit 1
fi

if ! command -v git-filter-repo >/dev/null 2>&1; then
  echo "FAIL: git-filter-repo not found. Install one of:" >&2
  echo "  pip install git-filter-repo" >&2
  echo "  sudo apt install git-filter-repo   # Debian/Ubuntu" >&2
  exit 1
fi

echo "[scrub-oss-history] Repository: $ROOT"
echo "[scrub-oss-history] HEAD: $(git rev-parse --short HEAD)"
echo "[scrub-oss-history] Paths to purge from history:"
printf '  - %s\n' "${PATHS[@]}"

if [[ -n "$(git status --porcelain)" ]]; then
  echo "FAIL: working tree not clean. Commit or stash first." >&2
  exit 1
fi

if [[ "$MODE" == "--dry-run" ]]; then
  echo ""
  echo "Commits touching these paths (sample):"
  for p in "${PATHS[@]}"; do
    count="$(git log --all --oneline -- "$p" 2>/dev/null | wc -l | tr -d ' ')"
    echo "  $p: $count commit(s) in history"
  done
  echo ""
  echo "To apply: $0 --execute"
  echo "Then: ./scripts/oss-history-audit.sh"
  echo "Then: git push --force-with-lease origin main   # coordinate with team first"
  exit 0
fi

echo ""
echo "This will REWRITE git history for paths listed above."
read -r -p "Type YES to continue: " confirm
if [[ "$confirm" != "YES" ]]; then
  echo "Aborted."
  exit 1
fi

args=(--force --invert-paths)
for p in "${PATHS[@]}"; do
  args+=(--path "$p")
done

# Remove listed paths from entire history (keep everything else).
git filter-repo "${args[@]}"

echo ""
echo "[scrub-oss-history] Running audit..."
if ./scripts/oss-history-audit.sh; then
  echo "[scrub-oss-history] OK — audit passed."
  echo "Next: force-push (after team notice): git push --force-with-lease origin main"
  echo "See docs/OSS-GIT-HISTORY.md and Birkly-Marketplace internal/planning/reviews/P86-M3-oss-history-handoff.md"
else
  echo "[scrub-oss-history] Audit still failing — inspect output above." >&2
  exit 1
fi
