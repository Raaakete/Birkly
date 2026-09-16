# Empty states & Guide handoff (P87 Layer A)

**Scope:** Core admin surfaces only. Plugin-specific empty states stay with plugins unless they reuse the shared helper without custom rules.

## Contract

Use `components/empty-states.css` classes:

| Piece | Class / element |
|-------|------------------|
| Wrapper | `.empty-state` (`.empty-state--compact` in panels/tables) |
| Title | `.empty-state-title` |
| Hint | `.empty-state-text` |
| Actions | `.empty-state-actions` with one `.btn-primary` and optional secondary |

**PHP templates:** render markup server-side (see `admin/templates/components/empty-state.html`).

**JS lists:** prefer `Birkly.renderEmptyState(options)` from `admin/js/empty-state.js`:

- `title`, `hint`
- `primary`: `{ label, href }` or `{ label, onClick }`
- `secondary`: `{ label, guideAction: '<journey-id>' }` → opens Guide on Journeys tab
- `helpTopic`: adds Learn more → Help topic id

## Core surfaces (v1)

| Surface | When | Primary CTA | Guide journey |
|---------|------|-------------|---------------|
| Collections (`content-groups.js`) | Zero collections | New collection | `first-content` (Show me how) |
| Entries (`entries.js`) | Zero entries, no filters | New entry | `first-content` |
| Hub installed (`plugins-page.js`) | Zero plugins | Browse Hub | panel open |
| Media (`media-page.js`) | Empty library, no filters | Upload | `first-content` |
| Dashboard content widget | Zero collections | New collection | `first-content` |
| Site delivery strip | Status not green | (existing Fix/Repair) | `first-content` nudge button |

Tertiary **Learn more** uses Help `topic` ids aligned with guide manifest `help_topic` fields.

## Guide coupling

- `BirklyGuide.open()` — panel on current tab.
- `BirklyGuide.open({ tab: 'journeys', journeyId: 'first-content' })` — focuses journey in panel.
- Empty states do **not** auto-open the Guide panel (P87-D16); only the one-time first-visit auto-open applies.
