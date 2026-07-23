#!/usr/bin/env bash
# Production / staging smoke — P12-M5 deploy closure, P13-M4 structured health validation.
# Checks CMS health (writable dirs, version, disk) and marketplace reachability.
#
# Usage:
#   ./scripts/production-smoke.sh [cms-base-url] [marketplace-url]
#
# Env:
#   BIRKLY_SMOKE_BASE_URL       — default CMS base (http://127.0.0.1:8080)
#   BIRKLY_MARKETPLACE_URL      — default marketplace base
#   BIRKLY_SMOKE_SKIP_MARKETPLACE=1 — skip marketplace probe (local/CI without egress)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE="${1:-${BIRKLY_SMOKE_BASE_URL:-http://127.0.0.1:8080}}"
MARKETPLACE="${2:-${BIRKLY_MARKETPLACE_URL:-https://marketplace.birkly.cloud}}"
BASE="${BASE%/}"
MARKETPLACE="${MARKETPLACE%/}"

FAIL=0
report() { echo "[production-smoke] $*"; }

if ! command -v curl >/dev/null 2>&1; then
  report "FAIL: curl not found"
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  report "FAIL: php not found"
  exit 1
fi

report "CMS base: $BASE"
report "Marketplace: $MARKETPLACE"

health_tmp=$(mktemp)
health_code=$(curl -sS -o "$health_tmp" -w "%{http_code}" "$BASE/api/health.php" 2>/dev/null || echo "000")
if [[ "$health_code" == "200" ]] || [[ "$health_code" == "503" ]]; then
  if smoke_meta=$(php -r '
    require_once $argv[2] . "/core/health.php";
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data)) { fwrite(STDERR, "invalid JSON\n"); exit(1); }

    $requiredKeys = birkly_health_required_response_keys();
    foreach ($requiredKeys as $key) {
      if (!array_key_exists($key, $data)) {
        fwrite(STDERR, "missing key: $key\n");
        exit(1);
      }
    }

    $status = (string) ($data["status"] ?? "");
    if ($status === "error") { fwrite(STDERR, "health status error\n"); exit(1); }

    $version = trim((string) ($data["version"] ?? ""));
    if ($version === "") { fwrite(STDERR, "missing version\n"); exit(1); }

    if (($data["service"] ?? "") !== "birkly-cms") { fwrite(STDERR, "unexpected service id\n"); exit(1); }
    if (trim((string) ($data["php"] ?? "")) === "") { fwrite(STDERR, "missing php\n"); exit(1); }
    if (trim((string) ($data["time"] ?? "")) === "") { fwrite(STDERR, "missing time\n"); exit(1); }

    foreach (["settings", "content", "cache", "logs", "media", "temp"] as $dir) {
      if (!isset($data["writable"][$dir]) || !is_array($data["writable"][$dir])) {
        fwrite(STDERR, "missing writable entry: $dir\n");
        exit(1);
      }
    }
    foreach (["settings", "content", "cache", "logs"] as $dir) {
      if (empty($data["writable"][$dir]["writable"])) {
        fwrite(STDERR, "not writable: $dir\n");
        exit(1);
      }
    }

    foreach (["free_bytes", "total_bytes", "free_percent", "low_space"] as $diskKey) {
      if (!array_key_exists($diskKey, $data["disk"] ?? [])) {
        fwrite(STDERR, "missing disk key: $diskKey\n");
        exit(1);
      }
    }

    if (!isset($data["marketplace"]["status"], $data["marketplace"]["url"])) {
      fwrite(STDERR, "missing marketplace block\n");
      exit(1);
    }

    foreach (["writable_dirs", "disk", "marketplace"] as $checkName) {
      if (!isset($data["checks"][$checkName]["status"])) {
        fwrite(STDERR, "missing checks.$checkName.status\n");
        exit(1);
      }
    }

    echo $version . "\t" . $status;
  ' "$health_tmp" "$ROOT" 2>/dev/null); then
    smoke_version="${smoke_meta%%$'\t'*}"
    smoke_status="${smoke_meta#*$'\t'}"
    report "OK: health ($smoke_version, status=$smoke_status)"
  else
    report "FAIL: health payload validation"
    FAIL=1
  fi
  if php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data) || !isset($data["studio"]["route_ready"])) { exit(2); }
    exit(!empty($data["studio"]["route_ready"]) ? 0 : 1);
  ' "$health_tmp" 2>/dev/null; then
    report "OK: Studio public route ready"
  elif php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    exit(is_array($data) && isset($data["studio"]["route_ready"]) ? 0 : 1);
  ' "$health_tmp" 2>/dev/null; then
    report "WARN: Studio /site/ routing not ready (see health.studio.hints)"
  fi
else
  report "FAIL: health HTTP $health_code ($BASE/api/health.php)"
  FAIL=1
fi
rm -f "$health_tmp"

build_tmp=$(mktemp)
build_code=$(curl -sS -o "$build_tmp" -w "%{http_code}" "$BASE/api/build_info.php" 2>/dev/null || echo "000")
if [[ "$build_code" == "200" ]]; then
  if php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data) || ($data["status"] ?? "") !== "ok") { exit(1); }
    $v = trim((string) ($data["version"] ?? ""));
    if ($v === "") { exit(1); }
    echo $v . "\n";
  ' "$build_tmp" >/dev/null; then
    build_version=$(php -r 'echo trim((string) (json_decode(file_get_contents($argv[1]), true)["version"] ?? ""));' "$build_tmp")
    report "OK: build_info version $build_version"
  else
    report "FAIL: build_info payload"
    FAIL=1
  fi
else
  report "FAIL: build_info HTTP $build_code"
  FAIL=1
fi
rm -f "$build_tmp"

mp_tmp=$(mktemp)
if [[ "${BIRKLY_SMOKE_SKIP_MARKETPLACE:-}" == "1" ]]; then
  report "SKIP: marketplace (BIRKLY_SMOKE_SKIP_MARKETPLACE=1)"
else
  mp_code=$(curl -sS -o "$mp_tmp" -w "%{http_code}" --connect-timeout 10 "$MARKETPLACE/health" 2>/dev/null || echo "000")
  if [[ "$mp_code" == "200" ]] || [[ "$mp_code" == "503" ]]; then
    if php -r '
      $data = json_decode(file_get_contents($argv[1]), true);
      if (!is_array($data)) { exit(1); }
      if (($data["status"] ?? "") === "error") { exit(1); }
      if (($data["service"] ?? "") !== "birkly-marketplace") { exit(1); }
      foreach (["writable", "disk", "checks"] as $key) {
        if (!array_key_exists($key, $data)) { exit(1); }
      }
      foreach (["data", "logs"] as $dir) {
        if (empty($data["writable"][$dir]["writable"])) { exit(1); }
      }
      foreach (["writable_dirs", "disk"] as $checkName) {
        if (!isset($data["checks"][$checkName]["status"])) { exit(1); }
      }
      exit(0);
    ' "$mp_tmp" 2>/dev/null; then
      report "OK: marketplace health ($MARKETPLACE/health)"
    else
      report "FAIL: marketplace health payload ($MARKETPLACE/health)"
      FAIL=1
    fi
  else
    report "FAIL: marketplace health HTTP $mp_code ($MARKETPLACE/health)"
    FAIL=1
  fi

  reg_tmp=$(mktemp)
  reg_code=$(curl -sS -o "$reg_tmp" -w "%{http_code}" --connect-timeout 10 "$MARKETPLACE/api/v1/registry" 2>/dev/null || echo "000")
  if [[ "$reg_code" == "200" ]]; then
    if php -r '
      $data = json_decode(file_get_contents($argv[1]), true);
      exit(is_array($data) && ($data["api_version"] ?? "") === "v1" ? 0 : 1);
    ' "$reg_tmp" 2>/dev/null; then
      report "OK: marketplace registry API"
    else
      report "FAIL: marketplace registry payload"
      FAIL=1
    fi
  else
    report "FAIL: marketplace registry HTTP $reg_code"
    FAIL=1
  fi
  rm -f "$reg_tmp"
fi
rm -f "$mp_tmp"

# Optional friendly /health alias when routed (subdir installs may only expose api/health.php)
alias_code=$(curl -sS -o /dev/null -w "%{http_code}" "$BASE/health" 2>/dev/null || echo "000")
if [[ "$alias_code" == "200" ]]; then
  report "OK: /health alias"
elif [[ "$alias_code" == "404" ]]; then
  report "SKIP: /health alias (use api/health.php)"
else
  report "WARN: /health alias HTTP $alias_code"
fi

if [[ "$FAIL" -ne 0 ]]; then
  report "RESULT: FAIL"
  exit 1
fi

report "RESULT: PASS"
exit 0
