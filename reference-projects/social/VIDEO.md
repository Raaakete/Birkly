# V-TPL-05 — Social reference site

**Status:** Stub — record walkthrough when ready.

## Story

Users register and log in via `data-birkly-action` forms, post to a social feed, view profiles, and browse merch with commerce add-to-cart.

## Pages to demo

1. `/reference-projects/social/website/index.html` — feed + create post
2. `/reference-projects/social/website/login.html` — `data-birkly-route="user_auth"`
3. `/reference-projects/social/website/register.html` — registration
4. `/reference-projects/social/website/profile.html?user_id=…` — user profile
5. `/reference-projects/social/website/post.html?slug=…` — post detail

## Talking points

- `user_logged_in` / `user_current` template conditionals
- `data-birkly-url` for public_submit forms
- No custom fetch() — client handles all form posts

## Prerequisites

```bash
./scripts/install-dev-plugins.sh
./scripts/install-reference-project.sh social
# Activate user_management + commerce in Admin
```
