# Project sites and plugins

How `project/` HTML works with active plugins: one client script tag, auto-loaded storefront behavior, member gates, and credentialed plugin API calls — including cross-origin sites.

## Summary

**In short:** Every project page needs **one** script: `birkly-client.js` (add `data-cms-url` when the site is not on the CMS host). Core loads plugin storefront scripts from each active plugin’s `plugin.json` manifest — you do **not** add `<script src="/plugins/…/storefront.js">` yourself. Member-only pages use `data-user-require-auth` on `<body>` or `<html>`. Custom plugin JavaScript should call plugin APIs through `Birkly.fetch` / `Birkly.routeFetch`, not hardcoded `/api/…` URLs.

## Beginner

### One script tag

Same host (CMS preview at `/project/`):

```html
<script src="/birkly-client.js"></script>
```

Cross-origin (e.g. `birkly.io` → `cms.birkly.cloud`):

```html
<script src="https://cms.birkly.cloud/birkly-client.js"
        data-cms-url="https://cms.birkly.cloud"></script>
```

- **`data-cms-url`** must point at the CMS base URL so content, sessions, and plugin APIs resolve correctly.  
- Your public site origin must be allowed by the CMS (CORS / licensed origins).  
- Full script-tag checklist: [Client setup](client-setup.md).

Do **not** add separate tags for User, Commerce, or Events storefront scripts. `birkly-client.js` fetches the manifest and injects them for you.

### What loads automatically

When a plugin is **active**, its `plugin.json` can declare storefront scripts:

| Plugin | Global | Typical behavior |
|--------|--------|------------------|
| User Management | `BirklyUser` | Login/logout forms, account UI, member auth gate |
| Commerce | `BirklyCommerce` | Cart, checkout, linked donation binders |
| Events | `BirklyEvents` | RSVP binders |

Automation and Studio do not ship separate storefront scripts for project sites.

Template helpers (`{commerce_cart_count}`, `{user_logged_in}`, etc.) still come from the templating engine — see [Plugin language extensions](plugin-language-extensions.md). Storefront scripts handle **interactive** behavior after the page loads.

### Member-only pages (User plugin)

Protect a page for signed-in members only:

```html
<!DOCTYPE html>
<html lang="en" data-user-require-auth data-user-login-url="/club/login.html">
<head>
  <meta charset="UTF-8">
  <title>Member feed</title>
</head>
<body>
  <h1>Welcome back</h1>
  <!-- loops, regions, etc. -->
  <script src="https://cms.birkly.cloud/birkly-client.js"
          data-cms-url="https://cms.birkly.cloud"></script>
</body>
</html>
```

- **`data-user-require-auth`** — guests are redirected to the login URL.  
- **`data-user-login-url`** — where to send guests (default `/club/login.html`).  
- Legacy alias: `data-birkly-require-member` (prefer `data-user-require-auth`).

The User plugin waits for plugin scripts to load before running the gate, so cross-origin login → redirect → member page works with only `birkly-client.js`.

### Shop, donate, and events pages

Use the same single script tag. Commerce cart/checkout and Events RSVP binders attach via auto-loaded storefront scripts when those plugins are active. Page recipes: [Storefront and events patterns](storefront-patterns.md), [Commerce linked checkout](commerce-linked-checkout.md).

### Deprecated: manual plugin script tags

**Do not** add tags like:

```html
<!-- deprecated — remove these -->
<script src="/plugins/user_management/assets/user-storefront.js"></script>
<script src="/plugins/commerce/assets/commerce-storefront.js"></script>
```

Manual tags are deprecated. The loader deduplicates by global name (`provides` in `plugin.json`), but omitting them keeps pages simpler and avoids version drift. Remove site-specific gate scripts (e.g. `club-gate.js`) — use `data-user-require-auth` instead.

## Advanced Users

### For developers & AI

**Init sequence (CSR and SSR)**

1. `detectApiEndpoint()` — resolve `Birkly.cmsUrl` from `data-cms-url` or same origin.  
2. `public.php?action=site_client_config` — merged plugin config (e.g. auth check routes).  
3. `public.php?action=storefront_scripts` — active plugins’ script URLs + versions.  
4. Inject each script from `cmsUrl` + path; skip if `window[provides]` already exists.  
5. `birkly:plugins-ready` — storefront scripts loaded.  
6. Plugin binders (auth gate, cart, RSVP) + template processing.  
7. `birkly:client-ready` — full client init complete.

SSR pages (`<meta name="birkly-ssr">`) may skip client template re-render but **still** load storefront scripts and run auth gates.

**`plugin.json` manifest (plugin authors)**

```json
{
  "storefront": {
    "scripts": [
      {
        "path": "assets/user-storefront.js",
        "provides": "BirklyUser",
        "load": "always"
      }
    ]
  }
}
```

| Field | Meaning |
|-------|---------|
| `path` | Relative to plugin root; served at `/plugins/{plugin_id}/{path}` |
| `provides` | Global for dedupe (`window.BirklyUser`) |
| `load` | `always` (default) or `when-detected` (future) |

Core aggregates via `birkly_get_storefront_scripts()`; inactive plugins are excluded.

**`Birkly.fetch` / `Birkly.routeFetch` (plugin API transport)**

Use for credentialed calls to `/api/index.php?route=…` with site session header — required for cross-origin project sites:

```javascript
const json = await Birkly.fetch({
  route: 'user_auth',
  action: 'check',
  method: 'GET',
});

await Birkly.routeFetch({
  route: 'commerce',
  action: 'add_to_cart',
  method: 'POST',
  body: { product_slug: 'supporter', quantity: 1 },
});
```

`Birkly.routeFetch` and object-form `Birkly.fetch` are equivalent. Both use `credentials: 'include'` and `X-Birkly-Site-Session` when present.

**Legacy public API form** — string action still works for content reads:

```javascript
const list = await Birkly.fetch('list_entries', { collection: 'blog' });
```

Do not hardcode `/api/index.php?route=commerce` in project or plugin storefront code.

**`site_client_config`**

Plugins contribute keys via the `birkly_site_client_config` filter (e.g. User plugin `auth.check` / `auth.login`). Core merges; no plugin-specific keys in core. Client reads once at init.

**Events**

| Event | When |
|-------|------|
| `birkly:api-ready` | `Birkly.cmsUrl` resolved |
| `birkly:plugins-ready` | Storefront scripts loaded |
| `birkly:client-ready` | Templates processed (if CSR), binders wired |
| `birkly:templates-processed` | Full-document template pass finished |

Custom site JS that depends on `BirklyUser` or `BirklyCommerce` should listen for `birkly:plugins-ready` or `birkly:client-ready`.

**Division of responsibility**

| Layer | Owns |
|-------|------|
| Core `birkly-client.js` | Templating, manifest loader, `Birkly.fetch`, site session transport, generic `data-birkly-route` forms |
| Plugin `*-storefront.js` | Auth gate, cart/checkout, RSVP, account UI |
| Merchant `project/` HTML | Markup + declarative `data-*` — no custom auth gate scripts |

**Common failures**

| Symptom | Fix |
|---------|-----|
| Gate never runs | User plugin inactive; missing `birkly-client.js`; gate attribute on wrong element |
| Cart/login fails cross-origin | Set `data-cms-url`; allow site origin on CMS |
| Duplicate plugin behavior | Remove manual `/plugins/…/storefront.js` tags |
| `BirklyUser` undefined | Wait for `birkly:plugins-ready`; confirm plugin active and manifest entry exists |

## Related

- [Client setup](client-setup.md)
- [Storefront and events patterns](storefront-patterns.md)
- [Commerce linked checkout](commerce-linked-checkout.md)
- [Plugin language extensions](plugin-language-extensions.md)
- [Client library](../04-integration/client-library.md)
- [User signup and membership](../06-extensibility/user-signup-membership.md)
- [Production optimization](production-optimization.md) — SSR still loads storefront scripts
