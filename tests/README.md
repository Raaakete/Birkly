# Birkly test harness (M5)

Dependency-free PHP regression suite — no PHPUnit or Composer required.

## Run all tests

```bash
php tests/run.php
```

CI and smoke test invoke this via `./scripts/ci-check.sh` and `./scripts/agent-smoke-test.sh`.

## Suites

| File | Purpose |
|------|---------|
| `template_parser_test.php` | Admin template directives (`{for each}`, `{count}`, `{admin_url}`, etc.) |
| `auth_boundary_test.php` | Unauthenticated admin endpoints return 401/403; public read + M4 bundle/snapshot gates |

## Fixtures

`tests/fixtures/auth_boundary.json` — expected HTTP status codes per endpoint. Edit the fixture when adding secured API routes; auth tests load probes from this file.

## Backward compatibility

- `php test-new-template.php` — wrapper for `template_parser_test.php`
- `./scripts/auth-probe.sh` — wrapper for `auth_boundary_test.php`

## qa-verifier

Documented command: **`php tests/run.php`** (exit 0 = pass).
