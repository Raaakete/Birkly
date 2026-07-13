# P28 — User journey catalog

**Date:** 2026-07-13 · **Runner:** `scripts/journey-runner.php` (P28-M1)

Each journey runs against a **real CMS instance** with **real files on disk**. Setup steps are executed by the runner before HTTP assertions — not simulated.

### Setup (real projects)

| Journey | Real setup command |
|---------|-------------------|
| J-INSTALL | `build-cms-release.sh` → unzip → `php -S` in extract dir |
| J-BLOG, J-PORTFOLIO | `install-reference-project.sh blog\|portfolio` |
| J-COFFEE, J-YOGA | Above + `install-dev-plugins.sh commerce\|events` (or marketplace install) |
| J-SOCIAL, J-INFLUENCER | `install-reference-project.sh` + user_management plugin |
| J-MARKETPLACE | Fresh CMS tree + marketplace client installs real ZIP to `plugins/` |
| J-KEKABA | kekaba repo + `migrate-kekaba-live-path.sh` + synced `Birkly-0.1/` |

**Video:** Each journey has matching V-BETA-* recording after CI pass — see `P28-human-review-pack.md`.

---

## J-INSTALL — Fresh CMS owner

| Step | Action | Assert |
|------|--------|--------|
| 1 | Extract release zip to temp dir | `START_HERE.html` exists |
| 2 | `php -S` + hit `/admin/login.php` | 200, login form |
| 3 | Create first user (onboarding API) | CSRF + session |
| 4 | Complete wizard (skip AI) | `setup_wizard_completed` |
| 5 | Dashboard loads | No template fatal |
| 6 | Checklist visible | 4 essential items |

**Milestone:** P28-M2 · **Extends:** `tests/onboarding_e2e_test.php`

---

## J-BLOG — Blog visitor (reference-projects/blog)

| Step | Action | Assert |
|------|--------|--------|
| 1 | GET `/reference-projects/blog/website/index.html` | 200, no `{for each` |
| 2 | Body contains hero + post titles | `page_bundle` rendered |
| 3 | GET `blog.html` | Post list |
| 4 | GET `post.html?slug=demo-post-1` | Full article HTML |

**Milestone:** P28-M3 · **Stories:** B-01–B-04

---

## J-PORTFOLIO — Portfolio visitor

| Step | Action | Assert |
|------|--------|--------|
| 1 | GET portfolio index | Featured projects visible |
| 2 | GET `project.html?slug=…` | Case study content |

**Milestone:** P28-M3 · **Stories:** P-01–P-03

---

## J-COFFEE — Shopper (commerce plugin)

| Step | Action | Assert |
|------|--------|--------|
| 1 | Install commerce plugin (dev or fixture) | Plugin active |
| 2 | Install coffee reference collections | Products exist |
| 3 | GET coffee index | Product grid |
| 4 | GET product page | `commerce_price_from` in HTML |
| 5 | POST add-to-cart API | Cart session/count |

**Milestone:** P28-M4 · **Stories:** C-01–C-04

---

## J-YOGA — Events visitor (events plugin)

| Step | Action | Assert |
|------|--------|--------|
| 1 | Install events plugin | Plugin active |
| 2 | GET yoga index | `upcoming_events` rendered |
| 3 | GET schedule.html | Recurring schedule |
| 4 | GET event detail | Event title + register link |

**Milestone:** P28-M4 · **Stories:** Y-01–Y-03

---

## J-SOCIAL — Community member (user_management + commerce)

| Step | Action | Assert |
|------|--------|--------|
| 1 | Register via public API/page | User created |
| 2 | Login | Session cookie |
| 3 | Create post | Entry in feed |
| 4 | GET feed | Post visible |

**Milestone:** P28-M5 · **Stories:** S-01–S-05

---

## J-INFLUENCER — Fan / merch

| Step | Action | Assert |
|------|--------|--------|
| 1 | GET influencer index | Link blocks |
| 2 | Merch section + add to cart | Commerce form works |

**Milestone:** P28-M5 · **Stories:** I-01–I-04

---

## J-ADMIN — Content editor

| Step | Action | Assert |
|------|--------|--------|
| 1 | Admin login | Session |
| 2 | Create draft entry | API 200 |
| 3 | Publish entry | Status published |
| 4 | Public API GET entry | Slug matches |

**Milestone:** P28-M6

---

## J-MARKETPLACE — Plugin install

| Step | Action | Assert |
|------|--------|--------|
| 1 | Fresh CMS without plugins | `plugins/` empty or sample only |
| 2 | Marketplace registry fixture | commerce offer available |
| 3 | Install via CMS marketplace client | Plugin files + active |
| 4 | Commerce admin route loads | 200 |

**Milestone:** P28-M7

---

## J-INVITE — Team invite

| Step | Action | Assert |
|------|--------|--------|
| 1 | Admin creates invitation | Token returned |
| 2 | GET `invite.php?token=…` | Accept form |
| 3 | Complete registration | User in `users/` |
| 4 | Login as invited user | Admin access per role |

**Milestone:** P28-M8

---

## J-KEKABA — Production reference site (live path)

| Step | Action | Assert |
|------|--------|--------|
| 1 | GET kekaba index | **No** `meta name="birkly-baked"` |
| 2 | Script tag canonical `birkly-client.js` | Present |
| 3 | GET blog/service pages | 200, content rendered |
| 4 | `page_bundle` or client render | No raw template tags |
| 5 | CMS embed health | `/Birkly-0.1/api/health.php` 200 |

**Milestone:** P28-M9–M10 · **Repos:** kekaba + Birkly embed sync

---

## J-HEALTH — Operations smoke subset

| Step | Action | Assert |
|------|--------|--------|
| 1 | `production-smoke.sh` | PASS |
| 2 | `api/health.php` writable dirs | settings, content, cache, logs |
| 3 | Marketplace registry ping | 200 or skip flag |
| 4 | Admin login page | 200 |

**Milestone:** P28-M13 · **Runs:** cron + after deploy

---

## J-WEBSITE — Marketing site

| Step | Action | Assert |
|------|--------|--------|
| 1 | Build/serve Birkly-Website static | 200 |
| 2 | Waitlist CTA link | Points to `birkly.com/#waitlist` |
| 3 | Legal links | ToS + Privacy resolve |

**Milestone:** P28-M16 · **Repo:** Birkly-Website

---

## Reporting

Each run writes `logs/journey-report.json`:

```json
{
  "run_at": "2026-07-13T06:00:00Z",
  "base_url": "http://127.0.0.1:8080",
  "journeys": [
    { "id": "J-BLOG", "result": "pass", "duration_ms": 420 }
  ]
}
```

CI fails if any journey `result !== "pass"`.
