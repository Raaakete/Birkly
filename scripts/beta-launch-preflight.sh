#!/usr/bin/env bash
# P28-M19 — Beta launch preflight: human Part 1 checklist + automated pre-checks.
#
# Part 1 (DNS, Stripe live, legal) is human-only — printed as unchecked items.
# Part 2 runs automated gates before beta go-live.
#
# Usage:
#   ./scripts/beta-launch-preflight.sh
#
# Exit codes: 0 = all automated checks pass, 1 = one or more automated checks failed
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PARENT="$(dirname "$ROOT")"
KEKABA_ROOT="${KEKABA_REPO:-$PARENT/kekaba}"
WEBSITE_ROOT="${BIRKLY_WEBSITE_REPO:-$PARENT/Birkly-Website}"

FAIL=0
declare -a SUMMARY_ROWS=()

report() { echo "[beta-launch-preflight] $*"; }

record_result() {
  local id="$1" result="$2" label="$3"
  SUMMARY_ROWS+=("$id|$result|$label")
  if [[ "$result" == "fail" ]]; then
    FAIL=1
  fi
}

run_required() {
  local id="$1" label="$2"
  shift 2
  report "==> $label"
  if "$@"; then
    record_result "$id" "pass" "$label"
    report "OK: $label"
  else
    record_result "$id" "fail" "$label"
    report "FAIL: $label"
  fi
}

run_optional_script() {
  local id="$1" label="$2" script_path="$3"
  shift 3
  if [[ ! -f "$script_path" ]]; then
    report "SKIP: $label ($script_path not found)"
    record_result "$id" "skip" "$label"
    return 0
  fi
  chmod +x "$script_path" 2>/dev/null || true
  run_required "$id" "$label" "$script_path" "$@"
}

check_kekaba_no_bake() {
  local index="$KEKABA_ROOT/index.html"
  if [[ ! -f "$index" ]]; then
    report "FAIL: kekaba index.html not found at $index"
    return 1
  fi
  if grep -qiE '<meta[^>]+name=["'\'' ]?birkly-baked' "$index"; then
    report "FAIL: birkly-baked meta tag present in $index"
    return 1
  fi
  report "OK: no birkly-baked meta in kekaba index.html"
  return 0
}

print_human_checklist() {
  report "Part 1 — Human sign-off (manual, not validated by this script)"
  echo ""
  cat <<'EOF'
[ ] DNS — production hostnames resolve (birkly.io, marketplace.birkly.io, kekaba.com, service.kekaba.com)
[ ] DNS — TLS certificates valid on all public hosts
[ ] Stripe — marketplace checkout uses live mode (not test keys)
[ ] Stripe — Connect payouts and webhooks verified on production
[ ] Legal — privacy policy and terms published and linked from marketing site
[ ] Legal — marketplace provider agreement and buyer terms reviewed for beta
EOF
  echo ""
}

print_summary_table() {
  report "Summary"
  printf '%-42s  %s\n' "Check" "Result"
  printf '%-42s  %s\n' "$(printf '%.0s-' {1..42})" "------"
  local row id result label
  for row in "${SUMMARY_ROWS[@]}"; do
    IFS='|' read -r id result label <<<"$row"
    printf '%-42s  %s\n' "$label" "$result"
  done
  printf '%-42s  %s\n' "$(printf '%.0s-' {1..42})" "------"
  if [[ "$FAIL" -eq 0 ]]; then
    printf '%-42s  %s\n' "Automated preflight" "PASS"
    report "RESULT: PASS (human Part 1 still required)"
  else
    printf '%-42s  %s\n' "Automated preflight" "FAIL"
    report "RESULT: FAIL"
  fi
}

report "Beta launch preflight (P28-M19)"
report "Repo: $ROOT"
echo ""

print_human_checklist

report "Part 2 — Automated pre-checks"
echo ""

run_required "ci-check" "ci-check.sh" "$ROOT/scripts/ci-check.sh"
run_required "journey-all" "journey-runner.php --all" php "$ROOT/scripts/journey-runner.php" --all
run_optional_script "catalog-audit" "audit-marketplace-catalog.sh" "$ROOT/scripts/audit-marketplace-catalog.sh"
run_required "kekaba-no-bake" "kekaba index.html (no birkly-baked meta)" check_kekaba_no_bake
run_optional_script "website-smoke" "Birkly-Website deploy-smoke" "$WEBSITE_ROOT/scripts/deploy-smoke.sh"

echo ""
print_summary_table

if [[ "$FAIL" -ne 0 ]]; then
  exit 1
fi
exit 0
