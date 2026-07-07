#!/usr/bin/env bash
# Promote bootstrap/Birkly-Marketplace to a standalone git repo.
# Usage: ./bootstrap/promote-marketplace.sh /path/to/Birkly-Marketplace-clone

set -euo pipefail

DEST="${1:-}"
SRC="$(cd "$(dirname "$0")/Birkly-Marketplace" && pwd)"

if [[ -z "$DEST" ]]; then
  echo "Usage: $0 /path/to/Birkly-Marketplace-clone"
  exit 1
fi

mkdir -p "$DEST"
rsync -a --exclude='.git' "$SRC/" "$DEST/"
cd "$DEST"
if [[ ! -d .git ]]; then
  git init -b main
fi
git add -A
git status
echo "Review changes, then: git commit -m 'P9-M0: Marketplace repo scaffold' && git remote add origin git@github.com:Raaakete/Birkly-Marketplace.git && git push -u origin main"
