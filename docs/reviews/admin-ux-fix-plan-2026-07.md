# Birkly admin — UX/UI fix plan (2026-07-26)

**Source audit:** `docs/reviews/admin-ux-audit-2026-07.md`  
**Goal:** Fix broken behaviour first, then unify feedback/dialog UI, then accessibility and IA.

The audit skewed toward UX (workflows, data safety, keyboard access). This plan adds an explicit **UI track** for visual polish: modals, toasts, contrast, tokens, and removing native browser dialogs.

---

## Tracks

| Track | Focus | Outcome |
|-------|-------|---------|
| **A — Stop the bleeding** | P0 functional breaks | Controls work; no silent data loss on create |
| **B — Feedback & dialogs (UI)** | Toasts, confirms, prompts, modals | One polished system; no `alert`/`confirm`/`prompt` |
| **C — Accessibility floor** | Contrast, labels, tabs, motion | WCAG floor on forms and navigation |
| **D — Save & feedback model** | Per-screen save, unsaved guards | Predictable save + visible success/error |
| **E — Scale & IA** | Pagination, settings IA, CSS split | Long-term structure |

---

## Track A — P0 (this PR, wave 1)

| ID | Fix | Files |
|----|-----|-------|
| A1 | Replace inline `onclick` with delegated `data-admin-action` | `delegated-actions.js`, templates |
| A2 | Select/radio/multiselect option builder (remove inline scripts) | `fieldtype-config.js`, fieldtypes |
| A3 | Bulk select-all ID mismatch | `smart-table.php` |
| A4 | Honour `action=create` — reject slug collision | `admin_collections.php` |
| A5 | `accessibility.js` idempotent init | `accessibility.js` |
| A6 | Settings toasts always visible (not hidden bar) | `settings-page.js` |

---

## Track B — Feedback & dialogs (this PR, wave 1–2)

| ID | Fix | Files |
|----|-----|-------|
| B1 | Toast: icon, dismiss, stack, dark theme | `ui-feedback.js`, `ui-feedback.css` |
| B2 | Confirm: danger variant, focus trap, backdrop blur | `ui-feedback.js`, `ui-feedback.css` |
| B3 | **Prompt modal** (replaces `prompt()`) | `ui-feedback.js` |
| B4 | Modal dismiss via `data-modal-dismiss` (no inline JS) | `delegated-actions.js`, `modal.html` |
| B5 | Migrate collection editor + entry-detail off native dialogs | inline JS in templates |
| B6 | Migrate settings `prompt()` denylist + media/project prompts | respective JS files |

**Target UI for dialogs**

- Centered card, 480px max, soft backdrop blur
- Primary / secondary / danger button hierarchy
- Escape and backdrop click cancel; focus trapped inside
- Toasts bottom-right, 4s auto-dismiss, manual close, type colour + icon

---

## Track C — Accessibility (next PR)

- Focus ring contrast (`--focus-ring` token)
- Help text and active-tab contrast
- Entry field `<label for>` wiring
- ARIA tabs + arrow keys on settings/content
- Keyboard field reorder in collection editor
- `prefers-reduced-motion` block
- Dark-theme semantic badge tokens

---

## Track D — Save model (next PR)

- Settings: per-tab dirty state; no cross-tab wipe on save
- Collection/entry: unsaved-changes guard + save-in-place toast
- Entry: fix scheduled date reset on failed save
- Schema destructive-change confirmation before save

---

## Track E — IA & scale (later)

- Settings six-page IA + search
- Entries server pagination/sort/search
- Extract collection-editor JS/CSS
- Per-file asset versioning
- Entry preview

---

## Execution order (agent)

1. **This turn:** A1–A6 + B1–B5 (foundation + highest-traffic screens)
2. **Next:** C + remaining B6 + D schema confirm
3. **Then:** E

---

## Regression guards

- Grep CI check: no `onclick=` in `admin/templates`
- Grep CI check: no bare `confirm(` / `alert(` / `prompt(` in `admin/js` (except ui-feedback shims)
- Manual: add select field options in collection editor; bulk select-all on entries list
