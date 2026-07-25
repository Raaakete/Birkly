# Phase 44 — Lead-engineer handoff (admin UI/UX excellence)

**Status:** Approved (2026-07-25, Keven) — **execute P44-M1→M12**  
**Human rule:** Execute autonomously. Notify human **once** at **P44-M12** closure.

**Master plan:** `docs/reviews/P44-admin-ux-excellence-plan.md`  
**Gap register:** `docs/reviews/P44-gap-register.md`  
**Matrix:** `docs/reviews/P44-verification-matrix.md`

**Baseline:** P43 complete on `main` (hosting URLs, routing, Hosting tab backend). Design review 2026-07-25 identified full admin UX remediation list.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 44 — Admin UI/UX excellence (full design remediation).

Human approved P44 (2026-07-25). Execute P44-M1→M12 autonomously in order.
Notify human ONCE at P44-M12 with V-UX matrix green + design-reviewer sign-off.

GOAL: Fix EVERYTHING from the 2026-07-25 admin design review — save trust, settings IA,
hosting wizard, tokens, a11y, feedback, i18n, project tab, ContentEditor mobile, polish.

DO NOT change P43 routing/hosting backend logic except UX wiring to api/hosting.php.
DO NOT start Studio P26 or P42 marketing template in this wave.

LOCKED DECISIONS (P44-D1–D12):
- M1 first: a11y bugs + save semantics before visual polish
- Settings → 4 areas: Site | Team & security | Integrations | Infrastructure
- Per-area save + dirty state; no misleading global save
- Hosting wizard (3 steps + preview) shared with onboarding
- Project tab: simple upload default, advanced IDE behind toggle
- Replace ALL admin alert()/confirm() with toast + confirm modal
- Font: load General Sans OR drop from tokens (document choice in M2)
- design-reviewer sign-off required at M12

READ FIRST:
1. docs/reviews/P44-admin-ux-excellence-plan.md
2. docs/reviews/P44-gap-register.md
3. docs/reviews/P44-verification-matrix.md
4. admin/js/settings-page.js (handleGlobalSave — lines ~2024)
5. admin/js/accessibility.js (aria-current, skip links)
6. admin/css/core/variables.css
7. admin/templates/settings.html (13 tabs → regroup)
8. admin/js/hosting-settings.js + api/hosting.php (P43 — UX only in P44)

═══ WAVE 1 — CRITICAL TRUST (M1, M4 partial) ═══

P44-M1:
- Fix accessibility.js: aria-current ONLY on .active nav links
- Skip link target #admin-sidebar (or add id="navigation" alias)
- Global save: relabel "Save general & security" OR hide on tabs with own save
- Matrix: V-UX-01–04

P44-M4 (start after M3 routes exist, or stub dirty on General first):
- Dirty badges per form section
- Area-specific sticky save bars
- V-UX-02, V-UX-05

═══ WAVE 2 — VISUAL FOUNDATION (M2) ═══

- Sidebar header → CSS tokens + dark mode (sidebar.css)
- Grep #3b82f6 in admin/css → mint --primary-*
- Link contrast typography.css
- General Sans: load self-hosted OR remove --font-primary (document in admin-design-system.md)
- forms.css :invalid → validate on submit/blur only
- plugins.css health badges → semantic tokens

═══ WAVE 3 — SETTINGS IA (M3, M4 complete) ═══

- 4 settings areas with sub-nav; tabs-v2 inside each area
- URL scheme: admin/settings?area=site&tab=hosting
- Redirect legacy ?tab=hosting → new URLs
- Remove/replace misleading global sticky save

═══ WAVE 4 — PRODUCT STORIES (M5, M6) ═══

P44-M5 Hosting wizard:
- 3 steps: CMS URL · public site mode · live preview
- Dokploy checklist visible (toggle "Self-hosted/Docker")
- Reuse in onboarding.js (same component/markup)

P44-M6 Project tab:
- Default: upload + file list + visit public URL (from hosting settings)
- "Advanced file editor" expands current IDE

═══ WAVE 5 — FEEDBACK + i18n (M7, M8) ═══

P44-M7: admin/js/feedback.js — toast + confirm modal, aria-live
Replace alerts in: hosting-settings.js, onboarding.js, settings-page.js,
collection-editor (generated JS sections), login if in scope

P44-M8: i18n audit — Hosting, Project, onboarding, entries bulk, sidebar Alpha Feedback
Use __() + locale files

═══ WAVE 6 — EDITOR + MOBILE + POLISH (M9, M10) ═══

P44-M9: ContentEditor overflow toolbar <1024px, dark mode, mint tabs
P44-M10: entries mobile filter drawer, marketplace skeletons, empty-state icons,
reduce inline styles on settings.html top sections

═══ WAVE 7 — DOCS + CLOSURE (M11, M12) ═══

P44-M11: docs/admin-design-system.md
P44-M12: Birkly-Docs guides/admin-ux.md, fill V-UX matrix,
design-reviewer + qa-verifier, ci-check green, single digest

DELEGATION:
cms-developer:        M1–M10
frontend-developer:   M10 mobile CSS if needed
docs-writer:          M11, M12 Birkly-Docs
design-reviewer:      M2 review, M5 wizard, M12 sign-off
qa-verifier:          V-UX matrix, regression smoke, axe spot check

BRANCHES: cursor/p44-m[N]-<slug>-030a

EXECUTION ORDER (strict):
M1 → M2 → M3 → M4 → M5 → M6 → M7 → M8 → M9 → M10 → M11 → M12

CLOSURE:
[ ] V-UX-01–33 green
[ ] No alert() in admin JS (grep audit)
[ ] design-reviewer approved
[ ] ci-check.sh green
[ ] Human notified once
```

---

## Human approval checklist

- [x] Full design review remediation scope accepted (2026-07-25)
- [x] P44-D1–D12 locked
- [x] P43 complete — P44 does not re-open routing
- [x] P44-M0 plan PR merged
- [ ] P44-M1→M12 execution at closure review

---

## Milestone delegation

| Milestone | Primary | Design review |
|-----------|---------|---------------|
| M1 | cms-developer | — |
| M2 | cms-developer | design-reviewer |
| M3–M4 | cms-developer | design-reviewer |
| M5 | cms-developer | design-reviewer |
| M6 | cms-developer | design-reviewer |
| M7–M8 | cms-developer | — |
| M9–M10 | cms-developer | design-reviewer |
| M11 | docs-writer | design-reviewer |
| M12 | lead-engineer | design-reviewer + qa-verifier |

---

## Related context

- P43 hosting backend: `core/hosting_urls.php`, `api/hosting.php`, `admin/js/hosting-settings.js`
- Design review issue IDs map to `P44-gap-register.md` (U1–U15)
- Supersedes separate P41 design-system-only wave — tokens doc is P44-M11
