# P35 — Verification matrix (Marketplace & Ops excellence)

**Status:** Complete — 2026-07-23 (M33 closure)  
**Plan:** `docs/reviews/P35-marketplace-ops-excellence-plan.md`  
**Human pack:** `docs/reviews/P35-human-review-pack.md`

---

## UI shell

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| UI-01 | Ops admin uses Birkly-style sidebar + topbar | pass | PR #21 · `views/shared/admin-shell-*.php` |
| UI-02 | Provider Studio uses same shell family | pass | PR #21 · provider `_header.php` |
| UI-03 | Cards, tables, buttons match CMS design tokens | pass | `birkly-admin.css` + `marketplace-shell.css` |
| UI-04 | Responsive at 1280px / tablet drawer | pass | `admin-shell.js` + sidebar.css |

---

## Ops admin users & fees

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| ADM-01 | Create second ops admin via /admin/users | pass | `/admin/users` · `admin_users_test.php` |
| ADM-02 | Moderator role cannot access releases upload | pass | `marketplace_admin_can_manage_releases()` |
| ADM-03 | Per-provider fee 10% saves and applies to checkout | pass | `stripe_checkout_e2e_test.php` |
| ADM-04 | Fee change appears in audit log | pass | `marketplace_set_provider_fee_tier` audit |
| ADM-05 | Env default password rejected when admins exist | pass | `admin_auth.php` · `MARKETPLACE_ALLOW_DEFAULT_ADMIN` |

---

## Database & security

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| DB-01 | SQLite migration imports existing JSON data | pass | PR #22 · `migrate-json-to-sqlite.php` |
| DB-02 | Order + license write is transactional | pass | `database.php` · repository layer |
| DB-03 | CSRF blocks forged admin POST | pass | `csrf.php` · admin routes |
| DB-04 | Rate limit on /admin/login | pass | `rate_limit.php` · `rate_limit_test.php` |
| SEC-01 | docs/SECURITY.md published | pass | `Birkly-Marketplace/docs/SECURITY.md` |

---

## Upload & permissions

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| UPL-01 | build-plugin-zip commerce artifact passes QC | pass | `permission_manifest_test.php` · scan v2 |
| UPL-02 | build-plugin-zip automation artifact passes QC | pass | official seed packages |
| UPL-03 | Raw folder zip shows clear preflight errors | pass | `artifact.php` preflight |
| PERM-01 | plugin.json data_access in manifest schema | pass | `offer-manifest.schema.json` |
| PERM-02 | CMS install shows permission consent | pass | PR #206 · `consent.php` |
| PERM-03 | Install blocked until user accepts | pass | `marketplace_consent_test.php` |

---

## API & offer pages

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| API-01 | GET /api/v1/offers/{slug} returns page + reviews | pass | PR #24 · `api_catalog_test.php` |
| API-02 | embed=1 returns chromeless offer page | pass | `offer_embed.php` · `?embed=1` |
| API-03 | Birkly-Website renders catalog from API | pass | PR #11 · `marketplace.html` |
| API-04 | CMS modal loads embed offer page | pass | PR #207 · `plugins-page.js` |
| API-05 | Provider can publish offer page from editor | pass | `/provider/offers/{slug}/page` |

---

## Ecosystem & payments

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| ECO-01 | commerce installable from marketplace only | pass | `official_plugins_cutover_test.php` |
| ECO-02 | install-dev-plugins.sh uses registry | pass | `install-dev-plugins.sh` |
| ECO-03 | studio published on marketplace | pass | `birkly-studio` seed offer |
| ECO-04 | P10 coupling register ecosystem complete | pass | `P10-plugin-coupling-register.md` |
| PAY-01 | Stripe test checkout E2E in CI | pass | `stripe_checkout_e2e_test.php` |
| PAY-02 | Live paid smoke (human) | pending | `DOKPLOY-DEPLOY.md` § 4b — needs Stripe keys |
| PAY-03 | Subscription license activates | pass | `stripe_checkout_e2e_test.php` |

---

## Regression (P34)

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| REG-01 | Three-surface isolation still holds | pass | `surface_test.php` |
| REG-02 | production-smoke.sh PASS | pending | Run on Dokploy after deploy |
| REG-03 | Feedback relay still works | pass | zero-config relay (P34) |

---

## Human review (M33)

| ID | Artifact | Status |
|----|----------|--------|
| HR-01 | P35 human review pack | pass | `P35-human-review-pack.md` |
| HR-02 | Keven UI walkthrough Ops + Provider | pending | After Dokploy redeploy |
| HR-03 | Paid checkout demo | pending | Stripe keys on VPS |

---

## Milestone closure (M0–M33)

| ID | Focus | Status |
|----|-------|--------|
| P35-M0 | Gap register + matrix | **pass** |
| P35-M1–M6 | Admin UI shell | **pass** |
| P35-M7–M10 | Ops fixes + admin users | **pass** |
| P35-M11–M14 | SQLite + security | **pass** |
| P35-M15–M18 | Upload + permissions | **pass** |
| P35-M19–M27 | API + offer pages + CMS embed | **pass** |
| P35-M28–M30 | Ecosystem cutover | **pass** |
| P35-M31–M32 | Stripe E2E + live smoke docs | **pass** |
| P35-M33 | Verification matrix + review pack | **pass** |
