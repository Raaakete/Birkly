# Phase 16, 17, 18 & 19 — Combined lead-engineer handoff (single wave)

**Status:** Approved (2026-07-07, Keven) — **single-wave execution** P16→P17→P18→P19 without pause  
**Baseline:** P15 complete — Birkly **121 test suites** · Marketplace **15 test suites**  
**Human rule:** Execute **all P16 + P17 + P18 + P19 milestones** autonomously in one wave. Notify human **once** at **P19-M8 closure** only.

**Master plan:** `docs/reviews/P16-P19-complete-plan.md`  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 16 · § Phase 17 · § Phase 18 · § Phase 19  
**Matrices:** `P16-verification-matrix.md` · `P17-verification-matrix.md` · `P18-verification-matrix.md` · `P19-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 16 + 17 + 18 + 19 — single combined wave.

Human approved P16+P17+P18+P19 to be developed IN ONE GO (2026-07-07). Execute P16-M0→M8, then P17-M0→M7, then P18-M0→M8, then P19-M0→M8 without waiting for human between phases. Notify human ONCE at P19-M8 closure only.

Read first:
- docs/reviews/P16-P19-lead-engineer-handoff.md (this file)
- docs/reviews/P16-P19-complete-plan.md
- START_HERE.html (0_START-HERE.html) · admin/js/onboarding.js · admin/templates/onboarding.html
- docs/onboarding-plan.md · docs/testing-onboarding.md
- docs/reviews/P5.6-admin-ai-onboarding-automation-plan.md (AI optional in wizard ONLY)
- scripts/cms-release-manifest.json · scripts/build-cms-release.sh
- docs/reviews/P13-deferred-todos.md (CSP TODO)
- docs/client-sites/kekaba-deploy-checklist.md
- Birkly-Marketplace/docs/PRODUCTION-RUNBOOK.md
- plugins/commerce/ · docs/roadmap/ROADMAP.md § Phase 16–19
- docs/PROJECT_VALUES.md

## Context

P13–P15 COMPLETE. Alpha platform feature-complete. Human still owns live DNS, Stripe live keys, legal review, kekaba live deploy — agent delivers playbooks and scripts.

ONE WAVE DELIVERS:

### Phase 16 — Beta go-live & production operations
- P16-M0: Go-live gap register, prod secrets threat model
- P16-M1: Per-repo production deploy playbooks
- P16-M2: Stripe live config guide + env templates
- P16-M3: Cloud SSO production endpoint docs
- P16-M4: Scheduled smoke scripts + digest cadence
- P16-M5: CSP nonce pass (admin) — close P13 CSP TODO
- P16-M6: kekaba live deploy assist + P15 content drift fix
- P16-M7: Open-beta website/admin copy
- P16-M8: Docs (NO notification — continue)

### Phase 17 — Commerce pro & fulfillment depth
- P17-M0: Commerce pro gap register
- P17-M1: Multi-warehouse routing v1
- P17-M2: Display currency + manual FX rate table (no live FX API)
- P17-M3: Amazon + eBay import adapters
- P17-M4: Bundle admin UX + shipping zones v2
- P17-M5: Post-purchase orchestration idempotency audit
- P17-M6: Docs · P17-M7: matrix (NO notification)

### Phase 18 — Ecosystem scale & developer experience
- P18-M0: Ecosystem gap register
- P18-M1: Self-serve provider onboarding (QC required)
- P18-M2: Review helpful votes + moderation UX
- P18-M3: Plugin health checks in CMS admin
- P18-M4: Theme/template_pack marketplace install in CMS
- P18-M5: Sub-entry activity stream
- P18-M6: Spaces v1 config stub
- P18-M7: i18n foundation + NPM docs go/no-go doc
- P18-M8: Docs + matrix (NO notification)

### Phase 19 — First-run & onboarding excellence (HUMAN PRIORITY)
- P19-M0: Onboarding gap register + first-run UX audit
- P19-M1: START_HERE.html in release zip; README "open this first"
- P19-M2: START_HERE v2 — right folder detection, unzip guidance, local vs cloud paths
- P19-M3: Install path router (?install=local|cloud) into admin onboarding
- P19-M4: Onboarding wizard v2 — use-case picker, resume state, marketplace hint
- P19-M5: Optional AI mini-wizard in onboarding (BYOAI inline: provider → key → test → role); SKIP always visible; NEVER add AI to dashboard required checklist (P5.6-D)
- P19-M6: Dashboard checklist v2 — essential only; AI item only if user skipped wizard AI step
- P19-M7: onboarding E2E tests + Birkly-Docs getting-started overhaul
- P19-M8: All matrices green, P16-P19 closure checklist, SINGLE human notification

## Locked decisions (do not re-ask human)

P16-D1: Agent delivers playbooks; human runs live deploy
P16-D2: Open beta = invite/waitlist, not public flood
P16-D3: No Sentry/npm in CMS
P16-D4: CSP nonce admin pass
P16-D5: NO separate P16 notification

P17-D1: Multi-warehouse allocate first with stock
P17-D2: Display currency via manual rate table; charge currency unchanged
P17-D3: Amazon/eBay adapters mocked in CI
P17-D4: Bundle/shipping polish only
P17-D5: NO separate P17 notification

P18-D1: Self-serve providers still require QC
P18-D2: Review helpful votes v1
P18-D3: Plugin health checks in admin
P18-D4: Theme install via marketplace client
P18-D5: Spaces stub only
P18-D6: i18n foundation English only
P18-D7: NPM docs go/no-go doc only
P18-D8: NO separate P18 notification

P19-D1: START_HERE.html is first file (rename from 0_START-HERE.html + redirect)
P19-D2: Local vs cloud/FTP paths on START_HERE → onboarding deep link
P19-D3: Wizard v2 with use-case + resume + marketplace hint
P19-D4: AI optional IN ONBOARDING ONLY — full BYOAI mini-wizard; NOT in dashboard essential checklist
P19-D5: Dashboard checklist essential steps only
P19-D6: SINGLE notification at P19-M8

P16-P19-D-EXEC: Single wave; one closure email at P19-M8

## Execution order (strict dependency)

P16-M0 → M1 → M2 → M3 → M4 → M5 ∥ M6 → M7 → M8
  → P17-M0 → M1 → M2 → M3 → M4 → M5 → M6 → M7
  → P18-M0 → M1 ∥ M2 (Marketplace) + M3 ∥ M4 (Birkly) → M5 → M6 → M7 → M8
  → P19-M0
  → P19-M1 → M2 (needs P16-M1 playbooks for cloud copy)
  → P19-M3 → M4 → M5 → M6 → M7
  → P19-M8 (closure — notify human ONCE)

P17 and P18 may overlap after P16-M2. P19-M5 can start after P19-M4 shell exists.

Branches:
- Birkly: cursor/p16-m[N]-<slug>-030a · cursor/p17-m[N]-<slug>-030a · cursor/p18-m[N]-<slug>-030a · cursor/p19-m[N]-<slug>-030a
- Birkly-Marketplace: cursor/p16-m[N]-* · cursor/p18-m[N]-*
- kekaba: cursor/p16-m6-*
- Birkly-Website: cursor/p16-m7-* · cursor/p19-m7-* (docs)
- Birkly-Docs: cursor/p16-m8-* through p19-m7-*

## Delegate

- cms-developer: P16 smoke/CSP/cloud SSO, P17 commerce, P18 plugin health/spaces/activity, P19 onboarding API + AI step
- cms-developer: Marketplace P16-M4, P18-M1/M2
- frontend-developer: P19 START_HERE v2, onboarding wizard v2, dashboard checklist, P16 open-beta copy
- frontend-developer: P17 commerce admin UI, P18 theme install UI
- docs-writer: P16 playbooks, P17–P19 docs, getting-started overhaul
- qa-verifier: All four matrices; onboarding E2E; release manifest includes START_HERE
- design-reviewer: P19 START_HERE + onboarding wizard (priority)

## Verification gates (before P19-M8)

- [ ] docs/reviews/P16-verification-matrix.md — all rows pass
- [ ] docs/reviews/P17-verification-matrix.md — all rows pass
- [ ] docs/reviews/P18-verification-matrix.md — all rows pass
- [ ] docs/reviews/P19-verification-matrix.md — all rows pass
- [ ] php tests/run.php — Birkly green
- [ ] php tests/run.php — Birkly-Marketplace green
- [ ] ./scripts/ci-check.sh — Birkly green
- [ ] ./scripts/build-cms-release.sh && unzip -l contains START_HERE.html
- [ ] docs/testing-onboarding.md checklist passes
- [ ] docs/reviews/P16-P19-phase-closure-checklist.md created
- [ ] Roadmap § Phase 16–19 marked complete

Run php tests/run.php on Birkly AND Birkly-Marketplace before every merge.
Merge PRs yourself after qa-verifier pass (and design-reviewer when UI changes).

Human still owns (document in P19-M8 closure):
- Production DNS/TLS
- Stripe live keys + price IDs
- cloud.birkly.io OAuth production
- Legal review on live hosts
- kekaba live deploy execution
- M1-T8 Resend digest email · M1-T12 branch protection
```

---

## Combined milestone checklist

| Phase | ID | Deliverable | Repo |
|-------|-----|-------------|------|
| P16 | M0–M8 | Go-live ops, smoke, CSP, beta copy | All |
| P17 | M0–M7 | Multi-warehouse, FX display, Amazon/eBay, bundles | Birkly |
| P18 | M0–M8 | Self-serve providers, reviews, plugin health, themes | Birkly + Marketplace |
| P19 | M0 | Onboarding gap + UX audit | Birkly |
| P19 | M1 | START_HERE in release zip | Birkly |
| P19 | M2 | START_HERE v2 folder/unzip guidance | Birkly |
| P19 | M3 | Install path router | Birkly |
| P19 | M4 | Onboarding wizard v2 | Birkly |
| P19 | M5 | **Optional AI mini-wizard** | Birkly |
| P19 | M6 | Dashboard checklist v2 | Birkly |
| P19 | M7 | E2E tests + docs | Birkly + Birkly-Docs |
| P19 | M8 | **Closure + single human notification** | All |

---

## Risk notes (single-wave)

| Risk | Mitigation |
|------|------------|
| P19 scope vs commerce phases | P19 is last; lead prioritizes P19-M5 AI step quality |
| START_HERE not in zip today | P19-M1 first; `release_manifest_test.php` |
| AI forced on users | P19-D4 + P5.6-D: skip + no dashboard AI requirement |
| kekaba P15 drift | P16-M6 explicit sync task |
| Large wave | One milestone per PR; merge frequently |
