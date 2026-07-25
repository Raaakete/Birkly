# P44 — Admin UI/UX excellence (full design remediation)

**Status:** Approved (2026-07-25, Keven) — **next active phase**  
**Repos:** Birkly (primary) · Birkly-Docs (admin UX guide)  
**Handoff:** `docs/reviews/P44-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P44-verification-matrix.md`  
**Gap register:** `docs/reviews/P44-gap-register.md`

---

## Problem

The Birkly admin is **structurally mature** (tokens, shell, dark mode, major workflows) but **product UX gaps** cause support burden and erode trust:

1. **Save settings** appears global but saves four tabs only.  
2. **13 settings tabs** without grouping.  
3. **Hosting** (P43 backend) still reads like engineering docs.  
4. **Visual system** half-applied — blue focus fallbacks, hardcoded sidebar, unused brand font.  
5. **Accessibility bugs** — skip link, `aria-current` on all nav items.  
6. **alert/confirm** instead of in-app feedback.  
7. **Large English-only** string pockets.  
8. **Project tab** feels like a developer IDE for all users.  
9. **ContentEditor + mobile** toolbars need polish.

This phase fixes **everything identified in the 2026-07-25 design review** — no separate P41/P42 UI waves.

---

## Vision

A **coherent, trustworthy admin** for non-technical site owners and developers alike:

- One clear **mental model for saving** settings  
- **Grouped settings** navigation  
- **Guided hosting** story aligned with P43 URLs  
- **Consistent mint brand** via tokens everywhere  
- **Accessible** navigation and feedback  
- **Translatable** admin UI  
- **Responsive** content and editor toolbars  

---

## Locked decisions (P44-D1–D12)

| ID | Decision |
|----|----------|
| **P44-D1** | Fix critical a11y + save semantics in **M1** before visual polish |
| **P44-D2** | Settings grouped into **4 areas** (Site · Team & security · Integrations · Infrastructure) |
| **P44-D3** | Global sticky save **hidden or relabeled** on tabs with independent persistence |
| **P44-D4** | Per-section **dirty state** indicator when fields change |
| **P44-D5** | Hosting wizard reuses P43 `api/hosting.php` — UX only, no routing changes |
| **P44-D6** | Project tab **simple view default**; file tree editor behind “Advanced” |
| **P44-D7** | Replace **all** admin `alert()` / `confirm()` with shared feedback components |
| **P44-D8** | Brand font: **load General Sans** from self-hosted or CDN **or** remove from tokens and document system UI stack — pick one in M2 |
| **P44-D9** | No new npm build step for admin CSS |
| **P44-D10** | design-reviewer sign-off required at **M12** before closure |
| **P44-D11** | qa-verifier runs V-UX matrix + regression smoke |
| **P44-D12** | Notify human **once** at P44-M12 closure |

---

## Settings information architecture (target)

### Level 1 — Settings areas (sidebar or sub-nav)

| Area | Tabs included |
|------|----------------|
| **Site** | General, Hosting, Domains, Project, Redirects |
| **Team & security** | Users & Roles, Security, Activity & audit |
| **Integrations** | API, Webhooks, AI |
| **Infrastructure** | Storage, Monitoring |

### Level 2 — tabs-v2 within each area (existing component)

Keep horizontal tabs inside each group — max ~5 per group.

### Save model

| Tab / area | Persistence |
|------------|-------------|
| General, Security, Storage, Monitoring | Area save bar: “Save site settings” / “Save security settings” etc. |
| Hosting | Dedicated save (existing) + dirty badge |
| Project | Per-file save + upload actions |
| Domains, Webhooks, Redirects | Inline API actions (no fake global save) |
| AI | Section saves within tab |

Remove misleading **Save settings** that implies all tabs.

---

## Hosting guided UX (P44-M5)

Replace prose card with **3 steps**:

1. **“Where do you log in?”** → CMS URL (+ Use current host)  
2. **“Where is your public website?”** → None / Elsewhere / In Birkly  
3. **“Preview”** → “You manage content at …” / “Visitors see …”

**Dokploy checklist:** expanded by default for self-hosted installs (detect or toggle “I use Docker/Dokploy”).

**Onboarding:** reuse same component after first admin (replace duplicate Hosting copy).

---

## Milestones

| ID | Focus | Owner | Status |
|----|--------|--------|--------|
| **P44-M0** | Gap register + plan + handoff (this PR) | product-planner | complete |
| **P44-M1** | Critical: `aria-current`, skip link, global save scope | cms-developer | pending |
| **P44-M2** | Token compliance: sidebar, focus, links, fonts, forms `:invalid` | cms-developer + design-reviewer | pending |
| **P44-M3** | Settings IA: 4 grouped areas + routing | cms-developer | pending |
| **P44-M4** | Dirty state + per-area save bars | cms-developer | pending |
| **P44-M5** | Hosting wizard + onboarding shared component | cms-developer + design-reviewer | pending |
| **P44-M6** | Project tab: simple upload vs advanced IDE | cms-developer | pending |
| **P44-M7** | Feedback system: toast + confirm modal; remove alerts | cms-developer | pending |
| **P44-M8** | i18n audit: settings, hosting, onboarding, entries, sidebar | cms-developer | pending |
| **P44-M9** | ContentEditor: overflow toolbar, dark mode, mint tokens | cms-developer | pending |
| **P44-M10** | Mobile entries toolbar, skeleton loaders, empty state icons | cms-developer + frontend-developer | pending |
| **P44-M11** | `docs/admin-design-system.md` + hex audit | docs-writer + design-reviewer | pending |
| **P44-M12** | Birkly-Docs guide, V-UX matrix, design-reviewer, closure | lead-engineer | pending |

**Depends on:** P43 complete (hosting URLs on `main`).

**Does not include:** Studio P26 · new marketing template pack (P42) · marketplace feature work.

---

## File map (primary touch points)

| Area | Paths |
|------|--------|
| Tokens | `admin/css/core/variables.css`, `typography.css` |
| Sidebar / header | `admin/css/layout/sidebar.css`, `header.css` |
| Settings | `admin/templates/settings.html`, `settings.css`, `settings-page.js`, `hosting-settings.js` |
| Save bar | `admin/templates/components/sticky-save-bar.php`, `sticky-save-bar.css` |
| Tabs | `admin/css/components/tabs-v2.css`, `admin/js/tabs-v2.js` |
| A11y | `admin/js/accessibility.js` |
| Feedback | `admin/css/components/messages.css`, `modals.css`, new `admin/js/feedback.js` |
| Onboarding | `onboarding.html`, `onboarding.js`, `onboarding.css` |
| ContentEditor | `fieldtypes/ContentEditor/editor.css`, `editor.js` |
| Entries | `entries.html`, `content.css` |
| Plugins | `plugins.html`, `plugins.css`, `plugins-page.js` |
| Empty state | `admin/templates/components/empty-state.html` |
| Locales | `admin/locale/*.php` or existing i18n files |
| Docs | `docs/admin-design-system.md`, Birkly-Docs admin UX guide |

---

## Task breakdown (sample tasks)

### P44-M1
- M1-T1 Fix `aria-current` — only `.active` nav links  
- M1-T2 Skip link → `#admin-sidebar`  
- M1-T3 Relabel or hide global save when not applicable  
- M1-T4 Tests or manual checklist in matrix V-UX-01–03  

### P44-M2
- M2-T1 Sidebar header tokens + dark mode  
- M2-T2 Grep `#3b82f6` in `admin/css/` → mint `--primary-*`  
- M2-T3 Link colors: contrast fix  
- M2-T4 Font decision + implement  
- M2-T5 `:invalid` only after submit  

### P44-M3
- M3-T1 Settings area sub-nav markup + routes (`?area=site&tab=hosting`)  
- M3-T2 Migrate tab panes into area groups  
- M3-T3 Update sidebar Settings active states  

### P44-M4
- M4-T1 `data-dirty` tracking per form section  
- M4-T2 Area-specific save buttons in sticky bar  
- M4-T3 Unsaved navigation warning (optional)  

### P44-M5
- M5-T1 `hosting-wizard` component (HTML/CSS/JS)  
- M5-T2 Wire to `api/hosting.php`  
- M5-T3 Onboarding reuse  
- M5-T4 Dokploy checklist visible default  

### P44-M6
- M6-T1 Simple project view: file list + upload + visit link  
- M6-T2 Advanced toggle restores current IDE  

### P44-M7
- M7-T1 `BirklyFeedback.toast()`, `BirklyFeedback.confirm()`  
- M7-T2 Replace alerts in onboarding, hosting, settings, collection-editor  
- M7-T3 `aria-live` region for toasts  

### P44-M8
- M8-T1 String audit spreadsheet or grep list  
- M8-T2 Hosting + Project + onboarding i18n  
- M8-T3 Entries bulk + sidebar i18n  

### P44-M9
- M9-T1 Toolbar overflow menu &lt;1024px  
- M9-T2 Dark mode editor smoke + fixes  
- M9-T3 Mint active tab tokens  

### P44-M10
- M10-T1 Entries filter drawer mobile  
- M10-T2 Plugin/marketplace skeleton cards  
- M10-T3 Empty state default icon  
- M10-T4 Reduce inline styles on settings.html (top offenders)  

### P44-M11
- M11-T1 `docs/admin-design-system.md`  
- M11-T2 Document tokens, buttons, forms, save patterns  

### P44-M12
- M12-T1 Birkly-Docs `guides/admin-ux.md`  
- M12-T2 Fill verification matrix  
- M12-T3 design-reviewer + qa-verifier sign-off  
- M12-T4 Human notification once  

---

## Values check

- **Dependency-free** — CSS/JS only, no new npm admin build  
- **UI decoupled** — feedback helpers in `admin/js/`, not in core PHP  
- **Self-contained** — admin-only changes; no plugin API breaks  

---

## Risk register

| Risk | Mitigation |
|------|------------|
| Settings URL bookmark breakage | Redirect old `?tab=` to new `?area=&tab=` |
| Large diff touch many files | Milestone order: critical → IA → polish |
| i18n scope creep | M8 bounded string list from gap register |
| ContentEditor regressions | V-UX editor tests + manual tablet check |
