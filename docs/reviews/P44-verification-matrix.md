# P44 — Verification matrix (admin UI/UX excellence)

**Plan:** `docs/reviews/P44-admin-ux-excellence-plan.md`  
**Status:** M11–M12 docs complete — closure pending M7–M10 + sign-offs  
**Last updated:** 2026-07-25 (M11/M12)

---

## Critical — save & accessibility (M1)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-01 | Settings → Hosting, change CMS URL, click global “Save settings” | Hosting **not** saved unless Hosting save used | M1 | **pass** — global bar hidden on hosting tab (`INDEPENDENT_SAVE_TABS`) |
| V-UX-02 | Settings → General, change field, area save | General settings persisted | M4 | **pass** — per-tab label + `GLOBAL_SAVE_TABS.general` |
| V-UX-03 | Keyboard: skip link | Focus moves to sidebar (`#admin-sidebar`) | M1 | **pass** — `accessibility.js` skip link `href="#admin-sidebar"` |
| V-UX-04 | Screen reader: nav | Only **one** link has `aria-current="page"` | M1 | **pass** — `aria-current` only on `.nav-link.active` |
| V-UX-05 | Tab away with dirty General form | Warning or dirty badge visible | M4 | **pass** — `#settings-dirty-badge` + `beforeunload` |

## Settings IA (M3)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-06 | Open Settings | 4 areas visible (Site, Team, Integrations, Infrastructure) | M3 | **pass** — `.settings-area-nav` in `settings.html` |
| V-UX-07 | Site area | Hosting, Domains, Project reachable without scrolling 13 tabs | M3 | **pass** — max 5 tabs per area |
| V-UX-08 | Old URL `?tab=hosting` | Redirects to `?area=site&tab=hosting` | M3 | **pass** — `redirectLegacySettingsUrl()` in `settings-page.js` |

## Hosting wizard (M5)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-09 | Hosting wizard step 1 | CMS URL + preview text | M5 | **pass** — `hosting-wizard.js` step 1 |
| V-UX-10 | Mode “In Birkly” | Link to Project tab; snippet shows cms URL | M5 | **pass** — `value="project"` + project link |
| V-UX-11 | Dokploy checklist | Visible without expanding `<details>` (self-host toggle) | M5 | **pass** — shown when self-hosted checkbox checked (not in `<details>`) |
| V-UX-12 | Onboarding hosting step | Same wizard component as Settings | M5 | **pass** — `onboarding.js` → `BirklyHostingWizard` |

## Visual tokens (M2)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-13 | Focus ring on input | Mint primary, not blue `#3b82f6` | M2 | **pending** — `border-color: var(--primary-500)` ok; some `rgba(59, 130, 246, …)` focus shadows remain in `admin/css/` |
| V-UX-14 | Sidebar header dark mode | Uses theme tokens, not fixed slate | M2 | **pass** — `.sidebar-header` uses `--bg-elevated`, `--text-primary` |
| V-UX-15 | Body link on light bg | WCAG AA contrast (manual or axe) | M2 | **pass** — links `--primary-700` in `typography.css` |
| V-UX-16 | `grep #3b82f6 admin/css` | Zero matches (or documented exceptions) | M2 | **pass** — zero matches (see `docs/admin-design-system.md`) |

## Feedback system (M7)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-17 | Hosting save error | Toast, not `alert()` | M7 | **pending** — `hosting-wizard.js` still uses `alert()` on error |
| V-UX-18 | Onboarding skip | Confirm modal, not native `confirm()` | M7 | **pending** — `onboarding.js` uses `confirm()` |
| V-UX-19 | Toast success | `aria-live="polite"` announces | M7 | **pending** — `BirklyFeedback` not shipped; MCP local toast has `aria-live` |

## i18n (M8)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-20 | Switch admin locale (if configured) | Hosting tab strings translate | M8 | **pending** — wizard strings hardcoded in JS |
| V-UX-21 | Grep hardcoded Hosting strings in templates | Wrapped in `__()` | M8 | **pending** — hosting wizard HTML in `hosting-wizard.js` |

## Project tab (M6)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-22 | Default Project view | Upload + file list, no full IDE | M6 | **pass** — `.project-simple-card` default visible |
| V-UX-23 | “Advanced editor” | Full tree + editor as today | M6 | **pass** — `#project-advanced-editor-toggle` reveals panel |

## ContentEditor (M9)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-24 | Tablet width 768px | Toolbar usable (overflow menu) | M9 | **pending** — horizontal scroll only; no overflow menu |
| V-UX-25 | Dark mode editor | No illegible toolbar text | M9 | **pending** — not verified in M9 scope |
| V-UX-26 | Active mode tab | Mint accent matches admin | M9 | **pending** — `#3b82f6` fallbacks in `fieldtypes/ContentEditor/editor.css` |

## Mobile & polish (M10)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-27 | Entries on 390px width | Filters accessible (drawer) | M10 | **pending** — toolbar stacks; no filter drawer |
| V-UX-28 | Marketplace loading | Skeleton cards, not text only | M10 | **pending** — no skeleton loaders in `plugins-page.js` |
| V-UX-29 | Empty entries list | Icon + message from shared component | M10 | **pass** — `empty-state` in `smart-table.html` |

## Docs & process (M11–M12)

| ID | Artifact | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-UX-30 | `docs/admin-design-system.md` | Tokens, buttons, save patterns documented | M11 | **pass** |
| V-UX-31 | Birkly-Docs admin UX guide | Published | M12 | **pass** — `guides/admin-ux.md` |
| V-UX-32 | design-reviewer | Sign-off recorded in matrix | M12 | **pending** — see sign-off section below |
| V-UX-33 | `ci-check.sh` | Green | M12 | **pending** — `scripts/ci-check.sh` not present in repo; use `admin-polish-smoke.sh` / manual smoke |

---

## Matrix summary (V-UX-01–33)

| Result | Count | IDs |
|--------|-------|-----|
| **pass** | 20 | 01–12, 14–16, 22–23, 29–31 |
| **pending** | 13 | 13, 17–21, 24–28, 32–33 |

---

## Regression smoke (M12)

- [ ] Login → Dashboard → Content → Entries → Entry editor save  
- [ ] Settings each area opens without JS errors  
- [ ] Plugins marketplace browse  
- [ ] Theme: Light / Dark / Auto  
- [ ] `cms.birkly.cloud/admin` production spot check  

---

## design-reviewer sign-off

| Field | Value |
|-------|-------|
| **Reviewer** | _pending — design-reviewer agent_ |
| **Date** | _TBD_ |
| **Scope reviewed** | Tokens (M2), hosting wizard UI (M5), settings IA (M3), `docs/admin-design-system.md` (M11) |
| **Outcome** | ☐ Approved &nbsp; ☐ Approved with notes &nbsp; ☐ Changes requested |
| **Notes** | _Placeholder — run visual review on Settings (4 areas), Hosting wizard, Project simple/advanced, light/dark themes._ |

---

## qa-verifier sign-off

| Field | Value |
|-------|-------|
| **Verifier** | _pending — qa-verifier agent_ |
| **Date** | _TBD_ |
| **Matrix run** | 20 pass / 13 pending (see summary above) |
| **Smoke** | _Not run in M12 docs PR — execute regression checklist before closure_ |
| **Outcome** | ☐ Pass &nbsp; ☐ Pass with exceptions &nbsp; ☐ Fail |
| **Exceptions** | V-UX-13, 17–21, 24–28, 32–33 documented as pending with reasons |
| **Notes** | _Re-run `rg '#3b82f6' admin/css/` and axe on Settings + Dashboard at closure._ |

---

## Closure gate (P44-M12)

- [ ] V-UX-01–33 addressed (20/33 pass; 13 pending)  
- [ ] No new critical a11y issues (axe on Settings + Dashboard)  
- [ ] design-reviewer approval  
- [ ] qa-verifier approval  
- [ ] Single human notification
