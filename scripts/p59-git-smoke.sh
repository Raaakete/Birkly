#!/usr/bin/env bash
# P59 manual smoke: drive the project git API end to end against a local bare
# repo. Requires a running dev server and an admin login.
#
#   php -S localhost:8080 -t . server-router.php
#   php scripts/create-default-user.php
#   ./scripts/p59-git-smoke.sh
set -euo pipefail

BASE="${BASE:-http://localhost:8080}"
EMAIL="${EMAIL:-admin@localhost}"
PASSWORD="${PASSWORD:-password}"
JAR="$(mktemp)"
BARE="${BARE:-$(mktemp -d)/project.git}"

cleanup() { rm -f "$JAR"; }
trap cleanup EXIT

say() { printf '\n=== %s\n' "$1"; }

say "login"
CSRF="$(curl -s -c "$JAR" "$BASE/admin/login.php" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')"
curl -s -b "$JAR" -c "$JAR" -o /dev/null -X POST "$BASE/admin/login.php" \
  --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASSWORD" --data-urlencode "_csrf=$CSRF"
CSRF="$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/index.php?page=settings" | grep -o 'name="csrf-token" content="[^"]*"' | head -1 | sed 's/.*content="//;s/"//')"
echo "csrf: ${CSRF:0:12}…"

# api <action> [json fields without the outer braces]
api() {
  local action="$1"
  local fields="${2:-}"
  local payload="{\"_csrf\":\"$CSRF\""
  [ -n "$fields" ] && payload="$payload,$fields"
  payload="$payload}"
  curl -s -b "$JAR" -c "$JAR" -H 'Content-Type: application/json' \
    -X POST "$BASE/api/project_git.php?action=$action" -d "$payload"
  echo
}

say "capability"
curl -s -b "$JAR" "$BASE/api/project_git.php?action=capability"; echo

say "bare repo at $BARE"
git init --bare --quiet "$BARE"
git --git-dir="$BARE" symbolic-ref HEAD refs/heads/main

say "connect"
api connect "\"provider\":\"generic\",\"remote_url\":\"$BARE\",\"branch\":\"main\",\"auth_mode\":\"none\""

say "status"
curl -s -b "$JAR" "$BASE/api/project_git.php?action=status"; echo

say "push"
api push '"message":"P59 smoke push"'

say "remote contents"
git --git-dir="$BARE" ls-tree -r --name-only main || true

say "pull preview (expect nothing new)"
api pull_preview

say "simulate a remote edit, then pull"
WORK="$(mktemp -d)"
git clone --quiet "$BARE" "$WORK/clone"
echo '<!-- remote edit -->' >> "$WORK/clone/index.html"
git -C "$WORK/clone" -c user.email=smoke@birkly.local -c user.name=Smoke commit -aqm 'remote edit'
git -C "$WORK/clone" push --quiet origin main
api pull_preview
api pull_apply

say "conflict: edit both sides"
echo '<!-- local edit -->' >> project/index.html
echo '<!-- second remote edit -->' >> "$WORK/clone/index.html"
git -C "$WORK/clone" -c user.email=smoke@birkly.local -c user.name=Smoke commit -aqm 'second remote edit'
git -C "$WORK/clone" push --quiet origin main
api pull_apply

say "resolve keep_birkly"
api resolve_conflict '"strategy":"keep_birkly"'

say "final remote index.html"
git --git-dir="$BARE" show main:index.html | tail -3

say "disconnect"
api disconnect

rm -rf "$WORK"
echo
echo "p59-git-smoke: done"
