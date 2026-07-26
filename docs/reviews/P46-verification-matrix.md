# P46 — Verification matrix (AI Presence)

**Plan:** `docs/reviews/P46-ai-presence-plan.md`  
**Status:** Complete (2026-07-26)

---

## Data model & API

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AI-01 | Schema validates | Load draft against `schema/ai_presence.schema.json` | PASS | M1 | **pass** |
| V-AI-02 | Save draft | POST admin API `save_draft` | draft.json updated | M1 | **pass** |
| V-AI-03 | Publish | POST `publish` | published.json + meta version bump | M9 | **pass** |
| V-AI-04 | Permissions | Viewer cannot publish | 403 | M2 | **pass** |

---

## Admin UI

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AI-05 | Nav item | Open admin sidebar | AI Presence between Media and Marketplace | M2 | **pass** |
| V-AI-06 | Tabs | Click each tab | Overview, Context, Claims, Media, Publish load | M2 | **pass** |
| V-AI-07 | Completeness | Empty draft | Score &gt; 0 with meaningful next steps | M3 | **pass** |
| V-AI-08 | Setup flow | Get started → skip | Lands on Context with no forced wizard | M4 | **pass** |
| V-AI-09 | Connect entry | Link About entry as brand source | Ref in draft, no text duplication | M6 | **pass** |
| V-AI-10 | Add claim | Create claim + 2 evidence | Status Supported | M7 | **pass** |
| V-AI-11 | External URL | Add evidence URL | Metadata shown; user verifies | M7 | **pass** |
| V-AI-12 | Media context | Attach hero image to Product X | represents + description saved | M8 | **pass** |

---

## Publish & public

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AI-13 | Well-known | GET `/.well-known/brand-context.json` | 200 published JSON | M10 | **pass** |
| V-AI-14 | Public API | GET `/api/public/ai_presence.php?action=brand` | 200 versioned JSON | M10 | **pass** |
| V-AI-15 | Draft hidden | Edit draft after publish | Public file unchanged until republish | M9 | **pass** |
| V-AI-16 | CORS | Fetch from registered public origin | Allowed | M10 | **pass** |
| V-AI-17 | Disabled | publication_settings.enabled=false | 404 or disabled response | M10 | **pass** |

---

## Freshness & preview

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AI-18 | Staleness | Edit linked CMS entry after publish | Warning on Overview/Publish | M11 | **pass** |
| V-AI-19 | Preview Q | Ask “What is brand known for?” | Answer + source list | M12 | **pass** |
| V-AI-20 | Preview draft isolation | Change draft, don't publish | Preview still uses published | M12 | **pass** |

---

## AI suggestions

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AI-21 | Suggest | Run suggest with AI connected | Returns suggestions, draft unchanged | M13 | **pass** |
| V-AI-22 | Accept | Accept one suggestion | Only that item in draft | M13 | **pass** |
| V-AI-23 | Reject | Reject suggestion | Not in draft | M13 | **pass** |

---

## Docs & automated tests

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-AI-24 | Birkly-Docs page | AI Presence product guide exists | M14 | **pass** |
| V-AI-25 | `tests/ai_presence_test.php` | PASS | M14 | **pass** |
| V-AI-26 | `./scripts/ci-check.sh` | PASS | M14 | **pass** |

---

## Closure

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-AI-27 | Human notify | Single closure message | M14 | **pass** |
