# P23 — Studio IDE gap register

**Date:** 2026-07-08

| ID | Gap | Mitigation |
|----|-----|------------|
| G-IDE-01 | Full WYSIWYG inline editing | Basic contenteditable on text nodes; defer rich text P24 |
| G-IDE-02 | Syntax highlight | Lightweight tokenizer (no npm) |
| G-IDE-03 | v1 canvas without version field | Auto-migrate to v2 on open |
| G-IDE-04 | Plugin block SSR | Compiler emits template tags; SSR uses page_bundle |
| G-IDE-05 | Draft preview without publish | `?birkly_studio_preview=1` + session check |
