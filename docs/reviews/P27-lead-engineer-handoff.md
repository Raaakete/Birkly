# Phase 27 — Lead-engineer handoff (Admin polish & platform fixes)

**Status:** Proposed (2026-07-09) — awaiting human approval  
**Studio P26:** **Paused** until P27-M16 closure  
**Human rule:** Execute **P27-M0→M16** autonomously after approval. Notify human **once** at **P27-M16** only.

**Master plan:** `docs/reviews/P27-admin-polish-plan.md`  
**Gap register:** `docs/reviews/P27-admin-polish-gap-register.md`  
**Matrix:** `docs/reviews/P27-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 27 — Admin polish & platform fixes.

Human paused Studio P26 to fix admin shell issues first.
Execute P27-M0→M16 autonomously in one wave.
Notify human ONCE at P27-M16 closure only.

READ FIRST:
1. docs/reviews/P27-lead-engineer-handoff.md
2. docs/reviews/P27-admin-polish-plan.md
3. docs/reviews/P27-admin-polish-gap-register.md
4. admin/partials/ (topbar, sidebar, user-menu, layout_footer, legal-footer)
5. admin/templates/dashboard.html, media.html, entry-detail.html, help.html
6. api/2fa.php, api/users.php, admin/login.php
7. docs/PROJECT_VALUES.md

DO NOT start P26 Studio work (animation, Figma, popups).

CRITICAL BUGS (M1–M2):
- Dashboard: fix birkly_paths.php require (//core/ fatal)
- Media: replace hardcoded /api/admin_media.php with Birkly.url()

UX (M3–M9):
- Breadcrumbs: Studio, entry forms, hamburger spacing
- Sidebar: correct bg (only nav boxes white); collapsible icon rail + bottom toggle
- Topbar: unified design language (theme switcher size, no stray blue)
- Avatar in topbar after upload
- Closed alpha label → sidebar footer; ToS/Privacy/Request invite → account dropdown
- AI + feedback FABs match Birkly tokens

SECURITY (M10–M11):
- Admin setting: force 2FA for all users
- 30-day grace per user; banner during grace; login blocked until 2FA after expiry

DOCS (M12–M13):
- scripts/sync-birkly-docs.sh → admin/docs/snapshots + manifest
- Help page comprehensive; remove plugin-insert section

MILESTONES M0–M16 — see master plan
Branches: cursor/p27-m[N]-<slug>-030a

DELEGATION:
cms-developer:      M1, M2, M10–M12, M14–M15
frontend-developer: M3–M9, M13
design-reviewer:    M5–M9 (mandatory)
docs-writer:        M12–M13, Birkly-Docs sync note
qa-verifier:        M16

CLOSURE:
[ ] Dashboard + media work on subdirectory install
[ ] Breadcrumbs + collapsible sidebar + topbar polish
[ ] 2FA enforcement + grace period
[ ] Docs sync + help v2
[ ] admin-polish-smoke.sh green
[ ] ROADMAP: P27 complete, P26 unpaused
[ ] Single digest notification
```

---

## Human approval checklist

- [ ] P27-D1–D16 accepted
- [ ] P26 Studio pause confirmed
- [ ] M0–M16 single-wave execution approved
