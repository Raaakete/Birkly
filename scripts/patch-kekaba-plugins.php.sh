#!/usr/bin/env bash
# Re-apply kekaba publish hook after platform sync overwrites core/plugins.php.
set -euo pipefail

PLUGINS="${1:-}"
if [[ -z "$PLUGINS" || ! -f "$PLUGINS" ]]; then
  echo "Usage: $0 /path/to/Birkly-0.1/core/plugins.php" >&2
  exit 1
fi

if grep -q 'kekaba_publish.php' "$PLUGINS"; then
  echo "[kekaba-patch] publish hook already present"
  exit 0
fi

MARKER='// Load scheduled tasks system'
HOOK='// Site-specific publish hooks (KEKABA)
if (file_exists(__DIR__ . '\''/../site/kekaba_publish.php'\'')) {
    require_once __DIR__ . '\''/../site/kekaba_publish.php'\'';
}
'

python3 - "$PLUGINS" "$MARKER" "$HOOK" <<'PY'
import sys
path, marker, hook = sys.argv[1], sys.argv[2], sys.argv[3]
text = open(path).read()
if 'kekaba_publish.php' in text:
    sys.exit(0)
idx = text.find(marker)
if idx == -1:
    print('Marker not found in plugins.php', file=sys.stderr)
    sys.exit(1)
# insert hook before marker
out = text[:idx] + hook + '\n' + text[idx:]
open(path, 'w').write(out)
print('[kekaba-patch] inserted publish hook')
PY
