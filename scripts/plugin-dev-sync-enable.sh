#!/usr/bin/env bash
# One-time enable plugin git auto-sync on a Docker/Dokploy install (writes to data volume).
#
# Usage (Dokploy Terminal or docker exec):
#   GITHUB_TOKEN=ghp_... ./scripts/plugin-dev-sync-enable.sh
#   ./scripts/plugin-dev-sync-enable.sh --token ghp_...
#
# After enable: redeploys restart the background pull loop automatically (entrypoint).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DATA_ROOT="${BIRKLY_DATA:-/app/data}"
SETTINGS="$DATA_ROOT/settings"
ENV_FILE="$SETTINGS/plugin_dev_sync.env"
INTERVAL="${PLUGIN_DEV_SYNC_INTERVAL:-60}"
ORG="${GITHUB_ORG:-Birkly-Other-Things}"
IDS="${PLUGIN_DEV_SYNC_IDS:-commerce,events,automation,user_management,studio}"

TOKEN="${GITHUB_TOKEN:-}"
while [ $# -gt 0 ]; do
  case "$1" in
    --token)
      TOKEN="${2:-}"
      shift 2
      ;;
    --org)
      ORG="${2:-}"
      shift 2
      ;;
    --interval)
      INTERVAL="${2:-60}"
      shift 2
      ;;
    *)
      echo "Unknown option: $1" >&2
      exit 1
      ;;
  esac
done

if [ -z "$TOKEN" ]; then
  echo "Optional: set GITHUB_TOKEN for private repos (fine-grained read on org repos)." >&2
fi

mkdir -p "$SETTINGS" "$DATA_ROOT/logs" "$DATA_ROOT/plugins"

{
  echo "PLUGIN_DEV_SYNC_ENABLED=1"
  echo "PLUGIN_DEV_SYNC_INTERVAL=${INTERVAL}"
  echo "GITHUB_ORG=${ORG}"
  echo "PLUGIN_DEV_SYNC_IDS=${IDS}"
  if [ -n "$TOKEN" ]; then
    echo "GITHUB_TOKEN=${TOKEN}"
  fi
} > "$ENV_FILE"
chmod 600 "$ENV_FILE"

ln -sfn /app/core "$DATA_ROOT/core" 2>/dev/null || true
ln -sfn /app/fieldtypes "$DATA_ROOT/fieldtypes" 2>/dev/null || true
ln -sfn /app/api "$DATA_ROOT/api" 2>/dev/null || true

if [ -x "$ROOT/scripts/plugin-dev-git-sync.sh" ]; then
  "$ROOT/scripts/plugin-dev-git-sync.sh" || true
fi

echo "[plugin-dev-sync-enable] Written $ENV_FILE"
echo "[plugin-dev-sync-enable] Auto-sync starts on every container start (interval ${INTERVAL}s)."
echo "[plugin-dev-sync-enable] Log: $DATA_ROOT/logs/plugin-dev-sync.log"
echo "[plugin-dev-sync-enable] Disable: set PLUGIN_DEV_SYNC_ENABLED=0 in that file."
