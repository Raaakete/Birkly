#!/bin/bash
# Double-click on macOS (Terminal). Runs the arm64 or Intel launcher next to this file.
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"
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
