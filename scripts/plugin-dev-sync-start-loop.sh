#!/bin/sh
# Start background plugin dev git sync (if enabled on volume). Safe to call from entrypoint and enable script.
set -eu

DATA_ROOT="${BIRKLY_DATA:-/app/data}"
ENV_FILE="$DATA_ROOT/settings/plugin_dev_sync.env"
PID_FILE="$DATA_ROOT/logs/plugin-dev-sync.loop.pid"

if [ ! -f "$ENV_FILE" ] || ! grep -q '^PLUGIN_DEV_SYNC_ENABLED=1' "$ENV_FILE" 2>/dev/null; then
  exit 0
fi

if [ -f "$PID_FILE" ]; then
  old_pid="$(tr -d '[:space:]' < "$PID_FILE" 2>/dev/null || true)"
  if [ -n "$old_pid" ] && kill -0 "$old_pid" 2>/dev/null; then
    echo "[plugin-dev-sync] background loop already running (pid $old_pid)"
    exit 0
  fi
fi

interval=60
if grep -q '^PLUGIN_DEV_SYNC_INTERVAL=' "$ENV_FILE" 2>/dev/null; then
  interval="$(grep '^PLUGIN_DEV_SYNC_INTERVAL=' "$ENV_FILE" | head -1 | cut -d= -f2 | tr -d '[:space:]')"
fi
if [ -z "$interval" ] || ! echo "$interval" | grep -qE '^[0-9]+$'; then
  interval=60
fi

mkdir -p "$DATA_ROOT/logs"

(
  export BIRKLY_DATA="$DATA_ROOT"
  while true; do
    if [ -x /app/scripts/plugin-dev-git-sync.sh ]; then
      /app/scripts/plugin-dev-git-sync.sh || true
    fi
    sleep "$interval"
  done
) &

echo $! > "$PID_FILE"
echo "[plugin-dev-sync] background loop started (pid $(cat "$PID_FILE"), every ${interval}s)"
