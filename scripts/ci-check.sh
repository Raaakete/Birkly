#!/usr/bin/env bash
# Lightweight public CI check for Birkly (template engine + syntax).
# Full admin suite lives in the private marketplace workspace.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> PHP syntax (core entrypoints)"
for f in server-router.php index.php admin/index.php core/version.php scripts/validate-project-templates.php; do
  php -l "$f" >/dev/null
done

echo "==> Template region tests"
node tests/template_regions_test.js
node tests/birkly_region_test.js

echo "==> Phantom template validator self-test"
php scripts/validate-project-templates.php --self-test

echo "==> AI Presence tests"
php tests/ai_presence_storage_test.php
php tests/ai_presence_test.php

echo "==> Other node template engine smoke tests"
node tests/for_loop_header_test.js
node tests/from_block_prefetch_test.js
node tests/loop_else_conditional_test.js
node tests/bundle_payload_validation_test.js
node tests/template_sanitize_test.js
# Note: marsk_render_engine_test.js needs marketplace starter fixtures; not required for public CI.

echo "ci-check: PASS"
