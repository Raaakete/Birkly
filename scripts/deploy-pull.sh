#!/usr/bin/env bash
# Pull latest Birkly platform code on a server (repo root — not admin/ alone).
set -euo pipefail

ROOT="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
BRANCH="${2:-main}"

if [[ ! -d "$ROOT/admin" || ! -d "$ROOT/api" || ! -d "$ROOT/core" ]]; then
  echo "ERROR: $ROOT is not a Birkly CMS root (need admin/, api/, core/ siblings)." >&2
  echo "Clone: git clone https://github.com/Raaakete/Birkly.git && cd Birkly" >&2
  exit 1
fi

cd "$ROOT"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull origin "$BRANCH"

echo ""
echo "Deployed $(git rev-parse --short HEAD) on branch $BRANCH"
echo "Verify: open /admin/deploy-check.php while logged in"
php -l admin/templates/content.html >/dev/null && echo "content.html: syntax OK"
