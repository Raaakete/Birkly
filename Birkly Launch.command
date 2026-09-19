#!/bin/bash
# Fallback double-click helper when Gatekeeper blocks the .app — opens Birkly Launch.app when present.
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"
if [[ -d "./Birkly Launch.app" ]]; then
  open "./Birkly Launch.app"
  exit 0
fi
if [[ "$(uname -m)" == "arm64" ]] && [[ -x "./birkly-launch-mac" ]]; then
  exec "./birkly-launch-mac"
fi
if [[ -x "./birkly-launch-mac-intel" ]]; then
  exec "./birkly-launch-mac-intel"
fi
if [[ -x "./birkly-launch-mac" ]]; then
  exec "./birkly-launch-mac"
fi
echo "birkly-launch-mac not found — use ./start-server.sh in Terminal instead."
read -r _
