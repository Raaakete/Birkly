# Phase 52 — Lead-engineer handoff (PHP-native SSR)

**Status:** **Approved** 2026-08-01 — execute autonomously  
**Human rule:** Execute M1→M5. Notify human **once** at P52-M5 with verification matrix green + localhost PHP-only SSR smoke.

**Master plan:** `docs/reviews/P52-php-native-ssr-plan.md`  
**Gap register:** `docs/reviews/P52-gap-register.md`  
**Matrix:** `docs/reviews/P52-verification-matrix.md`  
**Pasteable prompt:** `docs/reviews/P52-lead-engineer-prompt.md`

---

## Mission in one line

Replace Node-backed project SSR with an in-process PHP template engine, make **server** the default for Birkly-managed projects, and make **PHP the only host runtime** required for localhost SSR.

---

## Locked decisions (do not reopen)

| ID | Decision |
|----|----------|
| D1 | SSR runtime = PHP only (no Node on serve path) |
| D2 | Default `render_mode=server` when `hosting_type=birkly` |
| D3 | Browser keeps JS `birkly-template-engine.js` (client script) |
| D4 | Dual engines; shared fixtures are the parity contract |
| D5 | No PHP extensions (no V8Js / FFI-to-Node) |
| D6 | Template author syntax unchanged |
| D7 | Client fallback remains on engine/data failure |
| — | **No Node rollback flag** — go PHP-only after M1 green |
| — | Do **not** overwrite existing saved `render_mode=client` |

---

## PR strategy

| Wave | Branch | Contents |
|------|--------|----------|
| 1 | `cursor/p52-php-template-engine-afa4` | **M1 only** — PHP engine + dual fixtures |
| 2 | `cursor/p52-ssr-php-cutover-afa4` (or follow-up) | M2 — wire `project_ssr.php` |
| 3 | follow-up | M3 — defaults + Dockerfile + admin/docs |
| 4 | follow-up | M4 + M5 — delete Node surface, matrix, ROADMAP |

Base branch: `main`. Branch names must match `cursor/<name>-afa4`.

---

## Delegation

| Milestone | Agent |
|-----------|--------|
| M1, M2, M4 | cms-developer |
| M3 | cms-developer + frontend-developer (admin copy/status) |
| M5 | lead-engineer + qa-verifier |
| design-reviewer | only if Server-rendering card UX changes meaningfully |

---

## Execute in order

1. **M1** — Port `render()` to PHP; dual-run fixtures vs JS. No Dockerfile/default flip.
2. **M2** — `birkly_project_ssr_render()` → PHP in-process; Node-absent PATH → `X-Birkly-Render: server`.
3. **M3** — Default `render_mode=server` (unset/new only); strip `nodejs` from Docker; admin/docs.
4. **M4** — Delete Node SSR helpers/CLI.
5. **M5** — Full matrix + localhost PHP-only smoke + ROADMAP complete; notify human once.

---

## Hard constraints

- No new host dependencies.
- No template language syntax changes.
- Do not force-migrate sites that already saved `render_mode=client`.
- Keep client fallback on engine failure.
- Parity fixtures gate every grammar-touching PR after M1.
- Do not reopen P51 client-loading / settings surface unless a parity bug requires it.

---

## Key files

| File | Role |
|------|------|
| `birkly-template-engine.js` | Reference implementation for port + browser SSOT |
| `core/birkly_template_engine.php` | **New** — PHP `render` |
| `core/project_ssr.php` | Cutover target (M2) |
| `core/project_settings.php` | Defaults (M3) |
| `Dockerfile` | Remove nodejs (M3) |
| `scripts/birkly-ssr-cli.js` | Retire (M4) |
| `tests/template-engine/*` | Dual parity |
| `admin/js/project-settings.js` + `admin/templates/settings.html` | Default label / runtime status |

---

## Done when

- Matrix green (`P52-verification-matrix.md`)
- Localhost / Docker with PHP only: project HTML serves `X-Birkly-Render: server` at default settings
- No `nodejs` in Dockerfile; no Node on SSR happy path
- ROADMAP marks P52 complete
- Human notified once
