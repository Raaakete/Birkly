# Admin template parser: safe eval() replacement plan (M3-T5)

**Status:** Implemented (P2-M6) — `admin/template_safe_renderer.php` validates compiled PHP and renders via temp `include` (no `eval()`).

## Problem

`admin/template_parser.php` compiles natural-syntax templates to PHP and executes them via:

```php
eval('?>' . $parsed . '<?php');
```

Any bug in the parser → potential arbitrary PHP execution (see `SECURITY_AUDIT_REPORT.md`).

## Goal

Replace `eval()` with a **whitelist interpreter** that only allows known directives and safe output — no arbitrary PHP in templates.

## Proposed approach (dependency-free)

### Phase 1 — AST-lite interpreter (recommended)

1. **Parse** template into a tree of nodes: `Text`, `Variable`, `If`, `For`, `Directive` (translate, admin_url, json, count, include_script).
2. **Evaluate** nodes against `$data` array only — no `extract()` + eval.
3. **Output** via `htmlspecialchars()` except allowlisted raw fields (`content`, `*.content`).

| Directive | Evaluation |
|-----------|------------|
| `{var}` / `{a.b}` | Array path lookup + escape |
| `{for each x in arr}` | foreach over data key |
| `{if x exist}` | isset / !empty |
| `{translate 'key'}` | call `__()` |
| `{admin_url 'page' k='path'}` | call `admin_url()` |
| `{json x}` | `json_encode` |
| `{count x}` | `count()` on array |

### Phase 2 — Deprecate `{{ }}` dual syntax

Admin docs already drifted (`admin-templating.md` showed `{{ }}`). Canonical is single-brace `{for each}` (see Birkly-Docs). Keep `{{ }}` parser path temporarily with deprecation notice in admin Help.

### Phase 3 — Remove eval

- Swap `renderWithData()` to use interpreter.
- Keep `eval` path behind `settings/security.json` flag for one release if needed, default off.
- Delete eval path once `test-new-template.php` + admin page smoke pass.

## Non-goals (this milestone)

- Twig/Smarty/npm engines (violates dependency-free core).
- Changing client `birkly-client.js` syntax (separate engine, same surface language).

## Acceptance for implementation PR (future)

- [ ] `test-new-template.php` passes without eval
- [ ] No `eval(` in `admin/template_parser.php`
- [ ] Security audit P0 item closed
- [ ] Values check: dependency-free ✅

## References

- `admin/template_parser.php` — current compiler
- `test-new-template.php` — regression harness
- `docs/admin-templating.md` — admin UI decoupling rules
- Birkly-Docs `03-templating/canonical-syntax.md` — public syntax SSOT
