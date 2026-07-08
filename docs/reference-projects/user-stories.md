# Reference project user stories (P20-M3)

Catalog of user stories demonstrated by each Birkly reference project. All stories use **live `birkly-client.js` only** — templating and `data-birkly-*` attributes.

---

## Blog (V-TPL-01)

| ID | As a… | I want to… | So that… | Demo page |
|----|-------|------------|----------|-----------|
| B-01 | Visitor | See a homepage with hero text and recent posts | I understand what the site is about | `index.html` |
| B-02 | Visitor | Browse all published blog posts | I can find content by topic | `blog.html` |
| B-03 | Visitor | Open a post by slug | I can read the full article | `post.html?slug=…` |
| B-04 | Editor | Manage posts in the `blog` collection | Content updates appear on the live site | Admin → Content |

**Collections:** `blog`, `site_elements`  
**Plugins:** none

---

## Portfolio (V-TPL-02)

| ID | As a… | I want to… | So that… | Demo page |
|----|-------|------------|----------|-----------|
| P-01 | Visitor | See a gallery of published projects | I can scan the creator's work | `index.html` |
| P-02 | Visitor | Open a project detail page by slug | I can read the case study | `project.html?slug=…` |
| P-03 | Visitor | See featured projects highlighted | Standout work is visible first | `index.html` (featured flag) |

**Collections:** `projects`, `site_elements`  
**Plugins:** none

---

## Yoga (V-TPL-03)

| ID | As a… | I want to… | So that… | Demo page |
|----|-------|------------|----------|-----------|
| Y-01 | Visitor | See upcoming classes via `upcoming_events` | I know what's on soon | `index.html` |
| Y-02 | Visitor | View the weekly recurring schedule | I can plan my week | `schedule.html` |
| Y-03 | Visitor | Open an event detail and register | I can book a spot | `event.html?slug=…` |

**Collections:** `events`, `classes`, `site_elements`  
**Plugins:** `events`

---

## Coffee (V-TPL-04)

| ID | As a… | I want to… | So that… | Demo page |
|----|-------|------------|----------|-----------|
| C-01 | Shopper | Browse coffee products | I can choose what to buy | `index.html` |
| C-02 | Shopper | See variant options with `commerce_price_from` | I know the price before checkout | `product.html?slug=…` |
| C-03 | Shopper | Add a variant to cart via `data-birkly-route="commerce"` | I can purchase without custom JS | `product.html` |
| C-04 | Shopper | See cart count in the header | I know items are in my session | `index.html`, `product.html` |

**Collections:** `products` (with `variants` sub-collection), `site_elements`  
**Plugins:** `commerce`

---

## Social (V-TPL-05)

| ID | As a… | I want to… | So that… | Demo page |
|----|-------|------------|----------|-----------|
| S-01 | Visitor | Register an account | I can participate | `register.html` |
| S-02 | Member | Log in via `data-birkly-action` | I access my session | `login.html` |
| S-03 | Member | Create a public post | I can share with the community | `index.html` |
| S-04 | Visitor | Read the public feed | I see community activity | `index.html` |
| S-05 | Member | View my profile and posts | I can manage my presence | `profile.html?user_id=…` |
| S-06 | Member | Comment on a post | I can join discussions | `post.html?slug=…` |
| S-07 | Shopper | Buy merch with commerce forms | I can support the community | `index.html` (merch section) |

**Collections:** `social_posts`, `merch`, `site_elements`  
**Plugins:** `user_management`, `commerce`

---

## Influencer (V-TPL-06)

| ID | As a… | I want to… | So that… | Demo page |
|----|-------|------------|----------|-----------|
| I-01 | Fan | See blog, events, shop, and feed on one homepage | I get the full creator experience | `index.html` |
| I-02 | Fan | Read a blog post | I consume long-form content | `post.html` |
| I-03 | Fan | Register for an event | I can attend live sessions | `event.html` |
| I-04 | Fan | Buy coffee from the shop | I support the creator | `product.html` |
| I-05 | Member | Log in and view profile | I have a persistent identity | `login.html`, `profile.html` |

**Collections:** `blog`, `events`, `products`, `social_posts`, `site_elements`  
**Plugins:** `events`, `commerce`, `user_management`

---

## Cross-cutting acceptance

| Rule | All projects |
|------|--------------|
| Client | `<script src="/birkly-client.js" data-cms-url="http://localhost:8080"></script>` only |
| No hydrate / bake / snapshot | Verified by grep audit on `reference-projects/` |
| No custom `fetch()` in site JS | Forms use `data-birkly-*`; client handles API calls |
| Slug pages | `meta[name=birkly-page]` + `meta[name=birkly-collection]` |
| Paths | `/reference-projects/{name}/website/` |

## Install

```bash
./scripts/install-reference-project.sh <name>
```

See [reference-projects/README.md](../../reference-projects/README.md).
