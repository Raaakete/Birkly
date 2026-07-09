# P26 — Verification matrix

**Date:** 2026-07-09 · **Closure:** P26-M16 — **complete**

## Animation (STU-ANIM)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| STU-ANIM-01 | Timeline UI edits animations | pass | `plugins/studio/admin/studio-creative.js` (`renderTimelinePanel`) |
| STU-ANIM-02 | `@keyframes` in creative.css | pass | `plugins/studio/includes/StudioCreative.php`, `tests/studio_creative_test.php` |
| STU-ANIM-03 | Scroll trigger on publish | pass | `plugins/studio/assets/studio-creative-runtime.js` (IntersectionObserver) |
| STU-ANIM-04 | Click + load triggers | pass | `studio-creative-runtime.js`, `StudioCreative::buildRuntimeConfig` |

## Popup (STU-POPUP)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| STU-POPUP-01 | Popup builder UI | pass | `studio-creative.js` (`renderPopupPanel`) |
| STU-POPUP-02 | Click opens popup | pass | `studio-creative-runtime.js` (`openPopup`) |
| STU-POPUP-03 | Esc closes popup | pass | `studio-creative-runtime.js` (keydown Escape) |
| STU-POPUP-04 | Focus trapped in dialog | pass | `studio-creative-runtime.js` (`trapFocus`) |
| STU-POPUP-05 | Delay + exit intent | pass | `StudioCreative::buildPopupsHtml`, runtime init |

## Figma (STU-FIGMA)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| STU-FIGMA-01 | Import API | pass | `plugins/studio/api/studio.php` (`import_figma`) |
| STU-FIGMA-02 | Frames → sections | pass | `plugins/studio/includes/StudioFigmaImporter.php` |
| STU-FIGMA-03 | Fills → tokens | pass | `tests/studio_creative_test.php`, `tests/fixtures/studio/figma-sample.json` |
| STU-FIGMA-04 | IDE import modal | pass | `plugins/studio/admin/studio.html` (`#studio-figma-modal`) |

## Funnel (STU-FUNNEL)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| STU-FUNNEL-01 | funnel.json storage | pass | `StudioCreative::readFunnel` / `writeFunnel` |
| STU-FUNNEL-02 | Stepper in compiled HTML | pass | `StudioCreative::buildFunnelHtml`, `studio_creative_test.php` |
| STU-FUNNEL-03 | Step links to pages | pass | `studio-creative-smoke.php` |
| STU-FUNNEL-04 | Funnel editor UI | pass | `studio-creative.js` (`renderFunnelPanel`) |

## Regression (P25)

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| PR25-01 | P25 verification matrix | pass | `docs/reviews/P25-verification-matrix.md` |
| PR25-02 | `studio-smoke.sh` | pass | `scripts/studio-smoke.sh`, CI |
| PR25-03 | Engine v2 tests | pass | `tests/studio_template_engine_v2_test.php` |

## CI

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| CI-01 | `studio-creative-smoke.sh` | pass | `scripts/studio-creative-smoke.sh` |
| CI-02 | Full `ci-check.sh` | pass | 154 suites PASS |

**Status:** green (2026-07-09)
