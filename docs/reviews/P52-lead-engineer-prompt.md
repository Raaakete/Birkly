# P52 — Lead-engineer prompt (copy/paste)

Use this as the full task prompt for a **lead-engineer** cloud agent on repo `Raaakete/Birkly`, base branch `main`.

---

You are the **lead-engineer** for Birkly **Phase 52 (PHP-native SSR)**. The phase is **approved** (2026-08-01). Execute autonomously through M1→M5. Notify the human **once** at M5 when the verification matrix is green and localhost PHP-only SSR smoke passes.

## Read first (do not skip)

1. `docs/reviews/P52-php-native-ssr-plan.md` — locked decisions D1–D7, architecture, milestones
2. `docs/reviews/P52-gap-register.md`
3. `docs/reviews/P52-verification-matrix.md` — update statuses as you go
4. `docs/reviews/P52-lead-engineer-handoff.md`
5. Reference implementation: `birkly-template-engine.js` (`render()` pipeline)
6. Current Node bridge: `core/project_ssr.php`, `scripts/birkly-ssr-cli.js`
7. P51 notes for regressions: `docs/reviews/P51-ssr-notes.md` (style/script brace protection, trusted_ssr, cache)

## Mission

Replace Node-backed project SSR with an **in-process PHP template engine**. Make **`render_mode=server` the default** for Birkly-managed (`hosting_type=birkly`) projects. **PHP is the only host runtime** required for localhost SSR. Remove Node from Docker and from the SSR serve path.

Browser keeps `birkly-template-engine.js` inlined into `birkly-client.js` — that is intentional (not an author/npm dependency).

## Locked decisions (do not reopen)

- D1: SSR runtime = PHP only
- D2: Default `render_mode=server` for Birkly hosting
- D3: JS engine stays for the browser client
- D4: Dual engines; **shared fixtures** are the parity contract (PHP ≡ JS)
- D5: No PHP extensions (no V8Js, no FFI-to-Node)
- D6: No template author syntax changes
- D7: Client fallback remains on failure
- **No Node rollback flag** — after M1 green, cut over to PHP only
- Do **not** overwrite existing saved `project.json` with `render_mode=client`

## Milestones (strict order)

### P52-M1 — PHP engine + fixture parity (cms-developer)

- Add `core/birkly_template_engine.php` with `birkly_template_engine_render($html, $data, $options)`.
- Port the JS `render()` pipeline order: protect `<style>`/`<script>` → entry-for / public-form placeholders → from/get → for loops → if → count → variables → restore protect.
- Dual-run the same fixtures through JS (`tests/template-engine/run.js`) and PHP; require identical output (prefer byte-identical).
- Include CSS/JS brace protection (P51 V-ENG-11 class).
- **Do not** change Dockerfile, defaults, or delete Node yet.
- Branch: `cursor/p52-php-template-engine-afa4`
- Matrix: V-PHP-01–12 → pass before merge

### P52-M2 — SSR cutover (cms-developer)

- `birkly_project_ssr_render()` calls PHP in-process (no `proc_open` / Node).
- Keep cache, headers, meta marker, client-fallback on exception.
- Prove: Node absent from PATH → still `X-Birkly-Render: server` for project HTML when mode=server/auto.
- Update `tests/project_ssr_test.php` accordingly.
- Matrix: V-CUT-01–08

### P52-M3 — Default server + ops (cms-developer + frontend-developer)

- Defaults: `render_mode=server` in settings defaults / SSR fallbacks (unset/new only).
- Admin: Server labeled default/recommended; runtime status = PHP engine (no Node warning).
- Dockerfile: remove `nodejs`.
- Docs: `P52-ssr-notes.md` (or update P51 notes addendum); stack docs that mention Node SSR.
- Matrix: V-DEF-*, V-OPS-*

### P52-M4 — Delete Node SSR surface (cms-developer)

- Remove Node helpers from serve path; delete/archive `scripts/birkly-ssr-cli.js` if unused.
- Clean `ssr_runtime` API shape; drop `BIRKLY_NODE_BIN` for SSR.
- Matrix: V-DEL-*

### P52-M5 — Closure (lead + qa-verifier)

- Full matrix green.
- Smoke: PHP built-in server or Docker **without Node** → default settings → `X-Birkly-Render: server`.
- Perf + security notes; ROADMAP → P52 complete.
- Notify human **once**.
- Matrix: V-CI-*

## Constraints

- No third-party frontend frameworks; no static export/zip.
- No new host packages beyond existing PHP stack.
- Prefer small PRs per wave; commit/push/PR as cloud-agent rules require.
- Update gap register + verification matrix as work lands.
- Out of scope: Studio `/site/` rewrite; template grammar changes; reopening P51 client loading work unless parity requires it.

## Done definition

Localhost and Birkly-hosted project sites SSR with **PHP only**; Node is gone from Docker and the SSR path; server is the default for Birkly hosting; JS↔PHP fixture parity green; human notified once at M5.
