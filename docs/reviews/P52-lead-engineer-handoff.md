# Phase 52 — Lead-engineer handoff

**Plan:** `P52-php-native-ssr-plan.md`  
**Status:** Do not execute until human approves D1–D7 on the plan.

---

## Mission in one line

Replace Node-backed project SSR with an in-process PHP template engine, make **server** the default for Birkly-managed projects, and make **PHP the only host runtime** required for localhost SSR.

---

## Execute in order

1. **M1** — Port `render()` to PHP; dual-run fixtures vs `birkly-template-engine.js`. Do not flip Dockerfile/defaults yet.
2. **M2** — Point `birkly_project_ssr_render()` at PHP; prove Node-absent PATH still returns `X-Birkly-Render: server`.
3. **M3** — Default `render_mode=server` (unset/new only); strip `nodejs` from Docker; admin/docs.
4. **M4** — Delete Node SSR helpers/CLI.
5. **M5** — Matrix + smoke + ROADMAP.

Delegate: **cms-developer** for M1/M2/M4; **frontend-developer** for admin copy/status in M3; **qa-verifier** for M5; design-reviewer only if Server-rendering card copy/layout changes meaningfully.

---

## Hard constraints

- No new host dependencies (no V8Js, no npm for SSR).
- Do not change template author syntax.
- Do not force-migrate sites that already saved `render_mode=client`.
- Keep client fallback on engine failure.
- Parity fixtures gate every grammar-touching PR after M1.

---

## Key files

| File | Role |
|------|------|
| `birkly-template-engine.js` | Reference implementation for port + browser SSOT |
| `core/project_ssr.php` | Cutover target |
| `core/project_settings.php` | Defaults |
| `Dockerfile` | Remove nodejs |
| `scripts/birkly-ssr-cli.js` | Retire after cutover |
| `tests/template-engine/*` | Extend for dual parity |
| `admin/js/project-settings.js` + settings.html | Default label / runtime status |

---

## First branch after approval

`cursor/p52-php-template-engine-afa4` — M1 only.
