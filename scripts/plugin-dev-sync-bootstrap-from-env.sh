#!/bin/sh
# Bootstrap / refresh plugin-dev sync config from Dokploy/container env vars.
# Lets operators set GITHUB_TOKEN in the Dokploy UI and Redeploy — no terminal.
#
# Recognized env vars:
#   GITHUB_TOKEN              — required for private Birkly-Other-Things repos
#   PLUGIN_DEV_SYNC_ENABLED   — default 1 when GITHUB_TOKEN is set
#   PLUGIN_DEV_SYNC_INTERVAL  — default 60
#   GITHUB_ORG                — default Birkly-Other-Things
#   PLUGIN_DEV_SYNC_IDS       — default commerce,events,automation,user_management,studio
#   PLUGIN_DEV_SYNC_REPOS     — optional id:repo map
set -eu

DATA_ROOT="${BIRKLY_DATA:-/app/data}"
SETTINGS="$DATA_ROOT/settings"
ENV_FILE="$SETTINGS/plugin_dev_sync.env"

PROCESS_TOKEN="${GITHUB_TOKEN:-}"
PROCESS_ENABLED="${PLUGIN_DEV_SYNC_ENABLED:-}"
PROCESS_INTERVAL="${PLUGIN_DEV_SYNC_INTERVAL:-}"
PROCESS_ORG="${GITHUB_ORG:-}"
PROCESS_IDS="${PLUGIN_DEV_SYNC_IDS:-}"
PROCESS_REPOS="${PLUGIN_DEV_SYNC_REPOS:-}"

# Nothing to do unless Dokploy (or compose) provided a token or explicit enable.
if [ -z "$PROCESS_TOKEN" ] && [ "$PROCESS_ENABLED" != "1" ]; then
  exit 0
fi

mkdir -p "$SETTINGS" "$DATA_ROOT/logs" "$DATA_ROOT/plugins"

INTERVAL=60
ORG="Birkly-Other-Things"
IDS="commerce,events,automation,user_management,studio"
REPOS="commerce:Birkly-Plugin-Commerce,events:Birkly-Plugin-Events,automation:Birkly-Plugin-Automation,user_management:Birkly-Plugin-User,studio:Birkly-Plugin-Studio"
FILE_TOKEN=""
ENABLED=""

if [ -f "$ENV_FILE" ]; then
  # Read existing volume config without clobbering process env permanently.
  INTERVAL="$(grep -E '^PLUGIN_DEV_SYNC_INTERVAL=' "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' || true)"
  ORG="$(grep -E '^GITHUB_ORG=' "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' || true)"
  IDS="$(grep -E '^PLUGIN_DEV_SYNC_IDS=' "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' || true)"
  REPOS="$(grep -E '^PLUGIN_DEV_SYNC_REPOS=' "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' || true)"
  FILE_TOKEN="$(grep -E '^GITHUB_TOKEN=' "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' || true)"
  ENABLED="$(grep -E '^PLUGIN_DEV_SYNC_ENABLED=' "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' || true)"
fi

[ -n "$INTERVAL" ] || INTERVAL=60
[ -n "$ORG" ] || ORG="Birkly-Other-Things"
[ -n "$IDS" ] || IDS="commerce,events,automation,user_management,studio"
[ -n "$REPOS" ] || REPOS="commerce:Birkly-Plugin-Commerce,events:Birkly-Plugin-Events,automation:Birkly-Plugin-Automation,user_management:Birkly-Plugin-User,studio:Birkly-Plugin-Studio"

# Dokploy/process values win.
[ -n "$PROCESS_INTERVAL" ] && INTERVAL="$PROCESS_INTERVAL"
[ -n "$PROCESS_ORG" ] && ORG="$PROCESS_ORG"
[ -n "$PROCESS_IDS" ] && IDS="$PROCESS_IDS"
[ -n "$PROCESS_REPOS" ] && REPOS="$PROCESS_REPOS"

TOKEN="$FILE_TOKEN"
[ -n "$PROCESS_TOKEN" ] && TOKEN="$PROCESS_TOKEN"

if [ -n "$PROCESS_ENABLED" ]; then
  ENABLED="$PROCESS_ENABLED"
elif [ -n "$TOKEN" ]; then
  ENABLED=1
elif [ -z "$ENABLED" ]; then
  ENABLED=0
fi

{
  echo "PLUGIN_DEV_SYNC_ENABLED=${ENABLED}"
  echo "PLUGIN_DEV_SYNC_INTERVAL=${INTERVAL}"
  echo "GITHUB_ORG=${ORG}"
  echo "PLUGIN_DEV_SYNC_IDS=${IDS}"
  echo "PLUGIN_DEV_SYNC_REPOS=${REPOS}"
  if [ -n "$TOKEN" ]; then
    echo "GITHUB_TOKEN=${TOKEN}"
  fi
} > "$ENV_FILE"
chmod 600 "$ENV_FILE"

echo "[plugin-dev-sync] bootstrapped $ENV_FILE (enabled=${ENABLED})"
