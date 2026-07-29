# Reference projects

Six copy-paste starter sites ship with the Birkly CMS. Each demonstrates real templating patterns with **live `birkly-client.js` only** — no hydrate, snapshots, or custom `fetch()` in site JavaScript.

## Summary

**Reference projects** are ready-made HTML sites (blog, portfolio, yoga studio, coffee shop, social network, influencer hub) bundled in the Birkly repo under `reference-projects/`. Run `scripts/install-reference-project.sh <name>` from the Birkly root to copy collections into your CMS, then open the site at `/reference-projects/<name>/website/`. Every project uses the default live client script; they are the recommended starting point before you build your own templates.

## Beginner

**What are they?**

Think of reference projects as **starter kits**: each one is a small website with sample content and HTML templates already wired to Birkly. You can browse them to see how loops, conditionals, commerce forms, and login pages work — then copy ideas into your own site.

**The six projects**

| Project | Good for | Needs plugins? |
|---------|----------|----------------|
| **blog** | Homepage, post list, article pages | No |
| **portfolio** | Project gallery and case-study detail | No |
| **yoga** | Class schedule and event registration | Events |
| **coffee** | Product grid, variants, add to cart | Commerce |
| **social** | Login, feed, profile, comments, merch | User Management + Commerce |
| **influencer** | Blog + events + shop + social on one site | Events + Commerce + User Management |

**How to try one**

1. Start the Birkly dev server from the Birkly folder (see [Getting started](../00-overview/getting-started.md)).
2. Install a project (replace `blog` with any name from the table):

```bash
chmod +x ./scripts/install-reference-project.sh
./scripts/install-reference-project.sh blog
```

3. Open the site in your browser, for example:

```
http://localhost:8080/reference-projects/blog/website/index.html
```

Swap `blog` for `portfolio`, `yoga`, `coffee`, `social`, or `influencer` in both the install command and the URL.

4. For **yoga**, **coffee**, **social**, or **influencer**, install and activate the listed plugins first (**Admin → Plugins**), then run the install script.

**What you will not see**

Reference projects intentionally **do not** use advanced production tricks (pre-baked HTML, hydrate scripts, or snapshot files). That keeps them easy to learn and copy. If you need those techniques later, see [Production optimization](production-optimization.md).

## Advanced Users

**Source layout (Birkly repo)**

```
reference-projects/
├── blog/
│   ├── manifest.json       # optional plugins list
│   ├── collections/        # copied → content/collections/ on install
│   └── website/            # static HTML served from CMS root
├── portfolio/
├── yoga/
├── coffee/
├── social/
└── influencer/
```

**Install script**

`scripts/install-reference-project.sh <project-name>`:

- Copies `reference-projects/<name>/collections/*` → `content/collections/`
- Prints required plugins from `manifest.json` when present
- Website files stay in place under `reference-projects/<name>/website/`

**Canonical URLs (local dev)**

| Project | Index URL |
|---------|-----------|
| blog | `/reference-projects/blog/website/index.html` |
| portfolio | `/reference-projects/portfolio/website/index.html` |
| yoga | `/reference-projects/yoga/website/index.html` |
| coffee | `/reference-projects/coffee/website/index.html` |
| social | `/reference-projects/social/website/index.html` |
| influencer | `/reference-projects/influencer/website/index.html` |

**Client contract (all projects)**

```html
<script src="/birkly-client.js" data-cms-url="http://localhost:8080"></script>
```

- Templating + `data-birkly-*` attributes for forms, auth, commerce
- Slug pages: `meta[name=birkly-page]`, `meta[name=birkly-collection]`, optional `meta[name=birkly-slug]`
- **No** `birkly-hydrate.js`, **no** `<meta name="birkly-baked">`, **no** snapshot globals, **no** custom `fetch()` in site JS

**Combining projects**

Install merges collections by slug into `content/collections/`. Re-installing overwrites same-slug collections (`blog`, `events`, etc.). Use separate CMS instances or rename slugs when combining manually.

**Upstream catalog**

User-story IDs (V-TPL-01 … V-TPL-06) and per-page acceptance criteria live in the Birkly repo: `docs/reference-projects/user-stories.md` and each project's `VIDEO.md`.

## Marketing site (Birkly-Website)

The public marketing site is **not** one of the six `reference-projects/` starters, but it follows the same Light DOM rule for chrome:

- Header/footer markup lives in each HTML page inside `<birkly-header>` / `<birkly-footer>`.
- Site JS (`js/components.js`) is **enhance-only** — it never assigns `innerHTML` on those hosts (that would wipe CMS templates).
- Active nav uses platform `birkly-components.js` → `BirklyComponents.setActiveNav`.

Prefer `<birkly-region collection="site_elements" entry="header">` when chrome is CMS-authored; use the enhance-only pattern when chrome is intentionally static (logo + hardcoded nav), as on Birkly-Website.

## Related

- [Templating overview](templating-overview.md)
- [Client library](../04-integration/client-library.md)
- [Storefront and events patterns](storefront-patterns.md)
- [Production optimization](production-optimization.md) — optional hydrate/snapshot/bake (not used in reference projects)
