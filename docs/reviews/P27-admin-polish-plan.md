# P27 — Admin polish & platform fixes

**Status:** Proposed (2026-07-09) — awaiting human approval  
**Baseline:** P25 complete (153 suites) · P26 Studio creative suite **paused** per human  
**Human rule:** Execute P27-M0→M16 autonomously after approval. Notify human **once** at **P27-M16** only.

**Gap register:** `docs/reviews/P27-admin-polish-gap-register.md`  
**Handoff:** `docs/reviews/P27-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P27-verification-matrix.md`

---

## Vision

Fix broken admin flows and polish the shell **before** resuming Studio (P26). Focus on security policy (2FA enforcement), navigation chrome, docs shipping, and production-path bugs (dashboard, media).

**Scope:** Admin shell + core APIs + Birkly-Docs sync pipeline. **No `plugins/studio/` feature work** except breadcrumb integration fixes.

---

## Locked decisions (proposed)

| ID | Decision |
|----|----------|
| **P27-D1** | **Pause P26 Studio** until P27-M16 closure |
| **P27-D2** | 2FA enforcement: settings flag `require_2fa` + per-user `2fa_grace_until` (ISO date); block login after grace |
| **P27-D3** | Grace period default **30 days** from policy enable; admins can extend per user |
| **P27-D4** | Banner in admin for users inside grace period; modal gate at login after expiry |
| **P27-D5** | Breadcrumbs: single source via topbar hoist; plugin title + sub-page trail; gap between hamburger and trail |
| **P27-D6** | Sidebar bg = `--background-global`; nav links/cards = `--bg-elevated` |
| **P27-D7** | Collapsed sidebar: 64px icon rail; labels hidden; toggle button at sidebar bottom; `localStorage` preference |
| **P27-D8** | Topbar controls unified height (36px), neutral tokens; remove stray `#007bff` from FABs/theme |
| **P27-D9** | Program label default **Closed alpha**; single line in sidebar footer |
| **P27-D10** | ToS, Privacy, Request invite → account dropdown (not content footer) |
| **P27-D11** | Remove `admin-legal-footer` from main content area |
| **P27-D12** | `scripts/sync-birkly-docs.sh` copies Birkly-Docs corpus → `admin/docs/snapshots/` + manifest |
| **P27-D13** | CI fails if docs snapshot older than Birkly-Docs pin (or sync not run) |
| **P27-D14** | Help page: full corpus search; **remove** generic plugin-insert help section (plugins ship own docs) |
| **P27-D15** | All admin API fetches use `Birkly.url()` / `birkly_url()` — no bare `/api/` |
| **P27-D16** | Single notification at **P27-M16** |

---

## Milestones (16 — one wave)

| ID | Focus |
|----|--------|
| **P27-M0** | Gap register (this doc) + audit hardcoded admin URLs |
| **P27-M1** | **Dashboard fix** — `birkly_paths.php` require path; regression test |
| **P27-M2** | **Media fix** — `birkly_url()` for all `admin_media.php` calls; list/load smoke test |
| **P27-M3** | **Breadcrumbs** — plugin shell (Studio, commerce), entry-detail hierarchy, topbar spacing |
| **P27-M4** | **Sidebar colors** — global bg vs nav card bg |
| **P27-M5** | **Collapsible sidebar** — icon rail + bottom toggle + persist |
| **P27-M6** | **Topbar design pass** — theme switcher, icons, account chip unified |
| **P27-M7** | **Avatar in topbar** — refresh after upload; correct media URL |
| **P27-M8** | **Legal / program links** — closed alpha in sidebar footer; legal links in account menu |
| **P27-M9** | **FAB restyle** — AI chat + feedback match design tokens |
| **P27-M10** | **2FA enforcement** — settings UI + API + user grace fields |
| **P27-M11** | **2FA grace UX** — admin banner, login block + setup redirect after expiry |
| **P27-M12** | **Docs sync script** — Birkly-Docs → admin snapshots + manifest regen |
| **P27-M13** | **Help page v2** — comprehensive manifest; remove plugin-insert section |
| **P27-M14** | **Admin URL audit** — grep fix remaining hardcoded `/api/` and `/admin/` in admin JS |
| **P27-M15** | Tests + `scripts/admin-polish-smoke.sh` |
| **P27-M16** | Matrix green, closure, ROADMAP (P27 complete, P26 unpaused), single notification |

**Branches:** `cursor/p27-m[N]-<slug>-030a`

---

## Milestone detail (selected)

### P27-M1 — Dashboard

- [ ] Replace `dirname(dirname(__DIR__))` with `__DIR__ . '/../../core/birkly_paths.php'` or remove inline require (inject `$isLocalDev` from `admin/index.php`)
- [ ] Test: render dashboard template without fatal

### P27-M2 — Media

- [ ] All fetches: `Birkly.url('/api/admin_media.php?...')`
- [ ] Verify list + folders on subdirectory install fixture

### P27-M3 — Breadcrumbs

- [ ] `layout_footer.php`: margin between `#sidebar-toggle` and `#admin-topbar-breadcrumbs`
- [ ] Studio: ensure `{plugin_page_header}` or equivalent populates trail
- [ ] `entry-detail.html`: Collection → Entries → Entry title

### P27-M5 — Collapsible sidebar

- [ ] `.sidebar-collapsed` width 64px; tooltips on icons
- [ ] Toggle at `.sidebar-footer` bottom
- [ ] Keyboard accessible

### P27-M10–M11 — 2FA enforcement

- [ ] `settings/security.json` or platform settings: `require_2fa`, `require_2fa_enabled_at`
- [ ] User record: `2fa_grace_until`
- [ ] On login: if policy on && !user.2fa.enabled && now > grace → redirect to 2FA setup (no admin access)
- [ ] Admin settings → Security tab: toggle + grace period display

### P27-M12–M13 — Docs

- [ ] `scripts/sync-birkly-docs.sh` (sibling repo `Birkly-Docs` or env var `BIRKLY_DOCS_PATH`)
- [ ] Copy markdown/HTML topics to `admin/docs/snapshots/`
- [ ] Regenerate `help-offline-manifest.json`
- [ ] Release note: every Birkly tag runs sync before zip
- [ ] Remove help topics that duplicate per-plugin docs inserts

---

## Acceptance criteria

1. Dashboard loads without template fatal on production path fixture.
2. Media library lists files on kekaba-style subdirectory install.
3. Studio + entry editor show correct breadcrumb; hamburger has clear spacing.
4. Sidebar collapses to icons; toggle at bottom persists.
5. Avatar appears in topbar after account upload.
6. Closed alpha label in sidebar only; legal links in account menu.
7. Admin enables forced 2FA → user has 30 days → login blocked until 2FA set.
8. Help search covers synced Birkly-Docs corpus; no stale plugin-insert page.
9. CI green; admin-polish-smoke.sh green.

---

## P26 Studio — paused

Phase 26 (animation, Figma, popups) remains **approved but paused**. Resume after P27-M16.

Spec unchanged: `docs/reviews/P26-studio-creative-design-plan.md`

---

## Dependencies

```
Human pause P26
  → M0
  → M1 ∥ M2 (critical bugs first)
  → M3 → M4 → M5
  → M6 → M7 → M8 → M9
  → M10 → M11
  → M12 → M13
  → M14 → M15 → M16
```

---

## Values check

| Value | Alignment |
|-------|-----------|
| Dependency-free | Docs sync is shell script, no npm in CMS runtime |
| UI decoupled | CSS token pass; logic in existing APIs |
| Security by default | 2FA enforcement opt-in for admins |
| Birkly-Docs SSOT | Sync script mirrors docs into CMS ship bundle |
