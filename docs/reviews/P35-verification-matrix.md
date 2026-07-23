# P35 — Verification matrix (Marketplace & Ops excellence)

**Status:** M28–M33 complete — 2026-07-23  
**Plan:** `docs/reviews/P35-marketplace-ops-excellence-plan.md`  
**Human pack:** `docs/reviews/P35-human-review-pack.md`

---

## UI shell

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| UI-01 | Ops admin uses Birkly-style sidebar + topbar | in_progress | P35-M1–M3 branch |
| UI-02 | Provider Studio uses same shell family | in_progress | P35-M4 branch |
| UI-03 | Cards, tables, buttons match CMS design tokens | in_progress | marketplace-shell.css bridges |
| UI-04 | Responsive at 1280px / tablet drawer | pending | |

---

## Ops admin users & fees

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| ADM-01 | Create second ops admin via /admin/users | in_progress | /admin/users CRUD |
| ADM-02 | Moderator role cannot access releases upload | in_progress | releases gated admin-only |
| ADM-03 | Per-provider fee 10% saves and applies to checkout | pass | `stripe_checkout_e2e_test.php` · `provider_growth_test.php` |
| ADM-04 | Fee change appears in audit log | pass | `marketplace_set_provider_fee_tier` audit |
| ADM-05 | Env default password rejected when admins exist | in_progress | M10 guard in admin_auth |

---

## Database & security

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| DB-01 | SQLite migration imports existing JSON data | pending | |
| DB-02 | Order + license write is transactional | pending | |
| DB-03 | CSRF blocks forged admin POST | pending | |
| DB-04 | Rate limit on /admin/login | pending | |
| SEC-01 | docs/SECURITY.md published | pending | |

---

## Upload & permissions

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| UPL-01 | build-plugin-zip commerce artifact passes QC | pending | |
| UPL-02 | build-plugin-zip automation artifact passes QC | pending | |
| UPL-03 | Raw folder zip shows clear preflight errors | pending | |
| PERM-01 | plugin.json data_access in manifest schema | pending | |
| PERM-02 | CMS install shows permission consent | pending | |
| PERM-03 | Install blocked until user accepts | pending | |

---

## API & offer pages

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| API-01 | GET /api/v1/offers/{slug} returns page + reviews | pending | |
| API-02 | embed=1 returns chromeless offer page | pending | |
| API-03 | Birkly-Website renders catalog from API | pending | |
| API-04 | CMS modal loads embed offer page | pending | |
| API-05 | Provider can publish offer page from editor | pending | |

---

## Ecosystem & payments

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| ECO-01 | commerce installable from marketplace only | pass | `official_plugins_cutover_test.php` · seed offers |
| ECO-02 | install-dev-plugins.sh uses registry | pass | `scripts/install-dev-plugins.sh` · `MARKETPLACE_URL` |
| ECO-03 | studio published on marketplace | pass | `birkly-studio` seed offer + package |
| ECO-04 | P10 coupling register ecosystem complete | pass | `P10-plugin-coupling-register.md` § P35-M30 |
| PAY-01 | Stripe test checkout E2E in CI | pass | `stripe_checkout_e2e_test.php` |
| PAY-02 | Live paid smoke (human) | pending | `DOKPLOY-DEPLOY.md` § 4b — needs Stripe keys |
| PAY-03 | Subscription license activates | pass | `stripe_checkout_e2e_test.php` · `marketplace_subscription_license_test.php` |

---

## Regression (P34)

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| REG-01 | Three-surface isolation still holds | pass | `surface_test.php` |
| REG-02 | production-smoke.sh PASS | pending | Run against production |
| REG-03 | Feedback relay still works | pass | `platform_test.php` (Birkly) |

---

## Human review (M33)

| ID | Artifact | Status |
|----|----------|--------|
| HR-01 | P35 human review pack | pass | `P35-human-review-pack.md` |
| HR-02 | Keven UI walkthrough Ops + Provider | pending | Earlier waves M1–M6 |
| HR-03 | Paid checkout demo | pending | Requires Stripe live/test keys on VPS |

---

## Milestone closure (M28–M33)

| ID | Focus | Status |
|----|-------|--------|
| P35-M28 | Publish official plugins from Birkly provider | **pass** |
| P35-M29 | CMS dev/CI marketplace-only install path | **pass** |
| P35-M30 | Coupling register + docs ecosystem cutover | **pass** |
| P35-M31 | Stripe test-mode paid checkout E2E | **pass** |
| P35-M32 | Production paid smoke runbook | **pass** (doc; human execution pending) |
| P35-M33 | Verification matrix + human review pack | **pass** |
