#!/usr/bin/env bash
# Lightweight public CI check for Birkly (template engine + syntax).
# Full admin suite lives in the private marketplace workspace.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> PHP syntax (core entrypoints)"
for f in server-router.php index.php admin/index.php core/version.php core/project_ssr.php scripts/validate-project-templates.php; do
  php -l "$f" >/dev/null
done

echo "==> Client inlines shared engine verbatim (P51-M5 drift guard)"
bash scripts/sync-template-engine-inline.sh --check
node --check birkly-client.js

echo "==> Shared template engine syntax suite (V-ENG-01..10)"
node tests/template-engine/run.js

echo "==> Client/server render parity suite (V-DEC-01..08)"
node tests/template-engine/parity_client_server_test.js

echo "==> Project SSR pipeline tests (V-SSR-01..10)"
php tests/project_ssr_test.php

echo "==> Project render settings API tests (V-SET-03/04/05/07)"
php tests/project_ssr_settings_api_test.php

echo "==> Template region tests"
node tests/template_regions_test.js
node tests/birkly_region_test.js

echo "==> Client loading helpers tests (V-CLI-01..06)"
node tests/birkly_loading_test.js

echo "==> Phantom template validator self-test"
php scripts/validate-project-templates.php --self-test

echo "==> AI Presence tests"
php tests/ai_presence_storage_test.php
php tests/ai_presence_test.php

echo "==> DynamicItems multilang tests"
php tests/dynamicitems_multilang_test.php

echo "==> Multilanguage entry save tests"
php tests/multilang_entry_save_test.php

echo "==> MCP update_collection multilanguage tests"
php tests/ai_update_collection_multilang_test.php

echo "==> MCP multilanguage end-to-end tests"
php tests/ai_multilang_mcp_e2e_test.php

echo "==> MCP update_entry multilang tests"
php tests/ai_update_entry_multilang_test.php

echo "==> MCP update_entry normalization tests"
php tests/ai_update_entry_normalization_test.php

echo "==> Help snapshot normalization tests"
php tests/help_docs_snapshot_test.php

echo "==> Other node template engine smoke tests"
node tests/for_loop_header_test.js
node tests/from_block_prefetch_test.js
node tests/loop_else_conditional_test.js
node tests/bundle_payload_validation_test.js
node tests/template_sanitize_test.js
# Note: marsk_render_engine_test.js needs marketplace starter fixtures; not required for public CI.

echo "==> Admin UI language picker"
php tests/admin_ui_languages_test.php

echo "==> Admin locale audit"
php scripts/i18n-audit.php --selectable --strict-quality

echo "ci-check: PASS"
