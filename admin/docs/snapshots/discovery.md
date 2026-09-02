# Discovery

## Summary

**Discovery** is the admin home for how your public site is found — by search engines, chat AIs, and visitor browser assistants. Open **Discovery** in the admin sidebar (legacy URLs `?page=agent-guide` and `?page=ai-presence` redirect here). Three independent **packs** — **SEO**, **Agent Guide**, and **WebMCP** — each have their own on/off toggle and delivery mode. Turning a pack off stops public output; your settings are preserved.

## Beginner

### What Discovery does

Discovery answers one question: *"Can people and AIs find and understand my site?"*

| Pack | Who it helps | What visitors get |
|------|----------------|-------------------|
| **SEO** | Google, Bing, search-based AIs | Page titles, descriptions, sitemap, `robots.txt`, social preview images |
| **Agent Guide** | Chat and search AIs | `/for-agents`, `/llms.txt`, `/.well-known/agent-guide.json` |
| **WebMCP** | AI helpers in the visitor's browser | Tools from **installed plugins only** via `birkly-webmcp.js` |

Each pack works independently. You can run SEO without Agent Guide, or Agent Guide without WebMCP.

### Opening Discovery

1. In the Birkly admin sidebar, click **Discovery**.
2. You see three tabs: **SEO**, **Agent Guide**, **WebMCP**.
3. Turn a pack **on** with its master toggle, then configure delivery.

### Delivery modes

Every pack supports:

- **Auto-inject** — Birkly adds tags and scripts on Birkly-hosted `project/` pages during server render (SSR).
- **Manual** — Copy URLs and HTML snippets for external sites or third-party SEO plugins.
- **Download** — Export Agent Guide JSON (where applicable).

Use **auto-inject** when your site runs on Birkly's `project/` hosting. Use **manual** when the site is hosted elsewhere or you manage head tags yourself.

### Verify & monitor

After publishing, scroll to **Verify & monitor** at the bottom of Discovery. It checks the discovery cross-link mesh:

- `llms.txt` ↔ sitemap ↔ `robots.txt` ↔ `/for-agents` ↔ `agent-guide.json`
- **SEO health** — missing titles, duplicate descriptions, redirect loops

Fix any red items before sharing your site publicly.

### Site URL Map

Per-URL search and social listings live in **Discovery → SEO → Site URL Map** — not in the entry editor. See [Site URL Map](site-url-map.md).

## Advanced Users

**Config:** `settings/discovery.json` — per-pack `enabled`, delivery mode, and pack-specific options. Admin API: `api/discovery.php`.

**SEO pack:** Materializes sitemap, injects meta/JSON-LD via `core/project_ssr.php` → `birkly_project_ssr_inject_discovery_packs()`. Site URL Map rows drive per-URL overrides; see [Site URL Map](site-url-map.md).

**Agent Guide pack:** Published snapshot at `ai_presence/` → `/.well-known/agent-guide.json`, `/llms.txt`, `/for-agents`. Discovery channels documented in [Agent Guide public API](../04-integration/agent-guide-public-api.md).

**WebMCP pack:** Serves `birkly-webmcp.js`; tools registered by active plugins via `birkly_webmcp_register_tools()`. Inbound MCP (admin AI) is separate — see [MCP connections](mcp-connections.md).

**Redirects:** Legacy `?page=agent-guide` and `?page=ai-presence` resolve to Discovery for bookmark compatibility.

## Related

- [Site URL Map](site-url-map.md)
- [Agent Guide getting started](../02-content/agent-guide-getting-started.md)
- [WebMCP tools](../04-integration/webmcp-tools.md)
- [Agent Guide public API](../04-integration/agent-guide-public-api.md)
- [Detail pages and URL context](../03-templating/detail-pages-and-url-context.md)
