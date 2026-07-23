# P35 — Gap register (Marketplace & Ops excellence)

**Status:** Open — M0 seeded; Wave 1–2 in progress (2026-07-23)  
**Plan:** `docs/reviews/P35-marketplace-ops-excellence-plan.md`

---

## Gaps from P34 carry-over

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G35-01 | Stripe live keys + webhooks not on production | high | M31–M32 |
| G35-02 | CMS E2E install from production registry not verified | medium | M28–M29 |
| G35-03 | P34 M22 formal sign-off pending | low | M0 (close P34 in parallel) |

---

## UI / UX

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G35-04 | Marketplace uses custom dark theme, not Birkly admin shell | **blocker** | M1–M6 |
| G35-05 | Provider dashboard/offer forms inconsistent with CMS form patterns | high | M4–M5 |
| G35-06 | No responsive pass on Provider Studio tablet view | medium | M6 |

---

## Ops admin & fees

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G35-07 | Per-provider fee form nested inside table (`<form>` in `<form>` context) — unreliable saves | **blocker** | M7 |
| G35-08 | No `/admin/users` — only env bootstrap admin | **blocker** | M8–M10 |
| G35-09 | Production relies on `MARKETPLACE_ADMIN_PASSWORD` env | high | M10 |

---

## Data & security

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G35-10 | Orders, licenses, providers in JSON files — no transactions | **blocker** | M11–M12 |
| G35-11 | No CSRF on admin/provider POST forms | high | M13 |
| G35-12 | No rate limiting on auth/checkout APIs | medium | M13 |
| G35-13 | No `docs/SECURITY.md` threat model for marketplace | low | M13 |

---

## Plugin upload & permissions

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G35-14 | Security scan false-positive on `base64_decode(` (commerce CredentialStore) | **blocker** | M15 |
| G35-15 | Providers upload raw folders instead of `build-plugin-zip.sh` output | high | M18 |
| G35-16 | `plugin.json` permissions not shown to buyer before install | **blocker** | M16–M17 |
| G35-17 | `data_access` manifest field does not exist in schema | high | M16 |
| G35-18 | QC does not compare manifest vs code hooks | medium | M16 |

---

## Catalog & offer pages

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G35-19 | Dedicated SSR `/catalog` redundant vs API + website | medium | M19–M21 |
| G35-20 | Offer pages plain text — no provider page builder | **blocker** | M23–M25 |
| G35-21 | CMS modal builds minimal inline HTML, not rich offer page | high | M26–M27 |
| G35-22 | Birkly-Website has no marketplace API integration | high | M22 |

---

## Ecosystem cutover

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G35-23 | Official plugins still primary in Birkly `plugins/` dev tree | **blocker** | M28–M30 |
| G35-24 | Paid official tiers not E2E tested | high | M31–M32 |
| G35-25 | Studio plugin not on marketplace catalog | medium | M28 |

---

## Keven-reported (2026-07-23)

| ID | Report | Maps to |
|----|--------|---------|
| G35-R1 | Cannot set different fees per provider | G35-07 |
| G35-R2 | Commerce/automation upload security errors | G35-14, G35-15 |
| G35-R3 | Need ops admin user management | G35-08 |
| G35-R4 | Catalog should be API for website, not dedicated page | G35-19, G35-22 |
| G35-R5 | Rich offer pages + CMS modal | G35-20, G35-21 |
| G35-R6 | Accounts/payments need database + security | G35-10, G35-11 |
| G35-R7 | Test paid offers | G35-01, G35-24 |
| G35-R8 | Move official plugins to marketplace ecosystem | G35-23 |
