#!/bin/sh
# Pull git-based plugin folders on the data volume (test/staging dev sync).
# Config: /app/data/settings/plugin_dev_sync.env (on volume — survives redeploys).
# Started automatically by docker/entrypoint.sh when PLUGIN_DEV_SYNC_ENABLED=1.
set -eu

DATA_ROOT="${BIRKLY_DATA:-/app/data}"
ENV_FILE="$DATA_ROOT/settings/plugin_dev_sync.env"
PLUGINS_DIR="$DATA_ROOT/plugins"
LOG_FILE="$DATA_ROOT/logs/plugin-dev-sync.log"

log() {
  echo "[plugin-dev-sync] $*" >> "$LOG_FILE" 2>/dev/null || echo "[plugin-dev-sync] $*"
}

link_plugin_dev_paths() {
  ln -sfn /app/core "$DATA_ROOT/core" 2>/dev/null || true
  ln -sfn /app/fieldtypes "$DATA_ROOT/fieldtypes" 2>/dev/null || true
  ln -sfn /app/api "$DATA_ROOT/api" 2>/dev/null || true
}

repo_name_for_id() {
  id="$1"
  case "$id" in
    user_management) echo "birkly-user-management" ;;
    *) echo "birkly-${id}" ;;
  esac
}

pull_one() {
  id="$1"
  org="$2"
  token="$3"
  dir="$PLUGINS_DIR/$id"

  if [ ! -d "$dir/.git" ]; then
    return 0
  fi

  repo="$(repo_name_for_id "$id")"
  if [ -n "$token" ]; then
    url="https://x-access-token:${token}@github.com/${org}/${repo}.git"
    if git -C "$dir" pull -q "$url" 2>/dev/null; then
      log "pulled $id"
      return 0
    fi
  fi

  if git -C "$dir" pull -q 2>/dev/null; then
    log "pulled $id"
  else
    log "WARN: pull failed for $id"
  fi
}

if [ ! -f "$ENV_FILE" ]; then
  exit 0
fi

set -a
# shellcheck disable=SC1090
. "$ENV_FILE"
set +a

if [ "${PLUGIN_DEV_SYNC_ENABLED:-0}" != "1" ]; then
  exit 0
fi

if ! command -v git >/dev/null 2>&1; then
  log "WARN: git not installed — skip"
  exit 0
fi

mkdir -p "$DATA_ROOT/logs" "$PLUGINS_DIR"
link_plugin_dev_paths

ORG="${GITHUB_ORG:-Birkly-Other-Things}"
TOKEN="${GITHUB_TOKEN:-}"
IDS="${PLUGIN_DEV_SYNC_IDS:-commerce,events,automation,user_management,studio}"

IFS=,
for id in $IDS; do
  id="$(echo "$id" | tr -d '[:space:]')"
  [ -n "$id" ] || continue
  pull_one "$id" "$ORG" "$TOKEN"
done
