# P5-M4 design review — Settings AI tab (agents)

**Date:** 2026-06-29  
**Verdict:** **PASS** — design-reviewer approved

## Scope

- `admin/templates/settings.html` — AI tab, agents section, create modal
- `admin/js/mcp-connections.js` — agents list, empty OAuth copy, `/mcp` URL

## Result

Connector-first IA preserved. Agents terminology consistent after polish. Empty OAuth fields messaging clear in quick start, modal, and per-agent setup panels. HTTPS warning present.

## Non-blocking follow-ups (deferred)

- `aria-controls` on expand toggles
- Modal focus trap (settings-wide pattern)

---

**design-reviewer: approved**
