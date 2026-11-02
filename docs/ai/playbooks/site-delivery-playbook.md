# Site delivery & routing playbook (P66)

Use when the operator or agent needs **CMS URL**, **public site URL**, **multi-domain**, **delivery health**, or **routing** — not content in `project/`.

**Related:** `site-building` (what goes in `project/`), `get_site_delivery_status`, `run_site_delivery_probe`.

## Mental model

| Concept | Source of truth |
|---------|-----------------|
| CMS + public URLs | `settings/hosting.json` |
| HTTP routing by hostname | `core/hosting_routing.php` + `hosting.json` |
| CORS / Public API allowlist | Domains tab (must align with delivery roles) |
| Page files & SSR | `project/` + Project settings |

**One install, many hostnames** — primary + aliases for CMS and public surfaces. Not multi-site (different content per domain).

## Modes

| `public_site_mode` | Meaning |
|---------------------|---------|
| `none` | CMS only — no public site on this install |
| `external` | Public site hosted elsewhere (Netlify, etc.) |
| `project` | Public site served from `project/` on configured public URL |

## Agent tools

- **`get_site_delivery_status`** — hosting config snapshot, probe summary, domain role map
- **`run_site_delivery_probe`** — refresh probes (`deep: true` for outbound HTTP checks when supported)
- **`get_project_info`** — use `asset_url_example` / `page_url_example`; never hardcode `/project/` on public hosts

## URL helpers (templates)

From `get_project_info`:

- **`asset_url_example`** — CSS/JS/images on the public host (clean paths)
- **`page_url_example`** — HTML pages on the public host
- **`site_url_prefix`** — legacy prefix; prefer asset/page URL patterns on public hosts

## Multi-domain

- **Primary** — canonical URL in snippets, Visit link, SEO
- **Aliases** — extra hostnames; default **301 → primary** (`alias_redirect_to_primary`)
- **Domains tab → Delivery role** — assign `cms` · `public` · `api_only` (CORS-only)

## Troubleshooting flow

1. Call `get_site_delivery_status`
2. Read `delivery.overall` (green / yellow / red) and failing check codes
3. Fix in admin **Settings → Site delivery** (Hosting, Domains, Redirects) or suggest **Repair delivery**
4. Re-run `run_site_delivery_probe`

Common codes: empty CMS URL, unknown request host, public URL unreachable, domain not registered, snippet missing `data-cms-url`.

## Project settings (`settings/project.json`)

| Key | Purpose |
|-----|---------|
| `render_mode` | `server` · `client` · `auto` (SSR pipeline) |
| `custom_404_enabled` | When `true`, missing HTML-like URLs serve a project page with HTTP **404** |
| `custom_404_path` | Path inside `project/` (default `404.html`) |

Toggle via **Settings → Project → Custom 404 page**, or MCP `cms_admin_action` → `project_site.save_settings` with `custom_404_enabled` and `custom_404_path`. Only applies when Site delivery mode is **In Birkly (project/)**. Redirects, real files, CMS routes, and detail pages still win; the custom page replaces only the final plain `Not found` body. Use root-relative links in the 404 template.

## Automation (P66-D4)

- Detection runs on load; **proposal requires confirm** (Apply suggestion)
- Auto-apply only on localhost / `BIRKLY_TRUST_REQUEST_HOST` safe cases
- Env overrides (`BIRKLY_CMS_BASE_URL`, …) → read-only UI; probes still run

## Docs

Operator guide: Birkly-Docs `07-operations/site-delivery-routing.md` · Docker: `dokploy-hosting.md`
