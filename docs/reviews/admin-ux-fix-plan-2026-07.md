# Birkly admin — UX/UI fix plan (2026-07-26)

**Status:** Complete (all tracks A–E implemented in PR #314 follow-up commits)

---

## Tracks — completion summary

| Track | Status | Highlights |
|-------|--------|------------|
| **A — Stop the bleeding** | Done | CSP-safe actions, option builder, select-all, create slug guard, a11y idempotency, settings toasts |
| **B — Feedback & dialogs** | Done | BirklyFeedback toasts/confirm/prompt/confirmDestructive; native dialogs migrated admin-wide |
| **C — Accessibility** | Done | Focus ring tokens, help/tab contrast, entry labels, ARIA settings tabs, keyboard field reorder, reduced motion, dark badges |
| **D — Save model** | Done | Per-tab settings dirty state, entry save-in-place, datetime fix, schema diff confirm, unsaved guards |
| **E — Scale & IA** | Done | Settings 6-page IA + search, entries server pagination, column labels, collection-editor-core extract |

---

## Regression guards

```bash
rg 'onclick=' admin/templates admin/partials --glob '*.{html,php}'
rg '\b(alert|confirm|prompt)\(' admin/js admin/templates --glob '!ui-feedback.js' --glob '!utils.js'
```

Manual: settings tab switch with unsaved edits; entries pagination; collection schema destructive save confirm.
