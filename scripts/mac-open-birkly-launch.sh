#!/usr/bin/env bash
# One-shot helper after Chrome download: clear quarantine and open Birkly Launch.app
set -euo pipefail
DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DIR"
xattr -cr "Birkly Launch.app" . 2>/dev/null || true
if [[ ! -d "Birkly Launch.app" ]]; then
  echo "Birkly Launch.app not found in $DIR"
  exit 1
fi
open "Birkly Launch.app"
