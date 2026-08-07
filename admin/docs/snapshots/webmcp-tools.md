# Website tools (WebMCP)

**Website tools** (built on the emerging browser standard **WebMCP**) let an AI helper running in a *visitor's* browser use clear, safe actions on your live public site — submit a contact form, add a product to the cart, RSVP to an event — instead of guessing from the page's HTML. They are **off by default**. Turn them on in **Agent Guide → Tools**.

## Summary

Open **Agent Guide → Tools** and switch on **Website tools**. Choose which built-in actions to allow (page info, listing content, submitting a form, plus shop/event actions if you use those plugins), and which public forms may be submitted. Any action that changes something — submitting a form, adding to cart, checking out, signing in — always asks the **visitor** to confirm first. Nothing is exposed until you enable it; on a browser that doesn't support the feature yet, or when it's off, your site behaves exactly as it does today.

This is different from **Agent Guide** (the public fact sheet AI reads about your business) and from **Birkly MCP** (how AI apps like Claude or Cursor connect to your Birkly admin to help you manage content). See [Three layers, one hub](#three-layers-one-hub) below.

## Beginner

**What is a "website tool"?**

Normally, an AI assistant helping a visitor on your site has to guess what a form does or scrape your page for information. A **tool** is a clearly named, described action your site publishes — "submit the contact form", "list our blog posts", "add this product to the cart" — that a supporting AI browser can call directly and safely, the same way it would call any other function.

**Why is it off by default?**

Because it lets an AI helper **do things** on your live site. Birkly ships it off so nothing changes until you decide to turn it on. When you do, you choose exactly which actions are allowed, and you can turn it off again at any time. The riskiest actions — paying, signing in, submitting a form — always show the visitor a confirmation before anything happens.

**How do I turn it on?**

1. Open **Agent Guide** in the admin sidebar.
2. Go to the **Tools** tab.
3. Switch on **Website tools**.
4. Leave the built-in read-only actions on (page info, listing published content) — they're safe by default.
5. Turn on **form submitting** when you're ready, and pick which forms (from your Public API collections, e.g. "contact") may be used.
6. Only turn on shop or event actions if you actually use Commerce or Events on your site.

That's it — no code required for the built-in actions. If your site is hosted on Birkly (`project/`), tools start working automatically. If it's hosted elsewhere, add two script tags shown in **Agent Guide → Publish** (see [Hosting: project vs external](#hosting-project-vs-external)).

**What can visitors' AI helpers actually do?**

Only what you've enabled, and only public, visitor-level actions:

- Look up the current page's info, list or read your **published** content.
- Describe a public form so it can be filled in correctly.
- Submit a form you've explicitly allowed — after the visitor confirms.
- If you use Commerce: see the cart, add an item, start checkout (payment always needs confirmation).
- If you use Events: list events, RSVP (with confirmation).
- If you use user accounts and opt in: check sign-in status, or sign in (always with confirmation).

Website tools **never** touch your admin or CMS settings — they can only do what any visitor could already do on the public page.

**See it in action**

Birkly ships a small demo page (`project/webmcp-demo.html`) with a contact form, a button, and a custom tool — it shows the on/off states and the exact tools a supporting browser would see once website tools are enabled.

## Three layers, one hub

Agent Guide is the admin home for "how AI works with this site," organized as **three tabs** — Guide, Tools, Publish. **AI app connections (Birkly MCP) are not in this hub** — they live in **Settings → AI** only. Each layer answers a different question and lives in a different place — they are never the same thing:

| Layer | Question it answers | Where it actually lives |
|-----|---------------------|--------------------------|
| **Guide** (hub tab) | "What should AI say about us?" | Public JSON on your site domain (`/.well-known/agent-guide.json`) |
| **Tools** (hub tab) | "What can an AI helper *do* on our website while someone is visiting?" | **This page's** WebMCP runtime — runs in the visitor's browser |
| **Publish** (hub tab) | "Where do all of the above actually work, given how my site is hosted?" | Mode-aware summary |
| **Connections** (Settings → AI) | "How do ChatGPT / Claude / our built-in assistant connect to *this Birkly CMS*?" | Always your Birkly address (`{your-birkly-url}/mcp`) — never your website host |

Plain-language versions of the first three, matching what you'll see in the product:

> **Agent Guide** is the public fact sheet about your business for AI. You edit it here; visitors' AIs can read it from your website.

> **Birkly MCP** is how AI apps (Claude, Cursor, ChatGPT, …) connect to your Birkly admin/CMS to help you build and manage content. It always lives on your Birkly address — not on an external website host.

> **WebMCP** lets AI helpers in the visitor's browser use clear actions on your live website (like submitting a contact form or adding to cart) instead of guessing from the page. It's off until you turn it on. Your site still works the same for everyone else.

Do not confuse these: publishing your Agent Guide does not turn on website tools, and turning on website tools does not change what AI apps can do in your CMS. See [Agent Guide](../02-content/agent-guide.md) for the Guide tab and [MCP connections](../01-admin/mcp-connections.md) for Connections.

## Advanced Users

### How it works (browser API)

The runtime (`birkly-webmcp.js`) targets `document.modelContext` (falling back to the older, deprecated `navigator.modelContext`) — the in-browser surface a page uses to publish tools an AI agent running alongside the visitor can call. Rules:

- **Feature-detected.** If the browser doesn't expose `modelContext`, or website tools are off, the script registers nothing — no console errors, no layout shift, no behavior change. An unsupported browser sees your site exactly as it is today.
- **Secure context required.** The API needs HTTPS (localhost counts as secure for development).
- **Lifecycle via `AbortSignal`.** There is no "unregister" call — tools are removed by aborting the controller Birkly registered them with, which happens automatically on page navigation.
- **Public-site actuation only.** Website tools call the same public APIs and public form-submit path your site already uses — never an admin/CMS write endpoint.

Registration happens two ways, and Birkly uses both:

- **Declarative** — a `<form>` can carry `toolname` / `tooldescription` attributes; the browser derives the tool's inputs from the form's own fields. Birkly's `{entry for}` public forms already work this way once their collection is on the allowlist.
- **Imperative** — `modelContext.registerTool({ name, description, inputSchema, execute, annotations })`. Birkly's core tools, plugin-pack tools, and author tools all register this way under the hood.

### Built-in tool catalog

Only enabled tools are visible to a visitor's AI helper — a disabled tool is **absent**, not present-but-erroring.

**Core tools** (default on once the master switch is on; browser support required):

| Tool | Type | Confirmation | What it does |
|------|------|---------------|--------------|
| `birkly_get_page_context` | Read-only | None | Page title, URL, current language, and (on an entry page) its collection/slug. |
| `birkly_list_published_entries` | Read-only | None | Lists **published** entries for a collection relevant to the page. Public data only. |
| `birkly_get_published_entry` | Read-only | None | Returns one **published** entry by collection + slug/id. Public fields only. |
| `birkly_describe_form` | Read-only | None | Describes a public form on the page — collection, fields, labels, required flags. |
| `birkly_submit_public_form` | Mutating | **Visitor confirms** | Submits a `public_api` form. Only works for collections on the form allowlist. |
| `birkly_set_language` | Mutating | None | Changes the site language, only when the page has a language selector. |

**Commerce pack** (off by default; registers only when a Commerce storefront is present on the page):

| Tool | Type | Confirmation |
|------|------|---------------|
| `birkly_commerce_get_cart` | Read-only | None |
| `birkly_commerce_add_to_cart` | Mutating | Visitor confirms |
| `birkly_commerce_checkout` | Mutating | **Visitor confirms before paying** (highest consent level) |

**Events pack** (off by default; registers only when an Events storefront is present):

| Tool | Type | Confirmation |
|------|------|---------------|
| `birkly_events_list` | Read-only | None |
| `birkly_events_rsvp` | Mutating | Visitor confirms |

**User pack** (off by default; opt-in; registers only when a sign-in surface is present):

| Tool | Type | Confirmation |
|------|------|---------------|
| `birkly_user_session` | Read-only | None |
| `birkly_user_login` | Mutating | **Visitor confirms before signing in** (highest consent level) |

### Author tools (project-specific)

You can add your own tools without touching the core runtime:

**Declarative** — mark any element:

```html
<button type="button"
        data-birkly-webmcp="show_opening_hours"
        data-birkly-webmcp-description="Show this shop's opening hours to the visitor.">
  Show opening hours
</button>
```

Invoking the tool clicks the element (and fires a `birkly:webmcp-invoke` event you can listen for).

**Imperative** — register from your own script:

```javascript
if (window.Birkly && window.Birkly.webmcp) {
  window.Birkly.webmcp.register({
    name: 'get_todays_special',
    description: "Return today's special offer for this shop.",
    execute: function () {
      return { special: '10% off all pastries before noon.' };
    }
  });
}
```

`Birkly.webmcp.register()` is a thin, safe wrapper: it inherits Birkly's lifecycle handling and only actually registers once website tools are enabled and the browser supports the API. Mutating author tools should follow the same confirm-before-acting pattern as the built-in tools.

Useful checks in your own scripts:

```javascript
Birkly.webmcp.isSupported();   // true if this browser exposes modelContext
Birkly.webmcp.isEnabled();     // true if the site owner turned website tools on
Birkly.webmcp.listRegistered(); // names of tools currently live
Birkly.webmcp.setConfirm(fn);  // replace the default window.confirm() with your own dialog
```

### Enablement storage (what "off by default" means)

Configuration lives in `settings/webmcp.json`:

```json
{
  "enabled": false,
  "tools": {
    "birkly_get_page_context": true,
    "birkly_submit_public_form": true,
    "birkly_commerce_checkout": false
  },
  "form_allowlist": ["contact"]
}
```

- `enabled` — the master switch. When `false`, the public config endpoint reports `{ "enabled": false }` and nothing registers — an off site is indistinguishable from a site that has never heard of WebMCP.
- `tools` — per-tool on/off. Core tools default on once the master switch is on; plugin-pack tools default off until you enable them.
- `form_allowlist` — which `public_api` collections `birkly_submit_public_form` (and the matching declarative form tool) may submit. An empty allowlist means no form can be submitted even if the tool itself is on.

The admin **Tools** tab reads/writes this through an authenticated API. The runtime reads a small public, secret-free projection — only `enabled`, the enabled tool ids, and `form_allowlist` — never draft content or admin keys.

### Hosting: project vs external

Website tools always run on the **page origin** — wherever your HTML is actually served — and call the CMS's public APIs for data. **Agent Guide → Publish** shows the exact script tags for your hosting mode:

| Hosting mode | What happens |
|--------------|----------------|
| **`project`** (Birkly serves your site) | `birkly-webmcp.js` loads automatically on public pages once website tools are on — no snippet to copy. |
| **`external`** (your site is hosted elsewhere) | Add two script tags to every page, shown in Publish once website tools are on: |

```html
<script src="https://YOUR-CMS-HOST/birkly-client.js" data-cms-url="https://YOUR-CMS-HOST"></script>
<script src="https://YOUR-CMS-HOST/birkly-webmcp.js" data-cms-url="https://YOUR-CMS-HOST"></script>
```

`birkly-client.js` must load first — it owns page context and the public API connection that `birkly-webmcp.js` then registers tools against. Both scripts silently do nothing when website tools are off or the browser doesn't support the API.

This mirrors how your **Agent Guide JSON** reaches an external site: Birkly's canonical URL always works (`{cms}/api/public/ai_presence.php` / `{cms}/.well-known/agent-guide.json`); Publish additionally offers a **download** of `agent-guide.json` for your own `/.well-known/` folder, and a **reverse-proxy** snippet if you'd rather serve it from your own domain. **Birkly MCP** (Connections) never changes with hosting mode — AI apps always connect to your Birkly address, never your website host.

On **`external`** pages, `birkly-client.js` also quietly adds a discovery `<link rel="alternate" type="application/json" href="{cms}/.well-known/agent-guide.json">` to the page `<head>` whenever it's loaded with `data-cms-url`. This gives agents a way to find your canonical Guide on the Birkly host straight away, even before you've set up a download or reverse-proxy on your own domain. It's automatic, non-visual, and added at most once per page.

### Verifying it works (technical)

On a supported Chromium build (146+), enable the `#enable-webmcp-testing` flag and open a page with website tools on. Registered tools appear in the browser's model-context inspector (DevTools → Lighthouse → Agentic Browsing). This is a technical, opt-in check — Birkly does not ask non-technical owners to flip browser flags as a normal step.

**About PageSpeed Insights.** PageSpeed Insights / Lighthouse Agentic Browsing often report WebMCP audits as **"Not evaluated" / "Nicht bewertet"** because their hosted test browsers usually don't have WebMCP enabled. That does **not** mean your site failed — it means the audit browser couldn't check. Local Chrome with `#enable-webmcp-testing` (above) is the real verification. See Google's [registered WebMCP tools audit](https://developer.chrome.com/docs/lighthouse/agentic-browsing/registered-webmcp-tools) and the [WebMCP imperative API explainer](https://developer.chrome.com/docs/ai/webmcp/imperative-api).

To make tools discoverable as early as possible, Birkly (a) inlines the public config on `window.BIRKLY_WEBMCP_CONFIG` **before** loading `birkly-webmcp.js`, so the runtime registers tools synchronously with no network round-trip, and (b) emits declarative `toolname` / `tooldescription` attributes on `{entry for}` public forms so a tool is visible in static markup before any JS runs.

### Safety summary

- **Public-site actuation only.** No admin/CMS write tool is ever exposed — website tools can only do what a visitor could already do on the page.
- **Default off**, per site, until an owner explicitly enables it.
- **Per-tool control.** Disabled tools are absent from the agent's list, not erroring.
- **Confirmation gates.** Every mutating tool asks the visitor to confirm; payment and sign-in use the highest consent level.
- **Allowlisted forms only.** `birkly_submit_public_form` only works for collections you've explicitly listed.
- **Untrusted content.** Tools that accept or echo visitor-entered data are annotated `untrustedContentHint` so a well-behaved agent treats the payload as untrusted.
- **Silent no-op.** Unsupported browsers, or an off site, behave exactly as today — nothing breaks, nothing is logged loudly.

## Related

- [Agent Guide](../02-content/agent-guide.md) — the Guide tab (public fact sheet) and the wider hub
- [MCP connections](../01-admin/mcp-connections.md) — Settings → AI (Birkly MCP for AI apps; not in the Agent Guide hub)
- [Public forms](public-forms.md) — how `public_api` collections and `{entry for}` work under the hood
- [Client library](client-library.md) — `birkly-client.js`, which website tools load after
- [Agent Guide public API](agent-guide-public-api.md) — the Guide JSON website tools never duplicate
