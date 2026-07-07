# P13-M5 — Deferred non-critical TODOs

**Date:** 2026-07-07 · **Milestone:** P13-M5 code hygiene  
**Scope audited:** `admin/`, `core/`, `api/`  
**Critical security items:** closed in P13-M5 (see `api/roles.php` permission checks)

This document lists remaining TODO/FIXME items that are **not** security-critical and were intentionally deferred.

---

## api/

| File | Line | Item | Rationale |
|------|------|------|-----------|
| `api/validate.php` | ~102 | Phase 2 CSP: remove `unsafe-inline` / `unsafe-eval` via nonces | Hardening improvement; admin currently relies on inline scripts. Requires coordinated admin bundle refactor. |

---

## admin/

No `TODO` / `FIXME` markers remain in `admin/` after P13-M5.

**Note:** Commented debug traces in `admin/js/mouse-drag-drop.js` and JSDoc examples in `admin/js/simple-drag-drop.js` retain `console.log` in comments only — not shipped as runtime calls.

---

## core/

No `TODO` / `FIXME` markers in `core/` at audit time.

---

## Follow-up (out of P13-M5 scope)

- CSP nonce rollout (admin asset pipeline)
- Broader `grep` across `plugins/` and `fieldtypes/` if a later hygiene pass is scheduled
