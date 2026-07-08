# P22 — Studio plugin plan

**Status:** Approved 2026-07-08 (Keven)  
**Owner:** lead-engineer · **Plugin:** `plugins/studio/`

## Goal

Ship a deactivatable visual site builder inside Birkly. Projects live at `content/studio-projects/{id}/`. Compile canvas JSON to HTML + CSS + Birkly template tags. SSR default for published sites; live `birkly-client.js` for admin preview.

## Locked decisions (P22-D1–D12)

| ID | Decision |
|----|----------|
| P22-D1 | Plugin-only — `plugins/studio/`; deactivatable |
| P22-D2 | Storage — `content/studio-projects/{project-id}/` |
| P22-D3 | Dual editor — visual canvas + code tab |
| P22-D4 | Canvas JSON = editor source; compiled HTML/CSS = publish artifact |
| P22-D5 | Style kits = vendored static CSS — no npm/webpack at runtime |
| P22-D6 | v1 kits: minimal, bootstrap, tailwind-lite |
| P22-D7 | SSR default; live client for preview + optional export |
| P22-D8 | Thin core shelf — `register_public_route()` + server-router hook |
| P22-D9 | Export ZIP — never deletes in-CMS project |
| P22-D10 | Reuse ContentEditor layout patterns; Studio stays in plugin |
| P22-D11 | SSR aligns with P20 parity matrix; log gaps |
| P22-D12 | Single human notification at P22-M12 |

## Milestones

| Milestone | Focus |
|-----------|--------|
| P22-M0 | Gap register + parity requirements |
| P22-M1 | `core/plugin_public_routes.php` + plugin shell |
| P22-M2 | `StudioProjectRepository` CRUD |
| P22-M3 | Style kits in `plugins/studio/kits/` |
| P22-M4 | Visual layout editor |
| P22-M5 | Style inspector + custom.css |
| P22-M6 | Bindings UI + `StudioCompiler` |
| P22-M7 | Dual editor round-trip |
| P22-M8 | Live preview iframe (P21 bundle) |
| P22-M9 | SSR `StudioRenderer` + `/site/{slug}/` |
| P22-M10 | Export ZIP + publish revision |
| P22-M11 | Docs + V-STU-01 evidence |
| P22-M12 | Closure matrix + single notification |

## Storage layout

```
content/studio-projects/{project-id}/
├── project.json
├── styles/kit.css, custom.css
├── pages/{slug}.studio.json, {slug}.html
└── website/   # export mirror
```

Public URL: `/site/{project-slug}/` (home) · `/site/{project-slug}/{page-slug}/`

## Values check

✅ Self-contained plugin · ✅ dependency-free · ✅ UI decoupled from storage/API
