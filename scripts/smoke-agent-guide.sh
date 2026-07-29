#!/usr/bin/env bash
# Smoke test Agent Guide public endpoints (production or local).
# Usage:
#   ./scripts/smoke-agent-guide.sh
#   ./scripts/smoke-agent-guide.sh https://birkly.cloud https://cms.birkly.cloud

set -euo pipefail

PUBLIC_BASE="${1:-https://birkly.cloud}"
CMS_BASE="${2:-https://cms.birkly.cloud}"

PUBLIC_BASE="${PUBLIC_BASE%/}"
CMS_BASE="${CMS_BASE%/}"

fail=0

check() {
  local label="$1"
  local url="$2"
  local expect="${3:-200}"
  local code
  code=$(curl -sS -o /tmp/ag-smoke-body.json -w '%{http_code}' "$url" || echo "000")
  if [[ "$code" == "$expect" ]]; then
    echo "OK  $label ($code) $url"
    return 0
  fi
  echo "FAIL $label (got $code, want $expect) $url"
  head -c 200 /tmp/ag-smoke-body.json 2>/dev/null; echo
  fail=1
  return 1
}

check_json_ok() {
  local label="$1"
  local url="$2"
  local code
  code=$(curl -sS -o /tmp/ag-smoke-body.json -w '%{http_code}' "$url" || echo "000")
  if [[ "$code" != "200" ]]; then
    echo "FAIL $label (HTTP $code) $url"
    head -c 200 /tmp/ag-smoke-body.json; echo
    fail=1
    return 1
  fi
  if grep -q '"status"[[:space:]]*:[[:space:]]*"ok"' /tmp/ag-smoke-body.json 2>/dev/null \
     || grep -q '"schema_version"' /tmp/ag-smoke-body.json 2>/dev/null; then
    echo "OK  $label (200 + JSON) $url"
    return 0
  fi
  echo "FAIL $label (200 but not published guide JSON) $url"
  head -c 200 /tmp/ag-smoke-body.json; echo
  fail=1
  return 1
}

echo "Agent Guide smoke test"
echo "  Public site: $PUBLIC_BASE"
echo "  CMS:         $CMS_BASE"
echo

check_json_ok "well-known on public site" "$PUBLIC_BASE/.well-known/agent-guide.json"
check_json_ok "legacy brand-context on public" "$PUBLIC_BASE/.well-known/brand-context.json"
check_json_ok "well-known on CMS host" "$CMS_BASE/.well-known/agent-guide.json"
check_json_ok "public API brand" "$CMS_BASE/api/public/ai_presence.php?action=brand"
check_json_ok "public API summary view" "$CMS_BASE/api/public/ai_presence.php?action=brand&view=summary"

echo
if [[ "$fail" -eq 0 ]]; then
  echo "All checks passed."
  exit 0
fi
echo "Some checks failed."
echo "If you see \"Not found\" or \"not published\": open Agent Guide in admin,"
echo "enable publishing on the Publish tab, fill Sources + Context, then Publish."
exit 1
