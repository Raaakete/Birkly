# P24 — Studio visual excellence

**Status:** Approved (2026-07-08)  
**Baseline:** P23 complete — IDE shell, filesystem, canvas v2, SSR `/site/` + `studio_view.php`  
**Human rule:** Execute P24-M0→M20 autonomously. Notify human **once** at **P24-M20** only.

**Reference syntax:** `reference-projects/blog/website/index.html`  
**Spec:** `docs/TEMPLATE_LANGUAGE_SPEC.md`

---

## Vision

Make Studio feel like a **professional visual site builder**: polished IDE chrome, layout controls, CSS inspector, guided bindings, nav builder, and **SSR that never leaks raw `{for each…}` tags**.

---

## P0 — SSR template parity (blocking)

| ID | Focus |
|----|--------|
| **P0** | Fix `studio_view.php` showing raw template tags |
| **P24-M1** | `StudioCompiler` canonical syntax (`{endfor}`, `{endget}`, `ordered by newest`) |
| **P24-M2** | `StudioTemplateEngine.php` — PHP SSR engine |
| **P24-M3** | curl-style tests — body must NOT contain `{for each` |

---

## Milestones

| ID | Focus |
|----|--------|
| **P24-M0** | Gap register, plan, canonical syntax matrix |
| **P24-M4** | IDE visual redesign — toolbar, panels, palette |
| **P24-M5** | Layout tools — columns, gap, grid/flex |
| **P24-M6** | Section ops — duplicate, reorder |
| **P24-M7** | Status bar + SSR route hint |
| **P24-M8** | Inspector tabs — Properties / CSS |
| **P24-M9** | CSS rules panel — element.styles |
| **P24-M10** | Binding cards + canonical preview |
| **P24-M11** | Binding wizard modal (4 steps) |
| **P24-M12** | Filtered loop options — order, limit |
| **P24-M13** | Nav builder — `canvas.nav` |
| **P24-M14** | Nav compile in `StudioCompiler` |
| **P24-M15** | Tests — template engine, compiler, SSR curl |
| **P24-M16** | Reference project parity check |
| **P24-M17** | `P24-verification-matrix.md` |
| **P24-M18** | Phase closure checklist |
| **P24-M19** | ROADMAP update |
| **P24-M20** | Single human notification + digest |

**Branches:** `cursor/p24-m[N]-<slug>-030a`

---

## Canonical binding syntax (locked)

```text
{for each entry in 'blog' with status = published ordered by newest limit 3}
  <h2>{ title }</h2>
{endfor}

{from 'site_elements' get entry 'hero'}
  <h2>{'content'.0.'value'}</h2>
{endget}
```

**Never emit:** `{end}`, `{title}` without spaces in generated templates (compiler normalizes).

---

## Out of scope (P25)

- Full WYSIWYG rich text
- Figma import
- npm/webpack at runtime
