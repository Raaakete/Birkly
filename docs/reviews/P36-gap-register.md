# P36 — Gap register (CMS productization & platform depth)

**Status:** Open — seed at P36-M0  
**Plan:** `docs/reviews/P36-cms-productization-platform-plan.md`

---

## CMS download hygiene

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G36-01 | `demo-content/**` shipped in CMS zip (~140 files) | **blocker** | M1, M8 |
| G36-02 | `AGENTS.md` + agent scripts in release includes | high | M1 |
| G36-03 | Official plugin source still under `plugins/` in dev tree | high | M2 |
| G36-04 | `demo-website/` naming vs product "project" folder | medium | M4 |
| G36-05 | Customer README mentions dev/agent workflows | medium | M3 |

---

## Starters & templates

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G36-06 | Starters read local `demo-content/` only | **blocker** | M5–M8 |
| G36-07 | No Ops starter pack admin or API | **blocker** | M5–M6 |
| G36-08 | No `project/.gitkeep` skeleton in release | medium | M3 |
| G36-09 | Paid template packs not unified with starter install | medium | M20 |

---

## Ops platform

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G36-10 | Flat 13-link sidebar — no topic groups | **blocker** | M9 |
| G36-11 | P35 UI shell incomplete vs CMS (dark mode, drawer) | high | M10 |
| G36-12 | No starter pack manager on Ops | **blocker** | M11 |
| G36-13 | No featured offers curation for website | high | M11 |
| G36-14 | Installation detail is list-only | medium | M12 |
| G36-15 | Feedback has no ticket workflow | medium | M12 |
| G36-16 | No revenue / webhook health dashboard | medium | M13 |

---

## Provider Studio

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G36-17 | Provider nav minimal (5 links) | high | M14 |
| G36-18 | No offer analytics | high | M15 |
| G36-19 | No draft offer state | medium | M16 |
| G36-20 | Screenshot upload scattered in offer form | medium | M16 |

---

## Documentation

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G36-21 | No plugin economy curriculum | **blocker** | M17 |
| G36-22 | Docs reference `marketplace.birkly.io` | high | M19 |
| G36-23 | Permission manifest / data_access underdocumented | high | M17 |

---

## Public repo hygiene (P36-D11–D13)

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G36-26 | `docs/reviews/` (100+ phase files) on public Birkly | **blocker** | M1 |
| G36-27 | `docs/roadmap/ROADMAP.md` on public Birkly | **blocker** | M1 |
| G36-28 | `docs/agent-ops/` on public Birkly | **blocker** | M1 |
| G36-29 | `tests/` (163+ files) on public Birkly | **blocker** | M1 |
| G36-30 | 63 legacy `docs/*.md` planning files | high | M2 |
| G36-31 | Old `docs/*.html` inline docs site | high | M2 |
| G36-32 | No private Birkly-Internal repo | **blocker** | M0 |
| G36-33 | AGENTS.md + .cursor/ on public repo | **blocker** | M1 |

---

## Keven requests (2026-07-24)

| ID | Request | Maps to |
|----|---------|---------|
| G36-R7 | Public OSS — no planning/tests on GitHub | G36-26–G36-33, M0–M3 |
| G36-R8 | Planning in non-public repo | G36-32, Birkly-Internal |
