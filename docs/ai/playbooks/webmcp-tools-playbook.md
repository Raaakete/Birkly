# Website tools (WebMCP) playbook

Use for: Agent Guide **Tools** tab, visitor-facing AI helpers on the live site, form submission via WebMCP, Commerce/Events browser actions.

## What it is

**Website tools** (WebMCP) let an AI helper running in a **visitor's browser** use declared, safe actions on your public site — submit a contact form, read page context, add to cart — instead of guessing from HTML.

| Layer | Question | Where |
|-------|----------|-------|
| **Guide** | What should AI say about us? | `/.well-known/agent-guide.json`, `/llms.txt` |
| **Tools** (this playbook) | What can AI *do* on our site while someone visits? | `birkly-webmcp.js` in the visitor browser |
| **CMS MCP** | How does AI manage content in admin? | Settings → AI → MCP connector |

**Off by default.** Enable in **Agent Guide → Tools** (`?page=agent-guide&zone=tools`).

## Flow: enable website tools

1. Confirm Agent Guide is published if you also want public brand context (optional but common).
2. Open **Agent Guide → Tools**.
3. Enable **Website tools**.
4. Select built-in actions (page context, list entries, submit form; Commerce/Events packs when installed).
5. Allowlist public forms only — never admin or authenticated forms.
6. **Hosting:**
   - `project/` on Birkly → scripts injected automatically when enabled.
   - External site → copy script tags from **Agent Guide → Publish** tab.
7. Verify: Tools tab checklist; form fields have `toolname` / `tooldescription`; test in a WebMCP-capable browser.

## Flow: allowlist a contact form

1. Form must use public submit (`public_submit.php` or `{entry for}` scaffold).
2. In Tools tab, add form to allowlist (collection + form id).
3. Birkly stamps `toolname` and `tooldescription` on the form for browser registration.
4. Visitor AI proposes action → browser shows confirmation → submit runs through normal public API.

## Built-in tool families

| Tool | Read/write | Notes |
|------|------------|-------|
| Page context | Read | Title, URL, collection hints |
| List content | Read | Published entries when configured |
| Submit public form | Write | Requires allowlist + visitor confirm |
| Commerce pack | Read/write | Cart, checkout — plugin + allowlist |
| Events pack | Read/write | RSVP, tickets — plugin + allowlist |

## When user asks AI to "turn on website tools"

1. `read_birkly_docs` `webmcp-tools` or this playbook
2. Direct to **Agent Guide → Tools** — not Settings → AI
3. Explain separation from MCP admin connector and from Agent Guide JSON publish
4. If external hosting, point to Publish tab script snippets

## AI actions (admin MCP)

AI agents connected to Birkly admin **cannot** enable WebMCP remotely without `manage_settings` and appropriate admin actions. Default path: guide the human through Agent Guide → Tools UI.

Read published WebMCP config: public `api/public/webmcp.php` when enabled (site domain).

## Anti-patterns

- Enabling Tools without allowlisting forms (nothing useful registers)
- Confusing WebMCP with inbound MCP (admin content tools)
- Expecting Tools to publish Agent Guide JSON — separate switches
- Allowlisting write actions without visitor confirmation (Birkly always confirms writes)

## Related playbooks

- `agent-guide` — Guide · Tools · Publish hub; sources, claims, publish/unpublish
- `mcp-setup` — Connect Claude/Cursor/ChatGPT to CMS admin
- `public-forms` — Build contact/newsletter forms Tools may submit
- `commerce` / `events` — Plugin packs for shop and RSVP tools
