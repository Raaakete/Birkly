# P44 — Gap register (admin UI/UX excellence)

**Status:** Open — baseline for P44-M0  
**Plan:** `docs/reviews/P44-admin-ux-excellence-plan.md`  
**Source:** Full design review 2026-07-25 (post-P43)

---

## U1 — Global Save misleads users — **Critical**

| | |
|---|---|
| **Today** | Sticky “Save settings” only saves General, Security, Storage, Monitoring (`settings-page.js` `handleGlobalSave`) |
| **Problem** | Hosting, Project, Domains, Webhooks, Redirects, AI use separate saves — users think one click saves all |
| **Target** | Scoped save labels, per-tab dirty state, or hide global save on independent tabs |
| **Milestone** | P44-M1, P44-M4 |

## U2 — Settings tab overload (13 tabs) — **High**

| | |
|---|---|
| **Today** | Single row: General, Users, Security, API, Webhooks, Redirects, Hosting, Domains, Project, Activity, AI, Storage, Monitoring |
| **Problem** | Cognitive overload; Hosting/Domains/Project buried among power-user tabs |
| **Target** | Grouped IA: Site · Team & security · Integrations · Infrastructure |
| **Milestone** | P44-M3 |

## U3 — Hosting UX still engineer-oriented — **High**

| | |
|---|---|
| **Today** | P43 Hosting tab with prose, `<details>` Dokploy checklist, technical labels |
| **Problem** | Non-developers still confused (CMS URL vs public site vs Domains) |
| **Target** | 3-step wizard UI, live URL preview, checklist visible for self-hosters |
| **Milestone** | P44-M5 |

## U4 — Design tokens declared but not applied — **High**

| | |
|---|---|
| **Today** | `variables.css` mint tokens; sidebar hardcoded slate; focus `#3b82f6` fallbacks; General Sans not loaded |
| **Problem** | Inconsistent brand; blue reads as different product; weak link contrast |
| **Target** | Token compliance pass across `admin/css/` |
| **Milestone** | P44-M2 |

## U5 — Accessibility bugs in navigation — **High**

| | |
|---|---|
| **Today** | Skip link `#navigation` vs `#admin-sidebar`; `aria-current` marks all nav links |
| **Problem** | Screen reader / keyboard users get wrong landmarks |
| **Target** | Fix `accessibility.js`; skip links use theme tokens |
| **Milestone** | P44-M1 |

## U6 — alert() / confirm() feedback — **Medium**

| | |
|---|---|
| **Today** | onboarding.js, hosting-settings.js, collection-editor, settings — native dialogs |
| **Problem** | Poor a11y, breaks visual consistency, jarring on mobile |
| **Target** | Shared toast + confirm modal using `messages.css` / modal component |
| **Milestone** | P44-M7 |

## U7 — i18n gaps — **High**

| | |
|---|---|
| **Today** | Hosting tab, Project tab, bulk entries, onboarding starters, sidebar “Alpha Feedback” hardcoded English |
| **Problem** | Mixed translated/untranslated UI |
| **Target** | `__()` for all user-facing admin strings; locale keys |
| **Milestone** | P44-M8 |

## U8 — Project tab = developer IDE in Settings — **Medium**

| | |
|---|---|
| **Today** | Full tree + editor in Settings → Project |
| **Problem** | Intimidating for site owners who only upload HTML |
| **Target** | Default “simple” upload/view; “Advanced file editor” expand |
| **Milestone** | P44-M6 |

## U9 — ContentEditor toolbar overflow — **Medium**

| | |
|---|---|
| **Today** | Toolbar tightens only at 640px; blue accent fallbacks |
| **Problem** | Tablet overflow; visual mismatch with admin mint |
| **Target** | Overflow menu &lt;1024px; mint tokens; dark mode smoke |
| **Milestone** | P44-M9 |

## U10 — Mobile dense toolbars — **Medium**

| | |
|---|---|
| **Today** | Entries search + filter + actions crowd small screens |
| **Problem** | Hard to tap; filters hidden |
| **Target** | Filter drawer; sticky primary CTA |
| **Milestone** | P44-M10 |

## U11 — Loading states text-only — **Low**

| | |
|---|---|
| **Today** | Marketplace/plugins “Loading…” without skeletons |
| **Problem** | Feels unfinished |
| **Target** | Skeleton cards matching grid layout |
| **Milestone** | P44-M10 |

## U12 — Empty states inconsistent — **Low**

| | |
|---|---|
| **Today** | `empty-states.css` exists but not always used with icons |
| **Problem** | Blank areas feel broken |
| **Target** | Default icon in `empty-state.html`; use across list pages |
| **Milestone** | P44-M10 |

## U13 — Inline styles bypass system — **Medium**

| | |
|---|---|
| **Today** | 42+ inline `style=` on settings.html; 67 on collection-editor.html |
| **Problem** | Hard to maintain; dark mode breaks |
| **Target** | Utility classes; page-scoped CSS for worst offenders |
| **Milestone** | P44-M2, P44-M10 |

## U14 — No design system doc — **Medium**

| | |
|---|---|
| **Today** | Tokens in CSS only; no contributor guide |
| **Problem** | Regressions repeat (hex, blue focus) |
| **Target** | `docs/admin-design-system.md` + grep/lint guidance |
| **Milestone** | P44-M11 |

## U15 — Form validation UX — **Low**

| | |
|---|---|
| **Today** | `:invalid` red borders while typing |
| **Problem** | Aggressive error state before submit |
| **Target** | Validate on blur/submit only |
| **Milestone** | P44-M2 |

---

## Strengths to preserve

- Token foundation in `variables.css` + dark/auto theme
- Responsive shell (sidebar drawer, top bar, collapsible rail)
- tabs-v2 for many tabs
- Content hub, entries, entry editor sticky save
- Dashboard checklist + completion modal
- P43 hosting backend (URLs, routing) — **polish UX only in P44**
