# Phase 27 — Lead-engineer handoff (Admin polish & platform fixes)

**Status:** Approved (2026-07-09, Keven) — execute now  
**Studio P26:** **Paused** until P27-M16 closure  
**Human rule:** Execute **P27-M0→M16** autonomously. Notify human **once** at **P27-M16** only.

**Master plan:** `docs/reviews/P27-admin-polish-plan.md`  
**Gap register:** `docs/reviews/P27-admin-polish-gap-register.md`  
**Matrix:** `docs/reviews/P27-verification-matrix.md`

**Human decisions (locked):**
- 2FA grace period is **admin-configurable** in Settings (days, default 30)
- Docs sync copies from **sibling `Birkly-Docs`** at **release time** (`BIRKLY_DOCS_PATH` or `../Birkly-Docs`)

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 27 — Admin polish & platform fixes.

Human approved P27 (2026-07-09). Studio P26 is PAUSED until P27-M16 closure.
Execute P27-M0→M16 autonomously in one wave.
Notify human ONCE at P27-M16 closure only.

READ FIRST:
1. docs/reviews/P27-lead-engineer-handoff.md
2. docs/reviews/P27-admin-polish-plan.md
3. docs/reviews/P27-admin-polish-gap-register.md
4. docs/reviews/P27-verification-matrix.md
5. admin/partials/ (topbar, sidebar, user-menu, layout_footer, legal-footer)
6. admin/templates/dashboard.html, media.html, entry-detail.html, help.html
7. api/2fa.php, api/users.php, admin/login.php, admin/login.php
8. docs/PROJECT_VALUES.md

DO NOT start P26 Studio work (animation, Figma, popups).

═══ CRITICAL BUGS (M1–M2) ═══
- Dashboard: fix birkly_paths.php require (//core/ fatal in dashboard.html line 16)
- Media: replace ALL hardcoded /api/admin_media.php with Birkly.url()

═══ UX (M3–M9) ═══
- Breadcrumbs: Studio plugin, entry-detail hierarchy, hamburger ↔ breadcrumb spacing
- Sidebar: global bg; only nav item boxes white (--bg-elevated)
- Collapsible sidebar: 64px icon rail, toggle at BOTTOM, localStorage persist
- Topbar: unified design language (theme switcher size, neutral tokens, no stray blue)
- Avatar displays in topbar after account upload
- "Closed alpha" label → sidebar footer only
- ToS / Privacy / Request invite → account dropdown (remove content-area legal footer)
- AI + feedback FABs match Birkly design tokens

═══ SECURITY (M10–M11) ═══
- Settings → Security: toggle require_2fa for all users
- Grace period ADMIN-CONFIGURABLE (require_2fa_grace_days, default 30)
- Per-user 2fa_grace_until; admin can extend individual users
- Banner during grace; login blocked + forced 2FA setup after expiry

═══ DOCS (M12–M13) ═══
- scripts/sync-birkly-docs.sh copies sibling Birkly-Docs → admin/docs/snapshots/
- BIRKLY_DOCS_PATH env or default ../Birkly-Docs; run at RELEASE TIME
- Regenerate help-offline-manifest.json
- Help page v2: comprehensive search; REMOVE plugin-insert section

═══ MILESTONES ═══
M0–M16 — see master plan
Branches: cursor/p27-m[N]-<slug>-030a

DELEGATION:
cms-developer:      M1, M2, M10–M12, M14–M15
frontend-developer: M3–M9, M13
design-reviewer:    M5–M9 (mandatory)
docs-writer:        M12–M13, release sync docs
qa-verifier:        M16

CLOSURE (P27-M16):
[ ] Dashboard + media work on subdirectory install
[ ] Breadcrumbs + collapsible sidebar + topbar polish
[ ] 2FA enforcement with admin-configurable grace
[ ] Docs synced from sibling Birkly-Docs; help v2 live
[ ] admin-polish-smoke.sh green; 153+ suites PASS
[ ] ROADMAP: P27 complete, P26 unpaused
[ ] Single digest notification
```

---

## Human approval checklist

- [x] P27-D1–D16 accepted (2026-07-09)
- [x] P26 Studio pause confirmed (2026-07-09)
- [x] 2FA grace admin-configurable (2026-07-09)
- [x] Docs sync from sibling Birkly-Docs at release time (2026-07-09)
- [x] M0–M16 single-wave execution approved (2026-07-09)
