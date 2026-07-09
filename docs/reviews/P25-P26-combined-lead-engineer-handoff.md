# P25 + P26 — Combined lead-engineer handoff

**Status:** P25 complete on `main` · P26 approved (2026-07-09, Keven) — single run  
**Notify human:** **once** at **P26-M16** closure only (not separate P25 notification).

## Baseline

- P25 production builder shipped (`b204a9c`): canvas CSS, live preview, engine v2, layout/CSS studio, presets, `studio-smoke.sh`
- Verify P25 matrix + smoke before P26-M0

## P26 mission — Creative suite

| Milestone | Deliverable |
|-----------|-------------|
| M0 | Plan + gap register + verification matrix |
| M1 | `StudioCreative` compiler (keyframes, popups, funnel) |
| M2 | Animation timeline UI + scroll/click/load triggers |
| M3 | `@keyframes` + `creative.css` on publish |
| M4 | Runtime `studio-creative-runtime.js` (IntersectionObserver) |
| M5 | Popup builder UI |
| M6 | Popup a11y: `role=dialog`, focus trap, Esc |
| M7 | Delay + exit-intent triggers |
| M8 | `StudioFigmaImporter` + `import_figma` API |
| M9 | Figma import modal in IDE |
| M10 | Funnel `funnel.json` + stepper compile |
| M11 | Funnel editor panel |
| M12 | `get_funnel` / `save_funnel` API |
| M13 | Creative inspector tab + sidebar panels |
| M14 | `tests/studio_creative_test.php` |
| M15 | `studio-creative-smoke.sh` + docs |
| M16 | Verification matrix green + ROADMAP + single digest |

## Branches

`cursor/p26-m[N]-<slug>-030a`

## Out of scope (P27+)

Multi-user realtime, marketplace block store, Tailwind JIT

## Closure checklist

- [ ] P25 verification matrix green
- [ ] `studio-smoke.sh` green
- [ ] Scroll-triggered animation on published page
- [ ] Popup opens on click; Esc closes; focus trapped
- [ ] Figma import creates styled section
- [ ] Funnel stepper links pages
- [ ] `studio-creative-smoke.sh` green; CI PASS
- [ ] ROADMAP updated (P26 complete)
- [ ] Single digest notification
