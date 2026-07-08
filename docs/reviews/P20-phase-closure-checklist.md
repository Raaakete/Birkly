# P20 — Phase closure checklist

**Target:** P20-M12 — single human notification  
**Date started:** 2026-07-08  
**Date closed:** 2026-07-08

## Verification matrix

- [x] P20 verification matrix — all rows **pass** (`docs/reviews/P20-verification-matrix.md`)
- [x] JS↔PHP parity matrix — no client **fail** rows (`docs/reviews/P20-js-php-parity-matrix.md`)

## Test suites

- [x] Birkly `php tests/run.php` green — **141 suites PASS**
- [x] `./scripts/ci-check.sh` green
- [x] `page_bundle_test.php` + `client_bundle_fixture_test.php` + `client_template_parity_test.php`

## Reference projects (P20-D8–D11)

- [x] `reference-projects/` — six sites (blog, portfolio, yoga, coffee, social, influencer)
- [x] No `birkly-baked`, `birkly-hydrate.js`, or snapshot scripts in reference HTML
- [x] No custom `fetch()` in reference site JavaScript
- [x] Canonical `<script src="/birkly-client.js" data-cms-url="http://localhost:8080"></script>`
- [x] `scripts/install-reference-project.sh` copies collections to `content/collections/`

## Documentation

- [x] `docs/reference-projects/user-stories.md`
- [x] `docs/advanced/production-optimization.md` (hydrate optional only)
- [x] Birkly-Docs: `03-templating/reference-projects.md`, `production-optimization.md`

## Human notification

- [x] Single digest at P20-M12 (`scripts/send-agent-digest.php`)
