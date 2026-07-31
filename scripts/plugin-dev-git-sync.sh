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

# Official Birkly-Other-Things plugin repos (fallback when clone has no origin).
repo_name_for_id() {
  id="$1"
  case "$id" in
    commerce) echo "Birkly-Plugin-Commerce" ;;
    events) echo "Birkly-Plugin-Events" ;;
    automation) echo "Birkly-Plugin-Automation" ;;
    user_management) echo "Birkly-Plugin-User" ;;
    studio) echo "Birkly-Plugin-Studio" ;;
    *) echo "birkly-${id}" ;;
  esac
}

parse_github_remote() {
  url="$1"
  url="$(echo "$url" | sed -E 's#https://[^@/]+@#https://#')"
  case "$url" in
    git@github.com:*)
      path="${url#git@github.com:}"
      path="${path%.git}"
      path="${path%/}"
      org="${path%%/*}"
      repo="${path#*/}"
      ;;
    *github.com/*)
      path="${url#*github.com/}"
      path="${path%.git}"
      path="${path%/}"
      org="${path%%/*}"
      repo="${path#*/}"
      ;;
    *)
      return 1
      ;;
  esac
  [ -n "$org" ] && [ -n "$repo" ] || return 1
  echo "${org}/${repo}"
}

pull_one() {
  id="$1"
  default_org="$2"
  token="$3"
  dir="$PLUGINS_DIR/$id"

  if [ ! -d "$dir/.git" ]; then
    log "skip $id (not a git clone — no .git)"
    return 0
  fi

  org="$default_org"
  repo="$(repo_name_for_id_from_map "$id")"
  remote_url="$(git -C "$dir" remote get-url origin 2>/dev/null || true)"
  if [ -n "$remote_url" ]; then
    parsed="$(parse_github_remote "$remote_url" 2>/dev/null || true)"
    if [ -n "$parsed" ]; then
      org="${parsed%%/*}"
      repo="${parsed#*/}"
    fi
  fi

  branch="$(git -C "$dir" symbolic-ref -q --short HEAD 2>/dev/null || echo main)"
  if [ -z "$branch" ]; then
    branch=main
  fi

  err_file="$(mktemp)"
  ok=0

  if [ -n "$token" ]; then
    auth="$(printf 'x-access-token:%s' "$token" | base64 | tr -d '\n')"
    if git -C "$dir" -c "http.https://github.com/.extraheader=AUTHORIZATION: basic ${auth}" pull -q origin "$branch" 2>"$err_file"; then
      ok=1
    elif git -C "$dir" -c "http.https://github.com/.extraheader=AUTHORIZATION: basic ${auth}" pull -q origin main 2>"$err_file"; then
      ok=1
    fi
  fi

  if [ "$ok" -eq 0 ]; then
    if git -C "$dir" pull -q origin "$branch" 2>"$err_file"; then
      ok=1
    elif git -C "$dir" pull -q 2>"$err_file"; then
      ok=1
    fi
  fi

  if [ "$ok" -eq 1 ]; then
    log "pulled $id (${org}/${repo}, $branch)"
  else
    err="$(tr '\n' ' ' < "$err_file" | sed 's/[[:space:]]+/ /g' | cut -c1-240)"
    hint=""
    case "$err" in
      *Authentication* | *Invalid username* | *403* | *401*)
        hint=" — check GITHUB_TOKEN in $ENV_FILE (create a new fine-grained PAT with Contents: Read on ${org} repos)"
        ;;
    esac
    log "WARN: pull failed for $id (${org}/${repo}, branch ${branch}): ${err}${hint}"
  fi
  rm -f "$err_file"
}

if [ "${BIRKLY_PLUGIN_DEV_SYNC_DISABLED:-0}" = "1" ]; then
  exit 0
fi

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

# Optional override: PLUGIN_DEV_SYNC_REPOS=commerce:my-repo-name,events:other-repo
REPO_MAP="${PLUGIN_DEV_SYNC_REPOS:-}"

repo_name_for_id_from_map() {
  id="$1"
  if [ -n "$REPO_MAP" ]; then
    IFS=,
    for pair in $REPO_MAP; do
      key="${pair%%:*}"
      val="${pair#*:}"
      key="$(echo "$key" | tr -d '[:space:]')"
      val="$(echo "$val" | tr -d '[:space:]')"
      if [ "$key" = "$id" ] && [ -n "$val" ]; then
        echo "$val"
        return 0
      fi
    done
  fi
  repo_name_for_id "$id"
}

IFS=,
for id in $IDS; do
  id="$(echo "$id" | tr -d '[:space:]')"
  [ -n "$id" ] || continue
  pull_one "$id" "$ORG" "$TOKEN"
done
