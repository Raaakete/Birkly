# P44 — Verification matrix (admin UI/UX excellence)

**Plan:** `docs/reviews/P44-admin-ux-excellence-plan.md`  
**Status:** Draft — complete at P44-M12

---

## Critical — save & accessibility (M1)

| ID | Scenario | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-01 | Settings → Hosting, change CMS URL, click global “Save settings” | Hosting **not** saved unless Hosting save used | M1 |
| V-UX-02 | Settings → General, change field, area save | General settings persisted | M4 |
| V-UX-03 | Keyboard: skip link | Focus moves to sidebar (`#admin-sidebar`) | M1 |
| V-UX-04 | Screen reader: nav | Only **one** link has `aria-current="page"` | M1 |
| V-UX-05 | Tab away with dirty General form | Warning or dirty badge visible | M4 |

## Settings IA (M3)

| ID | Scenario | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-06 | Open Settings | 4 areas visible (Site, Team, Integrations, Infrastructure) | M3 |
| V-UX-07 | Site area | Hosting, Domains, Project reachable without scrolling 13 tabs | M3 |
| V-UX-08 | Old URL `?tab=hosting` | Redirects to `?area=site&tab=hosting` | M3 |

## Hosting wizard (M5)

| ID | Scenario | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-09 | Hosting wizard step 1 | CMS URL + preview text | M5 |
| V-UX-10 | Mode “In Birkly” | Link to Project tab; snippet shows cms URL | M5 |
| V-UX-11 | Dokploy checklist | Visible without expanding `<details>` (self-host toggle) | M5 |
| V-UX-12 | Onboarding hosting step | Same wizard component as Settings | M5 |

## Visual tokens (M2)

| ID | Scenario | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-13 | Focus ring on input | Mint primary, not blue `#3b82f6` | M2 |
| V-UX-14 | Sidebar header dark mode | Uses theme tokens, not fixed slate | M2 |
| V-UX-15 | Body link on light bg | WCAG AA contrast (manual or axe) | M2 |
| V-UX-16 | `grep #3b82f6 admin/css` | Zero matches (or documented exceptions) | M2 |

## Feedback system (M7)

| ID | Scenario | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-17 | Hosting save error | Toast, not `alert()` | M7 |
| V-UX-18 | Onboarding skip | Confirm modal, not native `confirm()` | M7 |
| V-UX-19 | Toast success | `aria-live="polite"` announces | M7 |

## i18n (M8)

| ID | Scenario | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-20 | Switch admin locale (if configured) | Hosting tab strings translate | M8 |
| V-UX-21 | Grep hardcoded Hosting strings in templates | Wrapped in `__()` | M8 |

## Project tab (M6)

| ID | Scenario | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-22 | Default Project view | Upload + file list, no full IDE | M6 |
| V-UX-23 | “Advanced editor” | Full tree + editor as today | M6 |

## ContentEditor (M9)

| ID | Scenario | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-24 | Tablet width 768px | Toolbar usable (overflow menu) | M9 |
| V-UX-25 | Dark mode editor | No illegible toolbar text | M9 |
| V-UX-26 | Active mode tab | Mint accent matches admin | M9 |

## Mobile & polish (M10)

| ID | Scenario | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-27 | Entries on 390px width | Filters accessible (drawer) | M10 |
| V-UX-28 | Marketplace loading | Skeleton cards, not text only | M10 |
| V-UX-29 | Empty entries list | Icon + message from shared component | M10 |

## Docs & process (M11–M12)

| ID | Artifact | Expected | Milestone |
|----|----------|----------|-----------|
| V-UX-30 | `docs/admin-design-system.md` | Tokens, buttons, save patterns documented | M11 |
| V-UX-31 | Birkly-Docs admin UX guide | Published | M12 |
| V-UX-32 | design-reviewer | Sign-off recorded in matrix | M12 |
| V-UX-33 | `ci-check.sh` | Green | M12 |

---

## Regression smoke (M12)

- [ ] Login → Dashboard → Content → Entries → Entry editor save  
- [ ] Settings each area opens without JS errors  
- [ ] Plugins marketplace browse  
- [ ] Theme: Light / Dark / Auto  
- [ ] `cms.birkly.cloud/admin` production spot check  

---

## Closure gate (P44-M12)

- [ ] V-UX-01–33 addressed  
- [ ] No new critical a11y issues (axe on Settings + Dashboard)  
- [ ] design-reviewer approval  
- [ ] Single human notification
