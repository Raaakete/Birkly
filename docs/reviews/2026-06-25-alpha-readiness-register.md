# Alpha readiness register — 2026-06-25

Findings for **Phase 3 closed alpha** gate (P3-M1). Severity: **P0** blocker · **P1** high · **P2** medium · **P3** low.

| ID | Type | Area | Finding | Severity | Phase 3 |
|----|------|------|---------|----------|---------|
| P3-A01 | ops | kekaba prod | Synced `Birkly-0.1/` (post PR #30) merged in repo (kekaba #3) but **not deployed** to kekaba.com host. Production still on pre-hotfix embed until human deploy. | P0 | P3-M1-T1 |
| P3-A02 | ops | Agent infra | M1-T8 Resend secrets not configured — daily digest writes file only, no email. | P1 | P3-M1-T2 |
| P3-A03 | ops | Agent infra | M1-T12 branch protection not enabled — `CI` not required on `main`. | P2 | P3-M1-T2 |
| P3-A04 | feature | Feedback | No in-app alpha feedback path; testers cannot report bugs from admin UI. | P1 | P3-M3 |
| P3-A05 | feature | Help | Admin Help is stub/scattered; not wired to Birkly-Docs SSOT. | P1 | P3-M4 |
| P3-A06 | ops | Updates | Update system (`api/updates.php`) not covered by automated E2E smoke in CI. | P1 | P3-M2-T1 |
| P3-A07 | ops | Backup | Backup create/restore path not documented with runnable smoke on cloud VM. | P1 | P3-M2-T2 |
| P3-A08 | security | Session | Session ID regeneration on login not verified in test harness (CLOSED_ALPHA item; may already be fixed — needs audit). | P2 | P3-M2-T3 |
| P3-A09 | content | kekaba | No new alpha-scoped pages/collections since Phase 2 deferral; production content gap for alpha story. | P2 | P3-M6 |
| P3-A10 | content | Birkly-Website | Beta site lacks closed-alpha messaging and updated legal copy for feedback data practices. | P2 | P3-M7 |
| P3-A11 | docs | Birkly-Docs | No `closed-alpha-guide.md` or dedicated ContentEditor doc page for testers. | P2 | **Deferred → Phase 4** |
| P3-A12 | ux | ContentEditor | Block system not started. | P3 | **Deferred → Phase 4** |
| P3-A13 | feature | BYOAI | AI permissions prompt-only; not enforced at execution — security gap. | P0 | P3-M2 |
| P3-A14 | feature | BYOAI | No curated Birkly rules/context pack; full docs dump is expensive and inconsistent. | P1 | P3-M3 |
| P3-A15 | feature | BYOAI | Chat uses brittle JSON-in-text action parsing vs structured tools. | P1 | P3-M4 |
| P3-A16 | feature | BYOAI | MCP writes lack role checks; shared-connection bug in chat. | P1 | P3-M2, P3-M5 |
| P3-A17 | feature | Automation | Admin automation page not routed — builder unreachable. | P0 | P3-M6 |
| P3-A18 | architecture | Automation | Still core, not plugin; cannot deactivate or ship independently. | P1 | P3-M6 |
| P3-A20 | feature | User plugin | Admin UI orphaned (`page=users` → settings, not plugin UI); permissions not on writes. | P0 | P3-M8 |

## Closed from Phase 2 (no re-open)

- Template `eval()` — removed (PR #26); hotfix PR #30 restores admin UI
- ContentEditor visual/HTML tabs — shipped (P2-M3)
- Collection groups DnD — shipped (P2-M4)
- Onboarding state — shipped (P2-M5)
- Page snapshots in dev — shipped (P2-M6)
- Client embed sync script — shipped (P2-M7)

## Human sign-off (P3-M1-T5)

- [x] Register + AI/automation audit reviewed
- [x] Phase 3 scope approved: BYOAI + automation plugin + user plugin + closed-alpha essentials (kekaba deferred)
- [x] Decisions locked: deactivatable automation plugin; default AI approval on; MCP key per connection
- [ ] kekaba production deploy (deferred): ___________

**Reviewer:** Keven **Date:** 2026-06-25
