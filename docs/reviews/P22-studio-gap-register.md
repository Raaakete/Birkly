# P22 — Studio plugin gap register

**Date:** 2026-07-08 · **Phase:** Studio visual site builder  
**Spec:** `docs/reviews/P22-studio-plugin-plan.md`

| ID | Area | Gap | Severity | Mitigation | Milestone |
|----|------|-----|----------|------------|-----------|
| G-STU-01 | SSR | `{entry for 'col'}` client-only blocks | medium | Compiler emits standard `{for each}` loops; log if detected | M6/M9 |
| G-STU-02 | SSR | Template functions require plugin bootstrap | low | `birkly_public_load_active_plugins()` before render | M9 |
| G-STU-03 | Canvas | Code tab round-trip may lose unknown element types | low | Preserve `raw` nodes; warn in UI | M7 |
| G-STU-04 | Style kits | tailwind-lite is utility subset only | low | Document in README; full JIT deferred P23 | M3/M11 |
| G-STU-05 | Preview | iframe needs local dev CORS | low | Use same-origin admin preview route | M8 |
| G-STU-06 | Export | ZipArchive optional on host | low | Graceful error message; SSR still works | M10 |
| G-STU-07 | Parity | Complex filters in loops | medium | Align with P20 matrix; SSR logs gaps | M9 |
| G-STU-08 | Deactivate | Public route 503 when plugin off | required | Route shelf checks `studio_plugin_active()` | M1/M9 |

## SSR parity requirements (P22-D11)

Studio compiled HTML uses Birkly template tags only. `StudioRenderer` must support all **pass** rows in `P20-js-php-parity-matrix.md`. Gaps logged to `logs/php_errors.log` with `studio_ssr:` prefix — never silent fail on publish.

## Closure

All **required** mitigations shipped in M1–M10. Residual G-STU-04 documented for P23.
