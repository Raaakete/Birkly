# P23 — Studio IDE (advanced builder + project workspace)

**Status:** Approved 2026-07-08 (Keven)  
**Owner:** lead-engineer · **Extends:** `plugins/studio/`

## Goal

Transform Studio v1 into a full **project IDE**: real filesystem tree, multi-file tabs, visual/code/split views, pro canvas v2, plugin blocks, revisions — while keeping Birkly contract (compiled HTML on public, SSR, no JSON runtime).

## Locked decisions (P23-D1–D17)

| ID | Decision |
|----|----------|
| P23-D1 | IDE replaces v1 single-page dropdown UI |
| P23-D2 | File tree backed by real disk under `content/studio-projects/{id}/` |
| P23-D3 | Sandboxed filesystem API — no path traversal |
| P23-D4 | Multi-file tabs with dirty state + sessionStorage restore |
| P23-D5 | Visual, Code, Split — all first-class view modes |
| P23-D6 | Open project ↗ opens home in **new browser tab** (`target=_blank`) |
| P23-D7 | Canvas schema v2 — backward compatible v1 upgrade on open |
| P23-D8 | DnD reorder sections/elements + layers panel |
| P23-D9 | Multi-column layouts + responsive breakpoints |
| P23-D10 | Design tokens in `tokens.json` per project |
| P23-D11 | Global components in `components/*.json` |
| P23-D12 | `register_studio_block()` for plugin blocks |
| P23-D13 | Section library in `plugins/studio/sections/` |
| P23-D14 | Undo stack + file revisions on save |
| P23-D15 | SSR parity sprint — log gaps per P22-D11 |
| P23-D16 | Export CSS optimizer (minify comments/whitespace) |
| P23-D17 | Single human notification at P23-M16 |

## Milestones

P23-M0 → M16 per lead-engineer handoff.

## Values check

✅ Self-contained plugin · ✅ No npm runtime · ✅ Public = compiled artifacts only
