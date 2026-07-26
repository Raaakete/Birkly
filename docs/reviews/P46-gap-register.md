# P46 — Gap register (AI Presence)

**Plan:** `docs/reviews/P46-ai-presence-plan.md`  
**Updated:** 2026-07-26

---

## G1 — Product surface

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G1-01 | No primary nav area for outward brand context | **High** | P46-M2 nav |
| G1-02 | Admin AI (chat/MCP) ≠ brand publishing for machines | **High** | P46 product positioning in docs |
| G1-03 | No completeness / next-steps guidance | **Med** | P46-M3 |
| G1-04 | No skippable first-time setup | **Med** | P46-M4 |

---

## G2 — Data & references

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G2-01 | No canonical ai_presence data model | **High** | P46-M1 schema |
| G2-02 | No draft vs published separation | **High** | P46-M9 |
| G2-03 | No structured claims + evidence | **High** | P46-M7 |
| G2-04 | No media context (represents / restrictions) | **Med** | P46-M8 |
| G2-05 | Connected content = manual copy-paste today | **High** | P46-M6 pickers |

---

## G3 — Public delivery

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G3-01 | No `/.well-known/brand-context.json` | **High** | P46-M10 |
| G3-02 | No public read API for brand context | **High** | P46-M10 |
| G3-03 | No JSON-LD export option | **Med** | P46-M10 |
| G3-04 | Canonical URL on CMS host only (wrong for headless) | **High** | P46-D7 + P43 public site |

---

## G4 — Trust & freshness

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G4-01 | No staleness when linked CMS changes | **Med** | P46-M11 |
| G4-02 | No context preview with sources | **Med** | P46-M12 |
| G4-03 | No evidence provenance in UI | **Med** | P46-M7 |

---

## G5 — AI assistance

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G5-01 | No suggest-from-CMS without auto-publish | **Med** | P46-M13 |
| G5-02 | Risk: chat AI invents brand facts | **High** | P46-D9 approve flow |

---

## G6 — Docs & i18n

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G6-01 | No Birkly-Docs AI Presence guide | **Med** | P46-M14 |
| G6-02 | No admin i18n keys for AI Presence | **Med** | P46-M14 |

---

## Existing assets to reuse

| Asset | Use in P46 |
|-------|------------|
| `api/ai_connections.php` | Suggestions (M13) |
| `api/public.php` patterns | Public read API (M10) |
| `core/hosting_urls.php` | Public site URL for well-known (M10) |
| `admin/js/tabs-v2.js` | Tab workspace (M2) |
| `admin/js/ui-feedback.js` | Toast save/publish (M2) |
| Media library + `core/media_storage.php` | Media context pickers (M8) |
| `/.well-known/oauth-*` routes | Pattern for brand-context route (M10) |
