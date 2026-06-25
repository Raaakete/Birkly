# Birkly platform changelog

Product changes on the **Birkly** core CMS (`Raaakete/Birkly`). Client site embeds sync separately per `docs/client-sites/cms-vendoring.md`.

Format: milestone batch → PRs → user-visible summary.

---

## 2026-06-25 — P3-M14 Documentation pack

| Area | Summary |
|------|---------|
| Internal | `docs/ai/BYOAI-ARCHITECTURE.md` — BYOAI component map, RBAC, MCP per-connection |
| Plugins | `plugins/automation/README.md` — deactivatable automation plugin ops |
| Ops | `docs/user-plugin-ops.md` — user plugin enablement, permissions, troubleshooting |
| Help | `docs/help-manifest.json` v2 — MCP, closed-alpha, content-editor, user-plugin doc paths |

Birkly-Docs: closed-alpha guide, MCP connections, ContentEditor blocks, user plugin pages; changelog and manifest updated.

---

## 2026-06-25 — Phase 2 verification hotfix

| PR | Summary |
|----|---------|
| (pending) | Template safe renderer: validate parser output only |

- **Regression fix:** P2-M6 safe renderer blocked legitimate admin template PHP (`require_once`, `include`, `required` field keys), breaking onboarding, entry editor, and entries list.
- **Fix:** Mask developer-authored PHP blocks before validation; use word-boundary checks for parser-generated PHP.
- **Verification:** 15 test suites PASS; browser QA PASS for onboarding, ContentEditor, groups DnD, `page_snapshot` API.
- **Docs:** `docs/reviews/ux-flow-matrix.md` updated — all Phase 2 matrix rows green.

---

## 2026-06-19 — P2-M7 Platform sync & kekaba proof

| PR | Repo | Summary |
|----|------|---------|
| [#27](https://github.com/Raaakete/Birkly/pull/27) | Birkly | `scripts/sync-client-embed.sh`, kekaba exclude list, plugins patch helper |
| [#2](https://github.com/Raaakete/kekaba/pull/2) | kekaba | Full `Birkly-0.1/` sync from platform main |
| [#2](https://github.com/Raaakete/Birkly-Website/pull/2) | Birkly-Website | Full `Birkly-0.1/` sync (+ bundle/hydrate APIs) |

- Client sites can upgrade embeds with one script; site-specific kekaba publish hooks preserved.
- Dev smoke: `page_bundle`, `page_snapshot`, public API 200 on both site CMS embeds.
- Production: `check-production.sh` on kekaba.com — bundle + snapshot OK.

---

## 2026-06-19 — P2-M6 CMS hardening

| PR | Summary |
|----|---------|
| [#26](https://github.com/Raaakete/Birkly/pull/26) | Security + platform quality |

- **Template parser:** `eval()` removed; `admin/template_safe_renderer.php` validates compiled PHP and renders via temp `include`.
- **Page snapshots:** `scripts/generate-page-snapshots.php` for dev/demo; hooked into demo bootstrap and agent smoke test.
- **Public API:** `list_collections` returns consistent `id`, `title`, `slug` (`name` → `title` fallback via `core/collection_meta.php`).
- **Admin JS:** Debug logging removed from `entry-editor.js`.
- **Tests:** 15 suites (added safe renderer, collection meta, page snapshot tests).

---

## 2026-06-19 — P2-M5 Onboarding

| PR | Summary |
|----|---------|
| [#25](https://github.com/Raaakete/Birkly/pull/25) | Onboarding redirect + checklist fixes |

- Redirect uses `setup_wizard_completed` (not “no entries on disk”).
- Dashboard merges API checklist with session status.
- Skip vs complete state fixed; `normalizeOnboardingUserKey()` for consistent file keys.
- Complete sets `setup_wizard_completed` + `demo_content_installed`.

---

## 2026-06-19 — P2-M3 ContentEditor (complete)

| PR | Task | Summary |
|----|------|---------|
| [#19](https://github.com/Raaakete/Birkly/pull/19) | T1–T4 | Visual/HTML tabs, stabilize fieldtype |
| [#21](https://github.com/Raaakete/Birkly/pull/21) | T5 | Project CSS preview (`ContentEditorPreview`, `preview-css.js`) |
| [#22](https://github.com/Raaakete/Birkly/pull/22) | T6 | Component registry (callout, quote, cta-button) |
| [#23](https://github.com/Raaakete/Birkly/pull/23) | T7 | Modular commands (`inline-format`, `block-format`, `bootstrap`) |
| [#24](https://github.com/Raaakete/Birkly/pull/24) | T8 | Internal `fieldtypes/ContentEditor/README.md` |

- Stored value remains pure HTML; components are HTML+CSS+JS self-contained in `fieldtypes/ContentEditor/`.

---

## 2026-06-19 — P2-M4 Collection groups DnD

| PR | Summary |
|----|---------|
| [#20](https://github.com/Raaakete/Birkly/pull/20) | Stabilize collection groups drag-and-drop |

- Three DnD scenarios reliable (simple, nested, mouse-drag paths).
- `core/collection_groups.php` + tests.

---

## 2026-06-19 — P2-M2 UX flow fixes

| PR | Summary |
|----|---------|
| [#16](https://github.com/Raaakete/Birkly/pull/16) | Select options + demo fieldtype aliases |
| [#17](https://github.com/Raaakete/Birkly/pull/17) | Entry slug preservation + create_collection schema |
| [#18](https://github.com/Raaakete/Birkly/pull/18) | UX matrix re-run; P2-M2 marked complete |

---

## 2026-06-19 — P2-M1 System review gate

| PR | Summary |
|----|---------|
| [#14](https://github.com/Raaakete/Birkly/pull/14) | Review gate before Phase 2 execution |

- UX flow matrix, issue register (P2-R01–P2-R15), walkthrough artifacts, automated harness.

---

## Related

- Birkly-Docs product sync: `meta/changelog-docs.md` (documentation-only changes)
- Roadmap: `docs/roadmap/ROADMAP.md`
- Internal specs index: `docs/reviews/internal-specs-index.md`
