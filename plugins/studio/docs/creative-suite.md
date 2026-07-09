# Studio creative suite (P26)

Motion, modals, Figma import, and funnel flows — built on the P25 production builder.

## Animation timeline

1. Open a page in Studio IDE.
2. Sidebar **Creative → Animation timeline** → **+ Animation**.
3. Set **Target element ID** (select element → **Use selected element**).
4. Choose trigger: **scroll** (into view), **click**, or **load**.
5. Edit keyframes JSON (opacity, transform, etc.).
6. **Save** and **Publish** — animations compile to `styles/creative.css` and run via `studio-creative-runtime.js`.

## Popups

1. **Creative → Popups** → **+ Popup**.
2. Set title, body HTML, trigger (click / delay / exit intent).
3. For click trigger, bind **Click target element ID** to a button on the canvas.
4. Published page includes accessible dialog markup (`role="dialog"`, `aria-modal`). Esc closes; focus is trapped.

## Figma import

1. Export or build simplified JSON with `frames[]` (each frame has `children` text nodes and optional `fills`).
2. Click **Figma** in the Creative sidebar (or toolbar area).
3. Paste JSON → **Import sections** — sections append to the current page; colors merge into `tokens.json`.

Example:

```json
{
  "frames": [{
    "name": "Hero",
    "type": "FRAME",
    "fills": [{ "color": { "r": 0.05, "g": 0.39, "b": 0.99, "a": 1 } }],
    "children": [{ "type": "TEXT", "characters": "Headline", "style": { "fontSize": 32 } }]
  }]
}
```

## Funnel flows

1. **Creative → Funnel flow** — enable stepper, add steps (page + label).
2. **Save funnel** — writes `funnel.json` at project root.
3. On publish, each page shows a stepper linking to funnel pages.

## Files

| Path | Role |
|------|------|
| `includes/StudioCreative.php` | Compile keyframes, popups, funnel HTML |
| `includes/StudioFigmaImporter.php` | Figma JSON → sections/tokens |
| `assets/studio-creative-runtime.js` | Public animations + popups |
| `admin/studio-creative.js` | IDE panels |

## Tests

```bash
php tests/studio_creative_test.php
./scripts/studio-creative-smoke.sh
```
