# P34 — Human review pack (Birkly Ops & platform production)

**Date:** 2026-07-23  
**Status:** Ready for Keven review (M22)  
**Plan:** `docs/reviews/P34-birkly-ops-platform-plan.md`  
**Matrix:** `docs/reviews/P34-verification-matrix.md`

---

## Executive summary

Phase 34 splits the Birkly Marketplace app across three production surfaces on one VPS (Dokploy):

| Surface | URL | Role |
|---------|-----|------|
| **Birkly Ops** | https://ops.birkly.cloud | Staff admin, feedback inbox, install registry, CMS releases |
| **Provider Studio** | https://providers.birkly.cloud | Seller signup, offers, Stripe Connect |
| **Public marketplace** | https://marketplace.birkly.cloud | Catalog, registry API, buyer checkout, webhooks |

The CMS default marketplace URL is now `https://marketplace.birkly.cloud`. Customer feedback can relay to Ops when `settings/platform.json` is configured.

---

## Automated verification (green)

Run on any machine with `curl`:

```bash
# From Birkly-Marketplace repo
./scripts/production-smoke.sh
```

Expected: `RESULT: PASS` for all three surfaces.

CMS + marketplace cross-check (optional):

```bash
# From Birkly repo — skip CMS if no local instance
BIRKLY_SMOKE_SKIP_MARKETPLACE=0 ./scripts/production-smoke.sh https://your-cms.example.com
```

Unit tests:

```bash
cd Birkly-Marketplace && php tests/run.php          # 19 suites
cd Birkly && php tests/platform_test.php            # relay + triage gate
cd Birkly && php tests/platform_ops_test.php
cd Birkly && php tests/marketplace_client_test.php
```

---

## What was deployed (Keven + agents)

- [x] DNS A records for ops, providers, marketplace → VPS
- [x] Dokploy app with Dockerfile, three domains, Volume Mount data/logs
- [x] Ops admin login with your email
- [x] Official catalog visible on marketplace
- [x] Surface isolation (ops cannot serve catalog; marketplace cannot serve /admin)
- [x] CMS code: production URL, Ops relay client, triage gate when relay configured
- [x] Provider Studio branding on providers host

---

## Manual checks (recommended)

### Ops staff (`ops.birkly.cloud`)

1. Log in at `/admin/login`
2. Open **Feedback** — inbox loads (empty until CMS relays)
3. Open **Installations** — list loads
4. Open **Releases** — channel UI loads
5. Open **QC** — queue UI loads

### Provider Studio (`providers.birkly.cloud`)

1. `/provider/signup` — form loads
2. Header shows **Birkly Provider Studio** and **Browse marketplace** link
3. `/` redirects to login or dashboard

### Public marketplace (`marketplace.birkly.cloud`)

1. Homepage + `/catalog` — offers listed
2. `/api/v1/registry` — JSON with `api_version: v1`
3. Offer detail page opens from catalog

### CMS install (when you have a test site)

1. **Plugins → Marketplace** — browses production registry
2. Install a **free official** plugin (e.g. Hello Starter)
3. Copy `docs/reviews/platform.json.example` → `settings/platform.json` on a customer install; set `feedback_relay_secret` to match Ops `OPS_FEEDBACK_HMAC_SECRET`
4. Submit feedback from floating widget — appears in Ops `/admin/feedback`

---

## Pending human decisions

| Item | When | Action |
|------|------|--------|
| **Stripe live keys** | Before paid checkout | Dokploy env + webhook `https://marketplace.birkly.cloud/webhooks/stripe` — see `Birkly-Marketplace/docs/DOKPLOY-DEPLOY.md` |
| **GitHub App webhook** | Before provider auto-releases | `https://marketplace.birkly.cloud/webhooks/github` |
| **CMS E2E install** | M11 closure | Install free plugin from a real CMS site against production registry |
| **P28 / P26 resume** | After M22 approval | Roadmap unblocks beta journeys + Studio creative |

---

## Known limitations

- Stripe checkout and Connect onboarding require live/test keys (not configured on VPS yet).
- CMS release channel on Ops has API + admin UI; no CMS release ZIP uploaded yet.
- `marketplace.birkly.io` legacy URLs may still appear in older docs — production is `*.birkly.cloud`.

---

## Sign-off

| Check | Reviewer | Date | Notes |
|-------|----------|------|-------|
| Three surfaces live | Keven | | |
| Ops admin usable | Keven | | |
| Marketplace catalog OK | Keven | | |
| Approve P34 closure | Keven | | |

**Approve** → agents resume P28/P26 per roadmap.  
**Changes requested** → note in GitHub issue or reply to agent thread.
