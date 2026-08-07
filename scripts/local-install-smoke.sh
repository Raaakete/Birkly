#!/usr/bin/env bash
# P58-M2 — Fresh release-tree install smoke (build zip → extract → php -S → onboarding).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${LOCAL_INSTALL_SMOKE_PORT:-$((19000 + ($$ % 1000)))}"
ZIP="/tmp/birkly-p58-m2-release-$$.zip"
EXTRACT="/tmp/birkly-p58-m2-install-$$"
PARENT_LEAK="/tmp/birkly-p58-m2-parent-$$"
COOKIE="/tmp/birkly-p58-m2-cookie-$$.txt"
SERVER_PID=""
FAIL=0

cleanup() {
  if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -f "$ZIP" "$COOKIE"
  rm -rf "$EXTRACT" "$PARENT_LEAK"
}
trap cleanup EXIT

log() { echo "[local-install-smoke] $*"; }
fail() { log "FAIL: $*"; FAIL=1; }

log "Building release zip..."
OUT="$ZIP" "$ROOT/scripts/build-cms-release.sh" >/dev/null

if [[ ! -s "$ZIP" ]]; then
  fail "release zip was not created: $ZIP"
fi

zip_check() {
  php -r '
    $zipPath = $argv[1];
    $entry = $argv[2];
    $z = new ZipArchive();
    if ($z->open($zipPath) !== true) {
        fwrite(STDERR, "cannot open zip\n");
        exit(1);
    }
    exit($z->locateName($entry) === false ? 1 : 0);
  ' "$ZIP" "$1"
}

if zip_check 'lang/en.json'; then
  log "OK: lang/en.json in release zip"
else
  fail "release zip missing lang/en.json"
fi

for skeleton in content/.gitkeep media/.gitkeep users/.gitkeep settings/.gitkeep logs/.gitkeep temp/.gitkeep; do
  if zip_check "$skeleton"; then
    log "OK: $skeleton in release zip"
  else
    fail "release zip missing $skeleton"
  fi
done

if [[ "$FAIL" -ne 0 ]]; then
  log "RESULT: FAIL (release zip contents)"
  exit 1
fi

mkdir -p "$PARENT_LEAK"
mkdir -p "$EXTRACT"
unzip -q "$ZIP" -d "$EXTRACT"

# Remove runtime dirs that may exist from skeleton — probe should recreate them.
for dir in content media users settings logs temp cache; do
  rm -rf "$EXTRACT/$dir"
done

log "Starting php -S on port $PORT (cwd=$EXTRACT)..."
php -S "127.0.0.1:$PORT" -t "$EXTRACT" "$EXTRACT/server-router.php" >/tmp/birkly-p58-m2-server-$$.log 2>&1 &
SERVER_PID=$!
sleep 1

BASE="http://127.0.0.1:$PORT"

probe_json="$(curl -fsS "$BASE/api/setup_probe.php" 2>/dev/null || true)"
if [[ -z "$probe_json" ]]; then
  fail "setup_probe unreachable"
else
  log "OK: setup_probe responded"
  echo "$probe_json" | php -r '
    $d = json_decode(stream_get_contents(STDIN), true);
    $dirs = $d["data"]["writable_dirs"] ?? [];
    foreach (["content","media","users","settings","logs","temp","cache"] as $name) {
      if (empty($dirs[$name]["exists"]) || empty($dirs[$name]["writable"])) {
        fwrite(STDERR, "FAIL writable_dirs.$name\n");
        exit(1);
      }
    }
    if (empty($d["data"]["has_lang_en"])) {
      fwrite(STDERR, "FAIL has_lang_en\n");
      exit(1);
    }
    echo "OK  setup_probe writable dirs + lang\n";
  ' || fail "setup_probe checks"
fi

EMAIL="p58-smoke-$$@birkly.test"
PASS='SecurePass123!'

create_body="$(curl -fsS -c "$COOKIE" -X POST "$BASE/api/onboarding.php?action=create_first_user" \
  -H 'Content-Type: application/json' \
  -d "{\"name\":\"P58 Smoke\",\"email\":\"$EMAIL\",\"password\":\"$PASS\"}" 2>/dev/null || true)"
if [[ "$(echo "$create_body" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["status"]??"";')" != "ok" ]]; then
  fail "create_first_user: $create_body"
else
  log "OK: create_first_user"
fi

if [[ ! -f "$EXTRACT/users/$(php -r "echo md5(strtolower(trim('$EMAIL')));").json" ]]; then
  fail "user file not under extract root"
else
  log "OK: user file under extract root"
fi

save_body="$(curl -fsS -b "$COOKIE" -X POST "$BASE/api/onboarding.php?action=save_site_info" \
  -H 'Content-Type: application/json' \
  -d '{"project_name":"P58 Smoke Site","project_description":"local install smoke"}' 2>/dev/null || true)"
if [[ "$(echo "$save_body" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["status"]??"";')" != "ok" ]]; then
  fail "save_site_info: $save_body"
else
  log "OK: save_site_info"
fi

if [[ ! -f "$EXTRACT/settings/admin.json" ]]; then
  fail "admin.json not under extract settings/"
else
  log "OK: settings/admin.json under extract root"
fi

starter_body="$(curl -fsS -b "$COOKIE" -X POST "$BASE/api/demo.php?action=install_starter" \
  -H 'Content-Type: application/json' \
  -d '{"template":"blank"}' 2>/dev/null || true)"
starter_status="$(echo "$starter_body" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["status"]??"";')"
starter_template="$(echo "$starter_body" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["template"]??"";')"
if [[ "$starter_status" != "ok" || "$starter_template" != "blank" ]]; then
  fail "install_starter blank: $starter_body"
else
  log "OK: install_starter blank (demo.php)"
fi

complete_body="$(curl -fsS -b "$COOKIE" -X POST "$BASE/api/onboarding.php?action=complete" \
  -H 'Content-Type: application/json' \
  -d '{"demo_content_choice":"delete"}' 2>/dev/null || true)"
if [[ "$(echo "$complete_body" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["status"]??"";')" != "ok" ]]; then
  fail "complete: $complete_body"
else
  log "OK: onboarding complete"
fi

if [[ -f "$PARENT_LEAK/settings/onboarding.json" || -f "$PARENT_LEAK/settings/admin.json" ]]; then
  fail "write leak detected in parent directory $PARENT_LEAK"
else
  log "OK: no write leak outside extract root"
fi

if [[ "$FAIL" -ne 0 ]]; then
  log "RESULT: FAIL"
  exit 1
fi

log "RESULT: PASS"
exit 0
