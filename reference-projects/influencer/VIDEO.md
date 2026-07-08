# V-TPL-06 — Influencer reference site

**Status:** Stub — record walkthrough when ready.

## Story

A creator runs one site combining blog posts, yoga events, coffee merch, and a social feed — all plugins active, live client only.

## Pages to demo

1. `/reference-projects/influencer/website/index.html` — unified homepage
2. `/reference-projects/influencer/website/post.html?slug=welcome-to-birkly`
3. `/reference-projects/influencer/website/event.html?slug=sunrise-vinyasa`
4. `/reference-projects/influencer/website/product.html?slug=ethiopian-single-origin`
5. `/reference-projects/influencer/website/login.html`

## Talking points

- Single `birkly-client.js` handles blog loops, `upcoming_events`, commerce cart, and user auth
- No hydrate or snapshot pipeline required
- Canonical path pattern for multi-section creator sites

## Prerequisites

```bash
./scripts/install-dev-plugins.sh
./scripts/install-reference-project.sh influencer
# Activate events, commerce, user_management in Admin
```
