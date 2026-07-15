# Birkly Reference Projects

Copy-paste starter sites for Birkly Phase 20. Each project demonstrates **live `birkly-client.js` only** — templating and `data-birkly-*` attributes, no hydrate, snapshots, or custom fetch JavaScript.

## Projects

| Project | Plugins | Demonstrates |
|---------|---------|--------------|
| [blog](blog/) | — | Blog list, post detail, site elements |
| [portfolio](portfolio/) | — | Gallery index, project detail pages |
| [yoga](yoga/) | events | `upcoming_events`, class schedule, event detail |
| [coffee](coffee/) | commerce | Product list, variants, `commerce_price_from`, add-to-cart forms |
| [social](social/) | user_management, commerce | Login/register, feed, profile, merch |
| [influencer](influencer/) | events, commerce, user_management | Blog + events + shop + social on one site |

Video walkthrough stubs: each project has `VIDEO.md` with story ID **V-TPL-01** … **V-TPL-06**.

## Quick start

1. Start the CMS dev server from the Birkly repo root:

```bash
php -S localhost:8080 -t . server-router.php
```

2. Install a reference project (copies collections into `content/collections/`):

```bash
chmod +x ./scripts/install-reference-project.sh
./scripts/install-reference-project.sh blog
```

3. Open the site:

```
http://localhost:8080/reference-projects/blog/website/index.html
```

4. For plugin projects, install and activate plugins first:

```bash
./scripts/install-dev-plugins.sh
# Then activate in Admin → Settings → Plugins
./scripts/install-reference-project.sh yoga
```

## Rules (all projects)

- **Client script only:** `<script src="/birkly-client.js"></script>` (CMS URL auto-detected from script origin on same host)
- **No** `<meta name="birkly-baked">`, **no** `birkly-hydrate.js`, **no** snapshot scripts
- **No** custom `fetch()` in site JavaScript — use templating and `data-birkly-*` forms
- Slug/detail pages use `meta[name=birkly-page]`, `meta[name=birkly-collection]`, and optionally `meta[name=birkly-slug]`
- Canonical site paths: `/reference-projects/{name}/website/`

## Structure

```
reference-projects/
├── README.md
├── blog/
│   ├── manifest.json
│   ├── VIDEO.md
│   ├── collections/     # Copied to content/collections/ on install
│   └── website/         # Served as static files from CMS root
└── …
```

## User stories

See [docs/reference-projects/user-stories.md](../docs/reference-projects/user-stories.md) for the full catalog (P20-M3).

## Advanced production path

Hydrate and snapshot builds are **optional** advanced optimizations — not used in reference projects. See [docs/advanced/production-optimization.md](../docs/advanced/production-optimization.md).

## Installing multiple projects

Each install merges collections by slug into `content/collections/`. Projects that share collection slugs (e.g. `blog`, `events`) will overwrite on re-install. Use separate CMS instances or rename collection slugs when combining projects manually.
