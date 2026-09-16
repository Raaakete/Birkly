#!/usr/bin/env bash
# P88 — reject high-entropy live token patterns outside allowlisted paths.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ALLOWLIST=(
  'tests/'
  'docs/'
  'admin/docs/'
  'scripts/plugin-dev-sync-enable.sh'
  'scripts/reset-admin-password.php'
  'lang/'
)

should_skip() {
  local file="$1"
  for prefix in "${ALLOWLIST[@]}"; do
    if [[ "$file" == *"$prefix"* ]]; then
      return 0
    fi
  done
  return 1
}

PATTERNS=(
  'ghp_[A-Za-z0-9]{20,}'
  'github_pat_[A-Za-z0-9_]{20,}'
  'sk-[A-Za-z0-9]{20,}'
  'AKIA[0-9A-Z]{16}'
)

fail=0
while IFS= read -r -d '' file; do
  if should_skip "$file"; then
    continue
  fi
  for pattern in "${PATTERNS[@]}"; do
    if grep -qE "$pattern" "$file" 2>/dev/null; then
      echo "[secret-scan] FAIL: pattern $pattern in $file"
      fail=1
    fi
  done
done < <(find . -type f \( -name '*.php' -o -name '*.js' -o -name '*.json' -o -name '*.sh' -o -name '*.py' \) \
  ! -path './vendor/*' ! -path './node_modules/*' ! -path './.git/*' -print0)

if [[ "$fail" -ne 0 ]]; then
  exit 1
fi

echo "[secret-scan] OK"
